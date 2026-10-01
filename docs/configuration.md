# Configuration reference

Every setting SchoolHub reads from the environment (`.env`), plus the
settings kept in code under `config/`. Never commit a real `.env`.

**Required in production** means the system misbehaves or is unsafe
without it.

## Application

| Setting | Production value | Notes |
|---|---|---|
| `APP_NAME` | `SchoolHub` | Shown in emails and titles. `.env.example` still says `SchoolApp`. |
| `APP_ENV` | `production` | **Required.** |
| `APP_KEY` | generated | **Required.** `php artisan key:generate`. Encrypts sessions and signed links; changing it signs everyone out. |
| `APP_DEBUG` | `false` | **Required.** `true` shows stack traces to users instead of the friendly error pages. |
| `APP_URL` | `https://schoolhubug.com` | **Required.** Used for links in emails and SMS, and for private photo links. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `en` | |
| `APP_MAINTENANCE_DRIVER` | `file` | `php artisan down` / `up`. |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost. |
| `LOG_STACK` / `LOG_LEVEL` | `daily` / `warning` | Daily log files kept `LOG_DAILY_DAYS` (14) days; a single file grows forever. |
| `SUPER_ADMIN_EMAIL` | owner's email | Platform owner account created by `db:seed` (default `adrianmugizi8@gmail.com`). |
| `SUPER_ADMIN_PASSWORD` | empty | Starting password for that account. Empty: a random one is generated and shown once. Only used when the account does not exist yet. |

## Database, sessions, cache, queue

| Setting | Production value | Notes |
|---|---|---|
| `DB_CONNECTION` | `mysql` | **Required.** MySQL 8; the migrations use MySQL-only SQL. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | server values | **Required.** Use a dedicated database user, not root. |
| `SESSION_DRIVER` | `database` | Sessions live in the `sessions` table. |
| `SESSION_LIFETIME` | `120` | Minutes of inactivity before sign-out. Expired sessions show a "session timed out" page. |
| `SESSION_ENCRYPT` | `false` | |
| `SESSION_SECURE_COOKIE` | `true` | **Required with HTTPS.** Sign-in cookie sent over HTTPS only (checked on System health). |
| `CACHE_STORE` | `database` | |
| `QUEUE_CONNECTION` | `database` | **Required: a queue worker must run** (see Deployment), or emails and imports never go out. |

## Files

| Setting | Production value | Notes |
|---|---|---|
| `FILESYSTEM_DISK` | `public` | Default disk; school logos. |
| `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK` | `local` | Where uploads wait before saving. |

Private files (photos, signatures) always use the `uploads` disk at
`storage/app/private/uploads`, served only through expiring signed links.
Upload size limits are set in `public/.user.ini` (12 MB upload, 16 MB
request); nginx `client_max_body_size` must be at least 16 MB.

## Email

