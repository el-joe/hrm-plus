<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 performance pass: indexes for query patterns of the dashboard
 * widgets, report centre and list screens not covered by the module migrations
 * (company-wide date-range filters that do not start with an employee id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Notification centre feed: newest first per user.
            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notifications_feed_index');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            // "On leave today", upcoming leave, leave request reports: status + date overlap.
            $table->index(['status', 'start_date', 'end_date'], 'leave_requests_status_dates_index');
        });

        Schema::table('leave_request_days', function (Blueprint $table) {
            // Leave usage / utilisation reports: counted days in a date range, all employees.
            $table->index(['date', 'is_counted'], 'leave_request_days_date_counted_index');
        });

        Schema::table('attendance_raw_events', function (Blueprint $table) {
            // Manual / imported entries report: source + work date range.
            $table->index(['source', 'work_date'], 'attendance_raw_events_source_work_date_index');
        });

        Schema::table('attendance_days', function (Blueprint $table) {
            // Company-wide date-range reports (lateness, absence, overtime) without a branch filter.
            $table->index(['work_date', 'employee_id'], 'attendance_days_work_date_employee_index');
        });

        Schema::table('employees', function (Blueprint $table) {
            // Turnover, terminations and inactive-employee reports / widgets.
            $table->index('termination_date', 'employees_termination_date_index');
        });

        Schema::table('candidates', function (Blueprint $table) {
            // "New candidates" widget and recruitment reports: created in a period.
            $table->index('created_at', 'candidates_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', fn (Blueprint $table) => $table->dropIndex('notifications_feed_index'));
        Schema::table('leave_requests', fn (Blueprint $table) => $table->dropIndex('leave_requests_status_dates_index'));
        Schema::table('leave_request_days', fn (Blueprint $table) => $table->dropIndex('leave_request_days_date_counted_index'));
        Schema::table('attendance_raw_events', fn (Blueprint $table) => $table->dropIndex('attendance_raw_events_source_work_date_index'));
        Schema::table('attendance_days', fn (Blueprint $table) => $table->dropIndex('attendance_days_work_date_employee_index'));
        Schema::table('employees', fn (Blueprint $table) => $table->dropIndex('employees_termination_date_index'));
        Schema::table('candidates', fn (Blueprint $table) => $table->dropIndex('candidates_created_at_index'));
    }
};
