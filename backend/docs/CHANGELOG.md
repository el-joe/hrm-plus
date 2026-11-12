# Changelog

All notable changes to HRM Plus. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
versions follow semantic versioning. Phases refer to `PROJECT_PLAN.md`.

## [1.0.0] — Unreleased

First complete release: Laravel backend with admin dashboard, employee web portal and REST API; Flutter employee
app; Arabic client documentation.

### Final polish pass (after `44aa52a`)

- **Arabic terminology unified** across the interface, seed data and the Arabic documents: departments
  «الأقسام» / «القسم» (was mixed with «الإدارات» / «الإدارة»; «الإدارة» now only means the admin side), positions
  «الوظائف» (job titles stay «المسميات الوظيفية»; «المناصب الوظيفية» removed), payroll deductions
  «الاستقطاعات» (was mixed with «الخصومات»), «القضايا التأديبية», «الأحداث الأولية», review queue
  «مراجعة تسجيلات الجوال والويب» (EN *Mobile and web check-in review*), dashboard widget
  «مستخدمو الإدارة النشطون» (EN *Active admin users*).
- **Audit log labels:** every audit event key has an Arabic and English label (`lang/*/audit.php`, 182 keys);
  unknown keys are humanised (`AuditLog::eventLabel()`). Used on the dashboard «آخر النشاطات», the audit log list,
  filter and detail page, and the payroll run history; a test fails when a new `->log('…')` event has no label.
- **`.env.example`:** `REPORTS_QUEUE_THRESHOLD_XLSX|CSV|PDF`, `REPORTS_PDF_MAX_ROWS`, and commented
  `APP_PREVIOUS_KEYS`, `LOG_DAILY_DAYS`, `SANCTUM_STATEFUL_DOMAINS`, `SANCTUM_TOKEN_PREFIX`, `SESSION_SAME_SITE`,
  `SCRAMBLE_DEV_TOOLS`, `API_VERSION`.
- **HR letters PDF** now rendered through the shared `App\Support\Pdf\ArabicPdf` (same output).
- **Repository:** the mPDF font cache `storage/framework/mpdf/` is no longer tracked.
- **Docs:** web portal check-in documented with the exact screen wording (user manual 25.1, role guide 12.1,
  attendance integration §10, deployment §6 + troubleshooting, frontend guide, API documentation); manual PDF
  regenerated.

### Web portal check-in (`44aa52a`)

- Employees can check in / out from the portal (card «تسجيل الحضور / الانصراف» on Home and at the top of
  My Attendance; `resources/js/pages/check-in.js`): browser geolocation (accuracy checked before sending) and, when
  the policy requires or offers it, a camera capture for face verification. The request goes through the same
  `MobileCheckIn` service as the app — geofence, accuracy, shift window, clock drift, repeated attempts, face
  off / optional / required with retries and HR-review fallback; browsers cannot report liveness or mock location.
- Per policy version: «السماح بتسجيل الحضور من بوابة الويب» (default on; off where mobile check-in was off) and
  «يجب أن يكون تسجيل الويب داخل النطاق الجغرافي» (default on; off = the position is only recorded).
- Attempts carry a `channel` (`mobile` / `web`); accepted web attempts become raw events with source
  `web_portal` («بوابة الويب (GPS)»); the review queue has a channel column and filter.
- `GET /me/attendance/today` rules add `web_checkin_enabled`, `web_checkin_requires_geofence`,
  `checkout_extra_meters`, `face_failure_action`; attempt resources add `channel`.
- Requires HTTPS (or localhost); `Permissions-Policy` allows camera and geolocation on `/portal` only.

### Phase 11 — Final testing, hardening and documentation (`f0b607c` and later)

- **Security:** private `local` disk no longer served over HTTP; security headers and nonce-based
  Content-Security-Policy middleware (`config/security.php`, `SECURITY_*` variables); session cookie marked Secure
  automatically on HTTPS; export routes throttled (default 20 / minute / user); file-extension checks on uploads;
  admin download access logging; identity and bank values redacted in audit metadata; tests asserting every admin
  route is authorised and every state-changing route is CSRF-protected.
- **Performance:** composite index migration `2026_11_22_100000_add_performance_indexes`; N+1 fixes in the schedule
  resolver, settings and reports (admin dashboard cold load 302 → 78 queries).
