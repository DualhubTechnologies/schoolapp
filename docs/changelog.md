# Changelog

What changed in SchoolHub, newest first, in terms of what schools and the
platform owner notice. Housekeeping commits (formatting, type fixes) are
folded into the change they belong to. Add an entry with every release.

## 29 September 2026

- **System health** page for the platform owner: shows whether email, SMS,
  the queue worker, the scheduler and backups are really working, and what
  to change on the server if not.
- **Nightly backups** (`backup:run`) of the database and uploaded files,
  kept 14 days; failures are reported.
- The platform owner's password is no longer kept in the code.
- Deploys now restart the queue worker so it runs the new code.
- Development documentation (README and `docs/`).
- **Error management.** Friendly error pages for missing pages, no access,
  timed-out sessions, too many attempts, server errors and updates, with
  Back/Home buttons and a WhatsApp help link. Every unexpected error gets a
  reference such as `E-7K3Q9P` that users can quote. The platform owner
  gets **Error reports** (grouped, searchable by reference, resolve or
  reopen) and an email when an error is new or returns.
- **Photo uploads fixed.** Editing a student or staff photo no longer
  fails to upload; the crop is now square and saved as a normal JPEG.
- **Staff ID cards**, and **Identity Cards** became its own module with a
  new access switch under Settings → Users. Staff records gained a photo
  and date of birth.

## 28 September 2026

- **ID cards.** Student ID cards with preview, card-printer print and A4
  PDF export; printing is blocked until each card has what it needs.
  Redesigned into a school **Template**: landscape or portrait, the
  school's colours, validity, rules on a shared back, card numbers, issue
  and expiry dates.
- **School approval.** Self-registered schools wait for the platform
  owner's approval on their own Awaiting approval page.
- Registration no longer hangs when the mail server is slow.
- Phones: no more false "Error while loading page"; clear notices when the
  connection is lost.
- Cleaner Edit school page for the platform owner.

## 27 September 2026

- **Transport.** Van routes and fares billed per learner, transport paid
  first, collections report and route lists; its own module.
- **Quick admission** of a learner from one short screen, and a
  professional admission letter.
- Parent page with SMS receipts, marks autosave, global search, a phone
  navigation bar, SchoolPay codes shown as a way to pay.
- Student photos, signatures and finance receipts made private.
- Recommended Uganda set-up offered to new schools on first sign-in.
- Platform owner: who is online, today's activity, activity logs in the
  sidebar.
- Tests run on MySQL and deploys happen only after they pass.
- Logo and photo uploads no longer hang on iPhones.
- Password rules relaxed to 6 characters minimum.

## 26 September 2026

- Automatic deployment to the server on every push to `main`.
- Live password and email checks and a welcome email on registration.
- Plan prices lowered by about 20%.
- Refreshed landing page.

## 22 September 2026

- Finance, year-end promotion and per-user access control (modules).
- Self-registration, landing page, subscriptions and role dashboards.
