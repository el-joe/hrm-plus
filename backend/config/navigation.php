<?php

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
|
| Entries of the admin sidebar and the employee portal navigation, read by
| App\Support\Navigation\NavigationBuilder.
|
| An item is shown only when its route exists AND the signed-in user passes
| its "ability" (a "module.action" permission or a Gate ability; null means
| every user of the area). Hiding a link is never the protection: the route
| itself must enforce the same permission.
|
| Each item: key (label = app.nav.{key}), route, ability, icon (x-icon name),
| active (route-name patterns that mark the item as current).
| Each admin section: key (label = app.nav.sections.{key}), items.
|
| Later modules add their entries here (sections from DESIGN_REFERENCE.md:
| dashboard, structure, employees, recruitment, attendance, leave, payroll,
| performance, reports, permissions, settings).
|
*/

return [

    'admin' => [
        [
            'key' => 'main',
            'items' => [
                ['key' => 'dashboard', 'route' => 'admin.dashboard', 'ability' => null, 'icon' => 'home', 'active' => ['admin.dashboard']],
            ],
        ],
        [
            'key' => 'employees',
            'items' => [
                ['key' => 'employees', 'route' => 'admin.employees.index', 'ability' => 'employees.view', 'icon' => 'users', 'active' => ['admin.employees.*']],
                ['key' => 'employee_groups', 'route' => 'admin.employee-groups.index', 'ability' => 'employee_groups.view', 'icon' => 'layers', 'active' => ['admin.employee-groups.*']],
                ['key' => 'employee_custom_fields', 'route' => 'admin.employee-custom-fields.index', 'ability' => 'employees.manage', 'icon' => 'settings', 'active' => ['admin.employee-custom-fields.*']],
            ],
        ],
        [
            'key' => 'recruitment',
            'items' => [
                ['key' => 'vacancies', 'route' => 'admin.vacancies.index', 'ability' => 'recruitment.view', 'icon' => 'briefcase', 'active' => ['admin.vacancies.*', 'admin.applications.*']],
                ['key' => 'candidates', 'route' => 'admin.candidates.index', 'ability' => 'recruitment.view', 'icon' => 'user-plus', 'active' => ['admin.candidates.*']],
                ['key' => 'interviews', 'route' => 'admin.interviews.index', 'ability' => 'interviews.view', 'icon' => 'calendar', 'active' => ['admin.interviews.*']],
                ['key' => 'offers', 'route' => 'admin.offers.index', 'ability' => 'offers.view', 'icon' => 'mail', 'active' => ['admin.offers.*']],
                ['key' => 'recruitment_stages', 'route' => 'admin.recruitment-stages.index', 'ability' => 'recruitment.manage', 'icon' => 'layers', 'active' => ['admin.recruitment-stages.*']],
                ['key' => 'evaluation_forms', 'route' => 'admin.evaluation-forms.index', 'ability' => 'recruitment.manage', 'icon' => 'award', 'active' => ['admin.evaluation-forms.*']],
            ],
        ],
        [
            'key' => 'lifecycle',
            'items' => [
                ['key' => 'onboarding', 'route' => 'admin.onboarding.index', 'ability' => 'onboarding.view', 'icon' => 'log-in', 'active' => ['admin.onboarding.*']],
                ['key' => 'offboarding', 'route' => 'admin.offboarding.index', 'ability' => 'offboarding.view', 'icon' => 'log-out', 'active' => ['admin.offboarding.*']],
                ['key' => 'onboarding_templates', 'route' => 'admin.onboarding-templates.index', 'ability' => 'onboarding.manage', 'icon' => 'file-text', 'active' => ['admin.onboarding-templates.*']],
            ],
        ],
        [
            'key' => 'attendance',
            'items' => [
                ['key' => 'attendance_dashboard', 'route' => 'admin.attendance.dashboard', 'ability' => 'attendance.view', 'icon' => 'bar-chart', 'active' => ['admin.attendance.dashboard']],
                ['key' => 'attendance_daily', 'route' => 'admin.attendance.daily', 'ability' => 'attendance.view', 'icon' => 'clock', 'active' => ['admin.attendance.daily', 'admin.attendance.days.*']],
                ['key' => 'attendance_timesheet', 'route' => 'admin.attendance.timesheet', 'ability' => 'attendance.view', 'icon' => 'calendar', 'active' => ['admin.attendance.timesheet']],
                ['key' => 'attendance_calendar', 'route' => 'admin.attendance.calendar', 'ability' => 'attendance.view', 'icon' => 'layout-grid', 'active' => ['admin.attendance.calendar']],
                ['key' => 'attendance_bulk', 'route' => 'admin.attendance.bulk.create', 'ability' => 'attendance.create', 'icon' => 'check-circle', 'active' => ['admin.attendance.bulk.*']],
                ['key' => 'attendance_batches', 'route' => 'admin.attendance.batches.index', 'ability' => 'attendance.view', 'icon' => 'upload', 'active' => ['admin.attendance.batches.*', 'admin.attendance.import*']],
                ['key' => 'attendance_corrections', 'route' => 'admin.attendance.corrections.index', 'ability' => 'attendance.view', 'icon' => 'pencil', 'active' => ['admin.attendance.corrections.*']],
                ['key' => 'attendance_reviews', 'route' => 'admin.attendance.reviews.index', 'ability' => 'attendance.view', 'icon' => 'map-pin', 'active' => ['admin.attendance.reviews.*']],
                ['key' => 'attendance_events', 'route' => 'admin.attendance.events.index', 'ability' => 'attendance.view', 'icon' => 'inbox', 'active' => ['admin.attendance.events.*']],
                ['key' => 'attendance_reports', 'route' => 'admin.attendance.reports.index', 'ability' => 'viewAttendanceReports', 'icon' => 'file-text', 'active' => ['admin.attendance.reports.*']],
                ['key' => 'attendance_locks', 'route' => 'admin.attendance.locks.index', 'ability' => 'attendance.manage', 'icon' => 'lock', 'active' => ['admin.attendance.locks.*']],
            ],
        ],
        [
            'key' => 'attendance_capture',
            'items' => [
                ['key' => 'attendance_devices', 'route' => 'admin.attendance-devices.index', 'ability' => 'attendance_devices.view', 'icon' => 'fingerprint', 'active' => ['admin.attendance-devices.*']],
                ['key' => 'attendance_work_locations', 'route' => 'admin.attendance.work-locations.index', 'ability' => 'attendance.manage', 'icon' => 'map-pin', 'active' => ['admin.attendance.work-locations.*']],
                ['key' => 'face_enrolments', 'route' => 'admin.attendance.face.index', 'ability' => 'face_verification.view', 'icon' => 'camera', 'active' => ['admin.attendance.face.*']],
            ],
        ],
        [
            'key' => 'scheduling',
            'items' => [
                ['key' => 'schedule_calendar', 'route' => 'admin.schedule-calendar', 'ability' => 'schedules.view', 'icon' => 'calendar', 'active' => ['admin.schedule-calendar*']],
                ['key' => 'shifts', 'route' => 'admin.shifts.index', 'ability' => 'shifts.view', 'icon' => 'clock', 'active' => ['admin.shifts.*']],
                ['key' => 'work_schedules', 'route' => 'admin.work-schedules.index', 'ability' => 'schedules.view', 'icon' => 'layers', 'active' => ['admin.work-schedules.*']],
                ['key' => 'schedule_assignments', 'route' => 'admin.schedule-assignments.index', 'ability' => 'schedules.view', 'icon' => 'users', 'active' => ['admin.schedule-assignments.*']],
                ['key' => 'schedule_changes', 'route' => 'admin.schedule-changes.index', 'ability' => 'schedules.view', 'icon' => 'history', 'active' => ['admin.schedule-changes.*']],
                ['key' => 'holidays', 'route' => 'admin.holidays.index', 'ability' => 'holidays.view', 'icon' => 'calendar', 'active' => ['admin.holidays.*']],
                ['key' => 'attendance_policies', 'route' => 'admin.attendance-policies.index', 'ability' => 'attendance_policies.view', 'icon' => 'shield-check', 'active' => ['admin.attendance-policies.*']],
                ['key' => 'attendance_statuses', 'route' => 'admin.attendance-statuses.index', 'ability' => 'attendance.manage', 'icon' => 'layout-grid', 'active' => ['admin.attendance-statuses.*']],
            ],
        ],
        [
            'key' => 'structure',
            'items' => [
                ['key' => 'company', 'route' => 'admin.company.show', 'ability' => 'company.view', 'icon' => 'building', 'active' => ['admin.company.*']],
                ['key' => 'org_chart', 'route' => 'admin.org-chart', 'ability' => 'departments.view', 'icon' => 'sitemap', 'active' => ['admin.org-chart']],
                ['key' => 'branches', 'route' => 'admin.branches.index', 'ability' => 'branches.view', 'icon' => 'map-pin', 'active' => ['admin.branches.*']],
                ['key' => 'departments', 'route' => 'admin.departments.index', 'ability' => 'departments.view', 'icon' => 'layers', 'active' => ['admin.departments.*']],
                ['key' => 'teams', 'route' => 'admin.teams.index', 'ability' => 'teams.view', 'icon' => 'users', 'active' => ['admin.teams.*']],
                ['key' => 'positions', 'route' => 'admin.positions.index', 'ability' => 'positions.view', 'icon' => 'briefcase', 'active' => ['admin.positions.*']],
                ['key' => 'job_titles', 'route' => 'admin.job-titles.index', 'ability' => 'job_titles.view', 'icon' => 'id-card', 'active' => ['admin.job-titles.*']],
                ['key' => 'job_grades', 'route' => 'admin.job-grades.index', 'ability' => 'job_grades.view', 'icon' => 'award', 'active' => ['admin.job-grades.*']],
                ['key' => 'employment_types', 'route' => 'admin.employment-types.index', 'ability' => 'employment_types.view', 'icon' => 'file-text', 'active' => ['admin.employment-types.*']],
                ['key' => 'cost_centers', 'route' => 'admin.cost-centers.index', 'ability' => 'cost_centers.view', 'icon' => 'coins', 'active' => ['admin.cost-centers.*']],
            ],
        ],
        [
            'key' => 'permissions',
            'items' => [
                ['key' => 'admin_users', 'route' => 'admin.admin-users.index', 'ability' => 'admin_users.view', 'icon' => 'users', 'active' => ['admin.admin-users.*']],
                ['key' => 'roles', 'route' => 'admin.roles.index', 'ability' => 'roles.view', 'icon' => 'shield', 'active' => ['admin.roles.*']],
            ],
        ],
        [
            'key' => 'system',
            'items' => [
                ['key' => 'audit_logs', 'route' => 'admin.audit-logs.index', 'ability' => 'audit_logs.view', 'icon' => 'history', 'active' => ['admin.audit-logs.*']],
                ['key' => 'settings', 'route' => 'admin.settings.index', 'ability' => 'settings.view', 'icon' => 'settings', 'active' => ['admin.settings.*']],
                ['key' => 'ui_kit', 'route' => 'admin.ui-kit', 'ability' => 'viewUiKit', 'icon' => 'layout-grid', 'active' => ['admin.ui-kit']],
            ],
        ],
    ],

    'portal' => [
        ['key' => 'home', 'route' => 'portal.home', 'ability' => null, 'icon' => 'home', 'active' => ['portal.home']],
        ['key' => 'my_profile', 'route' => 'portal.profile', 'ability' => null, 'icon' => 'user', 'active' => ['portal.profile']],
        ['key' => 'my_attendance', 'route' => 'portal.attendance', 'ability' => null, 'icon' => 'clock', 'active' => ['portal.attendance*']],
        ['key' => 'my_onboarding', 'route' => 'portal.onboarding', 'ability' => 'viewOwnOnboarding', 'icon' => 'check-circle', 'active' => ['portal.onboarding']],
        ['key' => 'my_interviews', 'route' => 'portal.interviews.index', 'ability' => 'viewOwnInterviews', 'icon' => 'calendar', 'active' => ['portal.interviews.*']],
    ],

];
