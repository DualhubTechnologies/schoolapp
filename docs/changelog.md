# Changelog

What changed in SchoolHub, newest first, in terms of what schools and the
platform owner notice. Housekeeping commits (formatting, type fixes) are
folded into the change they belong to. Add an entry with every release.

## 1 October 2026

- **Windows app, phase 2: the desktop edition** (`APP_EDITION=desktop`;
  the online system is `server`, the default, and is unchanged).
  First run asks for the school and its administrator and signs them in
  (no email code or approval); there is only ever one school. The public
  website, sitemap, parents' links, demo form, registration and the
  platform owner's panel are not there. A Backups page (Settings) makes
  a backup and downloads it to save on a flash disk. Texts that cannot
  go out because the computer is offline wait in an outbox and are sent
  every five minutes once it is online (`sms:send-queued`); after the
  first connection failure the rest of a batch goes straight to the
  outbox. Undo delete now works on SQLite as well. Settings for the
  edition are in `.env.desktop.example`.
- **Windows app, phase 1** (pushed earlier today): month charts, backups
  and date-only values work on SQLite.

- **Website visitors** (platform owner only, Platform Management): visits
  and unique visitors to the public pages for the last 7, 30 or 90 days
  or 12 months, a visits-per-day chart, and the top countries, cities,
  pages, how visitors arrived (Google, WhatsApp, Facebook, other pages…)
  and devices. No IP address is stored; unique visitors are counted with
  a one-way daily code. Bots, link previews, prefetches and the owner's
  own visits are not counted. Visits are recorded after the page is
  sent and kept for two years. Countries and cities come from MaxMind's
  free GeoLite2 database on the server (`geoip:update`, weekly), once
  `MAXMIND_ACCOUNT_ID` and `MAXMIND_LICENSE_KEY` are set; until then the
  page explains how to switch them on.

- **Public pages: About, Features, Pricing, Help and Contact**, each with
  its own address, title and description so Google can list them as
  sitelinks, in the menu and the sitemap. They reuse the home page's
  sections, which now live in resources/views/site. The Contact page
  and footer show the phone (0782 863209), location (Wakiso, Uganda)
  and hours (Monday to Friday, 8 am - 6 pm), and the same details go to
  Google with the logo. A demo request returns to the page it was sent
  from. /about is now a real page.
- Fixed: Blade read "@context" in yesterday's Google business details as
  a directive, so that block was not valid JSON. It is now built in PHP
  and tested to parse.

- **Google shows the SchoolHub logo.** The site had no favicon.ico, so
  Google showed a plain globe beside it in results. It now has one
  (16/32/48px), a 48px icon, a canonical address on the public pages,
  the logo as the publisher's logo, a sitemap.xml (named in robots.txt),
  and the old website's /about and /privacy-policy, which Google still
  lists, redirect to the home page and the privacy section of the terms.

## 30 September 2026

- **Marks and exams revised.** Teachers submit finished mark sheets and
  the Director of Studies approves them or returns them with a note;
  approved sheets are final until reopened. New **Marks Progress** page
  shows every sheet of an exam (not started, being entered, complete,
  submitted, approved) with "approve all submitted". Mark sheets can be
  downloaded to Excel, filled offline and uploaded, or printed blank or
  filled. Results and report cards can be produced for one exam alone
  (mid-term reports). The Exams list warns when a term's weights do not
  add up to 100%.
- **Attendance** module: daily class register (present, absent, late,
  excused), text parents of absent learners, attendance report with
  Excel export, and the term's attendance on report cards.
- **Messages (SMS)** module: bulk SMS to all families, a class or stream,
  families owing fees, or staff, personalised with the learner's name,
  class or balance; sent in the background with a history.
- **Two-step sign-in** with an authenticator app, from each user's new
  profile page (recommended for the platform owner and bursars).
- Security headers on every page, HTTPS links in production, and new
  System health checks: HTTPS and secure cookie, log rotation, disk space,
  owner two-step sign-in.

