# Full Enterprise HRM System — UI/UX Design + Complete Development

Act as a Principal Software Architect, Senior Laravel Engineer, Senior Flutter Engineer, UI/UX Designer, Database Architect, QA Engineer, and HRMS Product Specialist.

Your mission is to design and build a complete, enterprise-grade Human Resources Management System (HRMS) for a company. This must be a real, production-ready system, not a prototype, demo, or collection of static dashboards.

You are responsible for the entire project lifecycle:
1. Analyze the requirements and define the complete system architecture.
2. Design the complete UI/UX in Figma FIRST using the available Figma MCP integration.
3. Implement the complete backend and web application.
4. Implement the Flutter employee mobile application.
5. Integrate attendance devices, GPS-based attendance, and face verification through proper integration layers.
6. Implement payroll, allowances, deductions, payroll adjustments, recruitment, employee management, and all other HR modules.
7. Test the entire system, fix issues, and provide complete documentation for every administrative role and the employee.

Do not stop after producing an architecture document or a Figma design. Continue with implementation until the system is functional and all major modules are completed.

---

# 1. Project Structure & Technology Stack

The project root must contain two main directories:

```text
project-root/
│
├── backend/
│   └── Laravel application
│
└── application/
    └── Flutter employee mobile application
```

Additional root-level documentation files are acceptable, but the two main application directories must remain `backend` and `application`.

## Backend

Use:

- Laravel (latest version compatible with the installed PHP environment).
- PHP.
- MySQL.
- Laravel Blade for rendering web pages.
- Tailwind CSS for the complete UI.
- Pure Vanilla JavaScript (ES6+) for all frontend interactions.
- Laravel REST API for the mobile application.
- Laravel Sanctum or another appropriate secure authentication solution.
- Laravel Queues for background operations.
- Laravel Scheduler for recurring HR operations.
- Laravel Notifications for system notifications.
- Laravel Policies and Middleware for authorization.
- Spatie Laravel Permission or an equivalent mature permissions solution.

### Strict frontend requirements

DO NOT USE:

- Livewire (absolutely prohibited).
- Alpine.js.
- Vue.js.
- React.
- Inertia.js.
- jQuery.
- Any frontend framework that replaces Vanilla JavaScript.

Use Blade + Tailwind CSS + Pure Vanilla JavaScript only.

Organize JavaScript into reusable ES modules and separate files by feature. Do not put large amounts of JavaScript directly inside Blade templates.

Use Vite where appropriate for asset compilation.

The web application must include two separate experiences:

- Admin Dashboard.
- Employee Web Dashboard.

Each experience must have its own layout, navigation, permissions, and dashboard widgets.

## Flutter Application

The `application` directory is exclusively for the employee mobile application.

Use:

- Flutter.
- Dart.
- Clean, maintainable architecture.
- REST API communication with the Laravel backend.
- Secure token storage.
- Push notifications.
- Camera integration.
- GPS/location integration.
- Face verification integration.
- Arabic RTL and English LTR support.

Do not build an admin mobile application. The Flutter application is for employees only.

---

# 2. User Types, Authentication & Permissions

The system must have exactly TWO main account types:

## A. Admin

Admin represents anyone who manages, supervises, or operates the HR system.

Examples of administrative roles:

- Super Admin.
- HR Manager.
- HR Specialist.
- Recruiter.
- Payroll Accountant.
- Attendance Officer.
- Branch Manager.
- Department Manager.
- Direct Manager.
- Finance Officer.
- Reports Viewer.

These are permissions/roles within the Admin account type, NOT additional user types.

Build a complete dynamic Role-Based Access Control (RBAC) system.

The Super Admin must be able to:

- Create administrative users.
- Assign one or multiple roles to an administrative user.
- Create custom roles.
- Assign granular permissions to each role.
- Control access to individual modules.
- Control viewing, creating, editing, deleting, approving, rejecting, exporting, and managing permissions.
- Restrict administrative users to specific branches, departments, or employee groups.
- Review the access history of administrative accounts.

Examples:

An Attendance Officer can manage attendance but cannot access sensitive payroll information.

A Recruiter can manage candidates, vacancies, and interviews but cannot modify payroll.

A Branch Manager can view and manage employees within assigned branches only.

A Payroll Accountant can prepare payroll and settlements but cannot modify employee biometric settings.

The system must enforce authorization on the backend, not just hide buttons on the frontend.

### Important account behavior

An administrative user may also be a company employee.

Allow an Admin account to be linked to an employee profile, so an HR employee or manager can use the Admin Dashboard for work and still access their personal employee information through the Employee Dashboard, according to permissions.

## B. Employee

Employees must have access to their personal Employee Dashboard only.

Employees must never be able to access another employee's private information.

An employee should be able to view and manage their own allowed information and requests, including:

- Personal profile.
- Employment details.
- Attendance.
- Work schedules.
- Leave requests.
- Salary information.
- Payslips.
- Allowances.
- Deductions.
- Loans and advances.
- Documents.
- HR requests.
- Notifications.
- Announcements.
- Performance reviews.
- Training.
- Company assets assigned to them.

Every protected operation must be validated by backend authorization and employee ownership rules.

---

# 3. Figma Design — MUST BE THE FIRST IMPLEMENTATION STEP

Before writing application code, create the complete system design in Figma using the available Figma MCP tools.

Do not begin coding the UI before completing the Figma design phase.

First inspect the available Figma MCP tools and their capabilities. Use the appropriate workflow for creating a new Figma design file and building editable design frames.

If Figma MCP is unavailable or access fails, report the exact blocker and do not pretend that a Figma file was created.

## Design direction

Create a premium enterprise SaaS HRMS interface.

The UI must feel like a professionally designed enterprise product, not a generic admin template.

Design requirements:

- Modern, clean, premium visual identity.
- Professional dashboard layouts.
- Excellent typography.
- Consistent spacing.
- High-quality tables.
- Advanced filters.
- Attractive charts.
- Meaningful status indicators.
- Consistent icons.
- Reusable components.
- Responsive layouts.
- Light mode and dark mode.
- Arabic RTL as the primary language.
- English LTR support.
- Proper handling of Arabic numbers, dates, currency, and text alignment.

