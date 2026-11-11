<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee management (Phase 3b, spec module 3).
     *
     * - employees: personal data plus the CURRENT job placement, denormalised
     *   from the latest applied employee_assignments row (never edited directly).
     * - employee_assignments / employee_salaries: effective-dated history. A
     *   change is a new row; rows are never updated except to mark a scheduled
     *   (future-dated) row as applied or cancelled.
     * - Highly sensitive values (national id, passport number, bank account,
     *   IBAN) are encrypted with the application key; a keyed hash column keeps
     *   exact-match search possible without decrypting.
     *
     * Foreign keys restrict deletion everywhere: structure records in use are
     * deactivated, employees are terminated, never deleted.
     */
    public function up(): void
    {
        Schema::create('employee_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 30)->unique();

            // Personal information.
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('photo_path')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->char('nationality', 2)->nullable();
            $table->text('national_id')->nullable();
            $table->char('national_id_hash', 64)->nullable()->unique();
            $table->text('passport_number')->nullable();
            $table->char('passport_number_hash', 64)->nullable()->index();
            $table->date('passport_expiry_date')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('personal_email')->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('alternate_phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('country_code', 2)->nullable();

            // Current job placement (mirrors the latest applied assignment).
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('job_grade_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employment_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained('branch_locations')->restrictOnDelete();
            // App\Enums\EmployeeStatus: active | inactive | terminated.
            $table->string('status', 20)->default('active')->index();
            $table->date('hire_date');
            $table->date('original_hire_date');
            $table->date('probation_end_date')->nullable();

            // Current basic salary (mirrors the latest applied employee_salaries row).
            $table->decimal('basic_salary', 14, 2)->nullable();

            // Last termination, cleared on rehire (the history keeps every one).
            $table->date('termination_date')->nullable();
            $table->string('termination_type', 30)->nullable();
            $table->string('termination_reason', 1000)->nullable();
            $table->boolean('eligible_for_rehire')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'branch_id']);
            $table->index('hire_date');
        });

        Schema::create('employee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // App\Enums\AssignmentChangeType.
            $table->string('change_type', 30);
            $table->date('effective_date');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('job_grade_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employment_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained('branch_locations')->restrictOnDelete();
            $table->string('status', 20);
            // Termination details (change_type = termination).
            $table->string('termination_type', 30)->nullable();
            $table->boolean('eligible_for_rehire')->nullable();
            $table->string('reason', 1000)->nullable();
            // Null while a future-dated change waits for its effective date.
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
            $table->index(['applied_at', 'cancelled_at', 'effective_date'], $table->getTable().'_pending_index');
        });

        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // App\Enums\SalaryChangeType.
            $table->string('change_type', 30);
            $table->date('effective_date');
            $table->decimal('basic_salary', 14, 2);
            $table->decimal('previous_salary', 14, 2)->nullable();
            $table->char('currency', 3);
            $table->string('reason', 1000)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
            $table->index(['applied_at', 'cancelled_at', 'effective_date'], $table->getTable().'_pending_index');
        });

        Schema::create('employee_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('bank_name', 150);
            $table->string('account_holder', 150);
            $table->text('account_number')->nullable();
            $table->text('iban')->nullable();
            $table->char('iban_hash', 64)->nullable()->index();
            $table->string('swift_code', 11)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('employee_emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('relationship', 50);
            $table->string('phone', 30);
            $table->string('alternate_phone', 30)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_group_members', function (Blueprint $table) {
            $table->foreignId('employee_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['employee_group_id', 'employee_id']);
            $table->index('employee_id');
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('reference', 50)->nullable();
            // App\Enums\ContractType.
            $table->string('contract_type', 30);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('renewal_of_id')->nullable()->constrained('employee_contracts')->restrictOnDelete();
            $table->date('terminated_on')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->date('expiry_reminded_for')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'start_date']);
            $table->index('end_date');
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // App\Enums\DocumentCategory.
            $table->string('category', 30)->index();
            $table->string('title', 150);
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->boolean('visible_to_employee')->default(false);
            $table->string('notes', 1000)->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 100);
            $table->unsignedInteger('file_size');
            // The expiry date a reminder was last sent for (a new expiry date re-arms it).
            $table->date('expiry_reminded_for')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            // App\Enums\CustomFieldType.
            $table->string('type', 20);
            $table->boolean('is_required')->default(false);
            $table->boolean('visible_to_employee')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Choices of "select" custom fields, one row each.
        Schema::create('employee_custom_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained('employee_custom_fields')->cascadeOnDelete();
            $table->string('value', 60);
            $table->string('label_ar', 150);
            $table->string('label_en', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['custom_field_id', 'value']);
        });

        Schema::create('employee_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained('employee_custom_fields')->restrictOnDelete();
            $table->string('value_text', 2000)->nullable();
            $table->decimal('value_number', 18, 4)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'custom_field_id']);
        });

        // Race-safe counters (employee numbers, later other document numbers).
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->unsignedBigInteger('next_value');
            $table->timestamps();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('leader_employee_id')->nullable()->after('department_id')->constrained('employees')->restrictOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leader_employee_id');
        });

        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('employee_custom_field_values');
        Schema::dropIfExists('employee_custom_field_options');
        Schema::dropIfExists('employee_custom_fields');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('employee_group_members');
        Schema::dropIfExists('employee_emergency_contacts');
        Schema::dropIfExists('employee_bank_accounts');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('employee_assignments');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('employee_groups');
    }
};
