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
