# Module guides

How SchoolHub works out the figures schools rely on: fees, balances,
transport, results, promotion, payroll, subscriptions and ID cards. Each
section names the code that holds the rule, so the guide and the code can
be checked against each other.

## Fees and billing

**Code:** `App\Services\BillingService`, `App\Models\FeeStructure`

Billing puts charges onto student accounts. It works fee by fee, so it is
safe to run again:

- Run it once: every applicable fee is charged.
- Run it again: nothing happens. No duplicates.
- Add a fee next week and run again: only the new fee is charged.

**Which fees apply**

| Fee type | Charged |
|---|---|
| `per_term` | Each term, from the term it starts, until a newer version replaces it |
| `once` | Once per student, if never charged before (e.g. admission) |
| `on_demand` | Never automatically; billed on purpose to a class or student |

A fee can apply to all students or new students only, and to everyone or
one residency (day / boarding).

**Discounts and bursaries**

| Kind | Effect |
|---|---|
| Fixed amount on the whole bill | One pool, spent once across all fees (70,000 off the total, not off each line) |
| Percentage | Applies to every line it covers |
| Scoped to one fee | That fee only |
| Full bursary | Waives every fee |

Each award is for this term, this academic year, or ongoing, and is
ignored outside that period. Bursaries never cover transport.

## Balances and statements

**Code:** `App\Services\StudentLedger`

A student's balance is **worked out, never stored**: charges and payments
merged in date order with a running balance. Correct a charge or void a
payment and the balance is right at once.

