<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recruitment & applicant tracking (Phase 4, spec module 4).
     *
     * - recruitment_stages: the pipeline. Rows with vacancy_id NULL are the
     *   company default; a vacancy with uses_custom_pipeline has its own rows.
     *   `category` gives a stage its meaning (open / interview / offer / hired /
     *   rejected / withdrawn) so the code never depends on a stage's name.
     * - candidates: one row per person (duplicates detected by email and
     *   normalised phone); job_applications link a candidate to a vacancy and
     *   carry the current stage; application_stage_changes is the history.
     * - Salaries (vacancy range, expected / current / offered) are DECIMAL and
     *   only shown to holders of employee_financials.view (access is logged).
     * - Files live on the private "recruitment_files" disk, never under public/.
     */
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('title_ar', 150);
            $table->string('title_en', 150);
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->text('requirements_ar')->nullable();
            $table->text('requirements_en')->nullable();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('openings')->default(1);
            $table->unsignedSmallInteger('filled_count')->default(0);
            $table->decimal('salary_min', 14, 2)->nullable();
            $table->decimal('salary_max', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            // App\Enums\VacancyStatus.
            $table->string('status', 20)->default('draft')->index();
            // Listed on the public career page while open and inside the publish window.
            $table->boolean('is_public')->default(false);
            $table->date('publish_at')->nullable();
            $table->date('application_deadline')->nullable();
            $table->boolean('uses_custom_pipeline')->default(false);
            $table->foreignId('evaluation_form_id')->nullable();
            $table->foreignId('hiring_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'is_public', 'publish_at']);
            $table->index(['branch_id', 'department_id']);
        });

        Schema::create('recruitment_stages', function (Blueprint $table) {
            $table->id();
            // NULL: the company-wide default pipeline.
            $table->foreignId('vacancy_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name_ar', 100);
            $table->string('name_en', 100);
            // App\Enums\StageCategory.
            $table->string('category', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            // E-mail the candidate when moved into this stage.
            $table->boolean('notify_candidate')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['vacancy_id', 'sort_order']);
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150)->nullable();
            $table->string('name_en', 150)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable();
            // Digits only, for duplicate detection.
            $table->string('phone_normalized', 30)->nullable()->index();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->char('nationality', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('current_job_title', 150)->nullable();
            $table->string('current_employer', 150)->nullable();
            $table->decimal('experience_years', 4, 1)->nullable();
            $table->text('qualifications')->nullable();
            $table->json('skills')->nullable();
            $table->decimal('expected_salary', 14, 2)->nullable();
            $table->decimal('current_salary', 14, 2)->nullable();
            // App\Enums\CandidateSource.
            $table->string('source', 30)->index();
            $table->string('source_detail', 150)->nullable();
            $table->foreignId('referred_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('linkedin_url', 255)->nullable();
            $table->string('portfolio_url', 255)->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cv_name')->nullable();
            $table->string('cv_mime', 100)->nullable();
            $table->unsignedInteger('cv_size')->nullable();
            // Privacy consent given on the public career page.
            $table->timestamp('consent_at')->nullable();
            // Language for e-mails to the candidate (ar | en).
            $table->char('locale', 2)->nullable();
            // Set when the candidate is hired (the employee keeps candidate_id too).
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('candidate_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('candidate_tag', function (Blueprint $table) {
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['candidate_id', 'candidate_tag_id']);
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->restrictOnDelete();
            $table->foreignId('vacancy_id')->constrained()->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('recruitment_stages')->restrictOnDelete();
            // App\Enums\ApplicationStatus, derived from the stage category.
            $table->string('status', 20)->default('active')->index();
            // App\Enums\CandidateSource (where this application came from).
            $table->string('source', 30);
            $table->string('cover_letter', 5000)->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('stage_changed_at')->nullable();
            // Weighted average of the submitted scorecards, recomputed on each submission.
            $table->decimal('average_score', 5, 2)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('hired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['candidate_id', 'vacancy_id']);
            $table->index(['vacancy_id', 'stage_id']);
        });

        Schema::create('application_stage_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('recruitment_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('recruitment_stages')->nullOnDelete();
            // Snapshot of the stage names so history survives stage edits.
            $table->string('from_stage_name', 100)->nullable();
            $table->string('to_stage_name', 100);
            $table->string('note', 1000)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['job_application_id', 'created_at']);
        });

        Schema::create('candidate_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('candidate_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 100);
            $table->unsignedInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Interviews ---------------------------------------------------------

        Schema::create('evaluation_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_form_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 500)->nullable();
            // Relative weight; the aggregate is normalised by the sum of weights.
            $table->unsignedTinyInteger('weight')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('vacancies', function (Blueprint $table) {
            $table->foreign('evaluation_form_id')->references('id')->on('evaluation_forms')->nullOnDelete();
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->restrictOnDelete();
            // App\Enums\InterviewType.
            $table->string('type', 20);
            // Stored in UTC; `timezone` is the zone it was scheduled in and is shown to people.
            $table->dateTime('scheduled_at');
            $table->string('timezone', 64);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            // App\Enums\InterviewMode.
            $table->string('mode', 20);
            $table->string('location', 255)->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->foreignId('evaluation_form_id')->nullable()->constrained()->nullOnDelete();
            // App\Enums\InterviewStatus.
            $table->string('status', 20)->default('scheduled')->index();
            // App\Enums\InterviewDecision.
            $table->string('decision', 20)->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->decimal('average_score', 5, 2)->nullable();
            $table->string('notes', 2000)->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        // Interviewers are login accounts: admin users, or employees through their employee account.
        Schema::create('interview_interviewers', function (Blueprint $table) {
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->primary(['interview_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('interview_scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // App\Enums\ScorecardRecommendation.
            $table->string('recommendation', 20);
            // Weighted score on the evaluation scale (config recruitment.score_scale).
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['interview_id', 'user_id']);
        });

        Schema::create('interview_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_scorecard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_criterion_id')->constrained('evaluation_criteria')->restrictOnDelete();
            $table->unsignedTinyInteger('score');
            $table->string('comment', 500)->nullable();

            $table->unique(['interview_scorecard_id', 'evaluation_criterion_id'], 'interview_scores_unique');
        });

        // Offers -------------------------------------------------------------

        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('job_application_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('basic_salary', 14, 2)->nullable();
            $table->char('currency', 3);
            $table->date('start_date');
            $table->date('expires_on');
            $table->unsignedTinyInteger('probation_months')->nullable();
            $table->text('benefits')->nullable();
            $table->text('terms')->nullable();
            // App\Enums\OfferStatus.
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('response_note', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Candidate-to-employee lineage.
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('candidate_id')->nullable()->after('employee_number')->constrained()->restrictOnDelete();
            $table->foreignId('job_application_id')->nullable()->after('candidate_id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_application_id');
            $table->dropConstrainedForeignId('candidate_id');
        });

        Schema::dropIfExists('job_offers');
        Schema::dropIfExists('interview_scores');
        Schema::dropIfExists('interview_scorecards');
        Schema::dropIfExists('interview_interviewers');
        Schema::dropIfExists('interviews');
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropForeign(['evaluation_form_id']);
        });
        Schema::dropIfExists('evaluation_criteria');
        Schema::dropIfExists('evaluation_forms');
        Schema::dropIfExists('candidate_attachments');
        Schema::dropIfExists('candidate_notes');
        Schema::dropIfExists('application_stage_changes');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('candidate_tag');
        Schema::dropIfExists('candidate_tags');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('recruitment_stages');
        Schema::dropIfExists('vacancies');
    }
};
