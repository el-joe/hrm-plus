# HRM Plus — Project Plan & Progress Tracker

Source of requirements: [doc.md](doc.md). This file is the single progress tracker required by rule 28 of that spec. Tick boxes as work lands; do not mark an item done until its exit criteria are met.

Last updated: 2026-11-12 · Status: **Phases 0–7 complete (6 and 7: tests written, not run yet); Phase 8 (remaining HR modules, dashboard widgets, reports centre) next**

---

## 1. Where we are today (Phase 0 inspection results)

| Area | Finding | Impact |
|---|---|---|
| `backend/` | Fresh Laravel 13.42 skeleton. Only `User` model, 3 default migrations, `welcome.blade.php`. Pest 4, Pint, Laravel Boost, Tailwind 4, Vite 8 already installed. | Nothing to preserve or migrate; build from scratch. |
| `application/` | Fresh `flutter create` counter app. Bundle id is `com.example.application`. | Needs a real bundle id before any store or push setup. |
| Toolchain | PHP 8.3.36, Composer 2.9.5, Node 20.20, MySQL 8.0.46, Flutter 3.47.9 / Dart 3.13.7. PHP extensions present: bcmath, gd, imagick, intl, mbstring, pdo_mysql, redis, sodium, zip. | Everything the spec needs is installed. |
| Database | `.env` points at MySQL `backend` as `root` with an empty password; the connection is **refused** (`1045 Access denied`). | **Blocker for Phase 2.** No migration can run until credentials are fixed. |
| Android | `flutter doctor` reports Android licences not accepted. | Blocks Android builds; one command to fix. |
| Figma MCP | Connected and authenticated. Full seat on the Pro team "Youssef Magdy's team"; view-only seat on "Codefanz". | Phase 1 can proceed; the file will be created in the Pro team. |
| Locale | `APP_LOCALE=en`. | Switch to `ar` with `en` fallback in Phase 2. |
| Not installed yet | Sanctum, Spatie Permission, Excel/PDF libraries, OpenAPI generator, all Flutter packages. | Dependency list in section 4 needs approval (backend guidelines forbid adding dependencies without it). |

---

## 2. Team and ownership

| Role | Owns | Signs off on |
|---|---|---|
| **PM / Business Analyst** | Scope, this plan, assumptions log, requirement traceability, client documentation in Arabic | Phase exit, final implementation report |
| **Database Designer & Optimizer** | ERD, migrations, indexes, constraints, effective-dating and history patterns, query tuning | Schema before each phase's backend work starts |
| **Senior Backend Developer** | Laravel domain services, RBAC, attendance and payroll engines, REST API, queues, scheduler, integrations | Service-layer design, API contract |
| **Senior Frontend Developer** | Figma design, Blade + Tailwind + vanilla ES modules, both web dashboards, Flutter app | Design system, RTL/LTR and dark mode parity |
| **QA Tester** | Pest feature/unit tests, Flutter tests, the five end-to-end scenarios | Test suite green per module |
| **QC** | Code review, spec compliance (no Livewire/Alpine/jQuery, no placeholder buttons, no static numbers), security review, Pint | Release gate for every phase |
| **Problem Solver** | Integration unknowns (devices, face provider, push), calculation edge cases, defects that block a phase | Root-cause notes on every blocker |

The spec does not name a mobile developer or a UI designer. Both are assigned to the Senior Frontend Developer; Phases 1 and 10 are therefore the tightest on capacity.

---

## 3. Blockers and inputs needed from the client

| # | Item | Needed by | If not provided |
|---|---|---|---|
| B1 | Working MySQL credentials (ideally a dedicated `hrm_plus` user and database, plus a separate test database) | Phase 2 | Phase 2 cannot start |
| B2 | Approval of the dependency list in section 4 | Phase 2 | Phase 2 cannot start |
| B3 | Fingerprint device manufacturer, model, firmware | Phase 5 | Ship adapter interface + simulated adapter + CSV/Excel import only; no brand is claimed as supported |
| B4 | Face verification provider choice and licence/credentials | Phase 5 / 10 | Ship provider interface + clearly labelled simulated adapter + setup guide |
| B5 | Firebase project (FCM) and Apple push credentials | Phase 10 | Push code is wired, delivery untested |
| B6 | SMTP credentials | Phase 8 | Mail goes to the log driver |
| B7 | Country, currency and statutory rules (tax, social insurance) | Phase 7 | Engine ships with configurable, effective-dated rule tables and no seeded statutory rates |
| B8 | Company name, logo, brand colours, real bundle id | Phase 1 / 10 | Neutral enterprise palette; placeholder bundle id |
| B9 | Accept Android licences (`flutter doctor --android-licenses`) | Phase 10 | No Android build |