Use a professional color system suitable for an enterprise HR platform. Define the complete color palette and design tokens in Figma.

## Figma pages

Create separate Figma pages for:

1. Design System.
2. Admin Dashboard.
3. HR & Employee Management.
4. Recruitment & Hiring.
5. Attendance & Shifts.
6. Leave Management.
7. Payroll & Financial HR.
8. Performance & Training.
9. Reports & Analytics.
10. Settings & Permissions.
11. Employee Web Dashboard.
12. Flutter Employee Application.

## Required Figma screens

Design all major screens, including:

- Admin login.
- Admin dashboard.
- Employee login.
- Employee dashboard.
- Employee directory.
- Employee profile.
- Create/edit employee.
- Organizational structure.
- Branch management.
- Department management.
- Job positions.
- Recruitment dashboard.
- Job vacancies.
- Candidate management.
- Candidate profile.
- Interview management.
- Attendance dashboard.
- Attendance calendar.
- Daily attendance table.
- Bulk attendance operation.
- Attendance device management.
- GPS attendance settings.
- Face verification settings.
- Shift scheduling.
- Leave management.
- Leave approval.
- Payroll dashboard.
- Payroll runs.
- Payroll settlement.
- Allowance management.
- Deduction management.
- Advances and loans.
- Payslips.
- Reports.
- Role and permission management.
- System settings.
- Employee profile in the web portal.
- Employee attendance and leave screens.
- Employee Flutter dashboard.
- Flutter check-in/check-out.
- Flutter leave requests.
- Flutter payslips.
- Flutter employee profile.
- Flutter notifications.

Design reusable components rather than designing each page independently.

After Figma is completed, use the resulting visual design as the source of truth for implementation. Continue with development without waiting for unnecessary approval.

---

# 4. Complete Admin Dashboard Modules

The Admin Dashboard must be modular, scalable, and permission-driven.

Create a professional sidebar with dynamically visible menu items according to the logged-in administrator's permissions.

## Module 1: Admin Dashboard & Analytics

Provide a comprehensive HR overview with:

- Total employees.
- Active employees.
- Inactive employees.
- Employees on leave.
- Employees absent today.
- Employees currently present.
- Late arrivals.
- Pending leave requests.
- Pending HR requests.
- New candidates.
- Open job vacancies.
- Upcoming contract expirations.
- Upcoming birthdays.
- Upcoming employee anniversaries.
- Payroll summary.
- Monthly salary expenses.
- Branch comparison.
- Department comparison.
- Recruitment statistics.
- Attendance trends.
- Employee turnover.
- Employee distribution charts.

Dashboard widgets must be configurable according to permissions.

Allow filtering by date, branch, department, and employee group where applicable.

## Module 2: Company Structure

Build a complete organizational structure management module.

Include:

- Company profile.
- Branches.
- Branch locations.
- Departments.
- Teams.
- Job titles.
- Job positions.
- Job grades.
- Employment types.
- Cost centers.
- Reporting hierarchy.
- Organizational chart.
- Branch managers.
- Department managers.
- Direct managers.

A company can have multiple branches and departments.

Employees must be linked to the appropriate branch, department, job position, employment type, and direct manager.

Support historical changes to employee assignments instead of overwriting important historical records.

## Module 3: Employee Management

Build a complete employee directory and employee management system.

Each employee must have a detailed profile with organized tabs.

### Personal Information

- Full name.
- Employee number.
- Profile picture.
- Date of birth.
- Gender, where required by company policy.
- Nationality.
- National ID or passport details.
- Phone number.
- Email.
- Address.
- Emergency contact.
- Marital status, where applicable.

### Employment Information

- Branch.
- Department.
- Team.
- Job title.
- Job grade.
- Direct manager.
- Employment type.
- Employment status.
- Joining date.
- Probation period.
- Contract start date.
- Contract end date.
- Work location.
- Work schedule.
- Salary structure.
- Cost center.

### Employee Documents

Support secure file uploads and document management:

- National ID.
- Passport.
- Employment contract.
- Certificates.
- Qualifications.
- CV.
- Medical or insurance documents where applicable.
- Signed policies.
- Other attachments.

Provide document categories, expiry dates, reminders, and access restrictions.

### Employee Financial Information

- Basic salary.
- Allowances.
- Recurring deductions.
- One-time deductions.
- Loans.
- Advances.
- Bonuses.
- Overtime payments.
- Payroll history.

Sensitive financial information must be permission-protected.

### Employee History

Maintain a historical record of:

- Promotions.
- Salary changes.
- Department transfers.
- Branch transfers.
- Job title changes.
- Contract changes.
- Disciplinary actions.
- Performance reviews.
- Changes in employment status.

Never lose historical information when an employee's details change.

### Additional Employee Operations

- Create employee.
- Edit employee.
- Activate/deactivate employee.
- Terminate employee.
- Rehire employee.
- Transfer employee.
- Promote employee.
- Assign employee to a branch.
- Assign manager.
- Import employees from Excel.
- Export employee data.
- Bulk operations.

When an employee is terminated, preserve their historical attendance, payroll, documents, and financial records.

## Module 4: Recruitment & Applicant Tracking System (ATS)

Build a complete recruitment management system.

### Job Vacancies

- Create job vacancies.
- Job title.
- Description.
- Requirements.
- Branch.
- Department.
- Salary range (optional and permission-controlled).
- Employment type.
- Number of required candidates.
- Application deadline.
- Vacancy status.

### Recruitment Pipeline

Support configurable candidate stages, including:

- New Application.
- CV Review.
- Shortlisted.
- Initial Screening.
- Interview Scheduled.
- Technical Interview.
- HR Interview.
- Final Interview.
- Offer Sent.
- Hired.
- Rejected.
- Withdrawn.

### Candidate Management

- Full name.
- Contact information.
- CV upload.
- Experience.
- Qualifications.
- Skills.
- Expected salary.
- Current salary (restricted access).
- Recruitment source.
- Applied vacancy.
- Internal notes.
- Interview history.
- Evaluation scores.
- Attachments.

### Interview Management

- Schedule interviews.
- Assign interviewers.
- Interview date and time.
- Meeting location or link.
- Interview feedback.
- Evaluation forms.
- Scores.
- Final decisions.

