# Development accounts

Created by `Database\Seeders\DevelopmentAccountsSeeder`, which `php artisan migrate:fresh --seed`
runs in every environment **except production**, after `OrganisationStructureSeeder` (demo company
"Horizon Integrated Solutions": branches CAI / ALX / GIZ, 11 departments, 22 positions) and `EmployeeSeeder`
(60 demo employees EMP-00001 … EMP-00060 with history, contracts, bank accounts, groups, custom fields and
private demo documents — some expiring within 30 days; 3 terminated; one transfer scheduled for next month).
Never use these accounts outside local development.

Accounts are linked to employee profiles by **position** (`EmployeeSeeder::ACCOUNT_POSITIONS`: the first
active holder of that position), so the numbers below are what a fresh `migrate:fresh --seed` produces.

All accounts share the password **`password`**.

## Admin area — `/admin/login`

One account per default role (roles come from `config/permissions.php`).

| Email | Role | Notes |
|---|---|---|
| super.admin@hrmplus.test | Super Admin | Every permission |
| hr.manager@hrmplus.test | HR Manager | Linked to EMP-00002 (HR Director), so it can also open `/portal` |
| hr.specialist@hrmplus.test | HR Specialist | |
| recruiter@hrmplus.test | Recruiter | |
| payroll.accountant@hrmplus.test | Payroll Accountant | |
| attendance.officer@hrmplus.test | Attendance Officer | No payroll or financial permission |
| branch.manager@hrmplus.test | Branch Manager | Data scope: branch **ALX** (Alexandria); linked to EMP-00034 (Operations Manager) |
| department.manager@hrmplus.test | Department Manager | Data scope: department **IT** (and its sub-departments); linked to EMP-00014 (IT Manager) |
| direct.manager@hrmplus.test | Direct Manager | Linked to EMP-00023 (Sales Manager Cairo) |
| finance.officer@hrmplus.test | Finance Officer | |
| reports.viewer@hrmplus.test | Reports Viewer | |

The scopes are looked up by code from the demo structure; edit them under Admin users.

## Employee portal — `/portal/login` and mobile API — `POST /api/v1/auth/login`

| Email | Employee profile |
|---|---|
| employee1@hrmplus.test | EMP-00024 Sherif Soliman — Sales Representative Cairo (has a shared document) |
| employee2@hrmplus.test | EMP-00015 Aya Mansour — Backend Engineer |
| employee3@hrmplus.test | EMP-00036 Youssef Zaki — Storekeeper (Alexandria) |

Other employees have no login account; create one from the employee profile (Personal tab → Login
account), which emails a set-password link.

Admin accounts are refused by the mobile API (HTTP 403).

## Permissions on existing installs

New releases add permissions to `config/permissions.php`. On an existing database run

```bash
php artisan permissions:sync
```

(also run by `RolesAndPermissionsSeeder`). It creates missing permissions and gives each default role the
permissions of its default set that it was never offered before; a permission an administrator removed
from a role is not granted again, and custom grants are kept (`role_default_grants` remembers what was
offered). Phase 3b added `employee_sensitive.*` and `employee_groups.*`.

## Scheduled commands

Run `php artisan schedule:work` locally (cron `schedule:run` in production):
`employees:apply-scheduled-changes` (00:10, future-dated transfers / terminations / salary changes) and
`employees:send-expiry-reminders` (07:00, documents and contracts expiring within the lead time in
Settings → Notifications).

## Recruitment, onboarding and offboarding demo data (Phase 4)

`RecruitmentDefaultsSeeder` (every environment) creates the default 12-stage pipeline, the `job_offer`
approval workflow (one step: permission `offers.approve`), a "General interview evaluation" form and the
"Standard onboarding" checklist. `RecruitmentDemoSeeder` (not in production) then adds, through the real actions:

