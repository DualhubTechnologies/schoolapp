# Contributing and coding conventions

How to make a change to SchoolHub, and the house rules that keep the code
consistent. `AGENTS.md` holds the same rules for AI coding assistants.

## Workflow

1. **Branch** from `main`. Never commit straight to `main`: it deploys.
2. **Make the change** following the conventions below, with tests.
3. **Check locally** before pushing:
   ```bash
   vendor/bin/pint --dirty          # format
   composer types:check             # PHPStan level 7
   php artisan test --compact       # or just the tests you touched
   ```
   `composer ci:check` runs exactly what CI runs.
4. **Push the branch.** CI runs on `claude/**` branches and on pull requests.
5. **Merge to `main`** only when CI is green. Deployment is automatic
   ([Deployment](deployment.md)).

### Commit messages

Write for the school, not the code. The first line says what changed for
users, in plain English, e.g.:

- `Stop edited student and staff photos failing to upload`
- `Add staff ID cards and make Identity Cards its own module`

The body explains why, what the cause was, and anything a reviewer should
know.

## Conventions

### Words users see

- Plain, short English a school secretary would use: "learner", "parent /
  guardian", "stream", "term". No technical terms, codes or English idioms
  that do not travel.
- Say what happened and what to do next, e.g. "Missing: photo, sex, phone.
  Add it."
- Errors never blame the user.

### School data

- Every query on school data filters by the signed-in user's school
  ([Security](security.md)). Re-filter IDs that come from the URL.
- Gate every new page or resource with a module in
  `App\Support\Modules::CLASS_MAP`.

### Structure

- Business rules go in `app/Services`, not in pages or Blade views.
- Filament pages stay thin: they call services and show results.
- Shared Filament pieces go in `app/Filament/Support`.
- Follow the files next to yours: naming, folder layout, and how similar
  features were built.
- No new top-level folders and no new Composer or npm packages without
  agreement.

### PHP

- PHP 8 constructor promotion; explicit return and parameter types.
- PHPDoc with array shapes and generics where PHPStan needs them
  (e.g. `@return Collection<int, Student>`), and
  `@property Collection<…> $name` on pages that use Livewire
  `#[Computed]` properties.
- Comments are complete sentences explaining why, not what.
- Enum-like constants in TitleCase keys where enums are used; otherwise
  follow existing `CONSTANT = ['key' => 'Label']` arrays on models.
- Always curly braces for control structures.

### Printing and PDFs

- PDFs use dompdf: build layouts with tables and fixed mm or pt sizes. No
  flexbox, grid, CSS variables or gradients.
- Images in PDFs are embedded as data URIs (`PrivateFiles::dataUri`,
  `EmbedsImages`).
- Print views extend `fees.layout` and size the page with `@page`.

### Files and photos

- Personal pictures go on the private `uploads` disk and are shown through
  `PrivateFiles::url()`.
- Photo fields use `ImageShrinker::noBrowserResize()` and
  `ImageShrinker::saveWithin()`. Do not turn browser resizing back on (it
  hangs iPhones) and do not use the circle cropper (it saves huge PNGs).

### Phones

- Most users are on phones with slow or unreliable data. Keep pages light,
  make tap targets large, and check a new page at phone width.

### Database

- New columns and tables by migration only; never edit a migration that
  has run in production.
- MySQL is the target; test on MySQL (CI does).
- Money columns are decimals; format as `UGX 1,234`.

## Tests

Every change comes with a Pest feature test: see [Testing](testing.md).
Do not delete or skip tests to make CI pass.