### Recruitment Automation

- Email notifications.
- Interview reminders.
- Candidate status updates.
- Offer management.
- Convert accepted candidate into an employee.
- Preserve candidate-to-employee history.

Create a career page that can optionally be exposed publicly to receive applications.

## Module 5: Onboarding & Offboarding

### Onboarding

Create configurable onboarding workflows.

Examples:

- Collect employee documents.
- Sign contracts.
- Create employee account.
- Assign branch and department.
- Assign direct manager.
- Assign company assets.
- Assign work schedule.
- Complete onboarding checklist.
- Track onboarding progress.

### Offboarding

- Resignation.
- Termination.
- Contract expiration.
- Exit interview.
- Asset handover.
- Final financial settlement.
- Document handover.
- Account deactivation.
- Preserve employee records.

## Module 6: Attendance Management

This module is a critical part of the project.

It must support three attendance sources:

1. Physical biometric attendance devices.
2. Employee Flutter application using GPS and face verification.
3. Manual attendance management by authorized administrators.

Build a unified attendance engine that can process all three sources.

### A. Physical Fingerprint Attendance Devices

Support integrating physical biometric attendance devices used by the company.

The system architecture must support multiple devices and multiple branches.

Build:

- Attendance Devices.
- Device Configuration.
- Device Identification.
- Device-to-employee mapping.
- Device event synchronization.
- Attendance event processing.
- Duplicate event detection.
- Device synchronization logs.
- Failed synchronization handling.
- Last synchronization status.
- Device health status.
- Manual synchronization.
- Historical event imports.
- CSV/Excel import fallback.

Use a clean integration architecture with a common device adapter interface.

Do not assume that all fingerprint device brands use the same protocol.

Investigate the available device integration options based on the actual manufacturer, model, firmware, SDK, or API documentation.

Support appropriate integration methods such as:

- Push-based event delivery.
- Pull-based synchronization.
- Vendor SDK integration.
- ADMS or equivalent protocols when supported.
- File-based imports when direct integration is unavailable.

Do not claim that an untested device is supported.

Because the exact fingerprint device model may not yet be known, build the adapter architecture and a simulated device adapter first. Make it possible to add verified device-specific adapters later without redesigning the attendance system.

Store raw device events separately from processed attendance records.

Ensure duplicate events cannot produce duplicate attendance records.

Provide an administrator interface to review device events and resolve mapping or synchronization issues.

### B. GPS-Based Attendance Through Flutter

Employees must be able to check in and check out through the Flutter application using their current location.

The company must be able to define allowed attendance locations.

Support:

- Multiple company locations.
- Multiple branches.
- Location coordinates.
- Geofence radius or configurable geographic boundaries.
- Allowed check-in distance.
- Allowed check-out distance.
- Location accuracy thresholds.
- Attendance schedules.
- Grace periods.
- Check-in rules.
- Check-out rules.
- Break rules.
- Late arrival calculations.
- Early departure calculations.

When an employee tries to check in:

1. Request the required location permission.
2. Obtain the current GPS location.
3. Record latitude and longitude.
4. Record location accuracy.
5. Validate the location against authorized company locations.
6. Validate the employee's scheduled shift.
7. Perform the configured face verification.
8. Submit the attendance request to Laravel.
9. Perform server-side validation.
10. Return a clear result to the employee.

The system must not trust location coordinates sent by the client without server-side validation.

Record attendance source, timestamps, coordinates, accuracy, branch, device identifier, and verification result.

Flag suspicious attempts for administrative review, including detected mock-location indicators where available.

Do not silently approve suspicious attendance.

### C. Face Verification

Implement face-based verification for mobile attendance.

Requirements:

- Camera access.
- Clear face capture guidance.
- Face matching against the employee's enrolled identity.
- Liveness detection or an equivalent anti-spoofing mechanism.
- Verification result.
- Verification timestamp.
- Failed verification handling.
- Retry rules.
- Administrative review.
- Secure biometric enrollment and management.

Important security requirements:

- Do not implement fake face recognition using only image comparison or a simple camera screenshot.
- Use a verified face verification SDK or provider with a suitable mobile integration.
- Build an abstraction layer so the provider can be changed later.
- Never store raw facial images or biometric templates in plain text.
- Encrypt sensitive biometric data and strictly restrict access.
- Prefer provider-side matching or secure on-device processing where feasible.
- Include employee consent, retention policies, deletion procedures, and alternative/manual verification workflows for legitimate cases where face verification is unavailable.

If a real SDK/provider cannot be used because credentials or licensing are missing, implement the integration interface, configuration, simulated development adapter, and clear setup documentation. Do not present simulated verification as real biometric verification.

### D. Manual Attendance Entry — IMPORTANT CLIENT REQUIREMENT

The system MUST provide a fast and practical bulk attendance interface.

The administrator should be able to select:

- Date.
- Branch.
- Department.
- Team.
- Shift.
- Employee group.

Then use an operation like:

"Mark All as Present Except..."

The system should initially mark all eligible scheduled employees as present, and the administrator can select exceptions.

Exception options should include:

- Absent.
- Late.
- On Leave.
- Official Mission.
- Remote Work.
- Day Off.
- Sick Leave.
- Other configurable attendance statuses.

Also support:

- Select all.
- Unselect all.
- Search employee.
- Select individual employees.
- Bulk update.
- Individual attendance correction.
- Attendance notes.
- Import from Excel.
- Attendance approval workflows.

Require a confirmation screen before submitting bulk changes.

All bulk attendance actions must be logged in the audit trail.

Prevent duplicate attendance records and preserve the actual source of each record.

Manual attendance should be distinguishable from physical device and mobile attendance.

### E. Attendance Management & Calculations

Support:

- Daily attendance.
- Monthly attendance.
- In/out punches.
- Multiple punches per day.
- Missing punches.
- Late arrivals.
- Early departures.
- Absences.
- Overtime.
- Breaks.
- Shift changes.
- Holidays.
- Weekly days off.
- Rotating schedules.
- Night shifts.
- Cross-midnight shifts.
- Attendance corrections.
- Attendance approvals.
- Attendance reports.

Allow configurable attendance rules.