- **Students by class chart** shows boys and girls side by side in each
  class (they were stacked, so a class of mostly one gender showed one
  colour), labels each class with its total ("S.1 (84)"), and counts
  "Male"/"FEMALE" typed in any case.

- **Server readings for the platform owner.** System health opens with a
  Server panel (CPU load, memory, swap, disk, database response and
  size, uptime), refreshed every 30 seconds, each marked fine, "Watch"
  or "High" with what to do. The platform dashboard shows CPU, memory,
  disk and database as cards, and a high reading counts toward System
  health's warning badge.

- **Student imports capture residency.** The template has a `residency`
  column filled with the school's own names (Day, Boarding...), and the
  import sets it, so imported learners are billed their day or boarding
  fees. An unknown name is listed at the check with the names to use; a
  blank leaves it unset.

- **Report Cards: search and filters.** Search the class by name,
  admission number or LIN (Search button or Enter), and filter by sex,
  residency and whether the class teacher's comment is written. "Print
  all" becomes "Print N shown" and prints just those learners. A
  "Loading report cards…" indicator shows while a class loads.

- **Filters show only the school's own records.** The Class, Section,
  Residency and Academic year filters on Students, Sections, Payments,
  Billing, Fee Setup, Fee Balances, Fee Reminders and Terms listed every
  school's entries (a secondary school saw another school's "Baby
  Class"). They now list only the school's own.
- **ID cards: search and more filters.** A search box with a Search
  button (students by name, admission number, LIN or SchoolPay code,
  across the whole school or within a class; staff by name, number, job
  or phone), filters for sex, residency and house (students), sex
  (staff), and "Ready to print / Missing details", with "Clear search
  and filters". A "Loading ID cards…" indicator shows while the cards
  are fetched.

- **Student imports respect the plan.** A file with more new students
  than the plan has room for is no longer part-imported: nothing is
  imported, and the check explains the limit (plan, students now, total
  needed), lists the plans that would fit, links the administrator to
  the Subscription page with the right plan marked, or says how many
  rows to remove. "Check again" re-checks the same file after an
  upgrade.

- **Import dates are day first.** Student and staff templates now write
  dates as DD-MM-YYYY, and imports read the ways Excel saves them:
  14-03-2012, 4-3-2012, 14/03/2012, 14-03-12, 14-Mar-2012, 2012-03-14
  or Excel's own date number. A date that does not exist (31-02-2012)
  is refused, and the message shows the value that could not be read.

- **Staff bulk upload**, like students: a CSV template with five example
  staff (teaching and support, paid by bank and by mobile money), then
  upload, check and import from the Staff page. Each row can carry the
  job, category, department, employment details, TIN/NSSF, starting
  salary and bank or mobile money details. Rows with problems are listed
  and skipped; staff numbers the school already has are left alone, so a
  file can be uploaded again.
- Staff numbers are now unique within each school, not across all
  schools, so two schools can both have "ST-001".

- **ID card template preview.** The Template window shows a sample card
  (Mugizi Adrian at Sample Secondary School), front and back, redrawn
  as the orientation, colours, validity and rules change, before
  anything is saved.
- **ID cards print one card per page.** The back of each card was pushed
  down by the on-screen spacing and spilt onto an extra page; front and
  back now each fill exactly one CR80 page, with no blank page at the
  end.
- ID Cards page: cards grow on large screens with the back beside the
  fronts, and shrink to fit on phones.

- **Report card template.** Each school sets how its report cards look,
  once, from the Template button on Report Cards: Classic, Modern or
  Compact design, main and accent colours, page border, lettering, its own
  report title, an extra header line, a footer and a faint logo
  watermark, and which parts are printed (photo, exam columns, positions,
  teacher initials, conduct, promotion, next term date, fees, comments,
  signatures, grading key). Schools that never set one get the standard
  design. Parents' online cards use the same template.
- **Sharing report cards checks the marks first.** Share with parents now
  lists every exam still without marks, class by class, and will not
  share until they are entered or the head ticks "Share anyway".

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
