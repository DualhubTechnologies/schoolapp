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

## Database, sessions, cache, queue

| Setting | Production value | Notes |
|---|---|---|
| `DB_CONNECTION` | `mysql` | **Required.** MySQL 8; the migrations use MySQL-only SQL. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | server values | **Required.** Use a dedicated database user, not root. |
| `SESSION_DRIVER` | `database` | Sessions live in the `sessions` table. |
| `SESSION_LIFETIME` | `120` | Minutes of inactivity before sign-out. Expired sessions show a "session timed out" page. |
| `SESSION_ENCRYPT` | `false` | |
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

## Subscriptions (`config/subscriptions.php`)

| Setting | Default | Notes |
|---|---|---|
| `SUBSCRIPTION_TRIAL_DAYS` | `30` | Trial length after approval. |
| `SUBSCRIPTION_REMINDER_TIME` | `08:00` | Daily reminder run, Africa/Kampala time. |
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