Never hardcode attendance policies in controllers or frontend JavaScript.

Keep attendance calculations centralized in dedicated business services.

## Module 7: Shift & Schedule Management

Build:

- Fixed shifts.
- Flexible shifts.
- Rotating shifts.
- Night shifts.
- Multiple shifts per day.
- Weekly schedules.
- Monthly schedules.
- Employee-specific schedules.
- Department schedules.
- Branch schedules.
- Public holidays.
- Weekly holidays.
- Break periods.
- Shift assignments.
- Shift swaps.
- Schedule approvals.

Provide a visual calendar for administrators.

## Module 8: Leave & Time-Off Management

Build a complete leave management system.

Leave types must be configurable.

Examples:

- Annual Leave.
- Sick Leave.
- Emergency Leave.
- Unpaid Leave.
- Maternity Leave.
- Paternity Leave.
- Casual Leave.
- Other company-defined leave types.

Features:

- Leave policies.
- Leave eligibility.
- Accrual rules.
- Annual balance.
- Carryover rules.
- Maximum allowed days.
- Leave requests.
- Attachments.
- Approval workflows.
- Multiple approval levels.
- Rejection reasons.
- Cancellation requests.
- Leave calendars.
- Branch calendars.
- Department calendars.
- Employee leave history.
- Balance adjustments.

Leave calculations must be configurable and effective-dated.

Support different leave rules per employment type or employee group.

## Module 9: Payroll & Financial HR Management

This module must be designed as a complete and accurate payroll engine.

Do not implement payroll as a simple total of salary plus allowances minus deductions.

Create a structured, configurable payroll calculation engine with clear calculation history.

### A. Salary Structures

Support:

- Basic salary.
- Fixed salary.
- Daily salary.
- Hourly salary.
- Monthly salary.
- Salary components.
- Recurring payments.
- Variable payments.
- Effective dates.
- Salary change history.

### B. Allowances

The system must allow administrators to create unlimited configurable allowance types.

Examples:

- Transportation Allowance.
- Housing Allowance.
- Meal Allowance.
- Mobile Allowance.
- Attendance Allowance.
- Position Allowance.
- Performance Bonus.
- Commission.
- Overtime Payment.
- Other Allowances.

Allowances must support:

- Fixed amount.
- Percentage-based calculation.
- Recurring allowance.
- One-time allowance.
- Monthly allowance.
- Employee-specific allowance.
- Department-specific allowance.
- Branch-specific allowance.
- Effective date.
- Expiration date.
- Taxability/configurable payroll treatment.

### C. Deductions

Support all common configurable deduction categories, including:

- Absence deductions.
- Late arrival deductions.
- Early departure deductions.
- Unpaid leave deductions.
- Loan installments.
- Advance repayments.
- Insurance deductions.
- Tax deductions.
- Penalties.
- Disciplinary deductions, where legally permitted.
- Other company-defined deductions.

Each deduction must support:

- Fixed amount.
- Percentage.
- Recurring deduction.
- One-time deduction.
- Installment-based deduction.
- Effective date.
- Expiration date.
- Approval requirement.
- Reason and supporting documentation.

All statutory or legally regulated calculations must be configurable by country and effective date.

Do not hardcode tax, insurance, or statutory contribution rates.

### D. Loans & Advances

Build a complete employee loans and salary advances module.

Features:

- Loan applications.
- Advance requests.
- Loan approval workflow.
- Requested amount.
- Approved amount.
- Installment plan.
- Repayment schedule.
- Outstanding balance.
- Automatic payroll deductions.
- Early repayment.
- Settlement.
- Loan history.

### E. Payroll Processing

The payroll engine must calculate:

- Basic salary.
- Allowances.
- Bonuses.
- Commissions.
- Overtime.
- Attendance-related adjustments.
- Absence deductions.
- Late deductions.
- Unpaid leave.
- Loan installments.
- Advance repayments.
- Other deductions.
- Configurable taxes and insurance.
- Net salary.

Provide a transparent breakdown for every employee.

### F. Payroll Settlements — IMPORTANT CLIENT REQUIREMENT

The payroll settlement interface must support filtering and grouping by:

- Branch.
- All employees.
- Department.
- Team.
- Job position.
- Employee group.
- Individual employees.

Example:

The administrator selects Branch A and creates a payroll settlement for all employees in that branch only.

The administrator must be able to process each branch independently or create a consolidated company payroll.

Support:

- Payroll periods.
- Monthly payroll.
- Custom payroll periods.
- Payroll run creation.
- Draft payroll.
- Calculation.
- Recalculation.
- Review.
- Approval.
- Finalization.
- Locking finalized payroll.
- Adjustment after finalization through controlled correction/reversal workflows.
- Payslip generation.
- Payroll export.
- Payroll history.

An administrator must be able to inspect individual employee calculations before approving payroll.

Never silently modify a finalized payroll run.

### G. Payslips

Generate professional payslips containing:

- Employee information.
- Payroll period.
- Basic salary.
- Allowances.
- Bonuses.
- Overtime.
- Deductions.
- Loans and advances.
- Taxes and insurance where applicable.
- Gross salary.
- Net salary.
- Payment status.

Employees can view and download only their own payslips.

### H. Payroll Auditability

Every payroll calculation must be traceable.

Store:

- Input values.
- Salary structure version.
- Calculation rules version.
- Attendance inputs.
- Allowance inputs.
- Deduction inputs.
- Calculation result.
- Approval history.
- Adjustment history.
- Finalization information.

Use database transactions and appropriate locking to prevent duplicate or inconsistent payroll processing.

## Module 10: Performance Management

Build:

- Performance cycles.
- Employee goals.
- KPIs.
- Appraisals.
- Manager evaluations.
- Self-assessments.
- Competency assessment.
- Review comments.
- Scoring.
- Approval workflows.
- Performance history.

## Module 11: Training & Development

Support:

- Training programs.
- Courses.
- Training providers.
- Employee enrollment.
- Training sessions.
- Attendance.
- Certificates.
- Skills.
- Qualifications.
- Training budgets.
- Expiry reminders.

## Module 12: Employee Assets

Manage assets assigned to employees.

Examples:

