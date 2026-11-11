<?php

/*
| Attendance dashboard, calendar and reports (Phase 5b).
*/

return [

    'categories' => [
        'present' => 'Present',
        'late' => 'Late',
        'absent' => 'Absent',
        'on_leave' => 'On leave',
        'not_in' => 'Not yet in',
    ],

    'dashboard' => [
        'title' => 'Attendance dashboard',
        'subtitle' => 'Who is in today, by branch and department, from live data.',
        'expected' => 'Scheduled',
        'rate' => ':rate% attending',
        'by_branch' => 'By branch',
        'by_department' => 'By department',
        'breakdown' => 'Breakdown',
        'nobody_scheduled' => 'Nobody is scheduled to work on this date.',
        'latest_punches' => 'Latest punches',
        'latest_help' => 'Last 24 hours, every source.',
        'no_punches' => 'No punches in the last 24 hours.',
        'pending' => 'Waiting for action',
        'pending_items' => [
            'reviews' => 'Mobile attempts to review',
            'corrections' => 'Correction requests',
            'batches' => 'Manual batches awaiting approval',
            'overtime' => 'Overtime awaiting approval',
            'unmatched' => 'Unmatched device events',
        ],
        'devices' => 'Device health',
        'not_in_list' => 'Not yet in',
        'starts' => 'Shift :time',
    ],

    'calendar' => [
        'title' => 'Attendance calendar',
        'subtitle' => 'A month per employee, or a department / branch at a glance.',
        'month' => 'Month',
        'previous' => 'Previous',
        'next' => 'Next',
        'team' => 'Team month',
        'pick' => 'Choose an employee, a department or a branch',
        'pick_help' => 'The calendar shows processed days next to the expected schedule.',
        'short' => ['present' => 'P', 'late' => 'L', 'absent' => 'A', 'on_leave' => 'V', 'incomplete' => 'I', 'holiday' => 'H', 'rest_day' => 'R'],
        'legend' => [
            'present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'on_leave' => 'On leave', 'incomplete' => 'Incomplete',
            'holiday' => 'Holiday', 'scheduled' => 'Scheduled, not processed', 'rest' => 'Rest day',
        ],
    ],

    'index' => [
        'title' => 'Attendance reports',
        'subtitle' => 'Filter, review and export (Excel / CSV). Only data within your scope is included.',
    ],

    'reports' => [
        'daily' => ['title' => 'Daily attendance', 'description' => 'Every processed day with times, minutes, status and source.'],
        'monthly_summary' => ['title' => 'Monthly summary', 'description' => 'Per employee: days by status and total minutes for the period.'],
        'lateness' => ['title' => 'Lateness', 'description' => 'Days with late arrival, against the scheduled start.'],
        'absence' => ['title' => 'Absence', 'description' => 'Scheduled days without attendance.'],
        'overtime' => ['title' => 'Overtime', 'description' => 'Overtime minutes, approval state and the policy multiplier for payroll.'],
        'manual_entries' => ['title' => 'Manual entries', 'description' => 'Attendance entered by hand or imported, with who recorded it.'],
        'device_sync' => ['title' => 'Device synchronisation', 'description' => 'Every device sync run with its counters and errors.'],
    ],

    'columns' => [
        'manual' => 'Manual',
        'scheduled_days' => 'Scheduled days',
        'present_days' => 'Present',
        'late_days' => 'Late',
        'absent_days' => 'Absent',
        'leave_days' => 'On leave',
        'incomplete_days' => 'Incomplete',
        'manual_days' => 'Manual days',
        'scheduled_start' => 'Scheduled start',
        'flags' => 'Notes',
        'day_type' => 'Day type',
        'overtime_status' => 'Overtime status',
        'rate' => 'Multiplier',
        'recorded_by' => 'Recorded by',
        'recorded_at' => 'Recorded at',
        'note' => 'Note',
        'device' => 'Device',
        'serial' => 'Serial',
        'sync_type' => 'Type',
        'started_at' => 'Started',
        'fetched' => 'Read',
        'created' => 'New',
        'duplicates' => 'Duplicates',
        'unmatched' => 'Unmatched',
        'errors' => 'Errors',
        'simulated' => 'Simulated',
        'message' => 'Message',
    ],

    'day_types' => ['regular' => 'Working day', 'rest_day' => 'Rest day', 'holiday' => 'Holiday'],

    'export_xlsx' => 'Excel',
    'export_csv' => 'CSV',
    'empty' => 'No rows for these filters',
    'empty_help' => 'Change the period or the filters.',
    'range_too_long' => 'A report may cover at most :days days.',

];
