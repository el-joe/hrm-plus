# HRM Plus — Test catalogue

Generated 2026-11-11 by reading the Pest files in `backend/tests` (nothing was executed).
**54 files, 440 test cases.** Datasets (`->with(...)`) expand into more runs than listed here.

Run the whole suite (from `backend/`; `php artisan test` hides fatal errors in this environment):

```bash
env -i PATH=$PATH HOME=$HOME php vendor/bin/pest --parallel
```

Run one area: append the folder, e.g. `tests/Feature/Attendance`.

## Summary

| Area | Files | Tests |
|---|---:|---:|
| Feature/Admin | 5 | 61 |
| Feature/Api | 2 | 23 |
| Feature/Approvals | 1 | 14 |
| Feature/Attendance | 8 | 87 |
| Feature/Audit | 1 | 9 |
| Feature/Auth | 4 | 32 |
| Feature/Authorization | 4 | 32 |
| Feature/Employees | 9 | 73 |
| Feature/Frontend | 4 | 16 |
| Feature/Lifecycle | 2 | 12 |
| Feature | 1 | 8 |
| Feature/Recruitment | 3 | 22 |
| Feature/Settings | 1 | 6 |
| Feature/Structure | 9 | 45 |

## Feature/Admin

### `tests/Feature/Admin/AccessEscalationTest.php` (11)

- roles an admin may hand out › lets an admin assign a role whose permissions they all hold
- roles an admin may hand out › refuses a role that carries permissions the admin does not hold
- roles an admin may hand out › keeps a role the admin could not grant when other fields of the account are edited
- own account › does not let an admin change their own roles, a Super Admin included
- own account › does not let an admin lift their own data restrictions
- own account › still lets an admin edit their own name
- data scopes a restricted admin may hand out › accepts scopes inside the admin's own
- data scopes a restricted admin may hand out › refuses an unrestricted account or scopes outside the admin's own
- role permissions an admin may hand out › lets an admin edit a role that holds permissions they lack, without adding any
- role permissions an admin may hand out › still refuses to add a permission the admin lacks
- refuses to delete a role that is an approver in a workflow

### `tests/Feature/Admin/AdminUserControllerTest.php` (22)

- index › lists admin accounts only
- index › filters by role
- store › creates an admin with several roles, an employee link and data scopes
- store › audits the role assignment and the data scopes without storing the password
- store › returns 403 without the create permission
- store › requires the core fields
- store › rejects an email that is already used
- store › rejects an employee profile that does not exist
- store › rejects an employee profile that is already linked to another account
- store › rejects an unknown data scope type and non-numeric ids
- store › does not let a non Super Admin assign the Super Admin role
- update › updates the account, replaces its roles and audits the change
- update › removes all data scopes when they are cleared
- update › does not let an admin deactivate their own account
- update › returns 403 when a non Super Admin edits a Super Admin
- update › returns 404 for an employee account
- destroy › soft deletes an admin and audits it
- destroy › returns 403 when an admin deletes their own account, even a Super Admin
- destroy › returns 403 without the delete permission
- access history › shows the roles, effective permissions and recent sign-ins of an admin
- access history › lists the full login history of one admin only
- access history › returns 403 for the login history without the view permission

### `tests/Feature/Admin/AuditLogControllerTest.php` (5)

- lists audit entries for an admin with the audit permission
- returns 403 without the audit permission
- filters by event, actor and date range
- rejects a date range that ends before it starts
- shows the old and new values of one entry and escapes them

### `tests/Feature/Admin/RoleControllerTest.php` (14)

- index and forms › lists the roles
- index and forms › renders the permission matrix with the role's permissions ticked
- store › creates a custom role with the chosen permissions and audits them
- store › returns 403 without the create permission
- store › rejects a duplicate role name
- store › rejects a permission that is not in the catalogue
- store › does not let an admin grant a permission they do not hold
- update › changes the permissions of a role and audits the old and new sets
- update › keeps the name of a system role
- update › returns 403 when anyone edits the Super Admin role
- update › returns 403 without the update permission
- destroy › deletes a custom role that nobody holds
- destroy › returns 403 when deleting a system role
- destroy › refuses to delete a role that is still assigned