- Laptop.
- Desktop.
- Mobile phone.
- SIM card.
- Office equipment.
- Uniform.
- Company vehicle.
- Other assets.

Support:

- Asset assignment.
- Handover date.
- Return date.
- Asset condition.
- Employee acknowledgment.
- Asset history.
- Offboarding asset clearance.

## Module 13: Employee Requests & HR Services

Build an internal HR service request system.

Examples:

- Salary certificate.
- Employment certificate.
- Leave balance inquiry.
- Personal information correction.
- Salary inquiry.
- Document request.
- General HR inquiry.
- Other company-defined requests.

Features:

- Request categories.
- Request status.
- Priority.
- Assignment.
- Attachments.
- Internal notes.
- Employee-visible responses.
- Approval.
- Rejection.
- Escalation.
- Full request history.

## Module 14: Announcements & Internal Communication

Support:

- Company announcements.
- Branch-specific announcements.
- Department announcements.
- Employee group announcements.
- Important notices.
- Policy updates.
- Attachments.
- Read/unread status.
- Push notifications.
- Email notifications.

## Module 15: Disciplinary Actions & Warnings

Support configurable HR disciplinary records:

- Verbal warnings.
- Written warnings.
- Violations.
- Investigation records.
- Supporting documents.
- Employee responses.
- Approval workflows.
- Action history.

Restrict sensitive information to authorized administrators.

## Module 16: Reports & Analytics — IMPORTANT CLIENT REQUIREMENT

Create a dedicated reporting center with professional reports.

Every report should have appropriate filters.

Support filters by:

- Date range.
- Branch.
- Department.
- Team.
- Employee.
- Employee status.
- Job title.
- Manager.
- Employment type.

### Required Reports

**Employee Reports**
- Employee directory.
- Employee distribution.
- New hires.
- Terminated employees.
- Employee turnover.
- Employee history.
- Contract expiration.
- Employees by branch.
- Employees by department.

**Attendance Reports**
- Daily attendance.
- Monthly attendance.
- Absence.
- Late arrivals.
- Early departures.
- Overtime.
- Missing punches.
- Attendance source.
- Device synchronization.
- Attendance corrections.

**Leave Reports**
- Leave balance.
- Leave requests.
- Approved leaves.
- Rejected leaves.
- Leave utilization.
- Employees currently on leave.

**Payroll Reports**
- Payroll summary.
- Payroll by branch.
- Payroll by department.
- Gross salary.
- Net salary.
- Allowances.
- Deductions.
- Overtime payments.
- Loan balances.
- Advances.
- Payroll adjustments.
- Payroll history.

**Recruitment Reports**
- Vacancies.
- Candidate pipeline.
- Applications.
- Interview results.
- Hiring sources.
- Recruitment conversion.
- Time to hire.

**Performance Reports**
- Performance scores.
- Goals.
- Completed reviews.
- Training progress.

All reports must support appropriate export formats, such as Excel, CSV, and PDF.

Reports must respect the user's data access permissions.

## Module 17: Notifications & Automation

Implement centralized notifications.

Channels:

- In-app notifications.
- Email.
- Flutter push notifications.

Examples:

- Leave request submitted.
- Leave request approved/rejected.
- Attendance issue detected.
- Late arrival.
- Missing checkout.
- Contract expiration.
- Upcoming employee birthday.
- Payroll finalized.
- Payslip published.
- Training reminder.
- Interview reminder.
- HR request update.

Build configurable notification templates and preferences.

Use background jobs for heavy notifications and recurring processes.

## Module 18: System Settings

Include:

- Company settings.
- Branch settings.
- Working days.
- Timezone.
- Date format.
- Time format.
- Currency.
- Language.
- Arabic RTL / English LTR.
- Payroll settings.
- Attendance settings.
- Face verification settings.
- GPS/geofence settings.
- Leave settings.
- Notification settings.
- Email configuration.
- Push notification configuration.
- Document settings.
- Employee number generation.
- Custom fields.
- System parameters.

Make important business rules configurable instead of hardcoding them.

---

# 5. Employee Web Dashboard

Create a separate, polished employee portal.

The employee must see only their own information and permitted operations.

The Employee Dashboard should include:

## Employee Home

- Welcome message.
- Employee profile summary.
- Current attendance status.
- Today's shift.
- Check-in/check-out status.
- Monthly attendance summary.
- Remaining leave balance.
- Latest payslip.
- Pending requests.
- Latest announcements.
- Notifications.

## My Profile

- Personal data.
- Employment data.
- Branch and department.
- Direct manager.
- Contact details.
- Emergency contact.
- Documents.
- Employment history where appropriate.

Allow employees to request changes to protected information instead of modifying it directly.

## My Attendance

- Daily attendance.
- Monthly attendance.
- Attendance history.
- Check-in/check-out records.
- Working hours.
- Late arrivals.
- Absence.
- Overtime.
- Missing punches.
- Attendance correction requests.

## My Leaves

- Leave balance.
- Leave history.
- New leave request.
- Request attachments.
- Request status.
- Cancel leave request where policy permits.

## My Payroll

- Current salary structure, based on permissions.
- Payslips.
- Gross salary.
- Net salary.
- Allowances.
- Deductions.
- Loan balance.
- Advance history.

## My Requests

- Create HR request.
- Upload attachments.
- Follow request status.
- View responses.
- Access previous requests.

## Additional Pages

- Announcements.
- Notifications.
- Performance reviews.
- Training.
- Assigned company assets.
- Employee documents.
- Account settings.

The employee dashboard must be responsive and easy to use.

---

# 6. Flutter Employee Application

Build a complete, production-ready employee application in the `application` directory.

Do not build an admin application.

The mobile application must communicate with the Laravel REST API.

## Mobile Screens

- Splash Screen.
- Login.
- Forgot Password.
- OTP verification if configured.
- Home Dashboard.
- Employee Profile.
- Attendance.
- Check-in.
- Check-out.
- GPS location validation.
- Face verification.
- Attendance History.
- Work Schedule.
- Leave Balance.
- New Leave Request.
- Leave History.
- Payslips.
- Salary Breakdown.
- Loans and Advances.
- HR Requests.
- Request Details.
- Documents.
- Announcements.
- Notifications.
- Settings.
- Language Selection.