- **Payroll:** proration is now exact — amount × days ÷ period days, rounded once per line.
- **Reports:** access to a report area now also requires the area's module view permission; new permission
  `reports.manage` opens every area (Reports Viewer and HR Manager hold it). Recruiters see recruitment reports
  only; Finance Officers no longer see attendance reports.
- **Installation:** `php artisan hrm:create-super-admin` creates the first administrator on a production install
  (password prompted or `HRM_ADMIN_PASSWORD`, validated, audited). Production seeding verified: permissions,
  roles, workflows, notification templates and reference data only.
- **Testing:** factories for every model (109 added); scenario 5 and an 11-role smoke test; backend suite
  1386 passed.
- **Web-portal check-in (groundwork):** migration `2026_11_23_100000_add_web_portal_check_in` adds a check-in
  channel (mobile / web) to mobile attempts and per-policy switches for web check-in and its geofence
  requirement (feature completed in `44aa52a`, see above).
- **Documentation (Arabic and English):** `USER_MANUAL_AR` (+ PDF), `ROLE_GUIDES_AR`, `ROLE_PERMISSION_MATRIX_AR`,
  `ATTENDANCE_INTEGRATION_AR`, `PAYROLL_GUIDE_AR`, `API_DOCUMENTATION`, `DATABASE_ARCHITECTURE` (Mermaid ERDs),
  `DEPLOYMENT_GUIDE_AR`, this changelog and `FINAL_IMPLEMENTATION_REPORT`.

### Test-fix passes (`2ffd01a`, `320eb52`)

- Flutter: locale-independent API date formats, envelope error codes, payslip test scrolling — 107 tests pass,
  5 live-API tests skipped by default.
- Backend: failing tests fixed; full suite green (1201 passed at that point).

### Phase 10 — Flutter employee app (`336ccd2`)

- Feature-first app (package `hrm_plus`, placeholder bundle id `com.hrmplus.employee`): Riverpod, go_router with
  auth guard, dio client with envelope parsing, typed failures, token refresh, secure token storage.
- Arabic (default, RTL) and English; Material 3 theme from the design tokens with the Cairo font; money and digits
  formatted without floating-point numbers.
- 25 screens: auth with OTP password reset, home, attendance with GPS + face check-in / check-out covering every
  failure path (offline, permissions, GPS timeout, accuracy, geofence, camera, review, rejection), schedule, leave,
  payslips with breakdown and PDF, loans, HR requests, documents, announcements, notifications, settings.
- Push notifications behind a `PushService` interface (no-op without Firebase configuration), device-token
  registration and deep links; Android / iOS platform setup documented in `application/README.md`.

### Phase 9 — Employee portal pass and API gaps (`533a741`)

- Portal home (today, shift, month totals, balances, latest payslip, pending counts, upcoming shifts and holidays,
  tasks), *My documents*, *Account settings* (password, language, theme, signed-in browsers and app devices,
  recent sign-ins); ownership audit of every portal and `me/*` route; responsive pass at 320–375 px.
- API: `app/config`, forgot password with e-mailed OTP (`forgot`, `verify-otp`, `reset`), change password, app
  sessions, `me/home`, `me/documents/all`, contract download, `PATCH me/profile` (locale), profile photo.
- OpenAPI documentation with dedoc/scramble at `/docs/api` and a static `docs/openapi.json`;
  `docs/API_FLUTTER_NOTES.md`.

### Phase 8c — Admin dashboard and reports centre (`79f7b2b`)

- 22 permission-aware dashboard widgets from live queries with date / branch / department / group filters,
  per-admin customisation and caching.
- Reports centre: 52 reports (48 required + HR requests, asset register, disciplinary cases, manual attendance
  entries) with shared filters, charts, Excel / CSV / Arabic PDF export, queued large exports (*My exports*) and
  data-scope enforcement. Chart.js 4; central display formatter for dates and digits.

### Phase 8b — HR requests, announcements, notifications, settings (`1a3b526`)

- HR requests: categories with SLA, assignment, escalation, employee-visible replies vs internal notes, private
  attachments, letter templates with PDF letters, profile change requests (encrypted values).
- Announcements: bilingual sanitised rich text, targeting, scheduling, expiry, pinning, mandatory acknowledgement,
  read statistics.
- Notification centre: 70 events, editable templates per event × channel × language, user preferences, database /
  mail / push channels (FCM HTTP v1 or labelled simulated provider), delivery log, device tokens, automations.
