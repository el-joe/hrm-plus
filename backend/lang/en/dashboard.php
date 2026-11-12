<?php

return [
    'widgets' => [
        'total_employees' => ['title' => 'Total employees'],
        'active_employees' => ['title' => 'Active employees'],
        'inactive_employees' => ['title' => 'Inactive employees'],
        'employees_on_leave' => ['title' => 'Employees on leave'],
        'absent_today' => ['title' => 'Absent today'],
        'present_now' => ['title' => 'Currently present'],
        'late_arrivals' => ['title' => 'Late arrivals'],
        'pending_leave_requests' => ['title' => 'Pending leave requests'],
        'pending_hr_requests' => ['title' => 'Pending HR requests'],
        'new_candidates' => ['title' => 'New candidates'],
        'open_vacancies' => ['title' => 'Open vacancies'],
        'contract_expirations' => ['title' => 'Upcoming contract expirations'],
        'upcoming_birthdays' => ['title' => 'Upcoming birthdays'],
        'upcoming_anniversaries' => ['title' => 'Upcoming work anniversaries'],
        'payroll_summary' => ['title' => 'Payroll summary'],
        'monthly_salary_expenses' => ['title' => 'Monthly salary expenses'],
        'branch_comparison' => ['title' => 'Branch comparison'],
        'department_comparison' => ['title' => 'Department comparison'],
        'recruitment_statistics' => ['title' => 'Recruitment statistics'],
        'attendance_trends' => ['title' => 'Attendance trends'],
        'employee_turnover' => ['title' => 'Employee turnover'],
        'employee_distribution' => ['title' => 'Employee distribution'],
        'payroll_pending' => ['title' => 'Payroll runs awaiting approval'],
        'active_admins' => ['title' => 'Active admin users'],
        'roles' => ['title' => 'Roles'],
        'audit_today' => ['title' => 'Audit events today'],
        'failed_logins' => ['title' => 'Failed sign-ins'],
    ],

    'actions' => [
        'refresh' => 'Refresh',
        'customise' => 'Customise',
        'details' => 'Details',
    ],

    'hints' => [
        'today' => 'Today',
        'as_of_today' => 'As of today — the date range does not apply.',
        'of_scheduled' => 'of :count scheduled today',
        'came_in_today' => ':count came in today',
        'today_count' => ':count today',
        'overdue' => ':count overdue',
        'in_period' => 'In the selected period',
        'openings_left' => ':count openings left',
        'next_days' => 'Next :days days',
        'employees_paid' => ':count employees paid',
    ],

    'labels' => [
        'hires_in_period' => 'Hired in period',
        'turnover_12_months' => 'Turnover (12 months)',
        'years_of_service' => ':count year(s)',
        'today' => 'Today',
    ],

    'empty' => [
        'none' => 'Nothing to show.',
        'no_data' => 'No data for these filters.',
        'no_finalised_payroll' => 'No finalised payroll yet.',
        'no_widgets' => 'All widgets are hidden',
        'no_widgets_help' => 'Choose the widgets to show on your dashboard.',
        'no_permitted_widgets' => 'Your permissions do not include any dashboard widget.',
    ],

    'chart' => [
        'show_data' => 'Show data table',
        'category' => 'Category',
    ],

    'customise' => [
        'title' => 'Customise dashboard',
        'subtitle' => 'Choose which widgets you see and in which order. Only widgets your permissions allow are listed.',
        'widgets' => 'Widgets',
        'help' => 'Untick a widget to hide it; change the position numbers (or use the arrows) to reorder.',
        'position' => 'Position',
        'move_up' => 'Move up',
        'move_down' => 'Move down',
        'reset' => 'Restore default layout',
        'saved' => 'Your dashboard layout was saved.',
        'reset_done' => 'The default dashboard layout was restored.',
    ],
];