Only B1 and B2 stop work. Everything else has a documented fallback that the spec explicitly permits.

---

## 4. Architecture decisions (proposed)

**Accounts.** One `users` table with `account_type` (`admin` | `employee`) and a nullable `employee_id`. Two session guards and two route groups (`/admin`, `/portal`) with separate layouts. An admin linked to an employee profile can open the employee portal for their own record. Mobile uses Sanctum personal access tokens, employee accounts only.

**Authorisation.** Spatie Permission for roles and permissions, named `module.action` (view, create, update, delete, approve, reject, export, manage). A separate data-scope layer restricts each admin to branches, departments or employee groups, applied through Eloquent global scopes and policies so that it holds for web, API, reports and exports alike.

**Domain layout.** `app/Services/{Attendance,Payroll,Leave,Recruitment,Notifications,...}`, single-purpose `Actions`, Form Requests, Policies, API Resources. Controllers stay thin. No business rule lives in a controller, Blade file or JavaScript.

**History.** Effective-dated assignment tables (branch, department, position, manager, salary) instead of overwriting columns. Finalised payroll and processed attendance are immutable; corrections are new reversal/adjustment rows.

**Money.** `DECIMAL` columns and bcmath arithmetic only. Every payroll run stores its inputs, rule versions and line-by-line results.

**Attendance.** Raw events (device, mobile, manual) are stored separately from processed daily records. One engine consumes all three sources. Idempotency is enforced by unique keys on raw events and on employee+date+shift records.

**Integrations.** `AttendanceDeviceAdapter`, `FaceVerificationProvider` and `PushChannel` interfaces, each with a simulated development implementation that is labelled as simulated in the UI and in logs.

**Approvals.** One generic multi-level approval engine reused by leave, attendance corrections, loans, HR requests, schedule changes and disciplinary actions.

**Frontend.** Blade components + Tailwind 4 tokens taken from Figma + ES modules per feature under `resources/js/`, bundled by Vite. Logical CSS properties for RTL/LTR. Charts via a framework-free charting library.

**Proposed dependencies (need approval, B2).** Versions to be confirmed against Laravel 13 at install time.

| Purpose | Backend | Flutter |
|---|---|---|
| API auth | laravel/sanctum | dio, flutter_secure_storage |
| RBAC | spatie/laravel-permission | — |
| Excel / CSV | openspout/openspout (installed Phase 3b: streaming, no PhpSpreadsheet, supports PHP 8.3 / Laravel 13) | — |
| Arabic PDF | mpdf/mpdf (installed Phase 7b: 8.3.1, PHP 8.3 / Laravel 13; Cairo TTF in `backend/resources/fonts/cairo`, OFL) | — |
| OpenAPI | dedoc/scramble | — |
| Charts | Chart.js or ApexCharts (no framework) | fl_chart |
| State / routing | — | flutter_riverpod, go_router |
| Location / camera | — | geolocator, camera, permission_handler |
| Push | FCM HTTP v1 channel | firebase_messaging, flutter_local_notifications |
| i18n | Laravel lang files (ar, en) | flutter_localizations, intl |

---

## 5. Phase plan

Sizes are relative effort: S, M, L, XL. Each phase ends with the QC gate in section 6.

### Phase 0 — Inspection and planning · S · **done**
- [x] Inspect project root, both app directories, toolchain
- [x] Confirm nothing existing would be overwritten
- [x] Verify Figma MCP access
- [x] Record blockers and assumptions (sections 1, 3, 7)
- [x] Produce this plan

### Phase 1 — Figma design · XL · owner: Frontend
Must finish before any UI code is written.

