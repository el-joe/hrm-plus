<?php

/*
|--------------------------------------------------------------------------
| Permission catalogue — the single source of truth
|--------------------------------------------------------------------------
|
| Every admin permission is named "module.action". Nothing else in the code
| base may invent a permission name: add the module/action here, run
| `php artisan db:seed --class=RolesAndPermissionsSeeder`, and reference it
| through middleware (`can:module.action`) or a policy.
|
| Read through App\Services\Authorization\PermissionCatalogue.
|
*/

$crud = ['view', 'create', 'update', 'delete'];

return [

    'guard' => 'admin',

    'super_admin_role' => 'Super Admin',

    /*
     | The full set of actions a module may use, in display order for the matrix.
     */
    'actions' => ['view', 'create', 'update', 'delete', 'approve', 'reject', 'export', 'manage'],

    /*
     | module => actions it supports.
     */
    'modules' => [
        'dashboard' => ['view'],
        // Company structure (Phase 3a). "company" is the company profile; the org
        // chart is shown to anyone who may view departments.
        'company' => ['view', 'update'],
        'branches' => $crud,
        'departments' => $crud,
        'teams' => $crud,
        'positions' => $crud,
        'job_titles' => $crud,
        'job_grades' => $crud,
        'employment_types' => $crud,
        'cost_centers' => $crud,
        // Employees (Phase 3b). employees.manage: terminate / rehire, login
        // accounts, custom field definitions. employee_sensitive: national id
        // and passport numbers. employee_financials: salary, bank accounts and
        // job-grade salary bands. Every view of either is logged.
        'employees' => [...$crud, 'export', 'manage'],
        'employee_sensitive' => ['view', 'update'],
        'employee_financials' => ['view', 'update'],
        'employee_documents' => $crud,
        'employee_groups' => $crud,
        // Recruitment (Phase 4). recruitment.*: vacancies, candidates, pipeline;
        // recruitment.manage: pipeline stages and evaluation forms.
        // interviews.view: the interviews the admin is an interviewer on;
        // interviews.manage: every interview in scope. offers.approve/reject:
        // the "job_offer" approval workflow. Salaries anywhere in recruitment
        // need employee_financials.view.
        'recruitment' => [...$crud, 'approve', 'reject', 'export', 'manage'],
        'interviews' => [...$crud, 'manage'],
        'offers' => [...$crud, 'approve', 'reject'],
        'onboarding' => [...$crud, 'manage'],
        'offboarding' => [...$crud, 'approve', 'reject', 'manage'],
        // Attendance (Phase 5a). attendance.view: daily records, timesheets, raw
        // events; create: manual / bulk entry and imports; update: corrections
        // and voiding punches; approve/reject: corrections, manual batches and
        // overtime; manage: period locks, day statuses, reprocessing.
        // shifts.*: shift definitions. schedules.*: work schedules, assignments,
        // calendar overrides; schedules.approve/reject: shift changes and swaps.
        // attendance_policies.*: policy rules and their assignment.
        'attendance' => [...$crud, 'approve', 'reject', 'export', 'manage'],
        // Phase 5b. attendance_devices.view: devices, health, sync logs, mappings;
        // create/update/delete: device configuration; manage: sync now, historical
        // and file imports, push tokens, device-user mappings. The raw event review
        // (map / void) and the mobile review queue use attendance.update /
        // attendance.approve / attendance.reject. face_verification.view: the
        // enrolment register (every view logged); delete: erase an employee's
        // biometric data.
        'attendance_devices' => [...$crud, 'manage'],
        'face_verification' => ['view', 'delete'],
        'shifts' => [...$crud, 'approve', 'reject', 'manage'],
        'schedules' => [...$crud, 'approve', 'reject'],
        'holidays' => $crud,
        'attendance_policies' => $crud,
        'leaves' => [...$crud, 'approve', 'reject', 'export', 'manage'],
        'payroll' => [...$crud, 'approve', 'reject', 'export', 'manage'],
        'loans' => [...$crud, 'approve', 'reject', 'export'],
        'performance' => [...$crud, 'approve', 'reject', 'export', 'manage'],
        'training' => [...$crud, 'export', 'manage'],
        'assets' => [...$crud, 'export', 'manage'],
        'hr_requests' => [...$crud, 'approve', 'reject', 'manage'],
        'announcements' => $crud,
        'disciplinary' => [...$crud, 'approve', 'reject'],
        'reports' => ['view', 'export'],
        'notifications' => ['view', 'manage'],
        'approvals' => ['view', 'manage'],
        'settings' => ['view', 'manage'],
        'admin_users' => $crud,
        'roles' => $crud,
        'audit_logs' => ['view', 'export'],
    ],

    /*
     | Default (system) roles from spec section 2. A permission entry is either
     | an exact name, "module.*" for every action of a module, or "*" for all.
     */
    'roles' => [
        'Super Admin' => [
            'description' => 'Unrestricted access to every module and setting.',
            'permissions' => ['*'],
        ],
        'HR Manager' => [
            'description' => 'Runs all HR operations; no access to roles, admin accounts or payroll preparation.',
            'permissions' => [
                'dashboard.*', 'company.*', 'branches.*', 'departments.*', 'teams.*', 'positions.*', 'job_titles.*',
                'job_grades.*', 'employment_types.*', 'cost_centers.*', 'employees.*', 'employee_sensitive.*', 'employee_financials.view',
                'employee_documents.*', 'employee_groups.*',
                'recruitment.*', 'interviews.*', 'offers.*', 'onboarding.*', 'offboarding.*', 'attendance.*', 'attendance_devices.view',
                'face_verification.*', 'shifts.*', 'schedules.*', 'holidays.*', 'attendance_policies.*', 'leaves.*', 'payroll.view', 'loans.view', 'loans.approve', 'loans.reject',
                'performance.*', 'training.*', 'assets.*', 'hr_requests.*', 'announcements.*', 'disciplinary.*',
                'reports.*', 'notifications.*', 'approvals.*', 'settings.view', 'admin_users.view', 'audit_logs.view',
            ],
        ],
        'HR Specialist' => [
            'description' => 'Day-to-day employee administration without approvals or financial data.',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view', 'departments.view',
                'teams.view', 'teams.create', 'teams.update', 'positions.view', 'positions.create', 'positions.update',
                'job_titles.view', 'job_grades.view', 'employment_types.view', 'cost_centers.view',
                'employees.view', 'employees.create', 'employees.update', 'employees.export',
                'employee_sensitive.view', 'employee_groups.view',
                'employee_documents.view', 'employee_documents.create', 'employee_documents.update',
                'interviews.view',
                'onboarding.view', 'onboarding.create', 'onboarding.update',
                'offboarding.view', 'offboarding.create', 'offboarding.update',
                'attendance.view', 'shifts.view', 'schedules.view', 'holidays.view',
                'leaves.view', 'leaves.create', 'leaves.update',
                'training.view', 'training.create', 'training.update',
                'assets.view', 'assets.create', 'assets.update',
                'hr_requests.view', 'hr_requests.create', 'hr_requests.update',
                'announcements.view', 'announcements.create', 'announcements.update',
                'disciplinary.view', 'disciplinary.create',
                'reports.view', 'notifications.view',
            ],
        ],
        'Recruiter' => [
            'description' => 'Manages vacancies, candidates and interviews; cannot touch payroll.',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view', 'departments.view', 'positions.view',
                'job_titles.view', 'job_grades.view', 'employment_types.view', 'recruitment.*',
                'interviews.*', 'offers.view', 'offers.create', 'offers.update', 'offers.delete',
                'onboarding.view', 'onboarding.create', 'onboarding.update',
                'reports.view', 'notifications.view',
            ],
        ],
        'Payroll Accountant' => [
            'description' => 'Prepares payroll and settlements; cannot approve payroll or change attendance devices.',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view', 'departments.view', 'job_grades.view',
                'employment_types.view', 'cost_centers.view',
                'employees.view', 'employee_sensitive.view', 'employee_financials.view', 'employee_financials.update',
                'payroll.view', 'payroll.create', 'payroll.update', 'payroll.delete', 'payroll.export', 'payroll.manage',
                'loans.view', 'loans.create', 'loans.update', 'loans.export',
                'attendance.view', 'holidays.view', 'attendance_policies.view', 'leaves.view', 'reports.view', 'reports.export', 'notifications.view',
            ],
        ],
        'Attendance Officer' => [
            'description' => 'Manages attendance and shifts; no access to payroll or financial data.',
            'permissions' => [
                'dashboard.view', 'branches.view', 'departments.view', 'teams.view',
                'employees.view', 'attendance.*', 'attendance_devices.view', 'attendance_devices.update', 'attendance_devices.manage',
                'face_verification.view', 'shifts.*',
                'schedules.*', 'holidays.*', 'attendance_policies.view',
                'leaves.view', 'reports.view', 'reports.export', 'notifications.view',
            ],
        ],
        'Branch Manager' => [
            'description' => 'Views and manages employees of the assigned branches only (set data scopes on the account).',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view', 'departments.view',
                'teams.view', 'teams.create', 'teams.update', 'positions.view',
                'employees.view', 'employees.update', 'employees.export',
                'employee_documents.view',
                'attendance.view', 'attendance.create', 'attendance.update', 'attendance.approve', 'attendance.reject', 'attendance.export',
                'attendance_devices.view', 'shifts.view', 'shifts.create', 'shifts.update', 'shifts.approve', 'shifts.reject',
                'schedules.view', 'schedules.create', 'schedules.update', 'schedules.approve', 'schedules.reject', 'holidays.view',
                'leaves.view', 'leaves.approve', 'leaves.reject',
                'hr_requests.view', 'hr_requests.approve', 'hr_requests.reject',
                'performance.view', 'performance.create', 'performance.update', 'performance.approve', 'performance.reject',
                'training.view', 'assets.view', 'announcements.view', 'announcements.create',
                'disciplinary.view', 'disciplinary.create', 'interviews.view',
                'reports.view', 'reports.export', 'notifications.view',
            ],
        ],
        'Department Manager' => [
            'description' => 'Supervises employees of the assigned departments only (set data scopes on the account).',
            'permissions' => [
                'dashboard.view', 'departments.view', 'teams.view', 'teams.create', 'teams.update', 'positions.view',
                'employees.view',
                'attendance.view', 'attendance.approve', 'attendance.reject',
                'shifts.view', 'shifts.update', 'schedules.view', 'schedules.approve', 'schedules.reject', 'holidays.view',
                'leaves.view', 'leaves.approve', 'leaves.reject',
                'hr_requests.view', 'hr_requests.approve', 'hr_requests.reject',
                'performance.view', 'performance.create', 'performance.update', 'performance.approve', 'performance.reject',
                'training.view', 'announcements.view', 'announcements.create', 'interviews.view',
                'reports.view', 'notifications.view',
            ],
        ],
        'Direct Manager' => [
            'description' => 'Approves requests and reviews performance for direct reports.',
            'permissions' => [
                'dashboard.view', 'departments.view', 'teams.view', 'employees.view',
                'attendance.view', 'attendance.approve', 'attendance.reject',
                'leaves.view', 'leaves.approve', 'leaves.reject',
                'hr_requests.view', 'hr_requests.approve', 'hr_requests.reject',
                'performance.view', 'performance.create', 'performance.update',
                'interviews.view', 'notifications.view',
            ],
        ],
        'Finance Officer' => [
            'description' => 'Reviews and approves payroll and loans; read-only on financial data.',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view', 'departments.view', 'job_grades.view', 'cost_centers.view',
                'employees.view', 'employee_financials.view',
                'payroll.view', 'payroll.approve', 'payroll.reject', 'payroll.export',
                'loans.view', 'loans.approve', 'loans.reject', 'loans.export',
                'reports.view', 'reports.export', 'notifications.view',
            ],
        ],
        'Reports Viewer' => [
            'description' => 'Read-only access to the dashboard and reports.',
            'permissions' => ['dashboard.view', 'reports.view', 'reports.export'],
        ],
    ],

];
