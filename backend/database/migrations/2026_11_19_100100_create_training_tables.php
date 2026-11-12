<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Training and development (Phase 8a, spec module 11).
 *
 * - training_providers, training_programmes, training_courses (cost per participant,
 *   certificate validity, skills gained through training_course_skills);
 * - training_sessions: dated runs of a course (location / online link, capacity);
 * - training_enrolments: nominations (HR / manager) and self-requests; requests that
 *   need approval go through the approval engine (morph "training_enrolment"). The cost
 *   and the employee's branch / department are snapshotted for budgets and reports;
 * - training_attendances: one row per enrolment and session day;
 * - training_certificates: certificates, licences and qualifications (file on the
 *   private disk "training_files"), with expiry and reminder tracking;
 * - skills + employee_skills: the skills matrix (level 1–5, manual or from a course);
 * - training_budgets: per year and department (null department = company-wide).
 *
 * Money is DECIMAL and computed with bcmath.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            // App\Enums\TrainingProviderType: internal | external.
            $table->string('type', 20)->default('external');
            $table->string('contact_name', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('training_programmes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 2000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('category', 50)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('training_programme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_provider_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 2000)->nullable();
            // App\Enums\TrainingDeliveryMode: classroom | online | blended.
            $table->string('delivery_mode', 20)->default('classroom');
            $table->decimal('duration_hours', 6, 2)->default(0);
            $table->decimal('cost_per_participant', 14, 2)->default(0);
            $table->boolean('issues_certificate')->default(false);
            // Months a certificate of this course stays valid (null = no expiry).
            $table->unsignedSmallInteger('certificate_validity_months')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('training_course_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            // Level (1–5) an employee reaches on completing the course.
            $table->unsignedTinyInteger('level');
            $table->timestamps();

            $table->unique(['training_course_id', 'skill_id']);
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('training_course_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('delivery_mode', 20);
            $table->string('location', 255)->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->unsignedInteger('capacity');
            $table->string('trainer', 150)->nullable();
            // Null = the course's cost per participant.
            $table->decimal('cost_per_participant', 14, 2)->nullable();
            // Optional: a session held for one branch.
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            // App\Enums\TrainingSessionStatus: scheduled | completed | cancelled.
            $table->string('status', 20)->default('scheduled')->index();
            $table->string('notes', 2000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('start_date');
        });

        Schema::create('training_enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // App\Enums\TrainingEnrolmentSource: hr | manager | self.
            $table->string('source', 20);
            // App\Enums\TrainingEnrolmentStatus: pending | enrolled | completed | failed | no_show | rejected | cancelled.
            $table->string('status', 20)->index();
            // Snapshots for budgets and reports.
            $table->decimal('cost', 14, 2)->default(0);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 1000)->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('completion_note', 1000)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['training_session_id', 'employee_id']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('training_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_enrolment_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // App\Enums\TrainingAttendanceStatus: present | absent | late | excused.
            $table->string('status', 20);
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['training_enrolment_id', 'date']);
        });

        Schema::create('training_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('training_enrolment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_course_id')->nullable()->constrained()->nullOnDelete();
            // App\Enums\CertificateType: certificate | licence | qualification.
            $table->string('type', 20)->default('certificate');
            $table->string('name', 200);
            $table->string('issuer', 200)->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'expires_on']);
        });

        Schema::create('employee_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            // App\Enums\SkillSource: manual | training.
            $table->string('source', 20)->default('manual');
            $table->foreignId('training_enrolment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('assessed_on')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'skill_id']);
        });

        Schema::create('training_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            // Null = company-wide budget (enrolments of every department not budgeted separately).
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_budgets');
        Schema::dropIfExists('employee_skills');
        Schema::dropIfExists('training_certificates');
        Schema::dropIfExists('training_attendances');
        Schema::dropIfExists('training_enrolments');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_course_skills');
        Schema::dropIfExists('training_courses');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('training_programmes');
        Schema::dropIfExists('training_providers');
    }
};
