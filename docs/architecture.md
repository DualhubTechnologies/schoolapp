# Architecture

How SchoolHub is put together, for a developer joining the project or an
IT reviewer assessing it.

## The big picture

SchoolHub is a single Laravel 13 application serving many schools from one
database (multi-tenant by `school_id`). The interface is built with
Filament 5 and Livewire, so almost every screen is server-rendered PHP with
small, targeted JavaScript.

```
 Phone / computer browser  (installable as an app: public/sw.js)
            │
            ▼
   nginx + PHP-FPM  ──►  Laravel 13 application
                           ├── "app" panel   (/)       schools: admin, bursar, teachers
                           ├── "admin" panel (/admin)  platform owner (Super Admin)
                           ├── public pages            landing, terms, parent links (/p/{token})
                           ├── queue worker            emails, student CSV imports
                           └── scheduler               subscription reminders, clean-up
            │
            ├── MySQL 8          all data, sessions, cache, queue jobs
            ├── storage/app/public            school logos (public)
            ├── storage/app/private/uploads   student & staff photos, signatures (private)
            ├── SMTP mail server             emails
            └── Africa's Talking             SMS
```

## Panels

| Panel | Path | Who | Defined in |
|---|---|---|---|
| `app` | `/` | Everyone in a school (and the owner, who sees platform links) | `app/Providers/Filament/AppPanelProvider.php` |
| `admin` | `/admin` | Super Admin only (`User::canAccessPanel`) | `app/Providers/Filament/AdminPanelProvider.php` |

The app panel's menu groups, in order: Students, ID Cards, Fees, Transport,
Finance, Exams & Results, Human Resources, Academics, Settings, Platform
Management.

## Keeping schools apart

- Every school-owned table has a `school_id`.
- Each Filament resource narrows its query to the signed-in user's school in
  `getEloquentQuery()`; custom pages and controllers filter by
  `auth()->user()->school_id` themselves.
- Printable documents (receipts, report cards, payslips, ID cards) check the
  record's school against the user before rendering.
- The Super Admin has no `school_id` and works across schools only through
  the admin panel.

This is explicit scoping, not a global scope, so **every new query on
school data must filter by school**. See [Security](security.md).

## Who can do what

Two layers:

1. **Roles** (Spatie Permission), created by `RoleSeeder`: Super Admin,
   School Admin, Teacher, Accountant, Staff, Parent, Student.
   (`Modules::ROLE_DEFAULTS` also lists a Bursar role, which the seeder
   does not create.)
2. **Modules** (`app/Support/Modules.php`): the parts of the system a user
   may open. The School Admin ticks them per user under Settings → Users;
   a user with none ticked gets their role's defaults. School Admins have
   everything. Each page and resource is mapped to a module, and the same
   check hides the menu item and blocks the URL.

| Module | Covers |
|---|---|
| students | Student records, guardians |
| promotion | Year-end promotion |
| attendance | Daily class register, attendance report, texts to parents of absent learners |
| messages | Bulk SMS to families, a class, those owing fees, or staff |
| id_cards | Student and staff ID cards, the card template |
| fees | Payments, receipts, billing, balances, reminders, fee set-up |
| transport | Van routes, learners, route lists |
| finance | Expenses, income, budget, income vs expenditure |
| exams / exams_all | Marks (own subjects / all subjects, submitting / approving mark sheets), marks progress, results, report cards |
| hr | Staff, salaries, payroll, payslips |
| academics | Years, terms, classes, streams, subjects, grading |
| settings | School profile, users & access, audit trail |

## A school's life cycle

1. **Registers** at `/register` (school details, admin account, email code,
   terms version accepted). Status `pending`.
2. **Awaiting approval**: the school sees only the Awaiting approval page.
3. **Approved** by the owner: a trial starts (`subscriptions.trial_days`).
4. **Subscribed**: pays per term or year on a plan (limits on students and
   logins); reminders go out before the end date.
5. **Locked** if unpaid after the grace days, or suspended by the owner:
   only the Subscription page opens (`EnsureSchoolSubscribed`). Data is
   kept (see `config/legal.php`).

## Code map