> **Scope change (client, 2026-11-11):** Figma covers the design system, components and layout shells only (admin shell, employee portal shell, Flutter shell, auth, form and table page patterns, light/dark, RTL/LTR). The individual screens listed below are **not** designed in Figma; they are built directly in code from the system. This departs from spec §3 and is recorded as a client decision.
- [x] Create the Figma file in the Pro team; 12 pages as listed in spec §3 — https://www.figma.com/design/F6GhAOTTYlI93nUtDf1255 (tokens and measurements in [DESIGN_REFERENCE.md](DESIGN_REFERENCE.md))
- [x] **Design system:** colour tokens (light + dark), Arabic and Latin type scale, spacing, radius, elevation, icon set
- [x] **Components:** buttons, inputs, selects, date pickers, data table, filter bar, pagination, status badges, modals, confirmation dialog, toasts, tabs, breadcrumbs, sidebar, topbar, stat card, chart card, empty and loading states, file uploader, calendar, org-chart node, mobile app bar, bottom navigation, attendance action button
- [~] *(descoped — layouts only)* **Admin screens (34):** login, dashboard, employee directory, employee profile, create/edit employee, org structure, branches, departments, job positions, recruitment dashboard, vacancies, candidates, candidate profile, interviews, attendance dashboard, attendance calendar, daily attendance table, bulk attendance ("mark all present except"), devices, GPS settings, face verification settings, shift scheduling, leave management, leave approval, payroll dashboard, payroll runs, payroll settlement, allowances, deductions, loans and advances, payslip, reports centre, roles and permissions, system settings
- [~] *(descoped — portal shell + login only)* **Employee web (5+):** login, dashboard, profile, attendance, leaves
- [~] *(descoped — app shell + home only)* **Flutter (6+):** dashboard, check-in/out flow, leave requests, payslips, profile, notifications
- [x] RTL primary frames, LTR variant (admin dashboard), dark variants (admin dashboard, Flutter home)
- [ ] Consistency review (QC) and BA walkthrough against spec §4–§6

**Exit:** every screen in spec §3 exists as an editable frame built from components; file link recorded here.

### Phase 2 — Laravel foundation · L · owners: DB, Backend, Frontend
- [x] Fix `.env`, create databases, set locale `ar` / fallback `en`, timezone
- [x] Install approved dependencies
- [x] Schema: users, roles, permissions, admin data scopes, login history, audit logs, system settings, approval workflows and history
- [x] Two guards, admin and employee login, password reset, rate limiting, login history *(MFA is a documented seam only; no company-wide failed-login screen yet)*
- [x] RBAC: permission catalogue seeder, 11 default roles, custom role CRUD, multi-role assignment, branch/department/group scoping
- [x] Audit service (who, what, when, old value, new value, affected record) and sensitive-data access log
- [x] Admin layout and employee layout from Figma: sidebar driven by permissions, breadcrumbs, global search shell, dark mode, RTL/LTR switch *(UI kit at /admin/ui-kit; guide in backend/docs/FRONTEND_GUIDE.md)*
- [x] Shared JS modules: data table, filters, modal, toast, confirm, form validation, fetch wrapper
- [x] `/api/v1` foundation: Sanctum, response envelope, error handler, pagination/filter/sort conventions, throttling
- [x] Core settings module
- [x] Tests: admin auth, employee auth, role permissions, branch restriction, API auth, audit log, layouts/navigation/UI kit — 239 passing

**Exit:** a Super Admin can create a role, assign it, and the restricted admin is rejected by the backend on forbidden routes.

### Phase 3 — Company structure and employees · L
- [x] Company profile, branches and locations, departments, teams, job titles, positions, grades, employment types, cost centres *(Phase 3a; bilingual `name_ar`/`name_en` columns, deactivate-instead-of-delete when in use)*
- [x] Reporting hierarchy and organisational chart *(position-based reporting lines; position holders and department heads on the chart, plus a manager tree view)*
- [x] Employee profile with tabs: personal, employment, documents, financial, history *(Phase 3b; plus contracts; identity numbers encrypted + reveal logged; financial tab behind `employee_financials.view`, every view logged)*
- [x] Effective-dated assignment history (transfer, promote, manager change, status change) *(`employee_assignments` + `employee_salaries`, future-dated changes applied by `employees:apply-scheduled-changes`)*
- [x] Contracts; documents with categories, expiry, reminders, restricted private storage *(private disk `employee_files`, authorised + logged downloads, `employees:send-expiry-reminders` → database/mail notifications)*
- [x] Operations: activate/deactivate, terminate, rehire, bulk actions, Excel import, export *(openspout/openspout 5 for XLSX/CSV; import is dry-run first, then all-or-nothing)*
- [x] Employee number generation and custom fields *(pattern setting, race-safe `number_sequences`; typed custom field values)*
- [x] Tests: employee CRUD, data privacy, file permissions, history preservation *(plus import/export, scopes incl. sub-departments and groups, headcount, permission sync, API, portal)*