### `tests/Feature/Admin/SettingControllerTest.php` (9)

- shows a settings group with its current values
- redirects the settings home to the first group
- returns 404 for an unknown settings group
- returns 404 when saving an unknown settings group
- returns 403 when an admin who may only view settings tries to save them
- saves a group, treating unticked checkboxes as false
- saves list settings and rejects values outside the allowed options
- rejects an out-of-range value with a message naming the setting
- applies the configured minimum password length to new passwords

## Feature/Api

### `tests/Feature/Api/ApiConventionsTest.php` (9)

- list conventions › paginates with the pagination meta and only lists the employee's own records
- list conventions › caps per_page at the maximum
- list conventions › applies an allowed filter
- list conventions › sorts newest first by default and ascending on request
- list conventions › rejects a filter or sort that is not allowed
- error envelope › hides the exception message of a server error
- error envelope › answers unknown endpoints and wrong methods in the envelope
- error envelope › answers in JSON even when the client asks for HTML
- error envelope › rate limits the API per client

### `tests/Feature/Api/AuthApiTest.php` (14)

- login › issues an expiring token to an employee and records the login
- login › answers a wrong password with the validation envelope and records the failure
- login › requires email, password and a device name
- login › refuses an admin account even with the right password
- login › refuses an employee whose account is not active
- login › locks the login out with a 429 after too many failed attempts
- protected endpoints › returns the signed-in employee from me
- protected endpoints › answers 401 in the envelope without a valid token
- protected endpoints › rejects a token once it has expired
- protected endpoints › refuses a token that belongs to an admin account
- protected endpoints › refuses the token of an employee deactivated after logging in
- token lifecycle › revokes only the current token on logout
- token lifecycle › revokes every token of the account on logout-all
- token lifecycle › rotates the token on refresh

## Feature/Approvals

### `tests/Feature/Approvals/ApprovalServiceTest.php` (14)

- submit › opens a pending request at the first step and records the submission
- submit › refuses a workflow that is missing, inactive or has no steps
- submit › refuses a second request while one is still pending for the same record
- approve › walks through every step in order and closes the request on the last one
- approve › refuses an admin who is not the approver of the current step
- approve › refuses to act on a request that is already closed
- approve › matches the approver rule of the step
- approve › does not let the requester approve their own request unless the setting allows it
- approve › does not let a data-restricted approver decide on a record outside their scopes
- reject › closes the request at the current step with the reason
- reject › requires a reason
- cancel › lets the requester withdraw a pending request
- cancel › does not let anyone else withdraw it
- pendingFor › lists only the pending requests waiting on the user at their current step

## Feature/Attendance

### `tests/Feature/Attendance/AttendanceDashboardReportsTest.php` (6)

- counts scheduled, present, late and absent employees from the processed days
- shows employees without attendance as "not yet in" today until their shift is over
- renders the dashboard inside the admin data scope
- lists report rows from the processed days and exports only what is in scope
- opens attendance reports to report viewers, keeps device sync for device viewers and blocks others
- renders the monthly calendar for an employee and for a department

### `tests/Feature/Attendance/AttendanceDeviceTest.php` (10)

- accepts pushed logs only with the device token and stores them idempotently
- stores only a hash of the push token and invalidates it on rotation
- pulls the simulated device, logs the run, moves the cursor and reports re-pulled logs as duplicates
- records failed pulls, backs off and marks the device as failing
- schedules pulls only for active pull devices that are due
- keeps unknown device users as unmatched events until they are mapped, then processes them
- maps a single unmatched event from the review screen, optionally remembering the device user
- imports a CSV device log with a dry run that writes nothing first
- enforces permissions and the branch scope on devices
- creates and edits a device from the admin form, keeping stored credentials when left empty

### `tests/Feature/Attendance/AttendanceEngineTest.php` (14)

