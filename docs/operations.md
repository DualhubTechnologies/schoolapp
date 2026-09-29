# Operations runbook

Day-to-day running of SchoolHub, and what to do when something goes wrong.
Commands run on the server in `/var/www/schoolapp` unless stated.

## Daily and weekly checks

| When | Check | Where |
|---|---|---|
| Daily | Background services working | **System health** (red count = problems) |
| Daily | New or returning errors | Admin → **Error reports** (red count = open errors) |
| Daily | Schools awaiting approval | Admin → **Schools** (status: Awaiting approval) |
| Daily | New demo requests | Admin → **Demo requests** |
| Weekly | Failed queue jobs | `php artisan queue:failed` |
| Weekly | Disk space (photos grow) | `df -h`, `du -sh storage/app/private/uploads` |
| Weekly | Backups ran and were copied off-server | **System health** → Backups, and your off-server copy |
| Each term | Test a restore | Spare server |

## Errors reported by users

Users see a reference such as **E-7K3Q9P** on the error page, or in the
notice when a button or save fails.

1. Admin → **Error reports** → search the reference.
2. The report shows the error, where in the code it happened, how many
   times, the last school and page, and the stack trace.
3. Fix it (see [Contributing](contributing.md)), deploy, then
   **Mark resolved**. If it happens again it reopens and you are emailed.

You are emailed at `CONTACT_EMAIL` when an error is new or comes back,
if production email is set up. Occurrences older than 60 days are removed
nightly; the report keeps its count.

The full technical log is `storage/logs/laravel.log`.

## Common support requests

| Request | How |
|---|---|
| Approve a new school | Admin → Schools → open the school → **Approve** (a trial starts) |
| Record a school's payment | Admin → Schools → open the school → **Record payment** |
| Give a school more time | Admin → Schools → **More** → Extend |
| Suspend / unsuspend a school | Admin → Schools → **More** |
| Issue an activation code | Admin → Activation codes (the school enters it on its Subscription page) |
| User forgot their password | Sign-in page → "Forgot password" (needs working email). Or their School Admin resets it under Settings → Users. |
| User cannot see a menu | Their School Admin ticks the module under Settings → Users (e.g. **Identity cards**). |
| "Session timed out" | Normal after 2 hours idle. Sign in again. |
| Photos will not upload | Check the phone's connection, file under 10 MB, and nginx `client_max_body_size` ≥ 16M. |
| SMS not arriving | `SMS_DRIVER=africastalking`, API key set, Africa's Talking balance. With `log`, messages only go to the log. |
| Emails not arriving | `MAIL_MAILER` must not be `log`; check the SMTP settings and `storage/logs/laravel.log`. |

## Background processes

```bash
sudo supervisorctl status schoolhub-queue      # queue worker running?
sudo supervisorctl restart schoolhub-queue
php artisan queue:failed                       # jobs that failed
php artisan queue:retry all                    # retry them
php artisan schedule:list                      # what the scheduler runs
```

If welcome emails or student imports stop, the queue worker is almost
always the cause.

## Maintenance mode

```bash
php artisan down --retry=30     # users see "SchoolHub is being updated"
php artisan up
```

Phones show a calm "being updated" banner instead of an error while the
site is down.

## Caches

After changing `.env` on the server:

```bash
php artisan optimize:clear && php artisan optimize
sudo supervisorctl restart schoolhub-queue
```

## Backups and restore

A backup runs every night at 01:30 (`php artisan backup:run`) into
`storage/app/backups`, keeping 14 days:

- `schoolhub-db-YYYY-MM-DD-HHMMSS.sql.gz`: the whole database
- `schoolhub-files-YYYY-MM-DD-HHMMSS.tar.gz`: photos, signatures, logos

Run one by hand at any time with `php artisan backup:run`. A failed backup
appears in Error reports and on System health. Copy the folder off the
server every day; a backup on the same disk is lost with the server.

Restore on a spare server:

```bash
gunzip < schoolhub-db-YYYY-MM-DD-HHMMSS.sql.gz | mysql schoolhub_db
tar xzf schoolhub-files-YYYY-MM-DD-HHMMSS.tar.gz -C /var/www/schoolapp/storage/app
php artisan optimize:clear
```

Keep `.env` (especially `APP_KEY`) backed up separately and securely.
Without the same `APP_KEY`, private photo links and sessions will not work
after a restore.

## Incidents

| Severity | Examples | Response |
|---|---|---|
| Critical | Site down, data exposed between schools, data loss | Act immediately. Tell affected schools the same day. For personal-data breaches, notify the Personal Data Protection Office as the law requires. |
| High | Payments, receipts or payroll wrong; many users blocked | Same day; tell affected schools |
| Normal | One feature failing for some users | Within 2 working days |
| Low | Cosmetic, wording | Next release |

Afterwards write a short note: what happened, when, who was affected, the
cause, the fix, and what stops it happening again.

## Data requests (`config/legal.php`)

- A locked (unpaid) school's data is kept for 12 months before it may be
  deleted.
- A school's written deletion request is completed within 30 days.
- Schools get 30 days' notice before prices or terms change.