### Phase 4 — Recruitment, onboarding, offboarding · L · **done**
- [x] Vacancies, configurable pipeline stages, candidate records, CV upload, restricted salary fields *(bilingual vacancies scoped by branch/department, openings capped by the position's free seats; company default pipeline + per-vacancy copy, stage "meaning" categories; candidates with duplicate detection by e-mail/phone, tags, notes, attachments, CVs on the private `recruitment_files` disk; salaries only with `employee_financials.view`, every view logged; kanban board with drag and drop + keyboard/no-JS stage form, list view)*
- [x] Interviews: scheduling, interviewers, evaluation forms, scores, decisions *(timezone-aware, in person / online / phone; panel of admin users or employee accounts (portal "My interviews"); weighted evaluation forms, bcmath aggregated scores; interviewers see only their own interviews unless `interviews.manage`)*
- [x] Offers, email notifications, interview reminders *(offer flow draft → approval through the approval engine (`job_offer` workflow, setting) → sent → accepted/declined/expired; printable HTML offer letter — no PDF library added; queued candidate e-mails (received, stage, interview invitation/reminder, offer); `recruitment:send-interview-reminders` hourly, `recruitment:expire-offers` daily)*
- [x] Convert candidate to employee with preserved lineage *(employees.candidate_id / job_application_id, CV copied to employee documents, vacancy filled count, headcount validated, optional login invitation, onboarding started automatically)*
- [x] Optional public career page *(`/careers`, setting `recruitment.career_page_enabled` — on outside production; honeypot, per-IP rate limit, CV type/size from settings, returning applicants matched)*
- [x] Configurable onboarding checklists and progress tracking *(templates matched by position / department / employment type, due offsets, HR/IT/manager/finance/employee tasks, auto-completion; employee completes own tasks in the portal and via `GET /api/v1/me/onboarding`, `POST /api/v1/me/onboarding/tasks/{task}/complete`)*
- [x] Offboarding: resignation, termination, contract expiry, exit interview, asset and document handover, account deactivation *(handover items from pluggable providers — the Assets phase registers its own; exit interview form; completion terminates via TerminateEmployee on the last working day; termination now also disables linked admin accounts; `offboarding:detect-contract-expiries` opens contract-end cases)*
- [x] Tests: recruitment workflow; end-to-end scenario 1 up to account creation *(and API sign-in of the new employee)*

### Phase 5 — Attendance engine · XL · critical · **done** (5a core, 5b capture & visibility)
- [x] Schema (5a part): attendance policies (effective-dated versions + assignments), raw events, punches, daily records, corrections, manual batches, period locks, adjustments, shifts, schedules, overrides, holidays, configurable day statuses *(5b adds devices, device-employee mappings, sync logs, geofences, face verification records — linked to `attendance_raw_events` by id)*
- [x] Schema (5b part): devices, device-employee mappings, sync logs, work locations / geofences, face verification records *(`attendance_devices`, `attendance_device_users`, `attendance_device_sync_logs`, `attendance_device_events`, `attendance_work_location_rules`, `attendance_mobile_attempts`, `face_consents`, `face_enrolments`, `face_verifications`; mobile/face rules on `attendance_policy_versions`; radius geofences only — polygons not built)*
- [x] Shifts and schedules (spec module 7): fixed, flexible, rotating, night, cross-midnight, multiple per day; weekly and monthly schedules by employee, group, department, branch, company; holidays; breaks; swaps; approvals; visual calendar *(`ScheduleResolver::for($employee, $date)` is the single source; precedence employee > group > department (nearest up the tree) > branch > company; date overrides from the calendar and approved changes / swaps)*
- [x] Device layer: adapter interface, simulated adapter, push endpoint, pull sync job, manual sync, historical import, CSV/Excel fallback, health status, failure handling, event review screen *(no vendor adapter — B3 open; `docs/ATTENDANCE_DEVICES.md`; per-device bearer token stored hashed; exponential backoff; unmatched events mapped later)*
- [x] Duplicate-event protection at the database level *(unique `attendance_raw_events.idempotency_key`; unique day per employee + date + shift_key)*
- [x] GPS check-in/out API: server-side distance and accuracy validation, shift validation, mock-location flagging, suspicious-attempt review queue *(`/api/v1/me/attendance/{today,check-in,check-out,attempts}`; haversine + accuracy tolerance, time drift, repeated attempts; work-location rules per employee / department incl. remote; records through `RawEventRecorder`)*
- [x] Face verification: provider interface, simulated adapter, enrolment, consent, encrypted storage, retention and deletion, retry rules, manual fallback workflow *(no real provider — B4 open; `docs/FACE_VERIFICATION.md`; off/optional/required per policy version)*
- [x] Manual attendance: individual correction, **bulk "mark all present except"** with filters, exceptions, search, select all, confirmation screen, audit trail, Excel import, approval workflow *(manual input becomes raw events with source manual / import; marked "manual" in every view)*
- [x] Calculation service: lateness, early departure, absence, overtime, breaks, missing punches, grace periods, all driven by policy tables *(`AttendanceEngine` + `DayCalculator`, idempotent, calculation snapshot with policy version; `attendance:process` nightly; period locks with adjustment rows)*
- [x] Attendance dashboard, calendar, daily and monthly views, reports *(5b: dashboard, attendance calendar (employee grid / team matrix), 7 reports with Excel/CSV export; CSS bars, no chart library)*
- [x] Tests: device event processing, duplicate prevention, bulk attendance, geofence validation, face provider boundary, shift calculations; scenarios 2 (server side) and 3 *(5b test files written but NOT run yet at the user's request: `AttendanceDeviceTest`, `MobileAttendanceTest` (incl. scenario 2 end-to-end), `FaceVerificationTest`, `AttendanceDashboardReportsTest`)*

### Phase 6 — Leave management · M · **done** (tests written, not run yet — user decision)
- [x] Leave types, effective-dated policies per employment type or group, eligibility *(bilingual types: paid / unpaid + "deduct from payroll", balance or not, unit day / half day / hour, attachment above N days, gender, minimum service, times per employment (Hajj), max days per request, notice, past dates, counting of rest days / holidays, approval workflow per type, attendance day status, colour. Policies per type for the company / an employment type / an employee group (group > employment type > company), `effective_from` / `effective_to`; saving a newer one closes the open one (`SaveLeavePolicy`); "new version" action. Marital-status / religion eligibility not built — the spec does not ask for it)*
- [x] Accrual, annual balance, carry-over, maximums, balance adjustments *(append-only ledger `leave_balance_transactions` (accrual, usage, reversal, adjustment, carry-over, expiry, encashment; DECIMAL + bcmath; reference keys make automatic rows idempotent). Accrual methods: yearly up front, monthly, pro rata in the hire year, service-year tiers (topped up when a tier is reached). Ceiling, negative balance with limit, carry-over maximum + expiry (FIFO against leave taken), encashment flag/limit. `leave:accrue` daily, `leave:rollover` daily (acts once per leave year). Manual adjustments / encashments with reason, audited. Balance = ledger sum; available = balance − pending requests; future leave years count their projected entitlement)*
- [x] Requests with attachments, multi-level approval, rejection reasons, cancellation requests *(portal, API and admin on behalf (optionally "record as approved" with `leaves.manage`); working days from `ScheduleResolver` + holidays, half days, hours as a fraction of the scheduled day; overlap prevention (two halves allowed); notice / past-date / per-request / attachment / eligibility / balance checks; approval engine with the type's workflow (default two levels: manager in scope → HR), self-approval and deciding one's own leave blocked; rejection reason mandatory; cancellation: pending or not-yet-started approved leave at once, started leave through the `leave_cancellation` workflow; database + mail notifications to approvers and the employee)*
- [x] Calendars by company, branch, department; leave history *(admin leave calendar (month grid, filters, "my team" = direct reports, only-away, holidays, rest days, pending vs approved, people away per day, department conflict hint ≥ setting); conflicts on the request page; history on the balance page, the employee profile *Leave* tab and the portal)*
- [x] Feed approved leave into attendance and unpaid leave into payroll *(`ApprovedLeaveProvider` bound to `LeaveProvider`: counted days of approved / cancellation-pending requests → `on_leave` (with the type's day status, e.g. *Sick leave*), partial days → half-day leave flag; approve / cancel queue `AttendanceRecalculation` for past dates. `LeavePayrollFeed::unpaidDays()`, `unpaidBreakdown()`, `encashments()` for Phase 7)*
- [x] Admin screens and reports *(types, policies, requests queue with inline approve / reject, request page (days, attachments, approval history, team conflicts, balance), balances list + employee breakdown + ledger + adjustments, calendar, reports: balances, requests, usage by type, usage by department, upcoming, on leave — Excel / CSV export; portal *My leaves* + home "remaining leave"; API `me/leave/*`)*
- [x] Tests *(written, NOT run: `tests/Feature/Leave/LeaveBalanceTest.php`, `LeaveRequestTest.php`, `LeaveScreensTest.php`; `AdminNavigationTest` and `EmployeeCrudTest` updated for the new sidebar section and profile tab)*
- [x] Small fixes from the project lead: dev-seed company timezone `Africa/Cairo` (setting `localisation.timezone`, set by `OrganisationStructureSeeder`); face verification default **optional** (setting default + column default of `attendance_policy_versions.face_verification_mode`; seeded policies follow). Also fixed: approval listeners read the deciding action with `reorder()` (the `actions` relation is ordered ascending, so the attendance listener recorded the submitter as `decided_by`).

**Notes for Phase 7 (payroll).** Read leave only through `App\Services\Leave\LeavePayrollFeed`: `unpaidDays($employee, $from, $to)` (bcmath days of taken leave of types flagged *deduct from payroll*, dated in the period), `unpaidBreakdown()` (per date / type / request) and `encashments()` (encashment ledger rows to pay, positive days). Amounts are days (half days 0.5; hours as a fraction of the scheduled day) — payroll applies its own day rate. A cancellation approved after a period is finalised removes the days from the feed: payroll must treat the difference as a correction of the locked period. Paid leave needs no deduction; attendance `on_leave` days are not absences.

### Phase 7 — Payroll engine · XL · critical · **done** (7a components, 7b runs / payslips; tests written, not run)
- [x] Salary structures and components (monthly, daily, hourly, fixed), effective-dated, with change history *(7a: component catalogue with a closed list of rule types — fixed, % of basic or of another component (chains, no cycles), variable, per late / early minute, per absent day, overtime hours × policy multiplier, per unpaid-leave day, statutory; taxable / insurable / prorate / approval / payslip order / rounding. `employee_salaries` stays the only basic-salary source and gains `pay_basis` (monthly / daily / hourly / fixed, mirrored on `employees`). Structures → versions (never edited; a new version closes the previous one) → lines with overrides; assignments to employee / job grade / department (up the tree) / branch / company, effective-dated, precedence in that order)*
- [x] Allowance types: fixed or percentage, recurring or one-time, scoped to employee, department or branch, with start and end dates and payroll treatment *(7a: `pay_entries` kind earning — also employee group and company; precedence employee > group > department > branch > company per component; taxable / insurable / prorate overrides; approval when the component requires it (`payroll_allowance`); bulk to ticked employees (one batch) and Excel / CSV import with dry run; cancel (not started) / stop (running) — approved entries are never edited)*
- [x] Deduction types: fixed, percentage, recurring, one-time, instalment; approval and supporting documents *(7a: same table, kind deduction; reason required; instalment plans (last instalment takes the remainder); `payroll_deduction` workflow; documents on the private disk; nullable `source` morph for disciplinary actions later)*
- [x] Statutory rule tables by country and effective date; no hard-coded rates *(7a: rule sets (country, income tax / social insurance, demo flag) → versions (rates, floor / ceiling, annual exemption) → tax brackets; versions never edited; setting `payroll.country_code` (empty by default = none); dev seed has two DEMO tables labelled "DEMO — not legal advice" with made-up rates; `StatutoryCalculator` applies them)*
- [x] Loans and advances: request, approval, instalment schedule, automatic deduction, early repayment, settlement *(7a: portal, API `me/loans`, admin on behalf; limits in Settings → Loans; `loan_request` workflow with approved terms; schedule + append-only ledger; `LoanInstalments::dueFor()` / `markDeducted()` / `reverseDeduction()` for 7b; early repayment (last instalments first); skip / reschedule through `loan_change`; offboarding handover item + "recover in final pay")*
- [x] Read API for the engine *(7a: `PayrollInputs::for($employee, $from, $to)` → value objects with exact DECIMAL strings + `toArray()` snapshot; `ComponentCalculator`, `SalaryRates`, `Proration`, `StatutoryCalculator`; contract in `backend/docs/PAYROLL_ENGINE.md`)*
- [x] Tests (7a, written, NOT run): `tests/Feature/Payroll/ComponentCalculationTest.php`, `SalaryStructureTest.php`, `PayEntryTest.php`, `LoanTest.php`, `PayrollInputsTest.php`, `PayrollScreensTest.php`
- [x] Payroll periods (monthly and custom) and runs: draft → calculate → recalculate → review → approve → finalise → lock *(7b: periods follow `payroll.period_start_day`, pay date + attendance cut-off, close / reopen; `ManagePayrollRun` state machine with the run row locked on every transition; workflow `payroll_run` = HR review + Finance approval (`payroll.approve`, distinct approvers, never the submitter); finalisation needs `payroll_finalisation.manage`; every transition audited `payroll.run_*`)*
- [x] **Settlement scoping** by branch, department, team, position, group, individuals, or whole company; per-branch or consolidated runs *(7b: `PayrollPopulation` — AND across dimensions, OR within, include / exclude lists, sub-departments, placement at the period end, "moved out" listed in the old branch's preview; population preview before creation; one pay per employee and period: preview exclusion + unique `(payroll_period_id, employee_id, pay_slot)` + period row lock)*
- [x] Per-employee calculation breakdown screen *(7b: every line with source and explanation ("12 late minutes × 2.50 = 30.00"), inputs snapshot, attendance / leave quantities, placement segments, rule versions, warnings (negative net, missing salary, DEMO statutory, pending overtime / mobile reviews …), variance vs previous finalised pay; run review with filters and totals by component / branch / department)*
- [x] Post-finalisation correction and reversal workflow *(7b: `PayrollCorrections` scan (daily, before every calculation, "Scan now") turns attendance / leave changes after finalisation into pending adjustments priced with the frozen rates; manual corrections; carried by the next run or a correction run, applied on finalisation; full reversal = mirror run with negative amounts, payslips void, `reverseDeduction()`, pay slots released)*
- [x] Payslip generation, Arabic PDF, payment status, payroll export *(7b: payslips issued on finalisation; HTML + mPDF 8.3 PDF with the Cairo font (RTL, Arabic shaping), bilingual per employee locale, company logo; unpaid / partially paid / paid with payments and bulk "mark paid"; register and generic bank file (CSV / XLSX, openspout); portal *My payroll* and API `me/payslips`; every access logged)*
- [x] Calculation snapshot storage, transactions and row locking against double processing *(7b: `PayrollCalculator` stores inputs, quantities, rule versions, lines with explanations and a SHA-256 hash per employee; run totals hash at finalisation; the run is claimed (`calculating`) before a calculation; queued job `CalculatePayrollRun` (unique) for large runs with progress; taxable / insurable base configurable on the statutory version)*
- [x] Tests: payroll, allowance, deduction, loan instalment calculations; branch settlement; payslip access; scenario 4 *(7b, written, NOT run: `tests/Feature/Payroll/PayrollCalculatorTest.php`, `PayrollSettlementScopeTest.php`, `PayrollRunWorkflowTest.php`, `PayrollCorrectionTest.php`, `PayslipTest.php`, `FinalSettlementTest.php`, `PayrollScenarioFourTest.php`)*

**Notes for Phase 8 (dashboard and reports reading payroll).** Contract in `backend/docs/PAYROLL_ENGINE.md` §10: live pay of a period = `payroll_items` with `pay_slot = 'regular'` in runs with status `finalised` (reversed runs are excluded; reversal runs carry negative mirrors); component totals from `payroll_item_lines` (`code`, `kind`, `category`, `source`, signed `amount`); group by the period-end placement stored on the item (`branch_id`, `department_id`, `team_id`, `position_id`, `cost_center_id`); employer cost `employer_contributions`; payment state on `payslips`. `PayrollItem` and `Payslip` are data-scoped. The dashboard already shows "payroll runs awaiting approval" (real count). Reports still to build: payroll register / by component / by branch / variance, statutory (tax, insurance) summaries, bank payments, loan recovery.

### Phase 8 — Remaining HR modules · L
- [ ] Performance: cycles, goals, KPIs, self and manager assessment, competencies, scoring, approval
- [ ] Training: programmes, courses, providers, enrolment, sessions, attendance, certificates, skills, budgets, expiry reminders
- [ ] Assets: catalogue, assignment, handover, return, condition, acknowledgement, offboarding clearance
- [ ] HR requests: categories, priority, assignment, internal notes, employee-visible replies, escalation, history
- [ ] Announcements: targeting by company, branch, department, group; attachments; read status
- [ ] Disciplinary: warnings, violations, investigations, employee response, approval, restricted access
- [ ] Notifications: in-app, email, push; templates; preferences; queued delivery; scheduled jobs (contract expiry, birthdays, missing checkout, reminders)
- [ ] Admin dashboard: all 22 widgets from live queries, permission-aware, filterable
- [ ] Reports centre: 48 reports across employee, attendance, leave, payroll, recruitment, performance; shared filters; Excel, CSV and Arabic PDF export; data-scope enforcement
- [ ] Remaining settings screens

### Phase 9 — Employee web portal · M
- [ ] Home, profile with change requests, attendance with correction requests, leaves, payroll and payslips, HR requests, announcements, notifications, performance, training, assets, documents, account settings
- [ ] Ownership enforcement on every route
- [ ] Responsive pass on phone widths
- [ ] Tests: an employee cannot read another employee's data anywhere

### Phase 10 — Flutter employee app · XL
- [ ] Project setup: bundle id, feature-first structure, Riverpod, go_router, dio client, central error handling, secure token storage, Arabic and English localisation, theme from Figma
- [ ] Screens (25): splash, login, forgot password, OTP, home, profile, attendance, check-in, check-out, GPS validation, face verification, attendance history, schedule, leave balance, new leave, leave history, payslips, salary breakdown, loans and advances, HR requests, request details, documents, announcements, notifications, settings and language
- [ ] Check-in/out flow with every failure path: permission denied, camera error, GPS failure, outside geofence, poor accuracy, offline, verification failure; success shown only after server confirmation
- [ ] Push notifications; Android and iOS permission and platform configuration, documented
- [ ] Remaining `/api/v1` endpoints, API Resources, OpenAPI documentation
- [ ] Tests: authentication, API integration, attendance flows, location and camera permission handling, API errors, leave submission, payslip viewing, data loading
- [ ] Complete scenarios 1 and 2 against the real backend

### Phase 11 — Final testing and documentation · L
- [ ] Fresh migrate and seed with realistic development data, factories for every model
- [ ] Run all five end-to-end scenarios; test each of the 11 roles
- [ ] Security pass: CSRF, XSS, injection, upload validation, private file access, rate limits, secrets in logs
- [ ] Performance pass: indexes, N+1, report queries on seeded volume
- [ ] `backend/docs/`: USER_MANUAL_AR, ROLE_GUIDES_AR (11 roles plus employee web and mobile), ROLE_PERMISSION_MATRIX_AR, ATTENDANCE_INTEGRATION_AR, PAYROLL_GUIDE_AR, API_DOCUMENTATION, DATABASE_ARCHITECTURE with ERD, DEPLOYMENT_GUIDE_AR, CHANGELOG
- [ ] PDF of the Arabic user manual
- [ ] Clean-install verification from the deployment guide
- [ ] Final implementation report: completed modules, test results, outstanding external dependencies, setup steps

---

## 6. Definition of done (QC gate, applied to every phase)

1. Every screen in scope is functional end to end: no placeholder buttons, no static numbers, no TODO stubs.
2. Authorisation is enforced on the server and covered by a test that proves a forbidden user is rejected.
3. Arabic RTL and English LTR both render correctly, in light and dark mode.
4. No Livewire, Alpine, Vue, React, Inertia or jQuery; no large inline scripts in Blade.
5. No business rule hard-coded in controllers or JavaScript.
6. Important actions write an audit record.
7. Pest (or Flutter) tests for the phase pass; Pint is clean.
8. Simulated integrations are labelled as simulated wherever they appear.
9. This file and the changelog are updated.

---

## 7. Assumptions log

| # | Assumption | Configurable? |
|---|---|---|
| A1 | Single company, many branches (no multi-tenant SaaS) | Schema leaves room; not built |
| A2 | Arabic is the default locale, English the fallback | Yes, per user |
| A3 | Western digits by default, Arabic-Indic digits optional | Yes, system setting |
| A4 | Working week, weekend days, timezone and currency come from settings | Yes |
| A5 | Admins may be linked to an employee profile; employees never gain admin access through that link | Fixed rule |
| A6 | Offline attendance queueing is out of the first release; the app requires connectivity to check in | Can be added later |
| A8 | Tests run on MySQL database `hrm-plus_testing` because this server's PHP has no `pdo_sqlite` | Switch back if `php8.3-sqlite3` is installed |
| A9 | An admin with several data scopes sees records matching **any** of them (OR) | Client to confirm; AND is a small change |
| A7 | Device, face and push integrations ship as interfaces plus simulated adapters until B3–B5 are supplied | Yes |

---

## 8. Main risks

| Risk | Mitigation |
|---|---|
| Scope is very large for one team (18 admin modules, a portal, a mobile app, at least 45 designed screens, 48 reports) | Strict phase order; critical engines (attendance, payroll) get the deepest tests; BA tracks every spec bullet against this checklist |
| Figma phase is long and blocks all UI work | Backend schema and services for Phase 2 can be prepared in parallel as long as no UI is coded |
| Payroll correctness | bcmath, stored calculation snapshots, table-driven test cases agreed with the client before Phase 7 |
| Unknown hardware and face provider | Adapter pattern; nothing is described as supported until tested on the real device or SDK |
| Biometric data handling | Consent, encryption, retention and deletion designed in Phase 5, reviewed by QC before release |
| One person covering design, web frontend and Flutter | Component reuse from the design system; Flutter starts only after the API is stable |
