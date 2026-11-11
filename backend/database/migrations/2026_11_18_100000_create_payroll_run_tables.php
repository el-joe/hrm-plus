<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7b: payroll periods, runs (settlements), calculated employee items
 * with their lines and snapshot, post-finalisation adjustments, payslips
 * and payments. Money is DECIMAL; a finalised run is never updated
 * (corrections and reversals are new rows).
 *
 * Double-pay prevention: payroll_items.pay_slot is "regular" while an item
 * holds the employee's pay for the period (regular runs and final
 * settlements); unique (payroll_period_id, employee_id, pay_slot) makes a
 * second one impossible at the database level. Cancelling a run, removing an
 * employee from a draft or reversing a finalised run sets it back to null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statutory_rule_versions', function (Blueprint $table) {
            // Configurable base: component categories / codes whose deduction lines reduce the
            // taxable (income tax) or insurable (social insurance) base of the period.
            $table->json('base_reductions')->nullable()->after('annual_exemption');
            // Income tax only: the employee's social insurance share reduces taxable income.
            $table->boolean('deduct_employee_insurance')->default(false)->after('base_reductions');
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            // App\Enums\PayrollPeriodType: monthly | custom.
            $table->string('type', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date');
            // Last date attendance / leave changes are expected for this period.
            $table->date('cutoff_date')->nullable();
            // App\Enums\PayrollPeriodStatus: open | closed.
            $table->string('status', 20)->default('open')->index();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            // App\Enums\PayrollRunType.
            $table->string('type', 20)->index();
            $table->string('name', 200);
            // App\Enums\PayrollRunStatus.
            $table->string('status', 20)->default('draft')->index();
            // Settlement scope as chosen (branches, departments, teams, positions, groups,
            // individuals, exclusions) and its readable summary.
            $table->json('scope');
            $table->string('scope_summary', 1000)->nullable();
            // The only branch of the population, when there is exactly one (per-branch settlement).
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('corrects_run_id')->nullable()->constrained('payroll_runs')->restrictOnDelete();
            $table->foreignId('offboarding_case_id')->nullable()->constrained()->nullOnDelete();
            $table->char('currency', 3);
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('gross_total', 16, 2)->default(0);
            $table->decimal('deductions_total', 16, 2)->default(0);
            $table->decimal('net_total', 16, 2)->default(0);
            $table->decimal('employer_total', 16, 2)->default(0);
            // Queue progress of a calculation (large runs).
            $table->unsignedInteger('progress_total')->default(0);
            $table->unsignedInteger('progress_done')->default(0);
            $table->string('calculation_error', 2000)->nullable();
            // Hash over every item hash at finalisation: proves the finalised data never changed.
            $table->char('totals_hash', 64)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('finalised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalised_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 1000)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['payroll_period_id', 'status']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // "regular" while this item holds the employee's pay for the period (see header).
            $table->string('pay_slot', 20)->nullable();
            // App\Enums\PayrollItemStatus: pending | calculated | failed.
            $table->string('status', 20)->default('pending');
            // Placement at the period end (scoping, totals by branch / department, data scope).
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            // The employee changed branch / department inside the period (placement segments in quantities).
            $table->boolean('moved_in_period')->default(false);
            $table->char('currency', 3);
            $table->string('pay_basis', 20)->nullable();
            $table->decimal('basic_salary', 14, 2)->nullable();
            $table->decimal('proration_factor', 9, 6)->default(1);
            $table->decimal('gross_pay', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('employer_contributions', 14, 2)->default(0);
            $table->decimal('taxable_base', 14, 2)->default(0);
            $table->decimal('insurable_base', 14, 2)->default(0);
            $table->decimal('net_pay', 14, 2)->default(0);
            // PayrollInputs::toArray() + attendance / leave quantities + the context of every salary segment.
            $table->json('inputs')->nullable();
            $table->json('quantities')->nullable();
            // Statutory / structure / attendance policy versions used.
            $table->json('rule_versions')->nullable();
            $table->json('warnings')->nullable();
            $table->boolean('has_blocking_warning')->default(false);
            $table->char('calculation_hash', 64)->nullable();
            $table->string('error', 2000)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id', 'pay_slot'], 'payroll_items_pay_slot_unique');
            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['employee_id', 'payroll_period_id']);
            $table->index(['branch_id', 'payroll_run_id']);
            $table->index(['department_id', 'payroll_run_id']);
        });

        Schema::create('payroll_item_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            // earning | deduction | employer_contribution.
            $table->string('kind', 30);
            $table->string('category', 20);
            // App\Enums\PayrollLineSource.
            $table->string('source', 20);
            // The record behind the line: pay entry, loan instalment, statutory version, leave
            // encashment transaction, payroll adjustment.
            $table->nullableMorphs('sourceable');
            $table->decimal('quantity', 14, 4)->nullable();
            $table->decimal('rate', 16, 6)->nullable();
            // Signed: a refund correction of a deduction is a negative deduction.
            $table->decimal('amount', 14, 2);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_insurable')->default(false);
            $table->boolean('show_on_payslip')->default(true);
            // How the amount was obtained: [{key, params}] rendered in the viewer's language.
            $table->json('explanation')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['payroll_run_id', 'code']);
            $table->index(['employee_id', 'kind']);
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // The finalised item being corrected (null for a manual adjustment without one).
            $table->foreignId('original_item_id')->nullable()->constrained('payroll_items')->restrictOnDelete();
            $table->foreignId('original_period_id')->nullable()->constrained('payroll_periods')->restrictOnDelete();
            // App\Enums\PayrollAdjustmentSource: attendance | leave | manual.
            $table->string('source', 20);
            // App\Enums\PayrollAdjustmentStatus: pending | applied | dismissed.
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('salary_component_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('kind', 30);
            $table->string('category', 20);
            $table->decimal('quantity_delta', 14, 4)->nullable();
            $table->decimal('rate', 16, 6)->nullable();
            // Signed amount of the line to add (deduction kind: positive = more deduction, negative = refund).
            $table->decimal('amount', 14, 2);
            $table->string('reason', 1000);
            // Detection fingerprint so a re-scan never creates the same correction twice.
            $table->char('fingerprint', 64)->nullable()->unique();
            $table->json('details')->nullable();
            // Run item that carries it (reserved while the run is a draft; applied on finalise).
            $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->string('dismiss_reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('payroll_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            // App\Enums\PayslipStatus: issued | void.
            $table->string('status', 20)->default('issued');
            // App\Enums\PaymentStatus: unpaid | partially_paid | paid.
            $table->string('payment_status', 20)->default('unpaid')->index();
            $table->char('currency', 3);
            $table->decimal('gross_pay', 14, 2);
            $table->decimal('total_deductions', 14, 2);
            $table->decimal('net_pay', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->date('paid_on')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->string('locale', 5);
            // Cached PDF on the private disk (regenerated when missing; content comes from the frozen item).
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'payroll_period_id']);
        });

        Schema::create('payslip_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_payments');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_item_lines');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');

        Schema::table('statutory_rule_versions', function (Blueprint $table) {
            $table->dropColumn(['base_reductions', 'deduct_employee_insurance']);
        });
    }
};