## Mobile Attendance UX

The employee should have a prominent attendance action on the home screen.

Display:

- Current attendance status.
- Current date and time.
- Assigned shift.
- Allowed company location.
- Distance/validity status.
- GPS status.
- Face verification status.

Check-in flow:

1. Tap Check In.
2. Explain why location permission is needed.
3. Obtain the current location.
4. Validate the allowed geofence.
5. Open the camera.
6. Complete face verification.
7. Submit the request.
8. Display the server's final result.

Check-out follows a similar flow.

Do not indicate a successful attendance operation until the backend confirms it.

Handle permission denial, camera errors, GPS failures, invalid location, poor GPS accuracy, network failure, and verification errors gracefully.

If offline attendance queuing is implemented, mark the record as pending verification and never present it as approved until validated by the backend.

## Flutter Application Requirements

- Professional UI matching Figma.
- Full Arabic RTL.
- English LTR.
- Proper responsive layouts.
- Secure authentication.
- Secure local storage.
- API error handling.
- Loading states.
- Empty states.
- Validation messages.
- Push notifications.
- Camera and location permissions with clear explanations.
- Organized feature-based architecture.
- Reusable widgets.
- Centralized API client.
- Centralized exception handling.

Use a consistent state management approach and a maintainable routing structure.

Document all required mobile permissions and platform configuration for Android and iOS.

---

# 7. Laravel API Architecture

Build a structured versioned REST API for the Flutter application.

Use:

```text
/api/v1/
```

Organize the APIs by domain:

- Authentication.
- Employee Profile.
- Attendance.
- Face Verification.
- GPS Validation.
- Shifts.
- Leaves.
- Payroll.
- Payslips.
- Employee Requests.
- Documents.
- Announcements.
- Notifications.
- Assets.
- Performance.
- Training.

Requirements:

- Proper HTTP status codes.
- Consistent JSON response structure.
- Request validation.
- API Resources.
- Authorization.
- Pagination.
- Filtering.
- Sorting.
- Secure file uploads.
- Rate limiting.
- API versioning.
- Token lifecycle management.

Provide complete API documentation using an appropriate OpenAPI/Swagger solution.

The Flutter app must use actual implemented APIs, not mock data.

---

# 8. Database Architecture

Design a normalized and maintainable relational database.

At minimum, consider dedicated tables/entities for:

- Users.
- Roles.
- Permissions.
- Employees.
- Employee Documents.
- Employee Contracts.
- Employee History.
- Branches.
- Departments.
- Teams.
- Job Positions.
- Job Grades.
- Cost Centers.
- Reporting Relationships.
- Vacancies.
- Candidates.
- Applications.
- Interviews.
- Recruitment Evaluations.
- Onboarding Workflows.
- Offboarding Workflows.
- Attendance Devices.
- Attendance Device Events.
- Attendance Records.
- Attendance Corrections.
- Attendance Policies.
- Work Locations.
- Geofences.
- Face Verification Records.
- Shifts.
- Employee Schedules.
- Holidays.
- Leave Types.
- Leave Policies.
- Leave Balances.
- Leave Requests.
- Salary Structures.
- Salary Components.
- Allowances.
- Deductions.
- Employee Loans.
- Salary Advances.
- Loan Installments.
- Payroll Periods.
- Payroll Runs.
- Payroll Employees.
- Payroll Items.
- Payslips.
- Payroll Adjustments.
- Performance Cycles.
- Goals.
- Performance Reviews.
- Training Programs.
- Employee Trainings.
- Assets.
- Asset Assignments.
- HR Request Categories.
- HR Requests.
- Announcements.
- Notifications.
- Employee Warnings.
- Audit Logs.
- Approval Workflows.
- Approval History.
- System Settings.

This is a suggested baseline, not a restriction. Refine the data model based on actual business requirements.

### Database Design Rules

- Use foreign keys.
- Add appropriate indexes.
- Use unique constraints where needed.
- Add appropriate database transactions.
- Avoid unnecessary duplication.
- Use soft deletes only where appropriate.
- Preserve historical payroll and attendance records.
- Use decimal types for financial values, never floating-point calculations.
- Use effective dates for configurable business rules.
- Add audit fields where needed.
- Avoid large, unstructured JSON blobs as a substitute for proper relational design.

Create a database ERD and architecture documentation.

---

# 9. Audit Logs & Security

The system manages highly sensitive employee, financial, and biometric information.

Security is a core product requirement, not an optional improvement.

Implement:

- Secure authentication.
- Password hashing.
- Optional multi-factor authentication.
- Secure session handling.
- Role-based access control.
- Branch-level and employee-level data restrictions.
- Backend ownership checks.
- CSRF protection.
- XSS protection.
- SQL injection prevention.
- Rate limiting.
- Secure file upload validation.
- Sensitive file access restrictions.
- Secure API authentication.
- Audit logs.
- Login history.
- Failed login monitoring.
- Permission change history.
- Payroll operation history.
- Attendance correction history.
- Sensitive-data access logs.

Do not expose sensitive information in logs.

Implement configurable retention policies for sensitive biometric and employee data.

Every important administrative action must be traceable:

- Who performed it?
- What changed?
- When did it happen?
- What was the old value?
- What is the new value?
- Which employee or record was affected?

Never permanently delete finalized payroll or important historical attendance data through ordinary CRUD actions.

---

# 10. User Experience & Application Quality

The entire product must feel like one unified professional software platform.

### Admin experience

- Responsive sidebar.
- Breadcrumbs.
- Global search.
- Advanced data tables.
- Server-side pagination.
- Filters.
- Bulk operations.
- Confirmation dialogs.
- Form validation.
- Toast notifications.
- Loading states.
- Empty states.
- Export functionality.
- Consistent status badges.
- Reusable modal components.

### Employee experience

- Simple navigation.
- Mobile-friendly layouts.
- Clear attendance actions.
- Easy request submission.
- Accessible information.
- Clear status feedback.

### Arabic support

Arabic must be treated as a first-class language.

Implement:

- Complete RTL layouts.
- Correct text alignment.
- Arabic labels.
- Proper font selection.
- Arabic validation messages.
- RTL-aware dropdowns and modals.
- Arabic reports.
- Arabic PDF generation.
- English translation support.