- calculates a normal day from device punches in the branch timezone
- applies grace, counting mode and rounding of lateness from the policy row
- calculates early departure and overtime with minimum, rounding and approval from the policy
- handles a night shift that crosses midnight, attributing both punches to the start date
- pairs punches of unknown direction and deducts unpaid breaks as scheduled
- takes break time from the punch gaps in actual mode and flags excess breaks
- handles a missing check-out according to the policy
- calculates a flexible shift against required minutes and core hours
- marks rest days and holidays, and counts work on them as overtime
- repeats a recurring holiday every year
- is idempotent: processing twice gives the identical result and writes nothing
- rejects a duplicate raw event at the database level and returns the stored one
- expects no attendance before hire or after termination
- uses approved leave from the leave provider hook

### `tests/Feature/Attendance/FaceVerificationTest.php` (10)

- skips face verification when the policy turns it off
- verifies when a face is sent in optional mode and never accepts a failure silently
- requires a face in required mode, allows retries, then falls back to review
- treats a failed liveness check or provider error as a failure and can reject instead of reviewing
- sends employees who are not enrolled to the manual fallback
- records consent per text version, enrols through the API and stores the template encrypted on the private disk
- deletes biometric data when consent is withdrawn
- deletes biometric data when a termination takes effect
- purges enrolments and verification results past their retention period
- shows the enrolment register only with permission, logs each view and lets HR delete the data

### `tests/Feature/Attendance/ManualAttendanceTest.php` (16)

- runs scenario 3: officer marks all present except, confirms, and days plus audit history are written
- keeps employees with device attendance as recorded when marking all present
- lists only scheduled employees matching the filters and the admin data scope
- validates exception times and requires at least one selected employee
- sends a bulk batch for approval when the policy requires it, and applies it once approved by someone else
- applies an admin correction directly, replacing the recorded check-in, when the policy allows
- runs the correction approval flow for an employee request from the portal
- freezes locked days and records later corrections as adjustments, never overwriting
- imports attendance from a file with a dry run first, then confirms it as a batch
- enforces permissions and data scope on the attendance screens
- shows the day breakdown from raw events to calculation, and lets officers void a punch and decide overtime
- runs the server side of scenario 2: a mobile check-in is stored and shown to HR
- processes yesterday from the scheduled command, idempotently
- serves the portal page and the mobile API for the employee only
- links the onboarding "assign work schedule" task to the schedule assignment screen
- imports a split-shift day without one shift superseding the other

### `tests/Feature/Attendance/MobileAttendanceTest.php` (11)

- accepts a check-in inside the geofence and records it through the raw event pipeline at server time
- validates distance with the accuracy tolerance and rejects poor accuracy or points outside the geofence
- sends suspicious attempts to review instead of recording them
- rejects instead of reviewing when the policy says so
- flags the next attempt after repeated rejections
- is idempotent per request uuid and refuses a uuid of another employee
- lets a reviewer approve (recorded at the attempt time) or reject a flagged attempt, audited, never their own
- uses work-location rules: employee override, department rule and remote work
- returns today's state for the attendance action
- accepts employee tokens only and validates the request
- runs scenario 2: login, face enrolment, GPS + face check-in, processed day visible to HR

### `tests/Feature/Attendance/ScheduleResolverTest.php` (9)

- applies assignments with the precedence employee > group > department > branch > company
- inherits a schedule assigned to a parent department
- respects effective dates and lets the latest start win within a level
- lets date overrides, including a rest-day override, beat the pattern
- counts a rotating cycle from its start date
- uses a monthly pattern by day of the month
- falls back to the weekend rule of the settings without any schedule
- orders several shifts on one day by start time
- expects nothing before the hire date

### `tests/Feature/Attendance/SchedulingScreensTest.php` (11)

- refuses every configuration screen to an admin without the permissions
- lets the attendance officer create and edit a shift with breaks, and delete it while unused
- requires required minutes for a flexible shift
- saves a work schedule pattern, ignoring days beyond the cycle, and refuses deleting a schedule in use
- prefills the assignment form from the onboarding link and stores an employee assignment
- manages public holidays
- creates a policy with version 1, adds a later version only and assigns it
- keeps system attendance statuses and their codes
- shows only in-scope employees on the calendar and changes a date through an override
- runs shift changes and swaps through the approval workflow
- renders every configuration page in Arabic and English

