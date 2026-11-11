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