| What | Details |
|---|---|
| Vacancies | VAC-00001 Sales Representative Cairo, VAC-00002 Accountant (**filled**), VAC-00003 Backend Engineer (2 openings), VAC-00004 Delivery Driver Alexandria (own pipeline, not public), VAC-00005 HR Coordinator (draft). VAC-00001 and VAC-00003 are live on the career page `/careers`. |
| Candidates | 25 (24 applications, 1 talent-pool candidate), each with a demo PDF CV on the private `recruitment_files` disk |
| Interviews | Past ones scored by the panel; upcoming ones with **recruiter@**, **department.manager@** (admin, data-scoped to IT — still sees the interviews they sit on) and **employee2@hrmplus.test** (scores in the portal under *My interviews*) |
| Offers | OFR-00001 accepted and hired (Accountant → new employee *Yasmine El-Sherif*, EMP-00061, no login account, onboarding 40 % done), OFR-00002 awaiting approval (approve as **hr.manager@**), OFR-00003 sent |
| Offboarding | One resignation case (exit interview recorded, 2 items done) plus "contract end" cases opened by the contract-expiry scan |

Salary fields in recruitment (vacancy range, expected / current salary, offered salary) are visible only with
`employee_financials.view` (e.g. hr.manager@, not recruiter@); every view is written to the audit log as
`sensitive_access.recruitment_salary`. E-mails to candidates go to the log mailer (`storage/logs/laravel.log`)
once the queue runs (`php artisan queue:work`).

Additional scheduled commands: `recruitment:send-interview-reminders` (hourly), `recruitment:expire-offers`
(00:20) and `offboarding:detect-contract-expiries` (06:30). Phase 4 added the permissions `interviews.*` and
`offers.*` — run `php artisan permissions:sync` on existing databases.

## Attendance demo data (Phase 5a)

`AttendanceDefaultsSeeder` (every environment) creates the configurable day statuses (absent, on leave, official
mission, remote work, day off, sick leave), the approval workflows `attendance_correction`,
`attendance_manual_batch` (step: `attendance.approve`) and `schedule_change` (step: `schedules.approve`), and the
company-wide **STD** attendance policy (version 1 copied from Settings → Attendance). It also links the onboarding
task "Assign the work schedule" to the schedule assignment screen.

`AttendanceDemoSeeder` (not in production) then builds about five weeks of attendance up to yesterday, through the
real services (so takes ~1.5 minutes):

| What | Details |
|---|---|
| Timezone | Branches without a timezone get `Africa/Cairo` |
| Shifts | MOR 08:00–16:30 (30 min unpaid lunch), EVE 16:00–00:00, NGT 22:00–06:00 (night, cross-midnight), FLX flexible 07:00–19:00 (8 h, core 10:00–15:00), SPA + SPB split shift |
| Schedules | STD Sun–Thu MOR → company; FLEX → IT; ROT 6-day rotation (MOR, MOR, EVE, EVE, off, off) → OPS-WH; NIGHT Sat–Wed → group WH-NIGHT; SPLIT Sat–Thu SPA+SPB → SAL-ALX; MON monthly example (unassigned) |
| Policies | STD (company), OPS (branch ALX: 15 min grace, overtime auto-approved, missing check-out assumed), IT (department IT: breaks from punches, overtime from 60 min) |
| Holidays | **Demo** Egyptian public holidays of the current year (Islamic dates approximate — edit under Shifts & schedules → Public holidays) |
| Raw events | Simulated device punches (`DEMO-DEVICE-*`) and mobile punches (`DEMO-APP`, group FIELD and part of GIZ) with lateness, early leave, overtime, punched breaks, forgotten check-outs and absences, all `payload.demo = true` |
| Manual | One bulk "mark all present except" batch for HR on the latest working day (official mission, late, absent), one import batch for three Alexandria sales employees |
| Corrections | A pending portal request from **employee1@** (approve as attendance.officer@ or hr.manager@), an admin correction approved by hr.manager@ |
| Schedule changes | A pending shift change and an approved swap in the warehouse |
| Locks | The first demo week is locked (Attendance → Period locks) |

