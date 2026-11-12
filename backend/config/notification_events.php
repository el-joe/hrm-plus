<?php

/*
|--------------------------------------------------------------------------
| Notification events — the registry of the notification centre (Phase 8b)
|--------------------------------------------------------------------------
|
| Every notification the system sends to a user account is one of these
| events. App\Notifications\CentreNotification (the base of every notification
| class) resolves the event's text from the editable templates
| (notification_templates: per event, channel and language) and its channels
| from the recipient's preferences (notification_preferences).
|
| Each event:
|   module        grouping on the templates / preferences screens (label notifications.modules.{module})
|   audience      employee | admin | both — who can receive it (filters the preference screens)
|   title/message lang keys of the DEFAULT texts (Laravel ":placeholder" style). They are copied
|                 into notification_templates as "{{placeholder}}" by the NotificationDefaultsSeeder and
|                 "Reset to default" restores them.
|   placeholders  the ONLY names a template of the event may use ({{name}}), besides the common ones
|                 (config notification_events.common). Anything else is refused on save and renders empty.
|   mandatory     true: the user cannot switch it off (any channel).
|   off_by_default channels a user receives only after opting in.
|
| Label of an event: notifications.events.{key}. Add a new event here, its lang texts, and a class extending
| CentreNotification whose event() returns the key.
|
*/

