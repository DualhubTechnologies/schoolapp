# Testing

How SchoolHub is tested, what is covered, and what is still checked by
hand.

## Automated checks

Every push to a branch, every pull request and every push to `main` runs
`composer ci:check` in GitHub Actions (`.github/workflows/tests.yml`):

| Step | Tool | Fails when |
|---|---|---|
| Formatting | Laravel Pint (`pint --test`) | Code is not formatted to the project style |
| Types | PHPStan level 7 with Larastan (`phpstan.neon`) | A type error, undefined property or wrong return type |
| Tests | Pest on MySQL 8 | Any test fails |

Nothing reaches production unless all three pass on `main`.

`phpstan-baseline.neon` lists older findings recorded when the type check
was introduced. New code must pass cleanly; remove baseline entries as
they are fixed.

## Running tests locally

```bash
php artisan test --compact                               # everything
php artisan test --compact tests/Feature/IdCardsTest.php # one file
php artisan test --compact --filter="staff card"         # by name
```

`phpunit.xml` defaults to SQLite in memory, but the migrations use
MySQL-only SQL, so point the tests at a MySQL database the way CI does:

```bash
DB_CONNECTION=mysql DB_DATABASE=schoolapp_test DB_USERNAME=root DB_PASSWORD=secret \
  php artisan test --compact
```

The test database is wiped on every run: never point it at real data.
Mail uses the `array` mailer and SMS the `log` driver, so nothing is
really sent.

## What is covered

Feature tests in `tests/Feature` (27 files), including:

| Area | Tests |
|---|---|
| Registration & approval | `SchoolRegistrationTest`, `SchoolApprovalTest`, `EmailVerificationCodeTest`, `ConfirmAdminEmailTest`, `OnboardingFunnelTest` |
| Students | `QuickAdmissionTest`, `StudentImportTemplateTest`, `UndoDeleteTest` |
| Exams | `EnterMarksTest` |
| Transport | `TransportTest`, `TransportLedgerTest` |
| Parents | `ParentPageTest` |
| ID cards | `IdCardsTest`: readiness rules, template saving, card numbers, expiry, print and PDF in both orientations, staff cards, keeping schools apart |
| Errors | `ErrorManagementTest`: references, owner email once, reopening, friendly pages, finding by reference |
| Files & images | `PrivateFilesTest`, `ImageShrinkerTest`, `SchoolLogoUploadTest` |
| Platform | `ActivityLogsPageTest`, `OnlineUsersPageTest`, `PlatformActivityKpisTest`, `GlobalSearchTest` |
| Screens | `RecordFormPagesTest`, `SchoolFormLayoutTest`, `PhoneExperienceTest`, `LandingPageTest` |

## Writing a test

- Feature tests with Pest: `php artisan make:test --pest SomethingTest`.
- Set up the way existing tests do: seed `RoleSeeder`, create a school,
  start its trial (`SubscriptionManager::startTrial`), then act as a user
  with the right role.
- Test Filament pages with `Livewire::test(Page::class)` and printable
  documents with `$this->get(route(...))`.
- For anything touching school data, include a test that another school's
  records are refused.

## Checked by hand before a release

Automated tests cannot see how things look or behave on a real phone:

- New pages at phone width, on Android Chrome and iPhone Safari.
- PDFs and print views: open the file and look at the layout (dompdf
  differs from browsers).
- Photo upload from a phone camera, including cropping.
- Emails and SMS on a staging setup with real providers.

## Gaps worth closing

- Fees and billing (`BillingService`, tested today only through
  transport fares), payroll (`PayrollCalculator`) and results
  (`ResultsCalculator`) handle money and grades and deserve direct tests
  of their calculations.
- A test that every school-facing resource filters by school.