- Settings: e-mail delivery (test send), push (FCM key upload, test push), data retention, password policy, session
  lifetime, login throttling; secrets encrypted.

### Phase 8a — Performance, training, assets, disciplinary (`165ea22`)

- Performance cycles, goals / KPIs, self and manager assessment, competencies, rating scales, HR calibration,
  acknowledgement.
- Training providers, programmes, courses, sessions, enrolments with approval, attendance, certificates with expiry
  reminders, skills matrix, budgets.
- Asset catalogue, custody, acknowledgement, returns, history, offboarding clearance.
- Restricted disciplinary module: cases, investigations, evidence, actions with approval, employee responses,
  salary deductions into payroll.

### Phase 7b — Payroll engine, runs, settlements, payslips (`b78690a`)

- Payroll periods; run state machine draft → calculate → review → two-level approval → finalise → lock, with
  cancel and full reversal; row locking and queued calculation for large runs.
- Settlement scoping by branch, department, team, position, group, individuals or whole company; double-pay
  prevention by unique pay slot.
- Per-employee breakdown with explanations, input snapshot, rule versions, warnings and variance.
- Post-finalisation corrections (automatic scan + manual), correction runs, final settlement.
- Payslips (HTML and Arabic PDF with mPDF + Cairo), payments, register and bank file exports.

### Phase 7a — Payroll components, allowances, deductions, loans (`ef99232`)

- Salary components with a closed set of rule types; versioned salary structures with assignment precedence.
- Allowances and deductions (`pay_entries`) with scope precedence, recurrence, instalments, approval, bulk entry and
  import.
- Effective-dated statutory rule tables (no seeded legal rates; demo tables flagged).
- Loans and salary advances with approval, schedule, append-only ledger, early repayment and schedule changes.

### Phase 6 — Leave management (`0714c4e`)

- Configurable leave types, effective-dated policies per company / employment type / group, accrual methods,
  carry-over with expiry, encashment, append-only balance ledger.
- Requests with attachments, multi-level approval, cancellation workflow; leave calendar; reports; feeds into
  attendance and payroll.

### Phase 5b — Attendance capture (`e1844c4`)

- Device adapter architecture with a labelled simulated adapter, push endpoint, scheduled pull with backoff, manual
  and historical sync, CSV / Excel import, device-user mapping, health and sync logs.
- GPS check-in / check-out API with server-side geofence, accuracy, shift-window, time-drift and mock-location
  checks and a review queue.
- Face verification provider abstraction with a labelled simulated provider, consent, enrolment, encrypted storage,
  retention and deletion.
- Attendance dashboard, calendar and reports.

### Phase 5a — Attendance core (`b3f70f5`)

- Raw events vs processed days, idempotency keys, attendance engine with versioned policies, period locks and
  adjustments.
- Shifts (fixed, flexible, night, split), weekly / rotating / monthly schedules with precedence, calendar overrides,
  shift changes and swaps, holidays, configurable day statuses.
- Manual entry: individual corrections, bulk "mark all present except…" with confirmation and audit, Excel import,
  approval workflows.

### Phase 4 — Recruitment, onboarding, offboarding (`fcb28ae`)

- Vacancies, configurable pipelines, candidates, kanban, interviews with weighted scorecards, offers with approval,
  candidate-to-employee conversion, optional public career page.
- Onboarding checklists; offboarding cases with handover items, exit interview and termination.

### Phases 0–3 — Foundation, UI kit, structure, employees (`e367790`)

- Laravel 13 / PHP 8.3 foundation, two account types and guards, Spatie RBAC with 11 default roles and data
  scopes, audit and sensitive-access logging, settings, approval engine, API foundation (Sanctum, envelope,
  throttling).
- Figma design system and layout shells (screens built in code — client decision), Blade + Tailwind + vanilla JS UI
  kit, admin and portal layouts, RTL / LTR and dark mode.
- Company structure (branches and locations, departments, teams, positions, titles, grades, employment types, cost
  centres, org chart).
- Employees: tabbed profile, effective-dated assignments and salaries, contracts, documents on private storage,
  encrypted identity numbers, bulk operations, Excel import / export, custom fields, employee-number generation.

### Project start (`93775c0`)

- Repository initialised with the Laravel skeleton and the Flutter template.
