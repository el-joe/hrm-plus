<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disciplinary actions and warnings (Phase 8a, spec module 15). Restricted:
 * disciplinary.view only; every view is written to the sensitive-data access log.
 *
 * - violation_types: configurable catalogue (severity, suggested first action);
 * - disciplinary_cases: an incident about an employee, its investigation and outcome;
 * - disciplinary_case_notes: investigation notes / interview records / findings;
 * - disciplinary_evidence: supporting documents on the private disk "disciplinary_files";
 * - disciplinary_actions: the proposed / approved outcome (verbal → written → final
 *   warning escalation, suspension, salary deduction — a pending deduction pay entry
 *   with pay_entries.source = the action), approved through the approval engine
 *   (morph "disciplinary_action"), then issued to the employee who may respond.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violation_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            // App\Enums\ViolationSeverity: minor | moderate | major | gross.
            $table->string('severity', 20)->default('minor');
            // App\Enums\DisciplinaryActionType suggested for a first offence.
            $table->string('default_action', 30)->default('verbal_warning');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('disciplinary_cases', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('violation_type_id')->constrained()->restrictOnDelete();
            // Placement when reported (data scope and reports).
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('incident_date');
            $table->string('location', 255)->nullable();
            $table->text('description');
            // App\Enums\DisciplinaryCaseStatus: reported | investigating | pending_approval | action_issued | closed | dismissed.
            $table->string('status', 30)->default('reported')->index();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('investigator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('findings')->nullable();
            $table->string('closure_note', 2000)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'incident_date']);
        });

        Schema::create('disciplinary_case_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->cascadeOnDelete();
            // App\Enums\DisciplinaryNoteType: note | interview | finding.
            $table->string('type', 20)->default('note');
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('disciplinary_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->cascadeOnDelete();
            $table->string('description', 500)->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('disciplinary_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // App\Enums\DisciplinaryActionType: verbal_warning | written_warning | final_warning | suspension | salary_deduction | termination.
            $table->string('type', 30);
            // Warning level (1 verbal, 2 written, 3 final) — escalation reads the active ones.
            $table->unsignedTinyInteger('warning_level')->nullable();
            // The level the escalation rule suggested when this action was proposed.
            $table->unsignedTinyInteger('suggested_level')->nullable();
            $table->text('details');
            $table->date('effective_date');
            // Warnings stop counting for escalation after this date.
            $table->date('expires_on')->nullable();
            $table->date('suspension_start')->nullable();
            $table->date('suspension_end')->nullable();
            $table->boolean('suspension_paid')->nullable();
            $table->decimal('deduction_amount', 14, 2)->nullable();
            $table->foreignId('pay_entry_id')->nullable()->constrained()->nullOnDelete();
            // App\Enums\DisciplinaryActionStatus: pending_approval | approved | rejected | cancelled.
            $table->string('status', 20)->default('pending_approval')->index();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 1000)->nullable();
            // Issued to the employee (visible in the portal) once approved.
            $table->timestamp('issued_at')->nullable();
            $table->date('response_due_on')->nullable();
            $table->text('employee_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_actions');
        Schema::dropIfExists('disciplinary_evidence');
        Schema::dropIfExists('disciplinary_case_notes');
        Schema::dropIfExists('disciplinary_cases');
        Schema::dropIfExists('violation_types');
    }
};