## Feature/Audit

### `tests/Feature/Audit/AuditLoggingTest.php` (9)

- logs the creation of an auditable model with its new values
- logs an update with only the changed attributes, old and new
- logs a deletion with the values the record had
- records who acted, from where and on which URL
- never stores a password, neither plain nor hashed, when a user is created or updated
- redacts secret-looking keys at any depth of a custom event
- writes no audit entry when only excluded attributes change
- does not allow an audit entry to be changed or deleted
- audits a change to a secret-named setting without storing its value

## Feature/Auth

### `tests/Feature/Auth/AdminLoginTest.php` (10)

- renders the admin login page
- signs an admin in and records the successful login
- rejects a wrong password and records the failed attempt
- records a failed attempt against an unknown email
- rejects an employee account on the admin login without revealing it exists
- rejects an admin whose account is not active
- locks the login out after five failed attempts
- signs an admin out
- redirects guests from the admin area to the admin login
- sends a signed-in admin away from the login page to the dashboard

### `tests/Feature/Auth/AreaSeparationTest.php` (6)

- never lets a signed-in employee into the admin area
- throws an employee out even when their session is on the admin guard
- lets an admin linked to an employee profile open the portal from the admin session
- redirects an admin with no employee profile from the portal to the portal login
- ends the admin session of an account that was deactivated meanwhile
- ends the portal session of an employee that was deactivated meanwhile

### `tests/Feature/Auth/EmployeeLoginTest.php` (9)

- renders the employee login page
- signs an employee in to the portal and records the login
- shows the portal home to a signed-in employee
- rejects a wrong password on the portal login
- rejects an inactive employee on the portal login
- rejects an admin with no employee profile on the portal login
- lets an admin linked to an employee profile sign in to the portal
- redirects guests from the portal to the portal login
- signs an employee out

### `tests/Feature/Auth/PasswordResetTest.php` (7)

- emails an admin a reset link that points to the admin reset form
- emails an employee a reset link that points to the portal reset form
- answers the same way but sends nothing when the email is not an account of that area
- resets the password with a valid token, revokes API tokens and keeps the secret out of the audit log
- rejects an invalid reset token
- does not let an employee reset through the admin form even with a valid token
- rejects a new password that is too weak

## Feature/Authorization

### `tests/Feature/Authorization/DataScopeTest.php` (14)

- shows every record to an admin with no data scopes
- limits a branch-restricted admin to the records of their branches
- limits a department-restricted admin to the records of their departments
- shows records matching any of the scopes when several dimensions are assigned
- keeps the restriction when the query has its own or-conditions
- ignores data scopes for a Super Admin
- shows nothing when the model maps none of the dimensions the admin is restricted by
- scopes a child model through its parent relation
- answers record-level checks for policies
- does not restrict queries outside the admin area
- restricts every query automatically once the admin is active, including lookups by id
- lifts the automatic restriction inside withoutScoping only
- applies the signed-in admin's scopes to admin routes and returns 404 for a record outside them
- stores the scopes of an admin grouped by type

### `tests/Feature/Authorization/PermissionCatalogueTest.php` (5)

- every permission is named module.action using a known action
- the seeder creates the catalogue and the eleven default roles of the spec
- the Super Admin role holds every permission
- re-running the seeder keeps the permissions an admin gave to an existing role
- expanding an unknown permission pattern fails loudly

### `tests/Feature/Authorization/PermissionSyncTest.php` (4)

- adds catalogue permissions that appeared since the last sync to the default roles, once
- never re-grants a default permission an administrator removed, and keeps custom grants
- grants a permission newly added to a role's default set on existing installs
- is run by the roles seeder

### `tests/Feature/Authorization/RolePermissionEnforcementTest.php` (9)