| Folder | Holds |
|---|---|
| `app/Filament/App/Resources` | School-side lists and forms (Students, Payments, Staff, …) |
| `app/Filament/Pages` | School-side custom pages (Receive Payment, Enter Marks, Report Cards, ID Cards, …) |
| `app/Filament/Admin/Resources` | Owner pages (Schools, Plans, Error reports, …) |
| `app/Filament/Support` | Shared Filament pieces (ID card page base, error notices, fee reminder actions) |
| `app/Services` | Business logic: billing, results, promotion, payroll, ID cards, SMS, subscriptions, dashboards |
| `app/Http/Controllers` | Printable documents and public pages (receipts, payslips, report cards, ID cards, parent page) |
| `app/Support` | Cross-cutting helpers: modules, private files, image shrinking, error recorder |
| `app/Models` | Eloquent models (one per table) |
| `config/` | Includes SchoolHub's own: `academics`, `payroll`, `subscriptions`, `sms`, `contact`, `legal` |
| `resources/views` | PDF and print templates, error pages, Filament page views |
| `public/js/schoolhub-mobile.js` | Phone helpers: install prompt, offline / error banners |

## Main tables

| Area | Tables |
|---|---|
| Platform | `schools`, `users`, `plans`, `subscriptions`, `subscription_payments`, `subscription_activation_codes`, `subscription_reminders`, `demo_requests` |
| Students | `students`, `guardians`, `school_classes`, `sections`, `class_levels`, `houses`, `residency_types`, `student_imports` |
| Fees | `fee_structures`, `student_charges`, `student_payments`, `student_discounts`, `fee_reminders` |
| Transport | `transport_routes` (routes are linked from `students`) |
| Finance | `finance_entries`, `finance_categories`, `budget_lines` |
| Academics | `academic_years`, `terms`, `subjects`, `combinations`, `assessments`, `marks`, `mark_sheets`, `grading_scales`, `grading_bands`, `term_reports`, `promotions`, `promotion_rules` |
| HR & payroll | `staff`, `staff_salaries`, `staff_allowances`, `staff_deductions`, `staff_bank_details`, `salary_arrears`, `payroll_periods`, `payroll_entries`, `payroll_entry_items`, `paye_tax_brackets` |
| ID cards | `id_card_templates` |
| Attendance & messages | `attendance_records`, `message_batches` |
| Audit & errors | `activity_log`, `error_reports`, `error_occurrences` |
| Laravel | `sessions`, `cache`, `jobs`, `failed_jobs`, `notifications`, `imports`, `exports` |

## Documents and files

- **PDFs** are rendered with dompdf from Blade views. dompdf does not
  support flexbox or CSS grid, so PDF templates use tables and fixed sizes.
- **Print views** are plain HTML pages that open the browser's print dialog
  (`resources/views/fees/layout.blade.php` is the shared shell).
- **Private files** (photos, signatures) live on the `uploads` disk and are
  only shown through signed links that expire after 30 minutes
  (`App\Support\PrivateFiles`). PDFs embed them as data URIs.
- **Photos** are sent full size by phones and shrunk on the server
  (`App\Support\ImageShrinker`), because shrinking in the browser hangs on
  iPhones.

## Background work

| What | How |
|---|---|
| Welcome, approval, registration emails | Queued notifications (`QUEUE_CONNECTION=database`) |
| Student CSV import | `App\Jobs\ProcessStudentImport` on the queue |
| Bulk SMS | `App\Jobs\SendMessageBatch` on the queue |
| Subscription reminders | `subscriptions:remind`, daily at 08:00 Kampala time |
| Backup (database + files) | `backup:run`, daily at 01:30 Kampala time |
| Error occurrence clean-up | `model:prune`, daily at 02:30 |
| Scheduler heartbeat | Every minute; watched by System health |
| Error alert email | Sent after the response (`defer`), not queued |

## Errors

Unexpected errors are recorded by `App\Support\ErrorRecorder` into
`error_reports` (one per distinct error) and `error_occurrences` (each
time, with a reference like `E-7K3Q9P` shown to the user). The owner sees
them under Error reports and is emailed when an error is new or returns.
See [Operations](operations.md).