return [

    // Placeholders every template may use.
    'common' => ['recipient', 'app_name'],

    // Channels in display order. "database" is the in-app notification centre.
    'channels' => ['database', 'mail', 'push'],

    'modules' => [
        'hr_requests', 'announcements', 'leave', 'attendance', 'payroll', 'performance', 'training', 'assets',
        'disciplinary', 'employees', 'lifecycle', 'recruitment', 'approvals',
    ],

    'events' => [
        // Leave (Phase 6).
        'leave_submitted' => ['module' => 'leave', 'audience' => 'employee', 'title' => 'leave.notifications.submitted_title', 'message' => 'leave.notifications.submitted_message', 'placeholders' => ['employee', 'type', 'period', 'days', 'reason']],
        'leave_awaiting_approval' => ['module' => 'leave', 'audience' => 'admin', 'title' => 'leave.notifications.awaiting_approval_title', 'message' => 'leave.notifications.awaiting_approval_message', 'placeholders' => ['employee', 'type', 'period', 'days', 'reason']],
        'leave_approved' => ['module' => 'leave', 'audience' => 'both', 'title' => 'leave.notifications.approved_title', 'message' => 'leave.notifications.approved_message', 'placeholders' => ['employee', 'type', 'period', 'days', 'reason']],
        'leave_rejected' => ['module' => 'leave', 'audience' => 'both', 'title' => 'leave.notifications.rejected_title', 'message' => 'leave.notifications.rejected_message', 'placeholders' => ['employee', 'type', 'period', 'days', 'reason']],
        'leave_cancelled' => ['module' => 'leave', 'audience' => 'both', 'title' => 'leave.notifications.cancelled_title', 'message' => 'leave.notifications.cancelled_message', 'placeholders' => ['employee', 'type', 'period', 'days', 'reason']],
        'leave_cancellation_approved' => ['module' => 'leave', 'audience' => 'both', 'title' => 'leave.notifications.cancellation_approved_title', 'message' => 'leave.notifications.cancellation_approved_message', 'placeholders' => ['employee', 'type', 'period', 'days', 'reason']],
        'leave_cancellation_rejected' => ['module' => 'leave', 'audience' => 'both', 'title' => 'leave.notifications.cancellation_rejected_title', 'message' => 'leave.notifications.cancellation_rejected_message', 'placeholders' => ['employee', 'type', 'period', 'days', 'reason']],

        // Attendance automation (Phase 8b).
        'attendance_missing_checkout' => ['module' => 'attendance', 'audience' => 'employee', 'title' => 'notifications.automation.missing_checkout_title', 'message' => 'notifications.automation.missing_checkout_message', 'placeholders' => ['employee', 'date', 'time']],
        'attendance_late_arrival' => ['module' => 'attendance', 'audience' => 'employee', 'title' => 'notifications.automation.late_arrival_title', 'message' => 'notifications.automation.late_arrival_message', 'placeholders' => ['employee', 'date', 'minutes', 'time'], 'off_by_default' => ['mail']],
        'attendance_absent' => ['module' => 'attendance', 'audience' => 'employee', 'title' => 'notifications.automation.absent_title', 'message' => 'notifications.automation.absent_message', 'placeholders' => ['employee', 'date']],
        'attendance_absent_team' => ['module' => 'attendance', 'audience' => 'admin', 'title' => 'notifications.automation.absent_team_title', 'message' => 'notifications.automation.absent_team_message', 'placeholders' => ['employee', 'date'], 'off_by_default' => ['mail']],

        // Loans and payroll (Phase 7).
        'loan_submitted' => ['module' => 'payroll', 'audience' => 'both', 'title' => 'payroll.loans.notifications.submitted_title', 'message' => 'payroll.loans.notifications.submitted_message', 'placeholders' => ['type', 'employee', 'reference', 'amount', 'reason']],
        'loan_awaiting_approval' => ['module' => 'payroll', 'audience' => 'admin', 'title' => 'payroll.loans.notifications.awaiting_approval_title', 'message' => 'payroll.loans.notifications.awaiting_approval_message', 'placeholders' => ['type', 'employee', 'reference', 'amount', 'reason']],
        'loan_approved' => ['module' => 'payroll', 'audience' => 'both', 'title' => 'payroll.loans.notifications.approved_title', 'message' => 'payroll.loans.notifications.approved_message', 'placeholders' => ['type', 'employee', 'reference', 'amount', 'reason']],
        'loan_rejected' => ['module' => 'payroll', 'audience' => 'both', 'title' => 'payroll.loans.notifications.rejected_title', 'message' => 'payroll.loans.notifications.rejected_message', 'placeholders' => ['type', 'employee', 'reference', 'amount', 'reason']],
        'loan_change_awaiting_approval' => ['module' => 'payroll', 'audience' => 'admin', 'title' => 'payroll.loans.notifications.change_awaiting_approval_title', 'message' => 'payroll.loans.notifications.change_awaiting_approval_message', 'placeholders' => ['type', 'employee', 'reference', 'amount', 'reason']],
        'loan_change_approved' => ['module' => 'payroll', 'audience' => 'both', 'title' => 'payroll.loans.notifications.change_approved_title', 'message' => 'payroll.loans.notifications.change_approved_message', 'placeholders' => ['type', 'employee', 'reference', 'amount', 'reason']],
        'loan_change_rejected' => ['module' => 'payroll', 'audience' => 'both', 'title' => 'payroll.loans.notifications.change_rejected_title', 'message' => 'payroll.loans.notifications.change_rejected_message', 'placeholders' => ['type', 'employee', 'reference', 'amount', 'reason']],
        'payroll_run_awaiting_approval' => ['module' => 'payroll', 'audience' => 'admin', 'title' => 'payroll_runs.notifications.awaiting_approval_title', 'message' => 'payroll_runs.notifications.awaiting_approval_message', 'placeholders' => ['reference', 'name', 'period', 'reason']],
        'payroll_run_approved' => ['module' => 'payroll', 'audience' => 'admin', 'title' => 'payroll_runs.notifications.approved_title', 'message' => 'payroll_runs.notifications.approved_message', 'placeholders' => ['reference', 'name', 'period', 'reason']],
        'payroll_run_rejected' => ['module' => 'payroll', 'audience' => 'admin', 'title' => 'payroll_runs.notifications.rejected_title', 'message' => 'payroll_runs.notifications.rejected_message', 'placeholders' => ['reference', 'name', 'period', 'reason']],
        'payroll_run_finalised' => ['module' => 'payroll', 'audience' => 'admin', 'title' => 'payroll_runs.notifications.finalised_title', 'message' => 'payroll_runs.notifications.finalised_message', 'placeholders' => ['reference', 'name', 'period', 'reason']],
        'payslip_issued' => ['module' => 'payroll', 'audience' => 'employee', 'title' => 'payroll_runs.notifications.payslip_title', 'message' => 'payroll_runs.notifications.payslip_message', 'placeholders' => ['period', 'number']],

        // Performance (Phase 8a).
        'performance_launched' => ['module' => 'performance', 'audience' => 'employee', 'title' => 'performance.notifications.launched_title', 'message' => 'performance.notifications.launched_message', 'placeholders' => ['employee', 'cycle', 'reason']],
        'performance_launched_reviewer' => ['module' => 'performance', 'audience' => 'both', 'title' => 'performance.notifications.launched_reviewer_title', 'message' => 'performance.notifications.launched_reviewer_message', 'placeholders' => ['employee', 'cycle', 'reason']],
        'performance_self_submitted' => ['module' => 'performance', 'audience' => 'both', 'title' => 'performance.notifications.self_submitted_title', 'message' => 'performance.notifications.self_submitted_message', 'placeholders' => ['employee', 'cycle', 'reason']],
        'performance_awaiting_approval' => ['module' => 'performance', 'audience' => 'admin', 'title' => 'performance.notifications.awaiting_approval_title', 'message' => 'performance.notifications.awaiting_approval_message', 'placeholders' => ['employee', 'cycle', 'reason']],
        'performance_approved' => ['module' => 'performance', 'audience' => 'employee', 'title' => 'performance.notifications.approved_title', 'message' => 'performance.notifications.approved_message', 'placeholders' => ['employee', 'cycle', 'reason']],
        'performance_returned' => ['module' => 'performance', 'audience' => 'both', 'title' => 'performance.notifications.returned_title', 'message' => 'performance.notifications.returned_message', 'placeholders' => ['employee', 'cycle', 'reason']],
        'performance_acknowledgement_reminder' => ['module' => 'performance', 'audience' => 'employee', 'title' => 'performance.notifications.acknowledgement_reminder_title', 'message' => 'performance.notifications.acknowledgement_reminder_message', 'placeholders' => ['employee', 'cycle', 'reason']],

        // Training (Phase 8a + reminder 8b).
        'training_nominated' => ['module' => 'training', 'audience' => 'employee', 'title' => 'training.notifications.nominated_title', 'message' => 'training.notifications.nominated_message', 'placeholders' => ['employee', 'course', 'date', 'reason']],
        'training_submitted' => ['module' => 'training', 'audience' => 'both', 'title' => 'training.notifications.submitted_title', 'message' => 'training.notifications.submitted_message', 'placeholders' => ['employee', 'course', 'date', 'reason']],
        'training_awaiting_approval' => ['module' => 'training', 'audience' => 'admin', 'title' => 'training.notifications.awaiting_approval_title', 'message' => 'training.notifications.awaiting_approval_message', 'placeholders' => ['employee', 'course', 'date', 'reason']],
        'training_approved' => ['module' => 'training', 'audience' => 'both', 'title' => 'training.notifications.approved_title', 'message' => 'training.notifications.approved_message', 'placeholders' => ['employee', 'course', 'date', 'reason']],
        'training_rejected' => ['module' => 'training', 'audience' => 'both', 'title' => 'training.notifications.rejected_title', 'message' => 'training.notifications.rejected_message', 'placeholders' => ['employee', 'course', 'date', 'reason']],
        'training_cancelled' => ['module' => 'training', 'audience' => 'both', 'title' => 'training.notifications.cancelled_title', 'message' => 'training.notifications.cancelled_message', 'placeholders' => ['employee', 'course', 'date', 'reason']],
        'training_completed' => ['module' => 'training', 'audience' => 'both', 'title' => 'training.notifications.completed_title', 'message' => 'training.notifications.completed_message', 'placeholders' => ['employee', 'course', 'date', 'reason']],
        'training_session_reminder' => ['module' => 'training', 'audience' => 'employee', 'title' => 'notifications.automation.training_reminder_title', 'message' => 'notifications.automation.training_reminder_message', 'placeholders' => ['employee', 'course', 'date', 'location']],
        'training_certificate_expiring' => ['module' => 'training', 'audience' => 'admin', 'title' => 'training.notifications.certificate_title', 'message' => 'training.notifications.certificate_message', 'placeholders' => ['name', 'employee', 'date']],
        'training_certificate_expiring_own' => ['module' => 'training', 'audience' => 'employee', 'title' => 'training.notifications.certificate_title', 'message' => 'training.notifications.certificate_own_message', 'placeholders' => ['name', 'employee', 'date']],

        // Assets (Phase 8a).
        'asset_assigned' => ['module' => 'assets', 'audience' => 'employee', 'title' => 'assets.notifications.assigned_title', 'message' => 'assets.notifications.assigned_message', 'placeholders' => ['asset', 'date', 'condition']],
        'asset_returned' => ['module' => 'assets', 'audience' => 'employee', 'title' => 'assets.notifications.returned_title', 'message' => 'assets.notifications.returned_message', 'placeholders' => ['asset', 'date', 'condition']],

        // Disciplinary (Phase 8a). The notice to the employee cannot be switched off.
        'disciplinary_reported' => ['module' => 'disciplinary', 'audience' => 'admin', 'title' => 'disciplinary.notifications.reported_title', 'message' => 'disciplinary.notifications.reported_message', 'placeholders' => ['employee', 'reference', 'action', 'date', 'reason']],
        'disciplinary_awaiting_approval' => ['module' => 'disciplinary', 'audience' => 'admin', 'title' => 'disciplinary.notifications.awaiting_approval_title', 'message' => 'disciplinary.notifications.awaiting_approval_message', 'placeholders' => ['employee', 'reference', 'action', 'date', 'reason']],
        'disciplinary_approved' => ['module' => 'disciplinary', 'audience' => 'admin', 'title' => 'disciplinary.notifications.approved_title', 'message' => 'disciplinary.notifications.approved_message', 'placeholders' => ['employee', 'reference', 'action', 'date', 'reason']],
        'disciplinary_rejected' => ['module' => 'disciplinary', 'audience' => 'admin', 'title' => 'disciplinary.notifications.rejected_title', 'message' => 'disciplinary.notifications.rejected_message', 'placeholders' => ['employee', 'reference', 'action', 'date', 'reason']],
        'disciplinary_issued' => ['module' => 'disciplinary', 'audience' => 'employee', 'title' => 'disciplinary.notifications.issued_title', 'message' => 'disciplinary.notifications.issued_message', 'placeholders' => ['employee', 'reference', 'action', 'date'], 'mandatory' => true],
        'disciplinary_responded' => ['module' => 'disciplinary', 'audience' => 'admin', 'title' => 'disciplinary.notifications.responded_title', 'message' => 'disciplinary.notifications.responded_message', 'placeholders' => ['employee', 'reference', 'action', 'date', 'reason']],

        // Employee records and people events.
        'document_expiring' => ['module' => 'employees', 'audience' => 'both', 'title' => 'employees.reminders.document_title', 'message' => 'employees.reminders.document_message', 'placeholders' => ['title', 'employee', 'date']],
        'contract_expiring' => ['module' => 'employees', 'audience' => 'both', 'title' => 'employees.reminders.contract_title', 'message' => 'employees.reminders.contract_message', 'placeholders' => ['title', 'employee', 'date']],
        'birthday_upcoming' => ['module' => 'employees', 'audience' => 'admin', 'title' => 'notifications.automation.birthday_upcoming_title', 'message' => 'notifications.automation.birthday_upcoming_message', 'placeholders' => ['employee', 'date'], 'off_by_default' => ['mail']],
        'birthday_greeting' => ['module' => 'employees', 'audience' => 'employee', 'title' => 'notifications.automation.birthday_greeting_title', 'message' => 'notifications.automation.birthday_greeting_message', 'placeholders' => ['employee', 'company']],
        'work_anniversary' => ['module' => 'employees', 'audience' => 'employee', 'title' => 'notifications.automation.anniversary_title', 'message' => 'notifications.automation.anniversary_message', 'placeholders' => ['employee', 'company', 'years', 'date']],
        'work_anniversary_team' => ['module' => 'employees', 'audience' => 'admin', 'title' => 'notifications.automation.anniversary_team_title', 'message' => 'notifications.automation.anniversary_team_message', 'placeholders' => ['employee', 'years', 'date'], 'off_by_default' => ['mail']],

        // Onboarding, offboarding, probation.
        'onboarding_started' => ['module' => 'lifecycle', 'audience' => 'employee', 'title' => 'onboarding.notifications.started_title', 'message' => 'onboarding.notifications.started_message', 'placeholders' => ['count']],
        'offboarding_opened' => ['module' => 'lifecycle', 'audience' => 'admin', 'title' => 'onboarding.notifications.offboarding_title', 'message' => 'onboarding.notifications.offboarding_message', 'placeholders' => ['employee', 'date']],
        'probation_ending' => ['module' => 'lifecycle', 'audience' => 'admin', 'title' => 'notifications.automation.probation_title', 'message' => 'notifications.automation.probation_message', 'placeholders' => ['employee', 'date', 'days']],

        // Recruitment (Phase 4): interview panel members (admins or employees).
        'interview_assigned' => ['module' => 'recruitment', 'audience' => 'both', 'title' => 'recruitment.notifications.interview_assigned_title', 'message' => 'recruitment.notifications.interview_assigned_message', 'placeholders' => ['candidate', 'vacancy', 'date', 'time', 'timezone']],
        'interview_reminder' => ['module' => 'recruitment', 'audience' => 'both', 'title' => 'recruitment.notifications.interview_reminder_title', 'message' => 'recruitment.notifications.interview_reminder_message', 'placeholders' => ['candidate', 'vacancy', 'date', 'time', 'timezone']],

        // Approvals digest (Phase 8b): approvers with requests waiting longer than notifications.approvals_reminder_hours.
        'approvals_digest' => ['module' => 'approvals', 'audience' => 'admin', 'title' => 'notifications.automation.approvals_digest_title', 'message' => 'notifications.automation.approvals_digest_message', 'placeholders' => ['count', 'date', 'summary']],

        // HR requests and services (Phase 8b).
        'hr_request_received' => ['module' => 'hr_requests', 'audience' => 'employee', 'title' => 'hr_requests.notifications.received_title', 'message' => 'hr_requests.notifications.received_message', 'placeholders' => ['reference', 'subject', 'category', 'employee', 'due']],
        'hr_request_submitted' => ['module' => 'hr_requests', 'audience' => 'admin', 'title' => 'hr_requests.notifications.submitted_title', 'message' => 'hr_requests.notifications.submitted_message', 'placeholders' => ['reference', 'subject', 'category', 'employee', 'priority', 'due']],
        'hr_request_assigned' => ['module' => 'hr_requests', 'audience' => 'admin', 'title' => 'hr_requests.notifications.assigned_title', 'message' => 'hr_requests.notifications.assigned_message', 'placeholders' => ['reference', 'subject', 'category', 'employee', 'priority', 'due']],
        'hr_request_awaiting_approval' => ['module' => 'hr_requests', 'audience' => 'admin', 'title' => 'hr_requests.notifications.awaiting_approval_title', 'message' => 'hr_requests.notifications.awaiting_approval_message', 'placeholders' => ['reference', 'subject', 'category', 'employee']],
        'hr_request_reply' => ['module' => 'hr_requests', 'audience' => 'employee', 'title' => 'hr_requests.notifications.reply_title', 'message' => 'hr_requests.notifications.reply_message', 'placeholders' => ['reference', 'subject', 'author', 'excerpt']],
        'hr_request_employee_reply' => ['module' => 'hr_requests', 'audience' => 'admin', 'title' => 'hr_requests.notifications.employee_reply_title', 'message' => 'hr_requests.notifications.employee_reply_message', 'placeholders' => ['reference', 'subject', 'employee', 'excerpt']],
        'hr_request_status' => ['module' => 'hr_requests', 'audience' => 'employee', 'title' => 'hr_requests.notifications.status_title', 'message' => 'hr_requests.notifications.status_message', 'placeholders' => ['reference', 'subject', 'status', 'reason']],
        'hr_request_escalated' => ['module' => 'hr_requests', 'audience' => 'admin', 'title' => 'hr_requests.notifications.escalated_title', 'message' => 'hr_requests.notifications.escalated_message', 'placeholders' => ['reference', 'subject', 'employee', 'due', 'level', 'assignee'], 'mandatory' => true],

        // Announcements (Phase 8b). Announcements that require an acknowledgement cannot be switched off.
        'announcement_published' => ['module' => 'announcements', 'audience' => 'employee', 'title' => 'announcements.notifications.published_title', 'message' => 'announcements.notifications.published_message', 'placeholders' => ['title', 'summary', 'type']],
        'announcement_acknowledgement_required' => ['module' => 'announcements', 'audience' => 'employee', 'title' => 'announcements.notifications.acknowledgement_title', 'message' => 'announcements.notifications.acknowledgement_message', 'placeholders' => ['title', 'summary', 'type'], 'mandatory' => true],
    ],

];
