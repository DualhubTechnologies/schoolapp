# SchoolHub

School management for Ugandan primary and secondary schools, built by
DualHub Technologies. One installation serves many schools: each school
signs up, is approved by the platform owner, and runs its admissions,
fees, transport, finance, exams and report cards, payroll and ID cards
from any phone or computer.

Live at **schoolhubug.com**.

## What is in it

| Area | What schools do with it |
|---|---|
| Students | Admission (quick or full), guardians, classes and streams, houses, photos, CSV import, promotion at year end |
| Fees | Fee structures per class, term billing, receipts, balances, discounts, SMS and letter reminders, SchoolPay codes |
| Transport | Van routes and fares, learners on each route, route lists, collections |
| Finance | Expenses, other income, term budget, income vs expenditure |
| Exams & results | Assessments, marks entry, class results, report cards, sharing with parents by SMS link |
| HR & payroll | Staff records, salaries, allowances, deductions, arrears, payroll with PAYE, NSSF and LST, payslips |
| ID cards | Student and staff ID cards from the school's own template (colours, orientation, validity), print or PDF |
| Platform (owner) | School approval, plans and subscriptions, activation codes, demo requests, activity logs, online users, error reports |

## Tech stack

- **PHP 8.4**, **Laravel 13**, **Filament 5** (admin UI), **Livewire**
- **MySQL 8** (the migrations use MySQL-only SQL; tests run on MySQL too)
- **dompdf** for PDFs, **Spatie** permissions and activity log
- **Pest** tests, **PHPStan** (level 7, via Larastan), **Pint** formatting
- Node 22 + Vite for the Filament theme build

## Getting started (local)

Requirements: PHP 8.4 with `gd`, `pdo_mysql`, `mbstring`, `intl`, `zip`;
Composer 2; MySQL 8; Node 22.

```bash
git clone https://github.com/DualhubTechnologies/schoolapp.git
cd schoolapp
cp .env.example .env            # then set DB_* for your MySQL
composer setup                  # install, key, migrate, npm install + build
php artisan db:seed             # roles, PAYE brackets, the platform owner
composer run dev                # start the local development server
php artisan queue:work          # in another terminal: emails and imports
```

- School app: `/` (sign in, or register a school at `/register`)
- Platform owner: `/admin` (Super Admin only)

The platform owner (Super Admin) account is created by `SuperAdminSeeder`
from `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` in `.env`. Leave the
password empty and a random one is generated and shown once in the
console. No password is kept in the code.

## Everyday commands

| Command | What it does |
|---|---|
| `composer run dev` | Start the local development server (`php artisan dev`) |
| `php artisan queue:work` | Process queued jobs: welcome/approval emails, student CSV imports |
| `php artisan test --compact` | Run the test suite |
| `vendor/bin/pint --dirty` | Format the PHP you changed |
| `composer types:check` | PHPStan |
| `composer ci:check` | Exactly what CI runs: Pint (test mode), PHPStan, tests |
| `php artisan schedule:work` | Run the scheduler locally (reminders, clean-up) |

## Documentation

| Document | For |
|---|---|
| [Architecture](docs/architecture.md) | How the system is put together |
| [Configuration](docs/configuration.md) | Every environment setting |
| [Deployment](docs/deployment.md) | How code reaches production, server requirements, rollback |
| [Operations](docs/operations.md) | Day-to-day running and fixing |
| [Security](docs/security.md) | Access, school separation, files, secrets |
| [Modules](docs/modules.md) | How fees, results, payroll, ID cards and subscriptions work |
| [Contributing](docs/contributing.md) | Workflow and coding conventions |
| [Testing](docs/testing.md) | What is tested and how |
| [Decisions](docs/decisions.md) | Why key choices were made |
| [Changelog](docs/changelog.md) | What changed and when |

AI coding assistants also read [AGENTS.md](AGENTS.md).

## Licence

Proprietary. © DualHub Technologies. All rights reserved.
