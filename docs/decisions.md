# Decisions

Short records of why SchoolHub is built the way it is, so a later change
does not undo a choice without knowing the reason. Add a new entry
whenever a choice is not obvious from the code. Each entry: the
situation, the decision, and what it costs.

## 1. One database for all schools, separated by `school_id`

**Situation.** Many small schools, one small team, one server.
**Decision.** A single database; every school-owned row has a
`school_id`, and every query filters by it.
**Cost.** A missed filter could leak data between schools, so scoping is
checked in review and in tests ([Security](security.md)).

## 2. Filament and Livewire for the interface

**Decision.** Build screens with Filament 5 on Livewire: server-rendered
PHP, very little custom JavaScript.
**Why.** One language, fast to build forms and tables, works on cheap
phones.
**Cost.** Unusual layouts (ID cards, report cards) need custom Blade views.

## 3. Deploy only what the tests passed

**Decision.** Pushing to `main` runs Pint, PHPStan and the tests on
MySQL; only if they pass does the deploy run, and it deploys exactly that
commit.
**Why.** A broken check once stopped every deploy; a broken deploy would
stop every school.

## 4. Shrink photos on the server, not the phone

**Decision.** Phones upload the original picture; the server shrinks it
(`ImageShrinker`). Browser-side resizing is switched off.
**Why.** In-browser resizing hung on iPhones with large pictures.
**Cost.** Uploads are bigger, so PHP and nginx limits must allow them.

## 5. Square photo crop, no circle cropper

**Decision.** Photo fields crop square and keep the original format.
**Why.** The circle cropper saved full-size PNGs of 10–25 MB that failed
to upload, and a round photo did not fill the square frame on ID cards.

## 6. Private files behind expiring links

**Decision.** Photos and signatures live outside the public folder and
are shown only through signed links that expire after 30 minutes.
**Why.** They are personal data about children and staff.

## 7. Balances are worked out, never stored

**Decision.** A student's balance is calculated from charges and
payments each time (`StudentLedger`).
**Why.** There is no second copy to fall out of step: correct a charge or
void a receipt and every balance is right at once.

## 8. Billing is safe to run again

**Decision.** Billing reconciles fee by fee; running it twice charges
nothing new.
**Why.** Bursars re-run billing after adding a fee or a learner; it must
never double-charge.

## 9. Transport is paid first

**Decision.** Each payment clears the van before school fees.
**Why.** That is how Ugandan schools collect van money; it keeps route
lists and receipts consistent.

## 10. Subscription state is worked out from dates

**Decision.** No stored "status" that a nightly job updates; the state
comes from the dates each time.
**Why.** Nothing to forget to run, nothing to drift.

## 11. One ID card template per school

**Decision.** Each school has one template (orientation, colours,
validity, rules), shared by student and staff cards.
**Why.** Schools want one consistent card; simpler to manage.

## 12. ID card numbers are fixed for the year

**Decision.** Card number = school code / year / holder, not a new
number per print.
**Why.** A reprint matches the card it replaces, and nothing extra needs
storing.
**Cost.** No register of individual cards issued (lost-card tracking).
Revisit if schools need it.

## 13. Printing is blocked until a card is complete

**Decision.** Print and Export stay off until every card in the batch has
what its front needs, checked again on the server.
**Why.** A card found incomplete after printing wastes PVC stock.

## 14. Errors are grouped, with a reference for the user

**Decision.** Record each distinct error once with a count; give each
occurrence a short reference; email the owner only when an error is new
or returns.
**Why.** Users can quote what they saw; the owner sees what matters
without a flood of email.

## 15. No new dependencies without agreement

**Decision.** Packages are added only after discussion (e.g. a QR-code
library for ID cards is waiting on this).
**Why.** Each dependency is code to keep secure and up to date.
