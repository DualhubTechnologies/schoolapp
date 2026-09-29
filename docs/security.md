# Security notes

How SchoolHub protects school data, for developers and IT reviewers. The
system holds personal data about children (names, photos, dates of birth,
parents' phone numbers) and staff (salaries, national IDs, bank details),
so the rules below are not optional.

## Signing in and access

- Passwords are hashed with bcrypt (`BCRYPT_ROUNDS=12`); new passwords
  must meet the strength rules in `App\Support\PasswordStrength`.
- A new school's administrator confirms their email with a one-time code.
- Sessions are stored in the database and expire after 120 minutes idle.
- Sign-ins, failed sign-ins and sign-outs are recorded in the activity log
  with IP and device (Admin → Activity logs; each school's Audit Trail).
- Two panels: `/admin` is for the Super Admin only
  (`User::canAccessPanel`); `/` is for schools.
- Within a school, **roles** plus **modules** decide what each user can
  open. The same check hides the menu item and blocks the URL
  (`App\Support\Modules`, `App\Filament\Concerns\GatedByModule`).
- A locked or unapproved school can open only its Subscription or
  Awaiting approval page (`EnsureSchoolSubscribed`).

## Keeping schools apart (most important)

All schools share one database. Every school-owned record has a
`school_id`, and **every query on school data must filter by the signed-in
user's school**:

- Filament resources: in `getEloquentQuery()`.
- Custom pages and controllers: `->where('school_id', auth()->user()->school_id)`.
- Printable documents: check the record's school before rendering (e.g.
  `authorizeSchool()` in the document controllers).
- IDs from the URL (`?ids=1,2,3`) are always re-filtered by school, never
  trusted.

There is no global scope doing this automatically. When reviewing code,
look for any new query on a school table without a school filter. Tests
such as "does not print another school's students" guard the important
paths.

## Files

- **Photos and signatures** are private: stored on the `uploads` disk
  (`storage/app/private/uploads`), outside the public web folder, and shown
  only through signed links that expire after 30 minutes
  (`App\Support\PrivateFiles`).
- **School logos** are public by nature (`storage/app/public`).
- Photo uploads must be images of up to 10 MB (logos and signatures:
  JPEG, PNG or WebP only). JPEG, PNG and WebP pictures are re-encoded and
  shrunk on the server (`App\Support\ImageShrinker`).

## Parent links

Parents open their child's balance, receipts and report cards through a
private link `/p/{token}` sent by SMS, with no password. The token is a
random 10-character string, requests are rate-limited (30 per minute),
and report cards appear only after the school shares them. Anyone holding
the link can see that one child's page, so it is treated like a password.

## Errors and logging

- `APP_DEBUG=false` in production, so users never see code or stack
  traces; they see a friendly page and a reference.
- Unexpected errors are recorded for the platform owner (Error reports)
  with the page, school and user, but not form contents or passwords.
- The Laravel log (`storage/logs/laravel.log`) holds full error details
  and must not be publicly reachable.

## Secrets

- Real secrets live only in the server's `.env` and in GitHub Actions
  secrets (`SSH_*`). Never commit them.
- `APP_KEY` protects sessions and signed links; back it up securely and
  never share it.
- The deploy uses an SSH key limited to the deploy user.

## Known issues to fix

| Issue | Risk | Fix |
|---|---|---|
| The platform owner's starting password is written in `database/seeders/SuperAdminSeeder.php` and is in the Git history. | Anyone with access to the code knows the owner password if it was never changed. | Change the live password now. Then read the seeder password from an environment variable (or generate a random one and print it once), and treat the old value as exposed. |
| Scoping by school is done by hand in each query. | A missed filter could show one school's data to another. | Keep the tests; consider a shared scope or policy for new models; review every new query. |
| No database backup is configured in the repository. | Data loss if the server fails. | Set up nightly off-server backups and test restores ([Operations](operations.md)). |
| The queue worker is not restarted on deploy. | Workers can run old code after a deploy. | Add `php artisan queue:restart` to the deploy script. |

## Reporting a security problem

Email `CONTACT_EMAIL` (dualhubtechnologies@gmail.com) with details. Do not
discuss it in public channels until it is fixed.

## Legal obligations (Uganda)

SchoolHub processes personal data under the **Data Protection and Privacy
Act, 2019**. SchoolHub should be registered with the Personal Data
Protection Office, notify it of breaches as required, and give schools a
Data Processing Agreement. See the enterprise documents list; this note is
not legal advice.