- an Attendance Officer manages attendance but holds no payroll or financial permission
- default roles respect the separations required by the spec
- returns 403 to an Attendance Officer on a payroll-protected route
- lets a Payroll Accountant through the payroll-protected route
- lets an admin holding several roles use the permissions of all of them
- returns 403 to an Attendance Officer on every access-control screen
- returns 403 to an admin without any role on a permission-protected route
- a Super Admin passes a permission that exists in the catalogue but was never granted in the database
- hides navigation links the admin has no permission for

## Feature/Employees

### `tests/Feature/Employees/EmployeeApiTest.php` (4)

- returns the signed-in employee's own profile with masked identity and no salary
- lists only documents shared with the employee and serves their files
- refuses admin tokens and unauthenticated calls
- answers 404 for an employee account without a profile

### `tests/Feature/Employees/EmployeeCrudTest.php` (11)

- creates an employee with a generated number, the opening history row and defaults from the position
- validates the required fields and the placement combination
- refuses a duplicate national id, compared through the keyed hash
- ignores the salary of the create form without employee_financials.update
- edits personal data without touching the placement
- saves typed custom field values and group memberships
- lists, searches (number, name, national id) and filters employees
- renders every profile tab and the create form
- renders the create and edit forms
- needs the employee permissions
- generates numbers from the configured pattern

### `tests/Feature/Employees/EmployeeDocumentsTest.php` (9)

- stores uploads on the private disk under a random name
- rejects file types that are not allowed by the documents settings
- serves a document only through the authorised controller and logs the download
- refuses downloads without employee_documents.view, outside the scope or through another employee
- does not expose the files under any public URL
- soft deletes documents and keeps the file
- lets an employee download their own shared documents only
- stores contracts with their signed copy and serves it to employee viewers only
- renews a contract as a new row and keeps the old one unchanged

### `tests/Feature/Employees/EmployeeImportExportTest.php` (9)

- dry-runs an import, reporting each problem by line without saving anything
- imports every row of a valid file in one go, with numbers, history and salary
- imports nothing when a row breaks the headcount of a position, counting the rows above it
- does not let another admin confirm someone else's upload
- resolves import codes inside the admin's data scope only
- exports only the employees in the admin's scope, with protected columns by permission, and logs it
- adds identity and salary columns for admins allowed to see them
- needs employees.export and employees.create
- downloads an xlsx template with the columns the admin may fill

### `tests/Feature/Employees/EmployeeNumberAndRemindersTest.php` (4)

- generates unique, increasing numbers and skips numbers already taken
- never goes below the configured next sequence
- locks the counter row so concurrent hires cannot draw the same number
- reminds HR (in scope) and the employee once about documents and contracts expiring soon

### `tests/Feature/Employees/EmployeePrivacyTest.php` (10)

- stores identity numbers and bank details encrypted, with a keyed hash for lookups
- masks identity numbers and logs every reveal
- never reveals identity numbers without employee_sensitive.view
- keeps the audit trail of employee changes free of the protected values
- drops identity numbers sent by an editor without employee_sensitive.update
- guards the financial tab with employee_financials.view and logs each view
- hides salary rows from the history of non-financial viewers
- requires employee_financials.update to change a salary or a bank account
- shows job-grade salary bands only with the financial permission
- ignores salary bands submitted without the financial permission

### `tests/Feature/Employees/EmployeeScopeTest.php` (10)

- includes every sub-department in a department restriction, at any depth
- restricts by branch and answers 404 for employees outside it
- makes an employee-group restriction actually filter employees
- offers employee groups and employee profiles as pickers on the admin user form
- does not let a restricted admin place an employee outside their scope
- runs bulk operations as individual history entries, skipping what does not fit
- only touches employees in the admin's scope in bulk operations
- treats structure records used by employees as in use
- shows position holders and department heads on the organisation chart, and a manager tree
- sets a team leader from the employees in scope

### `tests/Feature/Employees/EmploymentHistoryTest.php` (12)

