<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR requests and services (Phase 8b, spec module 13).
 *
 * - hr_letter_templates: bilingual letter texts with {{placeholders}} (salary certificate, experience letter…);
 * - hr_request_categories: what an employee may ask for — type general | letter | profile_change, SLA,
 *   default priority / assignee / escalation target, optional approval (workflow "hr_request"), attachment rule;
 * - hr_request_category_fields + hr_request_field_values: extra questions per category and the answers;
 * - hr_requests: the request (reference HRQ-YYYY-NNNNN), status, assignment, SLA due time, escalation level;
 * - hr_request_messages: the thread — employee-visible replies, HR-only internal notes, system lines;
 * - hr_request_attachments: files on the private disk "hr_request_files" (HR-only ones flagged is_internal,
 *   issued letters flagged is_letter);
 * - hr_request_events: the full history (submission, assignment, status changes, escalations, approvals,
 *   letters, applied profile changes) with actor and old / new values;
 * - hr_profile_changes: the fields of a profile change request, old and new values encrypted at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->text('body_ar');
            $table->text('body_en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hr_request_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description_ar', 1000)->nullable();
            $table->string('description_en', 1000)->nullable();
            // App\Enums\HrRequestCategoryType: general | letter | profile_change.
            $table->string('type', 20)->default('general');
            $table->foreignId('hr_letter_template_id')->nullable()->constrained()->nullOnDelete();
            // Hours from opening to the due time; null = setting hr_requests.default_sla_hours.
            $table->unsignedInteger('sla_hours')->nullable();
            $table->string('default_priority', 10)->default('normal');
            $table->foreignId('default_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('escalation_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hr_request_category_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_category_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('label_ar', 150);
            $table->string('label_en', 150);
            // App\Enums\HrRequestFieldType: text | textarea | date | number | select.
            $table->string('type', 10)->default('text');
            // Choices of a select field (list of strings).
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['hr_request_category_id', 'key']);
        });

        Schema::create('hr_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('hr_request_category_id')->constrained()->restrictOnDelete();
            // Placement when submitted (reports).
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 200);
            $table->text('description')->nullable();
            // App\Enums\HrRequestPriority.
            $table->string('priority', 10)->default('normal');
            // App\Enums\HrRequestStatus.
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            // App\Enums\HrRequestSource: portal | api | admin.
            $table->string('source', 10)->default('portal');
            // Language of an issued letter (ar | en).
            $table->string('letter_language', 5)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('last_escalated_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('hr_request_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hr_request_category_field_id')->nullable()->constrained()->nullOnDelete();
            // Copied so the answer stays readable if the field is later edited or removed.
            $table->string('label_ar', 150);
            $table->string('label_en', 150);
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_request_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_id')->constrained()->cascadeOnDelete();
            // App\Enums\HrRequestMessageType: reply | internal_note | system.
            $table->string('type', 20);
            $table->text('body');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            // true when written by the employee (portal / app).
            $table->boolean('from_employee')->default(false);
            $table->timestamps();

            $table->index(['hr_request_id', 'type']);
        });

        Schema::create('hr_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hr_request_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 100);
            $table->unsignedBigInteger('file_size');
            // HR-only file (attached to an internal note); never listed for the employee.
            $table->boolean('is_internal')->default(false);
            // An issued letter (PDF snapshot).
            $table->boolean('is_letter')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_id')->constrained()->cascadeOnDelete();
            // submitted, assigned, status_changed, escalated, approval_approved, approval_rejected, letter_issued,
            // changes_applied, changes_rejected, attachment_added, priority_changed.
            $table->string('type', 30);
            $table->string('from_value', 100)->nullable();
            $table->string('to_value', 100)->nullable();
            $table->string('note', 1000)->nullable();
            // false: HR-only (assignment, escalation); true: shown in the employee's status history.
            $table->boolean('visible_to_employee')->default(false);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['hr_request_id', 'created_at']);
        });

        Schema::create('hr_profile_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_request_id')->constrained()->cascadeOnDelete();
            // App\Services\HrRequests\ProfileChangeFields key (phone, national_id, bank.iban, emergency.phone, …).
            $table->string('field', 40);
            // Encrypted (may hold identity numbers or bank details).
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            // App\Enums\HrProfileChangeStatus: pending | applied | rejected.
            $table->string('status', 10)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hr_request_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_profile_changes');
        Schema::dropIfExists('hr_request_events');
        Schema::dropIfExists('hr_request_attachments');
        Schema::dropIfExists('hr_request_messages');
        Schema::dropIfExists('hr_request_field_values');
        Schema::dropIfExists('hr_requests');
        Schema::dropIfExists('hr_request_category_fields');
        Schema::dropIfExists('hr_request_categories');
        Schema::dropIfExists('hr_letter_templates');
    }
};