Do not merely translate labels while leaving an LTR layout.

---

# 11. Application Architecture & Code Quality

Keep the Laravel code modular and maintainable.

Organize business logic by domain.

Examples:

```text
backend/
├── app/
│   ├── Models/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   ├── Employee/
│   │   │   └── Api/V1/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   └── Middleware/
│   ├── Services/
│   │   ├── Attendance/
│   │   ├── Payroll/
│   │   ├── Recruitment/
│   │   ├── Leave/
│   │   └── Notifications/
│   ├── Actions/
│   ├── Policies/
│   ├── Jobs/
│   ├── Events/
│   └── Listeners/
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   ├── admin/
│   │   └── employee/
│   ├── css/
│   └── js/
├── routes/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
└── docs/
```

Adjust the structure when needed, but preserve clear separation between application domains.

Avoid:

- Fat controllers.
- Duplicate business logic.
- Hardcoded permissions.
- Hardcoded payroll formulas.
- Hardcoded attendance rules.
- Hardcoded branch IDs.
- Repeated frontend code.
- Unnecessary dependencies.
- Fake API responses.
- Static dashboard numbers.
- Placeholder buttons.
- Non-functional forms.

Implement reusable services, actions, validation, and authorization.

---

# 12. Testing & Quality Assurance

Do not consider a feature complete merely because its interface exists.

Every important feature must actually work.

Create automated tests using the appropriate Laravel and Flutter testing tools.

### Backend Tests

Test:

- Admin authentication.
- Employee authentication.
- Role permissions.
- Branch restrictions.
- Employee data privacy.
- Employee CRUD.
- Recruitment workflow.
- Attendance device event processing.
- Duplicate event prevention.
- Manual bulk attendance.
- GPS geofence validation.
- Face verification provider integration boundaries.
- Shift calculations.
- Leave balance calculations.
- Leave approvals.
- Payroll calculations.
- Allowance calculations.
- Deduction calculations.
- Loan installments.
- Branch-based payroll settlements.
- Payslip access restrictions.
- API authentication.
- File permissions.
- Audit logs.

### Flutter Tests

Test:

- Authentication.
- API integration.
- Attendance flows.
- Location permission handling.
- Camera permission handling.
- API error handling.
- Leave submission.
- Payslip viewing.
- Employee data loading.

### End-to-End Scenarios

Verify realistic scenarios:

**Scenario 1: New Employee**
1. HR creates a vacancy.
2. Candidate applies.
3. Candidate passes the recruitment process.
4. Candidate is hired.
5. Candidate is converted into an employee.
6. Employee is assigned a branch and department.
7. Employee receives an account.
8. Employee logs in to the Flutter application.

**Scenario 2: Mobile Attendance**
1. Employee opens the mobile application.
2. Employee checks in.
3. GPS is validated against the assigned company location.
4. Face verification is completed.
5. Backend stores the attendance record.
6. HR sees the attendance in the admin dashboard.

**Scenario 3: Manual Attendance**
1. Attendance Officer selects a date, branch, and shift.
2. Officer selects "Mark All as Present Except".
3. Officer marks exceptions.
4. System saves the batch.
5. Attendance records and audit history are updated.

**Scenario 4: Payroll**
1. Payroll Accountant selects a payroll period.
2. Accountant filters by a specific branch.
3. System loads eligible employees.
4. Payroll engine calculates salaries.
5. Accountant reviews allowances and deductions.
6. Accountant approves the payroll.
7. Payslips are generated.
8. Employees can access their own payslips.

**Scenario 5: Permissions**
1. Super Admin creates an Attendance Officer role.
2. Super Admin grants attendance permissions only.
3. Attendance Officer logs in.
4. Officer can manage attendance.
5. Officer cannot access payroll or restricted employee financial information.
6. Backend rejects unauthorized API requests.

---

# 13. Documentation — IMPORTANT CLIENT REQUIREMENT

The client specifically requires a complete explanation file covering the entire control panel from the perspective of every role.

This is mandatory.

Create comprehensive Arabic documentation inside:

```text
backend/docs/
```

Required documents:

### 1. USER_MANUAL_AR.md

A complete Arabic user manual explaining the entire system.

Explain every major menu, page, button, form, table, filter, action, and workflow.

### 2. ROLE_GUIDES_AR.md

Create separate detailed usage guides for each administrative role:

- Super Admin.
- HR Manager.
- HR Specialist.
- Recruiter.
- Payroll Accountant.
- Attendance Officer.
- Branch Manager.
- Department Manager.
- Direct Manager.
- Finance Officer.
- Reports Viewer.

For every role, document:

- What this role is responsible for.
- Which menus are available.
- Which pages can be accessed.
- What data can be viewed.
- What actions can be performed.
- What actions are restricted.
- How to complete daily tasks.
- How approval workflows work.
- Practical step-by-step examples.

Also document the Employee Dashboard and Flutter application from the employee's perspective.

### 3. ROLE_PERMISSION_MATRIX_AR.md

Create a detailed permissions matrix explaining all roles and their permissions.

### 4. ATTENDANCE_INTEGRATION_AR.md

Explain:

- Physical fingerprint device integration.
- Device adapter architecture.
- Device configuration.
- Employee mapping.
- Event synchronization.
- GPS attendance.
- Geofencing.
- Face verification.
- Security and fallback workflows.

### 5. PAYROLL_GUIDE_AR.md

Explain payroll calculations, salary structures, allowances, deductions, adjustments, loans, advances, payroll approval, branch settlements, and payslips.

### 6. API_DOCUMENTATION.md

Document all API endpoints and authentication requirements.

### 7. DATABASE_ARCHITECTURE.md

Explain the database structure and key relationships.

### 8. DEPLOYMENT_GUIDE_AR.md

Explain server deployment, environment setup, queues, scheduler, storage, Flutter build configuration, and maintenance.

### 9. CHANGELOG.md

Track major implementation changes.

Documentation must describe the actual implemented system, not imagined features.

If possible, also generate a PDF version of the Arabic user manual.

---

# 14. Development Workflow — FOLLOW THIS ORDER