- records a transfer as a new row and keeps the previous placement
- records a promotion with the new grade
- refuses a change that changes nothing, and a manager who reports to the employee
- schedules a future change and applies it on its date
- cancels a scheduled change without deleting it
- keeps the newer placement when a back-dated change is recorded
- terminates: status, account, open contract and history, nothing deleted
- frees the seat of a terminated employee and rehires under the same number with the history kept
- activates and deactivates through status history rows
- records salary changes with bcmath, derives the type and never overwrites earlier rows
- enforces the position headcount on placement and on the position itself
- counts scheduled moves into a position as taken seats

### `tests/Feature/Employees/PortalProfileTest.php` (4)

- shows the employee their own profile only, read-only, without salary or full identity numbers
- masks the national id but shows its last digits
- lets an admin linked to an employee open their own portal profile
- answers 404 when the account has no employee profile

## Feature/Frontend

### `tests/Feature/Frontend/AdminDashboardTest.php` (3)

- shows real counts on the dashboard of a Super Admin
- only shows figures the admin has permission to see
- lists recent audit activity for an admin allowed to read the audit log

### `tests/Feature/Frontend/AdminNavigationTest.php` (5)

- shows every sidebar entry to a Super Admin
- keeps only the entries matching the admin permissions
- drops a section with no visible entry
- marks the entry of the current route as active
- renders only permitted links in the sidebar

### `tests/Feature/Frontend/LayoutRenderingTest.php` (4)

- renders the admin login page with the theme bootstrap in both directions
- renders every admin screen of a Super Admin in both directions
- renders the employee portal and its login in both directions
- shows a flashed status message as a toast

### `tests/Feature/Frontend/UiKitTest.php` (4)

- renders the UI kit for a Super Admin
- forbids the UI kit to an admin without the Super Admin role
- forbids the UI kit to an HR Manager
- sends a guest to the admin login instead of the UI kit

## Feature/Lifecycle

### `tests/Feature/Lifecycle/OffboardingTest.php` (7)

- opens a case with the generic handover checklist and only one open case per employee
- cannot be completed while handover items are open, then terminates the employee and switches off every linked login
- needs offboarding.approve and employees.manage to complete a case
- respects a future last working day: the linked admin login stays on until the scheduled termination applies
- disables a linked admin account when an employee is terminated directly, and does not re-enable it on rehire
- opens one contract-end case per expiring contract and notifies HR in scope
- scopes offboarding cases through the employee

### `tests/Feature/Lifecycle/OnboardingTest.php` (5)

- picks the most specific active template for the new hire
- copies the template tasks with due dates relative to the start date and notifies the employee
- tracks progress, completes itself when nothing is pending and reopens when a task is reset
- lets the employee complete only their own tasks, in the portal and through the API
- answers the API with null when the employee has no onboarding

## Feature

### `tests/Feature/LocaleTest.php` (8)

- renders in Arabic, right to left, by default
- switches a guest to English for the rest of the session
- saves the chosen language on the signed-in account
- uses the saved language of the signed-in user over the default
- rejects an unsupported language
- answers API errors in the language of the Accept-Language header
- falls back to the default language chosen in the system settings
- answers the API in the token owner's saved language when the app sends no Accept-Language

## Feature/Recruitment

### `tests/Feature/Recruitment/CareerPageTest.php` (5)

- lists only published vacancies, without salaries
- is switched off by the setting
- validates the CV type and size, the consent and the honeypot
- matches a returning applicant to the existing candidate and refuses a second application to the same vacancy
- rate limits applications per visitor

### `tests/Feature/Recruitment/RecruitmentAccessTest.php` (10)

- salary fields › hides expected and current salary from admins without employee_financials.view and ignores them on save
- salary fields › shows salaries to admins with employee_financials.view and logs every view
- salary fields › keeps the offered salary out of reach of recruiters, who cannot submit an offer without it
- data scope and permissions › limits a branch-scoped recruiter to the vacancies and candidates of that branch
- data scope and permissions › makes a scoped recruiter attach new candidates to a vacancy in scope
- data scope and permissions › refuses recruitment screens to admins without recruitment permissions
- data scope and permissions › lets only recruitment.manage edit the pipeline
- interviewer visibility › shows an interviewer with interviews.view only the interviews they sit on, whatever their data scope
- interviewer visibility › lets interviews.manage see every interview in scope
- interviewer visibility › lets an employee interviewer score through the portal, and nobody else