| View | Shows |
|---|---|
| Ledger | Every line, all terms (the bursar's record) |
| Statement | One term, summarised (what a parent receives) |
| Invoice | The current balance as a document |

Receipts are numbered per school; a voided receipt stays visible, stamped
VOID, and no longer counts.

## Transport

**Code:** `App\Services\Transport\TransportLedger`, `BillingService`

- A learner on a van route is charged that route's fare once per term
  (one-way fare if set and chosen; otherwise the full fare). No route
  means the parent brings them and transport costs nothing.
- **Transport is paid first**: each payment clears the van before it counts
  towards school fees, oldest term first. Money paid ahead also clears any
  van still owing. The parent still sees one bill and one balance.

## Fee reminders

**Code:** `App\Services\FeeReminderService`

Only students who owe are reminded, by SMS to the guardian or by printed
letter. By default no one gets a second SMS within a few days of the last.

## Results and report cards

**Code:** `App\Services\Academics\ResultsCalculator`, `config/academics.php`

- A subject's term score is the weighted average of the term's assessments
  the learner sat, each as a percentage of its "out of". If no assessment
  has a weight, they count equally.
- Default weights (`config/academics.php`): primary and A-Level BOT 20% /
  MOT 30% / EOT 50%; O-Level (new curriculum) EOT 80% and continuous
  assessment 20%.

| Curriculum | Overall result |
|---|---|
| Primary | Aggregate of the four core subjects (D1 = 1 … F9 = 9) and division |
| O-Level | Average score and achievement level (A–E) |
| A-Level | Points: principal subjects A–F (6–0) plus subsidiaries (1 each), out of 20 |

Positions are given in class and stream. Report cards are shared with
parents only when the school chooses to share them.

## Mark sheets and approval

A mark sheet is one exam, one class, one subject (`mark_sheets`):

| Status | Who can change the marks |
|---|---|
| Being entered | The subject teacher (and class teacher for their stream), and the Director of Studies |
| Submitted | Only those with "Marks for all subjects" (or School Admins) |
| Approved | Nobody, until the Director of Studies reopens it |

Locking an exam still closes every sheet of it. **Marks Progress** shows
every sheet's state; **Results for** on Results and Report Cards picks the
whole term or one exam alone. Spreadsheet upload matches learners by
admission number and applies the same checks as typing.

## Attendance

One record per learner per day (`attendance_records`). Late counts as
present; excused is shown apart. Class teachers take only their own
streams. "Text parents of absent learners" sends once per record, for
today's register only. Report cards show "Attendance: X of Y days" for the
term's dates, unless a days-present figure was typed on the term report.

## Messages (SMS)

Audiences: all families, a class or stream, families owing fees, all
staff. A family with several children receives one SMS unless the text
uses `{student}`, `{class}` or `{balance}`. Sending runs on the queue
(`SendMessageBatch`, never retried so nobody is texted twice); needs the
queue worker and `SMS_DRIVER=africastalking`.

**Report card template** (`App\Models\ReportCardTemplate`): one per
school, set by those who manage exams (head teacher, director of studies,
school admin). Until a school saves one, the defaults apply: Classic
design, navy and gold, no border, every part printed. The template
applies to printed cards and to cards parents open. The fees box is the
one part also chosen per print (the "Show fees balance" tick starts from
the template); parents never see fees on their online card.

**Sharing** (`App\Services\Academics\MarksCompleteness`): a subject counts
as taught in a class once any learner has a mark in it that term; each of
the term's exams for the class's curriculum must then have marks in it.
Missing ones are listed before sharing, and the head must tick "Share
anyway" to share regardless. Single learners without a mark are not
listed (absences and electives).

## Promotion

**Code:** `App\Services\Academics\PromotionService`, `PromotionAdvisor`

- Each learner is promoted, repeats, completes or leaves. "Next class" is
  the next in the school's order (S1 → S2, P6 → P7, top nursery → P1).
- The last class of a curriculum (P7, S4, S6) defaults to Completed; S4
  learners continuing to S5 are switched to Promote by hand.
- A learner is promoted at most once per academic year, and every run can
  be undone.
- The adviser recommends from the school's promotion rules: promote,
  probation, repeat, or "no results". The final decision is always made
  on the promotion screen.
- Unpaid balances carry into the next year automatically.

## Payroll

**Code:** `App\Services\Payroll\PayrollCalculator`, `PayrollService`, `config/payroll.php`

| Item | Rule |
|---|---|
| Gross | Basic + allowances + approved arrears |
| NSSF | 5% employee (deducted) and 10% employer (school's cost), on gross |
| PAYE | URA monthly bands (`paye_tax_brackets`) on taxable pay; employee NSSF is not deducted first |
| LST | Annual Local Service Tax by pay band, in equal parts July–October |
| Deductions | Loans, advances, SACCO, welfare in force that month |
| Net | Gross − NSSF − PAYE − LST − deductions |

All amounts are whole shillings. A payroll month moves from draft to
approved (figures locked) to paid, and produces payslips and the NSSF
schedule (PDF and Excel).

## Subscriptions

**Code:** `App\Services\Subscriptions\SubscriptionManager`, `config/subscriptions.php`

| State | Meaning |
|---|---|
| pending | Registered, awaiting the owner's approval |
| trial | On the free trial (30 days by default) |
| active | Paid up |
| grace | Period ended; still working for 14 days, with a red banner |
| expired | Grace over; locked until payment is recorded |
| suspended | Switched off by the owner; locked |
| none / rejected | Never subscribed / registration turned down |

The state is worked out from dates every time, so there is no nightly job
to forget. Plans limit active students and staff logins; parents and
students do not count. Reminders go out 14, 7, 3, 1 and 0 days before the
end (SMS only in the last 3 days).

## ID cards

**Code:** `App\Services\IdCardService`, `App\Models\IdCardTemplate`,
`App\Filament\Support\IdCardsPage`

- Each school has one **Template**: landscape or portrait, main and accent
  colours, validity (end of the academic year or a number of months), and
  the rules printed on the back. Only users with Settings access edit it.
- **Front**: the holder's photo, name and details. Students: student
  number, class and stream, sex, date of birth, house, residence and LIN
  when recorded, parent's phone. Staff: staff number, designation,
  department, sex, date of birth, NIN when recorded, phone.
- **Back**: the same on every card: return-to address, the school's rules,
  the head teacher's signature.
- **Card number**: school code / year of printing / holder, e.g.
  `SH84914/26/00142` (students) or `SH84914/26/S0049` (staff). The same
  person keeps the same number all year.
- **Issue date** is the date of printing; **expiry** follows the template.
- A card cannot print until it has everything the front needs. Students:
  photo, student number, class, date of birth, sex, parent's phone. Staff:
  photo, staff number, designation, sex, phone.
- **Print** makes one CR80 card per page, front then back, for a card
  printer. **Export PDF** lays out 8 landscape or 9 portrait cards per A4
  sheet, backs mirrored for double-sided printing.

## Parent page

**Code:** `App\Http\Controllers\ParentPageController`

A private link (`/p/{token}`) sent by SMS shows a parent their child's
balance, receipts and shared report cards, with no password. See
[Security](security.md).