Work in the following phases.

## Phase 0 — Project Inspection & Planning

Before making changes:

1. Inspect the project root.
2. Inspect the current PHP, Composer, Laravel, Flutter, and Dart environments.
3. Check whether either directory already contains an application.
4. Do not overwrite existing project work.
5. Analyze available dependencies and integrations.
6. Prepare a system architecture.
7. Identify external credentials or hardware information that will be required.
8. Record assumptions and blockers in documentation.

Do not stop because some integration credentials or a hardware model are unavailable.

Create clean interfaces and development adapters where necessary.

## Phase 1 — Figma Design

This phase MUST happen before UI implementation.

- Create the Figma file.
- Create the design system.
- Design all main Admin screens.
- Design all Employee Web screens.
- Design all Flutter screens.
- Build reusable components.
- Validate consistency and RTL behavior.
- Use the Figma design as the implementation reference.

## Phase 2 — Laravel Foundation

Implement:

- Laravel application setup.
- Database architecture.
- Authentication.
- Two user types.
- Admin RBAC.
- Employee account management.
- Admin layout.
- Employee layout.
- API foundation.
- Audit system.
- Core settings.

## Phase 3 — Company & Employee Management

Implement company structure, branches, departments, positions, employee profiles, contracts, documents, and employee history.

## Phase 4 — Recruitment & Onboarding

Implement vacancies, candidates, recruitment pipeline, interviews, hiring, onboarding, and offboarding.

## Phase 5 — Attendance Engine

Implement:

- Physical device integration architecture.
- Device management.
- Device events.
- GPS/geofence validation.
- Face verification provider abstraction.
- Manual attendance.
- Bulk attendance.
- Shifts.
- Attendance calculations.
- Attendance reports.

## Phase 6 — Leave Management

Implement leave types, policies, balances, requests, approval workflows, and reporting.

## Phase 7 — Payroll Engine

Implement:

- Salary structures.
- Allowances.
- Deductions.
- Loans.
- Advances.
- Overtime payments.
- Payroll calculations.
- Payroll adjustments.
- Branch-based settlements.
- Payroll approvals.
- Payslips.
- Financial reports.

Prioritize calculation correctness and automated tests.

## Phase 8 — Remaining HR Modules

Implement performance management, training, assets, HR requests, announcements, disciplinary records, notifications, and reporting.

## Phase 9 — Employee Web Dashboard

Complete the employee portal and connect it to actual backend functionality.

## Phase 10 — Flutter Employee Application

Build the Flutter application and integrate it with the actual Laravel APIs.

## Phase 11 — Final Testing & Documentation

- Run migrations.
- Seed development data.
- Test all roles.
- Test all critical workflows.
- Test mobile API integration.
- Test payroll calculations.
- Test attendance workflows.
- Fix reported errors.
- Generate complete documentation.
- Verify the project can be installed and deployed.

---

# 15. Development Rules You MUST Follow

1. This is a real production-oriented enterprise project.
2. Design in Figma before coding the UI.
3. Do not skip Figma and pretend it is complete.
4. Do not use Livewire under any circumstances.
5. Use Laravel Blade + Tailwind CSS + Pure Vanilla JavaScript for web.
6. Use Flutter only for the employee mobile application.
7. The system has exactly two main user types: Admin and Employee.
8. Administrative roles must be configurable through RBAC.
9. Build two separate web dashboards.
10. The Flutter app is for employees only.
11. Never rely on frontend-only permissions.
12. Do not use static numbers or fake data in production screens.
13. Do not create non-functional buttons or placeholder modules.
14. Do not implement fake GPS or fake face recognition and call them real integrations.
15. Build external integrations through maintainable interfaces.
16. Use database transactions for critical financial operations.
17. Preserve employee, payroll, and attendance history.
18. Validate all calculations with automated tests.
19. All administrative actions must be traceable.
20. Use Arabic RTL and English LTR throughout the system.
21. Follow Laravel and Flutter best practices.
22. Keep the application modular and easy to extend.
23. Do not ask unnecessary questions that prevent progress.
24. When information is missing, make a reasonable configurable assumption and document it.
25. Never fabricate third-party integration credentials, API responses, or hardware compatibility.
26. Complete one phase properly before moving to the next.
27. Do not stop after preparing plans, architecture, or documentation.
28. Keep track of completed work and remaining work in a project progress file.
29. Run relevant tests after every major module.
30. At the end, provide a concise implementation report with completed modules, test results, outstanding external dependencies, and exact setup instructions.

---

# 16. Final Deliverables

The final project must contain:

**Backend**
- Complete Laravel application.
- Two separate web dashboards.
- Admin authentication and dynamic RBAC.
- Employee authentication.
- Complete HR modules.
- Recruitment and ATS.
- Attendance engine.
- Device integration architecture.
- GPS and face verification integration layers.
- Leave management.
- Payroll engine.
- Branch-based payroll settlements.
- Reports.
- API for Flutter.
- Automated tests.
- Seeders and factories.

**Flutter**
- Complete employee application.
- Arabic RTL and English LTR.
- Secure authentication.
- GPS attendance.
- Face verification integration.
- Employee self-service.
- Leave requests.
- Payslips.
- Notifications.
- API integration.
- Tests.

**Figma**
- Complete design file.
- Design system.
- Admin screens.
- Employee web screens.
- Flutter screens.
- Reusable components.

**Documentation**
- Complete Arabic system user manual.
- Separate usage guides for all administrative roles.
- Employee usage guide.
- Permissions matrix.
- Attendance integration guide.
- Payroll guide.
- API documentation.
- Database architecture.
- Deployment guide.

# FINAL INSTRUCTION

Start by inspecting the project and the available Figma MCP tools.

Your FIRST actual product-design deliverable must be the complete Figma design.

After completing the Figma design, continue implementing the full system in the `backend` and `application` directories.

Do not deliver only a plan.

Do not create a beautiful dashboard with non-functional modules.

Do not leave important operations as TODO comments or placeholder implementations.

The final result must be a cohesive, secure, well-documented, fully functional HR Management System that a real company can use to manage its employees, recruitment, attendance, fingerprint devices, salaries, allowances, deductions, payroll settlements, and daily HR operations.