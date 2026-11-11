<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave and time-off management (Phase 6, spec module 8).
 *
 * - leave_types: configurable kinds of leave (bilingual) with request rules
 *   and eligibility;
 * - leave_policies: effective-dated entitlement / accrual / carry-over rules of
 *   a leave type for the whole company, an employment type or an employee group;
 * - leave_balance_transactions: the balance ledger. Rows are only ever added
 *   (accrual, usage, reversal, adjustment, carry-over, expiry, encashment);
 *   a balance is the sum of its rows for an employee, type and leave year;
 * - leave_requests (+ days, attachments): requests, one row per calendar day
 *   with the amount charged on that day. Approval runs through the approval
 *   engine (approval_requests, morph "leave_request").
 *
 * Amounts are DECIMAL days (half days 0.5, hours as a fraction of the day's
 * scheduled hours) and are calculated with bcmath.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_paid')->default(true);
            // Unpaid days of this type are deducted by payroll (App\Services\Leave\LeavePayrollFeed).
            $table->boolean('affects_payroll')->default(false);
            // Whether requests are checked against / charged to a balance (unpaid leave usually is not).
            $table->boolean('tracks_balance')->default(true);
            // App\Enums\LeaveUnit: day | half_day | hour — the smallest unit an employee may request.
            $table->string('unit', 10)->default('day');
            $table->boolean('requires_attachment')->default(false);
            // Attachment needed only when the request is longer than this many days (null = always).
            $table->decimal('attachment_after_days', 6, 2)->nullable();
            // Eligibility. App\Enums\Gender or null for everyone.
            $table->string('gender', 10)->nullable();
            $table->unsignedSmallInteger('min_service_months')->default(0);
            // e.g. Hajj: once per employment (null = unlimited).
            $table->unsignedSmallInteger('max_times_per_service')->nullable();
            // Request rules.
            $table->decimal('max_days_per_request', 6, 2)->nullable();
            $table->unsignedSmallInteger('notice_days')->default(0);
            $table->boolean('allows_past_dates')->default(false);
            $table->boolean('counts_rest_days')->default(false);
            $table->boolean('counts_holidays')->default(false);
            // approval_workflows.key run for requests of this type.
            $table->string('approval_workflow', 100)->default('leave_request');
            // Day status shown on attendance days covered by this leave (e.g. "sick_leave"); null = generic on leave.
            $table->foreignId('attendance_status_id')->nullable()->constrained()->nullOnDelete();
            // chart-1 … chart-6 (calendar colour).
            $table->string('color', 20)->default('chart-1');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            // App\Enums\LeavePolicyScope: company | employment_type | employee_group.
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            // Entitlement per leave year and how it is credited. App\Enums\LeaveAccrualMethod:
            // annual_upfront | monthly | pro_rata_hire | service_tiers.
            $table->decimal('entitlement_days', 6, 2)->default(0);
            $table->string('accrual_method', 20)->default('annual_upfront');
            // service_tiers: [{"min_years": 0, "days": "15.00"}, {"min_years": 10, "days": "30.00"}].
            $table->json('service_tiers')->nullable();
            // Carry-over to the next leave year: max days (0 = none, null = unlimited) and expiry.
            $table->decimal('carry_over_max_days', 6, 2)->default(0);
            $table->unsignedSmallInteger('carry_over_expiry_months')->nullable();
            // Accrual stops while the balance is at this ceiling (null = no ceiling).
            $table->decimal('max_balance_days', 6, 2)->nullable();
            $table->boolean('allow_negative_balance')->default(false);
            // How far below zero (null = no limit when negative balances are allowed).
            $table->decimal('negative_limit_days', 6, 2)->nullable();
            // Stored for payroll (Phase 7): unused days may be paid out.
            $table->boolean('encashable')->default(false);
            $table->decimal('encashment_max_days', 6, 2)->nullable();
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['leave_type_id', 'scope_type', 'scope_id', 'effective_from'], 'leave_policies_resolution_index');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            // App\Enums\LeaveDayPart: full | first_half | second_half | hours (partial parts are single-day requests).
            $table->string('day_part', 12)->default('full');
            $table->decimal('hours', 5, 2)->nullable();
            $table->time('start_time')->nullable();
            // Days charged (sum of the counted leave_request_days).
            $table->decimal('days', 8, 2);
            $table->string('reason', 1000)->nullable();
            // App\Enums\LeaveRequestStatus: pending | approved | rejected | cancelled | cancellation_pending.
            $table->string('status', 24)->default('pending')->index();
            // App\Enums\LeaveRequestSource: admin | portal | api.
            $table->string('source', 10);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'start_date', 'end_date']);
            $table->index(['leave_type_id', 'status']);
        });

        Schema::create('leave_request_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('date');
            // Leave year (setting leave.year_start_month) the day is charged to.
            $table->unsignedSmallInteger('leave_year');
            $table->string('day_part', 12)->default('full');
            // Days charged on this date (0 when a rest day / holiday is not counted).
            $table->decimal('amount', 6, 2);
            $table->decimal('hours', 5, 2)->nullable();
            $table->boolean('is_counted')->default(true);
            // rest_day | holiday | working (why a day is or is not counted).
            $table->string('day_kind', 12)->default('working');

            $table->index(['employee_id', 'date']);
        });

        Schema::create('leave_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 120);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('leave_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('leave_year');
            // App\Enums\LeaveTransactionType: accrual | usage | reversal | adjustment | carry_over | expiry | encashment.
            $table->string('type', 20);
            // Signed: credits positive, debits negative.
            $table->decimal('amount', 8, 2);
            $table->date('effective_date');
            // Carry-over credits: unused carried days expire after this date.
            $table->date('expires_on')->nullable();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_policy_id')->nullable()->constrained()->nullOnDelete();
            // Idempotency key of automatic rows, e.g. "usage:42:2026", "rollover:2025", "expiry:carry:17".
            $table->string('reference', 100)->nullable()->unique();
            $table->string('reason', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['employee_id', 'leave_type_id', 'leave_year'], 'leave_balance_ledger_index');
            $table->index(['type', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balance_transactions');
        Schema::dropIfExists('leave_request_attachments');
        Schema::dropIfExists('leave_request_days');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