Scheduled commands added: `attendance:process` (01:30, yesterday for everyone) and `attendance:process --pending`
(every 10 minutes, raw events whose processing job did not run). Events recorded with `process: 'queue'` are
calculated by `php artisan queue:work`. Phase 5a added the permissions `schedules.*`, `holidays.*` and
`attendance_policies.*` — run `php artisan permissions:sync` on existing databases.

## Attendance capture demo data (Phase 5b)

`AttendanceCaptureDemoSeeder` (not in production) runs after the Phase 5a seeder, through the real services:

| What | Details |
|---|---|
| Devices (all **simulated**) | `CAI-SIM-01` Cairo HQ, pull, most Cairo staff mapped (device user id = digits of the employee number), pulled from today's midnight; `ALX-SIM-01` Alexandria, push, one pushed batch and the same batch re-sent (duplicates); `GIZ-SIM-01` Smart Village, pull with "simulate failure" on (failed test + two failed pulls, backoff, *Needs attention*) |
| Unmatched events | Unknown device users 9001, 9002, 9003 (Cairo, one from a logged CSV import) and 7777 (Alexandria) — map them on the device page or under *Attendance → Raw events* |
| Work locations | One FIELD employee may check in anywhere this month; the IT department may use the Cairo HQ and Smart Village locations |
| Face | **employee1@** consented and enrolled (simulated provider); two field employees enrolled by HR, one withdrew consent (data deleted) |
| Mobile attempts (08:05, today or yesterday when seeded before 09:00) | employee1@ accepted check-in + a failed face match at check-out (sent to review — optional mode); employee3@ outside the geofence (rejected), mock location (flagged, pending), phone clock off (flagged, **approved** by attendance.officer@); employee2@ poor accuracy (rejected), not enrolled (flagged, pending) |

Face verification is **optional** by default since Phase 6 (setting *Attendance → require face verification* off; new policy
versions default to `optional` until a real provider is configured — blocker B4). Switch a policy version to *required* to test
the strict flow, which needs a consent + enrolment first: `POST /api/v1/me/face/consent` (`{"accepted": true, "version": "1.0"}`), then
`POST /api/v1/me/face/enrol` (`{"token": "sim-enrol"}`). With the simulated provider the face token decides the result:
`sim-no-match`, `sim-liveness-fail`, `sim-error`, anything else matches.

Push to a device from the command line (the token is shown once on the device page → *New push token*):

```bash
curl -X POST http://hrm-plus.loc/api/v1/devices/ALX-SIM-01/events -H "Authorization: Bearer <token>" -H "Accept: application/json" \
  -H "Content-Type: application/json" -d '{"events":[{"user_id":"36","time":"2026-11-11 08:02:00","direction":"in"}]}'
```

Scheduled commands added: `attendance:sync-devices` (every 5 minutes; pulls due devices, honours the backoff) and `face:purge`
(02:15, biometric retention). Phase 5b added the permission module `face_verification.*` and default grants of
`attendance_devices.*` — run `php artisan permissions:sync` on existing databases. Docs: `docs/ATTENDANCE_DEVICES.md`,
`docs/FACE_VERIFICATION.md`.

## Leave demo data (Phase 6)