### `tests/Feature/Recruitment/RecruitmentWorkflowTest.php` (7)

- creates a vacancy with a reference and keeps its openings within the position's free seats
- runs the whole hiring workflow from career page application to employee with preserved lineage
- refuses to hire without an accepted offer and never moves a card to the hired stage directly
- rejects a stage of another pipeline and records the rejection reason internally
- warns about duplicate candidates by e-mail or phone until the admin confirms
- sends interview reminders once, and expires sent offers past their date
- completes end-to-end scenario 1 up to the new employee signing in to the mobile app

## Feature/Settings

### `tests/Feature/Settings/SettingsServiceTest.php` (6)

- returns the configured default when nothing is stored
- stores a value and reads it back in its declared type
- returns a whole group as name => value
- fails loudly for a key that is not declared
- serves repeated reads from the cache and refreshes it after a write
- audits a changed setting with its old and new value

## Feature/Structure

### `tests/Feature/Structure/BranchLocationTest.php` (3)

- adds, edits and deletes an attendance location from the branch page
- validates the GPS point and geofence radius
- needs branches.update and only reaches locations through their own branch

### `tests/Feature/Structure/CompanyProfileTest.php` (5)

- shows the application name until the profile is saved, with the regional settings
- saves the profile with a logo, audits it and serves the logo publicly
- replaces and removes the logo without leaving files behind
- rejects an SVG or oversized logo and invalid details
- refuses to edit the profile without company.update

### `tests/Feature/Structure/DeletionProtectionTest.php` (5)

- refuses to delete a record that is still in use and explains why
- treats a branch used in an admin data restriction as in use
- counts references outside the admin's data scope too
- deletes a branch together with its locations once nothing else uses it
- keeps an unused job title deletable after its position moved to another title

### `tests/Feature/Structure/HierarchyRulesTest.php` (6)

- refuses to place a department under itself or under one of its descendants
- refuses a reporting line that would loop back to the position
- refuses a cost centre cycle
- accepts only one of the department's own positions as its head
- keeps a single headquarters branch
- shows a department with its sub-departments, teams and positions

### `tests/Feature/Structure/OrgChartTest.php` (5)

- renders the department tree with nested children, heads and counts
- renders the position reporting hierarchy for admins who may view positions
- falls back to the department view without positions.view, and needs departments.view
- makes a node whose parent is outside the admin's scope a root
- shows an empty state when there is nothing to chart

### `tests/Feature/Structure/StructureApiTest.php` (3)

- returns the company profile with the localisation settings in the requested language
- lists active branches with their active locations, paginated
- requires an employee token

### `tests/Feature/Structure/StructureCrudTest.php` (9)

- lists, creates and updates a record and audits both changes
- rejects a duplicate code and missing names
- refuses every action without the module permission
- deactivates and reactivates a record with an audit entry
- deletes an unused record and audits its last values
- sorts lists by whitelisted columns only
- filters lists by search text, status and parent record
- keeps decimal salary bands exact and requires the maximum to cover the minimum
- only offers and accepts active related records for new links

### `tests/Feature/Structure/StructureDataScopeTest.php` (7)

- shows a branch-restricted admin only their branches, departments, teams and positions
- answers 404 for records outside the admin's scope
- does not let a restricted admin link records to anything outside their scope
- lets only unrestricted admins add branches and departments
- shows a department-restricted admin their departments and the branches they belong to
- offers only in-scope branches and departments in the admin user restriction picker
- saves restrictions picked from real branches and shows their names

### `tests/Feature/Structure/StructureScreensTest.php` (2)

- renders every company structure screen in both directions
- shows the structure sidebar entries an HR Specialist may open
