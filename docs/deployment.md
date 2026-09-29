# Deployment runbook

How code reaches production, what the server needs, and what to do when a
deploy goes wrong.

## How a change reaches production

```
push to main ──► "tests" workflow ──(passes)──► "deploy" workflow ──► live server
                  Pint, PHPStan,                  builds assets,
                  full test suite on MySQL        pulls the tested commit over SSH
```

1. Work happens on a branch. Pushing a branch named `claude/**`, or opening
   a pull request, runs the **tests** workflow
   (`.github/workflows/tests.yml`): `composer setup`, then
   `composer ci:check` (Pint in test mode, PHPStan level 7, all tests on
   MySQL 8).
2. When `main` is pushed and **tests** passes, the **deploy** workflow
   (`.github/workflows/deploy.yml`) runs. If tests fail, nothing is
   deployed and the live site stays on the last good version.
3. **deploy** checks out exactly the tested commit, installs production
   dependencies, builds the front-end assets (`npm ci && npm run build`)
   and uploads `public/build` to the server.
4. Over SSH on the server, in `/var/www/schoolapp`:
   ```bash
   php artisan down --retry=30          # maintenance page (503)
   git fetch origin main
   git reset --hard "$DEPLOY_SHA"
   composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
   php artisan migrate --force
   php artisan optimize:clear
   php artisan optimize
   php artisan queue:restart            # workers reload the new code
   chown -R www-data:www-data storage bootstrap/cache public/build
   php artisan up                        # always, even if a step failed
   ```

A deploy can also be started by hand: GitHub → Actions → deploy → Run
workflow.

### GitHub secrets the deploy needs

| Secret | Holds |
|---|---|
| `SSH_HOST` | Server address |
| `SSH_USER` | Deploy user |
| `SSH_KEY` | Private key for that user |
| `SSH_PORT` | Optional, default 22 |

## Server requirements

| Need | Detail |
|---|---|
| OS & web | Linux, nginx, PHP-FPM 8.4 |
| PHP extensions | `gd` (photo resizing), `pdo_mysql`, `mbstring`, `intl`, `zip`, `bcmath`, `fileinfo`, `openssl`, `curl` |
| PHP limits | `public/.user.ini`: `upload_max_filesize = 12M`, `post_max_size = 16M` (read by PHP-FPM) |
| nginx | `client_max_body_size 16M;` or larger, or phone photos fail to upload. Document root `/var/www/schoolapp/public`. |
| Database | MySQL 8, dedicated user and database |
| HTTPS | Required (sessions, installable app) |
| Writable | `storage/`, `bootstrap/cache/` by `www-data` |
| `.env` | On the server only; see [Configuration](configuration.md) |

### Background processes (must be running)

**Queue worker**: sends queued emails (welcome, approval, registration)
and processes student CSV imports. Example Supervisor programme:

```ini
[program:schoolhub-queue]
command=php /var/www/schoolapp/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/var/www/schoolapp/storage/logs/queue.log
```

**Scheduler**: subscription reminders (08:00 Kampala), the nightly
backup (01:30), the error-log clean-up (02:30) and a heartbeat every
minute that System health watches. One cron entry for `www-data`:

```cron
* * * * * cd /var/www/schoolapp && php artisan schedule:run >> /dev/null 2>&1
```

After setting these up, open **System health** (platform owner menu): it
shows whether email, SMS, the queue worker, the scheduler and backups are
really working, and what to change if not.

## Checks after a deploy

1. Open the site and sign in.
2. GitHub → Actions: both **tests** and **deploy** are green for the commit.
3. Platform owner: open **Error reports** (no new errors since the deploy)
   and **System health** (everything "Working").
4. If the change touched emails, SMS or PDFs, try one of each.

## When a deploy goes wrong

| Situation | What to do |
|---|---|
| Tests failed | Nothing was deployed. Fix on a branch, push, and let CI pass. |
| Deploy failed partway | The site is brought back up automatically. Read the failed step in Actions. Fix and push, or re-run the deploy workflow. |
| Site broken after a deploy | Roll back (below), then investigate on a branch. |
| Migration failed | Check `storage/logs/laravel.log` on the server. Fix the migration in a new commit; do not edit a migration that already ran in production. |

### Rolling back

Preferred, through Git, so the history stays true:

```bash
git revert <bad-commit>     # on a branch, then merge to main
git push origin main        # tests run, then deploy
```

Emergency, on the server (the next deploy overwrites it):

```bash
cd /var/www/schoolapp
php artisan down
git reset --hard <last-good-commit>
composer install --no-dev --optimize-autoloader --no-interaction
php artisan optimize:clear && php artisan optimize
php artisan up
```

A rollback does not undo migrations. If a migration must be reversed, run
`php artisan migrate:rollback --step=1` only after checking what its
`down()` does and taking a database backup.

## Backups

`php artisan backup:run` runs nightly at 01:30 (needs the scheduler). It
writes a compressed MySQL dump and an archive of `storage/app/private/uploads`
(photos, signatures) and `storage/app/public` (logos) to
`storage/app/backups`, keeps 14 days, and reports any failure to Error
reports. The server needs `mysqldump`, `gzip` and `tar`.

Still needed on the server:

- copy `storage/app/backups` **off the server** every day (e.g. rclone or
  rsync to another machine or cloud storage), keeping 30 days or more
- test a restore on a spare server at least once a term

See [Operations](operations.md).