`LeaveDefaultsSeeder` (every environment) creates the approval workflows a leave type can use: `leave_request` (two levels:
*Manager approval* = permission `leaves.approve` within the approver's data scope, then *HR approval* = `leaves.manage`),
`leave_request_hr` (one level, `leaves.manage`) and `leave_cancellation` (cancelling approved leave that has started, `leaves.manage`).

`LeaveDemoSeeder` (not in production) then builds, through the real actions (queue switched to `sync` while seeding; notifications
are faked during seeding by `RecruitmentDemoSeeder`, as before):

| What | Details |
|---|---|
| Company timezone | The dev seed sets *Settings → Localisation → timezone* to `Africa/Cairo` (like the branches), so "today" on the dashboards is Cairo's today |
| Leave types (**DEMO**, Egyptian-style — check against the labour law) | ANNUAL (half days, 3 days notice), SICK (past dates allowed, certificate above 1 day, shown as *Sick leave* in attendance, HR-only approval), CASUAL (hours, max 2 days per request), UNPAID (no balance, deducted by payroll, 7 days notice), MATERNITY (women, 10 months service, calendar days, 3 times per employment), HAJJ (5 years service, once per employment, calendar days) |
| Policies | ANN-STD by years of service (15 / 21 from 1 year / 30 from 10 years; carry over up to 7 days expiring after 3 months; ceiling 45; encashable up to 10), ANN-PT for part-time staff (12 days, monthly), ANN-FIELD for group FIELD (24 days, pro rata, carry 10 for 6 months), SICK-OLD (12 days) closed on 31 Dec by SICK (15 days from 1 January — effective dating), CASUAL 7, MATERNITY 90, HAJJ 30 |
| Balances | Last leave year accrued in full, a few days taken, rolled over (carry-over / expiry rows), then this year accrued up to today |
| Requests (~49) | Approved through both levels in the attendance demo window (the simulated punches of those days are voided, so attendance shows *On leave* / *Sick leave*), sick leave with a demo certificate, half-day and hourly casual leave, unpaid leave last month (payroll feed), a maternity leave in progress, future approved leave, requests pending at the manager level and at the HR level, rejected, withdrawn, cancelled before the start, and two **cancellation requests** waiting for HR |
| Portal | employee1@ and employee2@ have their own portal requests (employee1@ also an approved day last fortnight); employee3@ is on a rotation, so the demo may skip his hourly request when the day is a rest day |

Try it: approve as **branch.manager@** (only Alexandria employees, first level), **department.manager@** (IT) or
**direct.manager@**, then as **hr.manager@** (HR level, cancellations). Employees request and cancel under *My leaves* in the
portal or through `GET/POST /api/v1/me/leave/*`.

Scheduled commands added: `leave:rollover` (00:30, opens the new leave year once — a no-op on other days) and `leave:accrue`
(00:40, credits entitlements due by today and expires unused carried-over days). Both are idempotent and accept `--date` /
`--year` and `--employee`. Approved or cancelled leave recalculates past attendance days through the queue
(`php artisan queue:work`); future days are processed by the nightly `attendance:process`. Phase 6 added the permission modules
`leave_types.*`, `leave_policies.*` and `leave_balances.*` (leave requests keep the existing `leaves.*`) — run
`php artisan permissions:sync` on existing databases.

## Payroll components demo data (Phase 7a)

`PayrollDefaultsSeeder` (every environment) creates the approval workflows `payroll_allowance`
(step: `allowances.approve`), `payroll_deduction` (`deductions.approve`), `loan_request` and `loan_change`
(`loans.approve`). No component, structure or statutory rate is seeded in production (blocker B7).

`PayrollDemoSeeder` (not in production) then adds, through the real actions:

| What | Details |
|---|---|
| Setting | `payroll.country_code = EG` so the demo statutory tables apply |
| Components | BASIC, HOUSING (25 % of basic), TRANSPORT (500), MEAL, PERF_BONUS (variable, **needs approval**), COMMISSION, OVERTIME (attendance-policy multipliers), LATE / EARLY (per minute), ABSENCE (per day), UNPAID (per unpaid-leave day), PENALTY (variable, **needs approval**), RECOVERY, SI_EMP / SI_ER (social insurance), TAX |
| Statutory tables | `DEMO-SI-EG` (2 versions) and `DEMO-TAX-EG` — flagged **"DEMO — not legal advice"**, made-up round numbers, active so 7b can calculate in development |
| Structures | **STD** (company; v1 2024, v2 from 2026-07-01 with transport 600), **MGT** (grades G6–G8: housing 30 %, transport 1000, no overtime), **OPS** (branch ALX: housing 20 %, transport 750); **employee2@**'s profile (EMP-00015) gets MGT from next month |
| Basic salaries | Unchanged: the 60 employees keep their `employee_salaries` history (pay basis *monthly*) |
| Allowances | Branch ALX meal allowance + an employee override (precedence), a one-time performance bonus for department SAL-CAI approved by finance.officer@, a bulk 5 % commission batch for five Cairo sales staff, a **pending** performance bonus (approve as finance.officer@ or hr.manager@) |
| Deductions | An approved penalty, a 4-month instalment recovery, a **pending** 2 % penalty |
| Loans | LN-…01 **employee2@** active since June (June–August recovered before go-live, September onwards by the payroll runs), LN-…02 **employee1@** pending (portal), ADV-…01 **employee3@** advance repaid early, LN-…03 rejected, ADV-…02 withdrawn, LN-…04 active with a **pending reschedule** request (October instalment recovered by the October run) |
| Leave encashment | Component LEAVE_ENCASH (setting `payroll.leave_encashment_component`) |

Who does what: **payroll.accountant@** configures components, structures and statutory tables, files
allowances / deductions (single, bulk, Excel import) and loans on behalf, records repayments and final settlements;
**finance.officer@** (and **hr.manager@**) approve entries and loans. The employee profile tab *Compensation*
needs `employee_financials.view` (every view logged). Employees use the portal *My loans* or `GET/POST /api/v1/me/loans`.
Phase 7a added the permissions `salary_components.*`, `salary_structures.*`, `allowances.*`, `deductions.*` and
`statutory_rules.*` — run `php artisan permissions:sync` on existing databases.

## Payroll runs demo data (Phase 7b)

`PayrollDefaultsSeeder` (every environment) adds the approval workflow **`payroll_run`**: step 1 "HR review",
step 2 "Finance approval", both permission `payroll.approve` — the submitter can never approve, and the same
person cannot approve both levels. `PayrollRunDemoSeeder` (not in production) then runs, through the real actions
and dated as if they happened then:

| What | Details |
|---|---|
| Periods | 2026-09, 2026-10 (finalised), 2026-11 (open; pay date 27th, cut-off 25th) |
| September | PR-2026-00001 **Cairo branch settlement** and PR-2026-00002 consolidated run of everybody else (the Cairo staff are left out as already paid) — calculated by payroll.accountant@, approved by hr.manager@ then finance.officer@, finalised by finance.officer@, all payslips paid by bank transfer `BANK-2026-09-30` |
| October | PR-2026-00003 consolidated, finalised; payslips paid except three unpaid and one **partially paid** |
| Correction | PADJ-2026-00001: transport allowance of October recorded in November (manual), carried by the November run |
| Final settlement | An employee with an active loan resigns (offboarding case, last working day 2026-11-05): PR-2026-00004 prorates November to the 5th, encashes 10 annual-leave days, recovers the loan (partially — net pay is not enough, the rest stays outstanding), finalised; the case's *Final financial settlement* item is done and the case completed (employee terminated) |
| November | PR-2026-00005 consolidated, **calculated and in review** — submit it as payroll.accountant@, approve as hr.manager@ and finance.officer@, finalise as finance.officer@ |

Who does what: **payroll.accountant@** creates periods and runs (wizard: scope → population preview → calculate →
review), records corrections, exports the register and bank file; **hr.manager@** and **finance.officer@** approve;
only **finance.officer@** (`payroll_finalisation.manage`) finalises and reverses, and records payments
(`payslips.manage`). Employees: portal **My payroll** and `GET /api/v1/me/payslips` (employee1@ … 3@ have payslips;
employee3@ is in Alexandria). Every payslip view / download is in the audit log (`sensitive_access.payslip`).

Background calculation: runs above `payroll.sync_calculation_limit` employees (default 150) are calculated by
`php artisan queue:work`. Scheduled: `payroll:scan-corrections` (02:45). Phase 7b added the permissions
`payroll_finalisation.manage` and `payslips.view / export / manage` (and grants `payroll.approve / reject` to the
HR Manager) — run `php artisan permissions:sync` on existing databases.
