<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll components (Phase 7a, spec module 9 A–D).
     *
     * - employee_salaries stays the ONE source of the basic salary; it gains a
     *   pay_basis (monthly / daily / hourly / fixed) so every dated salary row
     *   says how the amount is expressed. employees.pay_basis mirrors the
     *   latest applied row, like employees.basic_salary.
     * - salary_components: the component catalogue (earnings, deductions,
     *   employer contributions) with a closed list of rule types — no formulas.
     * - salary_structures → versions → lines: templates of components; a change
     *   is a new version, never an edit of a version in use. Assigned to an
     *   employee, job grade, department, branch or the company with
     *   effective-dated rows (a new assignment closes the open one).
     * - pay_entries: allowances (incl. bonuses, commissions) and deductions
     *   (incl. penalties, instalment deductions) for an employee, employee
     *   group, department, branch or the company; optional approval, files.
     * - statutory_rule_sets → versions → brackets: tax / social insurance by
     *   country and effective date. Rates are data only; demo rows are flagged.
     * - employee_loans, loan_instalments, loan_transactions (ledger), loan_changes
     *   (skip / reschedule requests through approval).
     *
     * Money is DECIMAL and computed with bcmath only.
     */
    public function up(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            // App\Enums\PayBasis.
            $table->string('pay_basis', 20)->default('monthly')->after('basic_salary');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('pay_basis', 20)->nullable()->after('basic_salary');
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            // App\Enums\PayComponentKind / PayComponentCategory / PayCalculationType.
            $table->string('kind', 30)->index();
            $table->string('category', 30);
            $table->string('calculation_type', 30);
            // fixed: amount per period; quantity types: fixed amount per unit (null = derived from the salary rate).
            $table->decimal('amount', 14, 4)->nullable();
            // percentage: percent; quantity types: multiplier of the salary rate.
            $table->decimal('rate', 9, 4)->nullable();
            // percentage: base component (null = basic salary).
            $table->foreignId('base_component_id')->nullable()->constrained('salary_components')->restrictOnDelete();
            // overtime_hours: take the multiplier of the attendance policy version (regular / rest day / holiday).
            $table->boolean('use_policy_multiplier')->default(false);
            // statutory: App\Enums\StatutoryType.
            $table->string('statutory_type', 30)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_insurable')->default(false);
            $table->boolean('prorate_partial_period')->default(true);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('show_on_payslip')->default(true);
            // App\Enums\PayRounding; precision null = setting payroll.rounding_precision.
            $table->string('rounding', 20)->default('half_up');
            $table->unsignedTinyInteger('rounding_precision')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('salary_structure_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['salary_structure_id', 'version']);
            $table->index(['salary_structure_id', 'effective_from'], 'salary_structure_versions_from_index');
        });

        Schema::create('salary_structure_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->restrictOnDelete();
            // Overrides of the component's default amount / rate / base (null = the component's).
            $table->decimal('amount', 14, 4)->nullable();
            $table->decimal('rate', 9, 4)->nullable();
            $table->foreignId('base_component_id')->nullable()->constrained('salary_components')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['salary_structure_version_id', 'salary_component_id'], 'salary_structure_lines_component_unique');
        });

        Schema::create('salary_structure_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained()->restrictOnDelete();
            // App\Enums\SalaryStructureScope; scope_id null for the company.
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id', 'effective_from'], 'salary_structure_assignments_scope_index');
        });

        Schema::create('pay_entry_batches', function (Blueprint $table) {
            $table->id();
            // bulk | import.
            $table->string('source', 20);
            $table->string('kind', 30);
            $table->unsignedInteger('entries_count')->default(0);
            $table->string('file_name')->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pay_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            // App\Enums\PayComponentKind: earning (allowance) | deduction.
            $table->string('kind', 30)->index();
            $table->foreignId('salary_component_id')->constrained()->restrictOnDelete();
            // App\Enums\PayEntryTarget + the target. branch_id / department_id are also
            // filled for employee targets (placement when created) — they carry the data scope.
            $table->string('target_type', 20);
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            // App\Enums\PayEntryCalculation.
            $table->string('calculation', 20);
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('rate', 9, 4)->nullable();
            $table->foreignId('base_component_id')->nullable()->constrained('salary_components')->restrictOnDelete();
            // App\Enums\PayEntryRecurrence.
            $table->string('recurrence', 20);
            $table->date('start_date');
            // Recurring: last day (null = open-ended). One-time: equals start_date.
            $table->date('end_date')->nullable();
            // Instalment deductions: total split into instalment_count monthly instalments.
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->unsignedSmallInteger('instalment_count')->nullable();
            // Payroll treatment overrides (null = the component's flag).
            $table->boolean('is_taxable')->nullable();
            $table->boolean('is_insurable')->nullable();
            $table->boolean('prorate')->nullable();
            // App\Enums\RequestStatus.
            $table->string('status', 20)->default('pending')->index();
            $table->string('reason', 1000)->nullable();
            $table->string('notes', 1000)->nullable();
            // Origin record (e.g. a disciplinary action in a later phase).
            $table->nullableMorphs('source');
            $table->foreignId('pay_entry_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
            $table->index(['target_type', 'employee_id']);
        });

        Schema::create('pay_entry_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_entry_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 120);
            $table->unsignedInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('statutory_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->char('country_code', 2)->index();
            // App\Enums\StatutoryType.
            $table->string('type', 30);
            // Illustrative values only ("DEMO — not legal advice"), never real rates.
            $table->boolean('is_demo')->default(false);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['country_code', 'type']);
        });

        Schema::create('statutory_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statutory_rule_set_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            // Social insurance: percent of the insurable base, clamped to floor / ceiling (per month).
            $table->decimal('employee_rate', 7, 4)->nullable();
            $table->decimal('employer_rate', 7, 4)->nullable();
            $table->decimal('salary_floor', 14, 2)->nullable();
            $table->decimal('salary_ceiling', 14, 2)->nullable();
            // Income tax: brackets apply to annual taxable income after the annual exemption.
            $table->decimal('annual_exemption', 14, 2)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['statutory_rule_set_id', 'version']);
        });

        Schema::create('statutory_rule_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statutory_rule_version_id')->constrained()->cascadeOnDelete();
            $table->decimal('lower_bound', 14, 2);
            // Null = no upper limit (last bracket).
            $table->decimal('upper_bound', 14, 2)->nullable();
            $table->decimal('rate', 7, 4);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // App\Enums\LoanType / LoanStatus / LoanSource.
            $table->string('type', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->string('source', 20);
            $table->decimal('requested_amount', 14, 2);
            $table->unsignedSmallInteger('requested_instalments');
            $table->date('requested_start');
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->unsignedSmallInteger('instalment_count')->nullable();
            // First instalment month (stored as the first day of the month).
            $table->date('start_date')->nullable();
            // Ledger total (kept in step with loan_transactions inside the same transaction).
            $table->decimal('outstanding_amount', 14, 2)->default(0);
            $table->string('reason', 1000)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('loan_instalments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            // App\Enums\LoanInstalmentStatus.
            $table->string('status', 20)->default('scheduled');
            // Recovered from the final settlement (offboarding).
            $table->boolean('is_final_settlement')->default(false);
            // Set by payroll (Phase 7b) when a finalised run deducted it.
            $table->nullableMorphs('settled_by');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status', 'due_date']);
        });

        Schema::create('loan_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_instalment_id')->nullable()->constrained()->restrictOnDelete();
            // App\Enums\LoanTransactionType. amount: + raises the balance, − lowers it.
            $table->string('type', 30);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->date('occurred_on');
            $table->string('reference', 100)->nullable()->unique();
            $table->string('note', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('loan_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained()->restrictOnDelete();
            // App\Enums\LoanChangeType / RequestStatus.
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');
            // skip: the instalment to move to the end of the schedule.
            $table->foreignId('loan_instalment_id')->nullable()->constrained()->restrictOnDelete();
            // reschedule: remaining balance over this many instalments from this month.
            $table->unsignedSmallInteger('new_instalment_count')->nullable();
            $table->date('new_start_date')->nullable();
            $table->string('reason', 1000);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_changes');
        Schema::dropIfExists('loan_transactions');
        Schema::dropIfExists('loan_instalments');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('statutory_rule_brackets');
        Schema::dropIfExists('statutory_rule_versions');
        Schema::dropIfExists('statutory_rule_sets');
        Schema::dropIfExists('pay_entry_attachments');
        Schema::dropIfExists('pay_entries');
        Schema::dropIfExists('pay_entry_batches');
        Schema::dropIfExists('salary_structure_assignments');
        Schema::dropIfExists('salary_structure_lines');
        Schema::dropIfExists('salary_structure_versions');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('salary_components');

        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('pay_basis'));
        Schema::table('employee_salaries', fn (Blueprint $table) => $table->dropColumn('pay_basis'));
    }
};
