<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance core (Phase 5a): shifts, schedules, holidays, policies, the
     * raw-event -> punch -> attendance-day pipeline, manual entry, corrections
     * and period locks.
     *
     * Times of day (shift start / end, break windows) are wall-clock TIME
     * values in the employee's branch timezone. Instants (punches, scheduled
     * start / end of a processed day) are DATETIME in UTC.
     *
     * Several tables use `shift_key` (= shift_id, or 0 when there is no shift)
     * so that "employee + date + shift" can be a real UNIQUE key: MySQL does
     * not treat NULLs as equal in unique indexes.
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            // App\Enums\ShiftType: fixed | flexible.
            $table->string('type', 20);
            // Fixed: the shift. Flexible: the window in which the hours may be worked.
            // end_time <= start_time means the shift ends on the next day (cross-midnight).
            $table->time('start_time');
            $table->time('end_time');
            // Flexible only: minutes to work and optional core hours that must be covered.
            $table->unsignedSmallInteger('required_minutes')->nullable();
            $table->time('core_start_time')->nullable();
            $table->time('core_end_time')->nullable();
            // Punches this long before the start / after the end still belong to the shift.
            $table->unsignedSmallInteger('checkin_opens_before_minutes')->default(120);
            $table->unsignedSmallInteger('checkout_closes_after_minutes')->default(240);
            // Optional per-shift overrides of the attendance policy grace periods.
            $table->unsignedSmallInteger('late_grace_minutes')->nullable();
            $table->unsignedSmallInteger('early_leave_grace_minutes')->nullable();
            $table->boolean('is_night')->default(false);
            // Calendar colour token (chart-1 ... chart-6).
            $table->string('color', 10)->default('chart-1');
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('shift_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->nullable();
            // App\Enums\BreakType: fixed (start/end) | flexible (duration only).
            $table->string('type', 20);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('is_paid')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            // App\Enums\ScheduleType: weekly (day_index 0 = Sunday ... 6) |
            // rotating (day_index 0 ... cycle_length_days - 1 counted from cycle_start_date) |
            // monthly (day_index 1 ... 31 = day of the month).
            $table->string('type', 20);
            $table->unsignedSmallInteger('cycle_length_days')->nullable();
            $table->date('cycle_start_date')->nullable();
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // A day of the pattern with no row is a rest day; several rows = several shifts that day.
        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_index');
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['work_schedule_id', 'day_index', 'shift_id']);
        });

        // Precedence: employee > employee_group > department (nearest up the tree) > branch > company.
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained()->restrictOnDelete();
            // App\Enums\AttendanceScope; scope_id is null for "company".
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id', 'effective_from']);
        });

        Schema::create('schedule_change_requests', function (Blueprint $table) {
            $table->id();
            // App\Enums\ScheduleChangeType: change | swap.
            $table->string('type', 20);
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            // change: the requested shift (null = day off). swap: filled from the other employee's day.
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('swap_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('reason', 1000);
            // App\Enums\RequestStatus: pending | approved | rejected | cancelled.
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
        });

        // Date-specific roster entries; when an employee has any for a date they replace the pattern.
        Schema::create('schedule_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('shift_key')->default(0);
            // App\Enums\OverrideSource: manual | change_request | swap.
            $table->string('source', 20);
            $table->foreignId('schedule_change_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date', 'shift_key']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->date('start_date');
            $table->date('end_date');
            // Repeats every year on the same month/day range (from start_date's year on).
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_paid')->default(true);
            // Null: the whole company; otherwise that branch only.
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });

        // Configurable day statuses used by manual entry ("Other configurable attendance statuses").
        Schema::create('attendance_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            // App\Enums\AttendanceStatusCategory: present | absent | on_leave | rest_day.
            $table->string('category', 20);
            // Counted as a worked day (scheduled minutes) when no punches exist, e.g. official mission.
            $table->boolean('counts_as_worked')->default(false);
            $table->boolean('is_paid')->default(true);
            // System statuses cannot be deleted or have their code changed.
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Rules are versioned: a change is a new version effective from a date, so
        // recalculating an old day always uses the rules that applied on that day.
        Schema::create('attendance_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_policy_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->date('effective_from');
            // Lateness.
            $table->unsignedSmallInteger('late_grace_minutes');
            $table->boolean('late_count_from_shift_start')->default(true);
            $table->unsignedSmallInteger('late_rounding_minutes')->default(0);
            $table->unsignedSmallInteger('late_absent_after_minutes')->nullable();
            // Early departure.
            $table->unsignedSmallInteger('early_leave_grace_minutes');
            $table->unsignedSmallInteger('early_leave_rounding_minutes')->default(0);
            // Absence and missing punches.
            $table->unsignedSmallInteger('min_worked_minutes_for_present')->nullable();
            // App\Enums\MissingPunchAction: incomplete | assume_schedule | absent.
            $table->string('missing_punch_action', 20);
            $table->unsignedSmallInteger('punch_dedupe_seconds')->default(60);
            // Overtime (rates are stored for payroll; nothing here computes pay).
            $table->boolean('overtime_enabled')->default(true);
            $table->unsignedSmallInteger('overtime_min_minutes');
            $table->unsignedSmallInteger('overtime_rounding_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_max_daily_minutes')->nullable();
            $table->boolean('overtime_requires_approval')->default(true);
            $table->boolean('overtime_count_early_arrival')->default(false);
            $table->boolean('rest_day_work_is_overtime')->default(true);
            $table->decimal('overtime_rate_regular', 4, 2);
            $table->decimal('overtime_rate_rest_day', 4, 2);
            $table->decimal('overtime_rate_holiday', 4, 2);
            // Breaks. App\Enums\BreakMode: scheduled | actual.
            $table->string('break_mode', 20);
            $table->unsignedSmallInteger('max_break_minutes')->nullable();
            // Approval requirements.
            $table->boolean('manual_entry_requires_approval')->default(false);
            $table->boolean('correction_requires_approval')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['attendance_policy_id', 'version'], 'attendance_policy_versions_version_unique');
            $table->unique(['attendance_policy_id', 'effective_from'], 'attendance_policy_versions_effective_unique');
        });

        // Precedence: employee > employee_group > employment_type > department > branch > company.
        Schema::create('attendance_policy_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_policy_id')->constrained()->restrictOnDelete();
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id', 'effective_from'], 'attendance_policy_assignments_scope_index');
        });

        Schema::create('attendance_period_locks', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            // Null: every branch.
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at');
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->index(['start_date', 'end_date', 'unlocked_at']);
        });

        Schema::create('attendance_manual_batches', function (Blueprint $table) {
            $table->id();
            // App\Enums\RawEventSource: manual (bulk screen) | import (Excel).
            $table->string('source', 20);
            // Bulk entry: the date marked. Import: null (rows carry their own dates).
            $table->date('work_date')->nullable();
            // Bulk entry: branch / department / team / shift / group used to pick the employees.
            $table->json('filters')->nullable();
            // Counts per action, shown on the confirmation screen and kept for the record.
            $table->json('summary')->nullable();
            $table->string('file_name')->nullable();
            $table->string('note', 1000)->nullable();
            // App\Enums\ManualBatchStatus: draft | pending_approval | applied | rejected | cancelled.
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_manual_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_manual_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('shift_key')->default(0);
            // App\Enums\ManualEntryAction: present | late | custom_times | status.
            $table->string('action', 20);
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->foreignId('attendance_status_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['attendance_manual_batch_id', 'employee_id', 'work_date', 'shift_key'], 'attendance_manual_entries_unique');
            $table->index(['employee_id', 'work_date']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            // Requested punches (UTC) and / or day status; raw events to void.
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->foreignId('attendance_status_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('void_raw_event_ids')->nullable();
            $table->string('reason', 1000);
            // App\Enums\CorrectionSource: admin | portal | api.
            $table->string('source', 20);
            // App\Enums\CorrectionStatus: pending | applied | rejected | cancelled.
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
        });

        /*
         | Every attendance input, whatever the source, lands here first and is
         | never edited (only voided). The engine reads ONLY this table.
         |
         | Extension point for Phase 5b: device, location and face-verification
         | details go into their own tables keyed by attendance_raw_event_id (or
         | new nullable columns); source / source_reference / payload are generic.
         */
        Schema::create('attendance_raw_events', function (Blueprint $table) {
            $table->id();
            // App\Enums\RawEventKind: punch (a clock in/out instant) | day_mark (a status for a whole day).
            $table->string('kind', 20)->default('punch');
            // App\Enums\RawEventSource: device | mobile_gps | manual | import.
            $table->string('source', 20);
            // Null while a device user id is not mapped to an employee yet (status "unmatched").
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            // Punch: the instant, UTC. Day mark: when it was recorded.
            $table->dateTime('occurred_at');
            // Timezone the source reported the time in (for display / audit).
            $table->string('timezone', 64);
            // App\Enums\PunchDirection: in | out | unknown (punches only).
            $table->string('direction', 10)->nullable();
            // Day marks always, manual punches optionally: pins the event to a date / shift.
            $table->date('work_date')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            // Day mark: the status (null = clear an earlier mark).
            $table->foreignId('attendance_status_id')->nullable()->constrained()->restrictOnDelete();
            // e.g. device serial + device user id, "batch:12", import file name.
            $table->string('source_reference', 191)->nullable();
            // Duplicate protection: the same input can never be stored twice.
            $table->string('idempotency_key', 191)->unique();
            $table->json('payload')->nullable();
            $table->string('note', 1000)->nullable();
            // What created it: attendance_manual_entry, attendance_correction, ... (morph map alias).
            $table->nullableMorphs('origin');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            // App\Enums\RawEventStatus: pending | processed | unmatched | void.
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'occurred_at']);
            $table->index(['employee_id', 'work_date']);
            $table->index(['source', 'occurred_at']);
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('shift_key')->default(0);
            // Placement on that date (for scoping and reports), from the assignment history.
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('timezone', 64);
            // App\Enums\AttendanceDayStatus.
            $table->string('status', 20);
            $table->foreignId('attendance_status_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('holiday_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_rest_day')->default(false);
            $table->string('leave_reference', 100)->nullable();
            $table->dateTime('scheduled_start_at')->nullable();
            $table->dateTime('scheduled_end_at')->nullable();
            $table->unsignedInteger('scheduled_minutes')->default(0);
            $table->dateTime('first_in_at')->nullable();
            $table->dateTime('last_out_at')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('shortfall_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('overtime_approved_minutes')->nullable();
            // App\Enums\OvertimeStatus: none | pending | approved | rejected.
            $table->string('overtime_status', 20)->default('none');
            $table->json('flags')->nullable();
            // Sources of the punches / marks used (device, mobile_gps, manual, import).
            $table->json('sources')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->foreignId('attendance_policy_version_id')->nullable()->constrained()->nullOnDelete();
            // Inputs, rules and every step of the calculation, for the breakdown screen and payroll audits.
            $table->json('calculation')->nullable();
            $table->char('calculation_hash', 64)->nullable();
            $table->timestamp('calculated_at')->nullable();
            // Set while the day is inside a locked period: the row is frozen, changes go to attendance_adjustments.
            $table->foreignId('attendance_period_lock_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date', 'shift_key']);
            $table->index(['work_date', 'status']);
            $table->index(['branch_id', 'work_date']);
            $table->index(['department_id', 'work_date']);
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_raw_event_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->dateTime('punched_at');
            $table->string('direction', 10);
            $table->string('source', 20);
            // The processed day / shift the punch was attributed to.
            $table->foreignId('attendance_day_id')->nullable()->constrained()->nullOnDelete();
            // Within the policy's dedupe window of an earlier punch: kept for the record, not paired.
            $table->boolean('is_duplicate')->default(false);
            $table->timestamps();

            $table->index(['employee_id', 'punched_at']);
        });

        // Changes to a day after its period was locked: the locked row stays as it was.
        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_day_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->string('status_before', 20);
            $table->string('status_after', 20);
            $table->integer('worked_minutes_delta')->default(0);
            $table->integer('late_minutes_delta')->default(0);
            $table->integer('early_leave_minutes_delta')->default(0);
            $table->integer('shortfall_minutes_delta')->default(0);
            $table->integer('break_minutes_delta')->default(0);
            $table->integer('overtime_minutes_delta')->default(0);
            $table->string('reason', 500);
            $table->foreignId('attendance_policy_version_id')->nullable()->constrained()->nullOnDelete();
            $table->json('calculation')->nullable();
            $table->char('calculation_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Set when the period is unlocked: the day row then carries the full figures again.
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'attendance_adjustments', 'attendance_punches', 'attendance_days', 'attendance_raw_events',
            'attendance_corrections', 'attendance_manual_entries', 'attendance_manual_batches', 'attendance_period_locks',
            'attendance_policy_assignments', 'attendance_policy_versions', 'attendance_policies', 'attendance_statuses',
            'holidays', 'schedule_overrides', 'schedule_change_requests', 'schedule_assignments', 'work_schedule_days',
            'work_schedules', 'shift_breaks', 'shifts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