| Setting | Production value | Notes |
|---|---|---|
| `MAIL_MAILER` | `smtp` | **Required.** The default `log` only writes emails to the log file: nothing is sent (sign-up codes, approval emails, error alerts). |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` | provider values | |
| `MAIL_TIMEOUT` | `10` | Seconds before giving up on the mail server, so a slow server does not hang sign-up. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | e.g. `no-reply@schoolhubug.com`, `SchoolHub` | **Required.** |

## SMS (`config/sms.php`)

| Setting | Production value | Notes |
|---|---|---|
| `SMS_DRIVER` | `africastalking` | `log` records messages without sending. |
| `SMS_COUNTRY_CODE` | `256` | Local numbers (07…) are converted to international. |
| `AFRICASTALKING_USERNAME` | account username | `sandbox` uses the test endpoint. |
| `AFRICASTALKING_API_KEY` | secret | **Required for SMS.** |
| `AFRICASTALKING_SENDER_ID` | approved sender name | Optional. |

## SchoolHub contact details (`config/contact.php`)

Shown to users (help link, error pages) and used for owner emails.

| Setting | Default |
|---|---|
| `CONTACT_PHONE` | `0782 863209` |
| `CONTACT_WHATSAPP` | `256782863209` (international, no +) |
| `CONTACT_EMAIL` | `dualhubtechnologies@gmail.com`: demo requests and error alerts go here |
| `CONTACT_COMPANY` | `DualHub Technologies` |
| `CONTACT_LOCATION` | `Wakiso, Uganda`: Contact page and footer |
| `CONTACT_LOCALITY` | `Wakiso`: the town sent to Google |
| `CONTACT_HOURS` | `Monday to Friday, 8:00 am – 6:00 pm` |
| `CONTACT_OPENS` / `CONTACT_CLOSES` | `08:00` / `18:00`: hours sent to Google (days are Monday–Friday, in the file) |

Keep these the same as the Google Business Profile, so Google matches the two.

## Windows app licences (`config/licence.php`)

Schools using SchoolHub on Windows (`APP_EDITION=desktop`) pay with a
licence key, checked on their computer without internet.

| Setting | Where | Notes |
|---|---|---|
| `LICENCE_PRIVATE_KEY` | online server `.env` only | Signs licence keys. **Secret**: never in the code or the Windows app. |
| `LICENCE_PUBLIC_KEY` | server `.env` and the Windows app | Checks licence keys. Not secret. |

Set up once, on the server: `php artisan licence:keygen` writes both into
`.env` and prints the public key, which is built into the Windows app.
Then `php artisan config:cache`. Making a new pair later stops every
licence already issued, so the command refuses unless given `--force`.

Issuing: Platform Management → **Windows licences** → *Issue licence*,
with the school name and code exactly as the school's Licence page shows
them. *Show key* gives the key to send; *Renew* issues the next period.

## Website visitor locations (`config/services.php` → `maxmind`)

The Website visitors page (platform owner) shows each visitor's country
and city from MaxMind's free GeoLite2 City database, kept on the server.
Without it, visits are still counted but have no place.

| Setting | Notes |
|---|---|
| `MAXMIND_ACCOUNT_ID` | From a free account at maxmind.com (GeoLite2). |
| `MAXMIND_LICENSE_KEY` | Generated under *Manage license keys*. Secret. |
| `MAXMIND_DATABASE` | Optional; default `storage/app/geoip/GeoLite2-City.mmdb`. |

After setting them: `php artisan config:cache`, then `php artisan geoip:update`
once. The scheduler refreshes the database every Wednesday at 03:15.

## Subscriptions (`config/subscriptions.php`)

| Setting | Default | Notes |
|---|---|---|
| `SUBSCRIPTION_TRIAL_DAYS` | `30` | Trial length after approval. |
| `SUBSCRIPTION_REMINDER_TIME` | `08:00` | Daily reminder run, Africa/Kampala time. |
| `LANDING_PRICE_MARKUP` | `10` | Percent added to plan prices where the public landing page shows them (nearest UGX 1,000). Billing uses the plan prices. `0` shows them as they are. |
| `SUBSCRIPTION_MOMO`, `SUBSCRIPTION_AIRTEL`, `SUBSCRIPTION_BANK` | empty | How schools pay SchoolHub; shown on the Subscription page. |
| `SUBSCRIPTION_CONTACT_PHONE`, `SUBSCRIPTION_CONTACT_EMAIL` | empty | Billing contact shown to schools. |

In code (change by editing the file): billing cycles (term 4 months, year
12), warning 14 days before the end, 14 grace days before locking,
reminders at 14/7/3/1/0 days, SMS only in the last 3 days, parents and
students not counted towards login limits.

## Settings kept in code

| File | Holds |
|---|---|
| `config/academics.php` | Curricula, default class names, school calendar, assessment types and default weights, subject lists |
| `config/payroll.php` | NSSF rates (5% employee, 10% employer), PAYE order, arrears taxable, LST months (July–October) and bands |
| `config/legal.php` | Terms version (bump when the terms change), data kept 12 months after a school locks, deletion 30 days after a written request, 30 days' notice of changes |
| `config/subscriptions.php` | See above |

PAYE brackets are data, not config: seeded by `PayeTaxBracketSeeder` into
`paye_tax_brackets`.

## Not used today

`REDIS_*`, `MEMCACHED_HOST` and `AWS_*` are Laravel defaults; SchoolHub
does not use Redis, Memcached or S3 at present.
