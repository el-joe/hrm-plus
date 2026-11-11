<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onboarding & offboarding (Phase 4, spec module 5).
     *
     * - onboarding_templates (+ tasks): configurable checklists matched to a new
     *   hire by position, department and employment type (most specific wins).
     *   Starting an onboarding COPIES the template tasks, so editing a template
     *   never changes onboardings already running.
     * - offboarding_cases (+ items): resignation / termination / contract
     *   expiry, exit interview, handover checklist. Items may point at a source
     *   record (itemable morph) — the seam the Assets module plugs into.
     *   Completing a case terminates the employee (EmploymentHistory keeps every
     *   record) and switches off their logins.
     */
    public function up(): void
    {
        Schema::create('onboarding_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            // Matching criteria; NULL = any.
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('onboarding_template_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_template_id')->constrained()->cascadeOnDelete();
            $table->string('title_ar', 200);
            $table->string('title_en', 200);
            $table->string('description', 1000)->nullable();
            // App\Enums\TaskAssignee.
            $table->string('assignee', 20);
            // Days relative to the start date (negative = before the first day).
            $table->smallInteger('due_offset_days')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('onboarding_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_application_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date');
            // App\Enums\OnboardingStatus.
            $table->string('status', 20)->default('in_progress')->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained()->cascadeOnDelete();
            $table->string('title_ar', 200);
            $table->string('title_en', 200);
            $table->string('description', 1000)->nullable();
            $table->string('assignee', 20);
            $table->date('due_date')->nullable();
            // App\Enums\TaskStatus.
            $table->string('status', 20)->default('pending');
            $table->string('note', 1000)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['onboarding_id', 'status']);
        });

        Schema::create('offboarding_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // App\Enums\TerminationType (resignation, dismissal, contract_end, ...).
            $table->string('type', 30);
            // App\Enums\OffboardingSource: manual | contract_expiry.
            $table->string('source', 20)->default('manual');
            $table->foreignId('employee_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->date('notice_date')->nullable();
            $table->date('last_working_date');
            $table->string('reason', 1000)->nullable();
            $table->boolean('eligible_for_rehire')->default(true);
            // App\Enums\OffboardingStatus.
            $table->string('status', 20)->default('open')->index();
            // Exit interview.
            $table->timestamp('exit_interview_at')->nullable();
            $table->foreignId('exit_interviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('exit_interview_answers')->nullable();
            $table->text('exit_interview_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('employee_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('offboarding_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offboarding_case_id')->constrained()->cascadeOnDelete();
            // App\Enums\HandoverCategory.
            $table->string('category', 20);
            $table->string('title', 200);
            $table->string('description', 1000)->nullable();
            $table->string('assignee', 20);
            // Source record of the item (e.g. an assigned asset in a later phase).
            $table->nullableMorphs('itemable');
            $table->string('status', 20)->default('pending');
            $table->string('note', 1000)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offboarding_items');
        Schema::dropIfExists('offboarding_cases');
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('onboardings');
        Schema::dropIfExists('onboarding_template_tasks');
        Schema::dropIfExists('onboarding_templates');
    }
};
