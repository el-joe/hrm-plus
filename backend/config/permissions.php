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
        // Leave (Phase 6). leaves.*: leave requests — view the queue, calendar and
        // history; create: file a request on an employee's behalf; approve/reject:
        // the leave approval workflows (within the data scope); export: leave
        // reports; manage: record leave as approved, cancel approved leave directly.
        // leave_types.* / leave_policies.*: configuration. leave_balances.view: balances
        // and ledger; update: manual adjustments and encashments; export: balance export.
        'leaves' => [...$crud, 'approve', 'reject', 'export', 'manage'],
        'leave_types' => $crud,
        'leave_policies' => $crud,
        'leave_balances' => ['view', 'update', 'export'],
        // Payroll components (Phase 7a). salary_components.* / salary_structures.*:
        // the component catalogue and the structures (salary_structures.update also
        // assigns them); allowances.* / deductions.*: entries (create includes bulk and
        // import, delete = stop / cancel an entry), approve/reject: their approval
        // workflows; statutory_rules.*: tax and social insurance tables.
        // loans.view: loans and advances in scope; create: request on an employee's
        // behalf; update: early repayment, schedule change requests, final settlement;
        // delete: cancel a pending request; approve/reject: loan and schedule-change
        // workflows. Amounts anywhere need employee_financials.view on the profile tab.
        // Payroll runs (Phase 7b). payroll.view: periods, runs, employee breakdowns and
        // corrections in scope; create: new runs (wizard), corrections, final settlements;
        // update: calculate / recalculate, population changes, submit for approval;
        // delete: cancel a draft run; approve/reject: the "payroll_run" workflow (two
        // levels, distinct approvers, never the submitter); export: register and bank
        // files; manage: payroll periods. payroll_finalisation.manage: finalise (lock,
        // issue payslips, deduct loans) and reverse a finalised run — kept apart from
        // preparation on purpose. payslips.view: employees' payslips (every view logged);
        // export: bulk PDF / payslip lists; manage: payment status (paid / partially paid).
        'payroll' => [...$crud, 'approve', 'reject', 'export', 'manage'],
        'payroll_finalisation' => ['manage'],
        'payslips' => ['view', 'export', 'manage'],
        'salary_components' => $crud,
        'salary_structures' => $crud,
        'allowances' => [...$crud, 'approve', 'reject', 'export'],
        'deductions' => [...$crud, 'approve', 'reject', 'export'],
        'statutory_rules' => $crud,
        'loans' => [...$crud, 'approve', 'reject', 'export'],
        // Phase 8a. performance.view: reviews of the admin's own reporting line (direct and
        // indirect reports, or reviews they are the reviewer of) inside the data scope;
        // performance.manage: every review in scope plus cycles, rating scales and
        // competencies; create/update: goals and the manager assessment; approve/reject: the
        // "performance_review" calibration workflow; export: review lists.
        'performance' => [...$crud, 'approve', 'reject', 'export', 'manage'],
        // training.view: catalogue, sessions, enrolments, certificates, skills, budgets in scope;
        // create: nominate employees (enrolled at once with training.manage, otherwise sent for
        // approval); update: attendance, completion, certificates, skills; approve/reject: the
        // "training_enrolment" workflow; manage: providers, programmes, courses, skills, budgets.
        'training' => [...$crud, 'approve', 'reject', 'export', 'manage'],
        // assets.view: catalogue and custody in scope (asset branch); create: new assets;
        // update: assign, return, maintenance, lost / damaged; delete: retire; manage: categories.
        'assets' => [...$crud, 'export', 'manage'],
        // Phase 8b. hr_requests.view: requests in scope (the request queue); create: file a request on an
        // employee's behalf; update: handle (assign, reply, internal notes, status, issue letters);
        // delete: cancel a request; approve/reject: the "hr_request" approval workflow of categories that need
        // approval, and applying / rejecting profile change requests (the employee fields also need
        // employees.update, employee_sensitive.update for identity numbers, employee_financials.update for bank
        // details); export: request lists; manage: categories, letter templates, every request in scope
        // (not only assigned ones) and reassignment.
        'hr_requests' => [...$crud, 'approve', 'reject', 'export', 'manage'],
        // announcements.view: list and read statistics; create/update/delete: drafts, publish, pin, archive
        // (restricted admins only target their own data scope); export: read / acknowledgement report.
        'announcements' => [...$crud, 'export'],
        // Restricted (Phase 8a). disciplinary.view: case files, investigations, evidence and
        // actions in scope — every view is written to the sensitive-data access log; without
        // it an admin holding disciplinary.create can only report incidents and follow the
        // status of the cases they reported. update: investigate, propose actions, close;
        // approve/reject: the "disciplinary_action" workflow; manage: violation types.
        'disciplinary' => [...$crud, 'approve', 'reject', 'export', 'manage'],
        // Phase 8c reports centre and dashboard aggregates. reports.view: the reports centre and the
        // dashboard widgets of every module (aggregates, inside the data scope); salary columns and payroll
        // reports also need employee_financials.view, disciplinary ones disciplinary.view (both logged).
        // reports.export: Excel / CSV / PDF exports and queued exports (My exports).
        'reports' => ['view', 'export'],
        // Phase 8b. Everyone has their own notification centre and preferences (no permission).
        // notifications.view: notification templates (read) and the delivery log; manage: edit / reset
        // templates and retry failed deliveries. Mail / push configuration is settings.manage.
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
                // Phase 8c (project lead): HR Managers apply bank-detail change requests.
                'employee_financials.update',
                'employee_documents.*', 'employee_groups.*',
                'recruitment.*', 'interviews.*', 'offers.*', 'onboarding.*', 'offboarding.*', 'attendance.*', 'attendance_devices.view',
                'face_verification.*', 'shifts.*', 'schedules.*', 'holidays.*', 'attendance_policies.*', 'leaves.*', 'leave_types.*', 'leave_policies.*', 'leave_balances.*', 'payroll.view', 'payroll.approve', 'payroll.reject', 'payslips.view', 'loans.view', 'loans.approve', 'loans.reject',
                'salary_components.view', 'salary_structures.view', 'allowances.view', 'allowances.approve', 'allowances.reject',
                'deductions.view', 'deductions.approve', 'deductions.reject', 'statutory_rules.view',
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
                'leaves.view', 'leaves.create', 'leaves.update', 'leave_types.view', 'leave_policies.view', 'leave_balances.view',
                'training.view', 'training.create', 'training.update',
                'assets.view', 'assets.create', 'assets.update',
                'hr_requests.view', 'hr_requests.create', 'hr_requests.update',
                'announcements.view', 'announcements.create', 'announcements.update',
                'disciplinary.create',
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
                'payroll.view', 'payroll.create', 'payroll.update', 'payroll.delete', 'payroll.export', 'payroll.manage', 'payslips.view', 'payslips.export',
                'loans.view', 'loans.create', 'loans.update', 'loans.delete', 'loans.export',
                'salary_components.*', 'salary_structures.*', 'statutory_rules.*',
                'allowances.view', 'allowances.create', 'allowances.update', 'allowances.delete', 'allowances.export',
                'deductions.view', 'deductions.create', 'deductions.update', 'deductions.delete', 'deductions.export',
                'attendance.view', 'holidays.view', 'attendance_policies.view', 'leaves.view', 'leave_types.view', 'leave_policies.view', 'leave_balances.view', 'leave_balances.export', 'reports.view', 'reports.export', 'notifications.view',
            ],
        ],
        'Attendance Officer' => [
            'description' => 'Manages attendance and shifts; no access to payroll or financial data.',
            'permissions' => [
                'dashboard.view', 'branches.view', 'departments.view', 'teams.view',
                'employees.view', 'attendance.*', 'attendance_devices.view', 'attendance_devices.update', 'attendance_devices.manage',
                'face_verification.view', 'shifts.*',
                'schedules.*', 'holidays.*', 'attendance_policies.view',
                'leaves.view', 'leave_types.view', 'reports.view', 'reports.export', 'notifications.view',
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
                'leaves.view', 'leaves.approve', 'leaves.reject', 'leave_types.view', 'leave_balances.view',
                'hr_requests.view', 'hr_requests.approve', 'hr_requests.reject',
                'performance.view', 'performance.create', 'performance.update', 'performance.approve', 'performance.reject',
                'training.view', 'training.create', 'assets.view', 'announcements.view', 'announcements.create',
                'disciplinary.create', 'interviews.view',
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
                'leaves.view', 'leaves.approve', 'leaves.reject', 'leave_types.view', 'leave_balances.view',
                'hr_requests.view', 'hr_requests.approve', 'hr_requests.reject',
                'performance.view', 'performance.create', 'performance.update', 'performance.approve', 'performance.reject',
                'training.view', 'training.create', 'announcements.view', 'announcements.create', 'interviews.view',
                'disciplinary.create',
                'reports.view', 'notifications.view',
            ],
        ],
        'Direct Manager' => [
            'description' => 'Approves requests and reviews performance for direct reports.',
            'permissions' => [
                'dashboard.view', 'departments.view', 'teams.view', 'employees.view',
                'attendance.view', 'attendance.approve', 'attendance.reject',
                'leaves.view', 'leaves.approve', 'leaves.reject', 'leave_types.view', 'leave_balances.view',
                'hr_requests.view', 'hr_requests.approve', 'hr_requests.reject',
                'performance.view', 'performance.create', 'performance.update',
                'disciplinary.create',
                'interviews.view', 'notifications.view',
            ],
        ],
        'Finance Officer' => [
            'description' => 'Reviews and approves payroll and loans; read-only on financial data.',
            'permissions' => [
                'dashboard.view', 'company.view', 'branches.view', 'departments.view', 'job_grades.view', 'cost_centers.view',
                'employees.view', 'employee_financials.view',
                'payroll.view', 'payroll.approve', 'payroll.reject', 'payroll.export', 'payroll_finalisation.manage',
                'payslips.view', 'payslips.export', 'payslips.manage',
                'loans.view', 'loans.approve', 'loans.reject', 'loans.export', 'leave_balances.view',
                'salary_components.view', 'salary_structures.view', 'statutory_rules.view',
                'allowances.view', 'allowances.approve', 'allowances.reject', 'allowances.export',
                'deductions.view', 'deductions.approve', 'deductions.reject', 'deductions.export',
                'reports.view', 'reports.export', 'notifications.view',
            ],
        ],
        'Reports Viewer' => [
            'description' => 'Read-only access to the dashboard and reports (no salary or disciplinary columns).',
            'permissions' => ['dashboard.view', 'reports.view', 'reports.export'],
        ],
    ],

];
