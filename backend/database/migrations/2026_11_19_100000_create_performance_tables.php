<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance management (Phase 8a, spec module 10).
 *
 * - rating_scales (+ levels): configurable scales; each level has a value (what a
 *   reviewer gives an item, e.g. 1–5) and a min_score threshold that maps a weighted
 *   overall score to the final rating band;
 * - competencies: the competency framework (bilingual, by category);
 * - performance_cycles (+ cycle competencies with weights): annual, semi-annual,
 *   quarterly or probation cycles with the goals / competencies split of the score;
 * - performance_reviews: one per employee and cycle (reviewer = the manager as an
 *   employee), self and manager scores, overall weighted score, final rating,
 *   calibration through the approval engine (morph "performance_review"), and the
 *   employee's acknowledgement;
 * - performance_goals (+ updates): goals and KPIs (weight, target, progress history);
 * - performance_review_competencies: per-review competency ratings.
 *
 * Scores are DECIMAL and computed with bcmath (App\Services\Performance\PerformanceScoring).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_scales', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('rating_scale_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rating_scale_id')->constrained()->cascadeOnDelete();
            // The rating a reviewer gives an item (e.g. 1 … 5).
            $table->decimal('value', 5, 2);
            $table->string('label_ar', 100);
            $table->string('label_en', 100);
            $table->string('description', 500)->nullable();
            // Lowest weighted overall score that earns this level as the final rating.
            $table->decimal('min_score', 5, 2);
            // Badge colour token (chart-1 … chart-6).
            $table->string('color', 20)->default('chart-1');
            $table->timestamps();

            $table->unique(['rating_scale_id', 'value']);
        });

        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            // App\Enums\CompetencyCategory: core | leadership | technical | behavioural.
            $table->string('category', 20)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            // App\Enums\PerformanceCycleType: annual | semi_annual | quarterly | probation.
            $table->string('type', 20)->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('rating_scale_id')->constrained()->restrictOnDelete();
            // Split of the overall score; both add up to 100.
            $table->decimal('goals_weight', 5, 2)->default(70);
            $table->decimal('competencies_weight', 5, 2)->default(30);
            $table->boolean('self_assessment_enabled')->default(true);
            $table->date('self_assessment_due')->nullable();
            $table->date('manager_assessment_due')->nullable();
            // Participants (null = everybody): limited to a branch / department.
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            // App\Enums\PerformanceCycleStatus: draft | active | closed.
            $table->string('status', 20)->default('draft')->index();
            $table->string('description', 2000)->nullable();
            $table->timestamp('launched_at')->nullable();
            $table->foreignId('launched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
        });

        Schema::create('performance_cycle_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained()->restrictOnDelete();
            $table->decimal('weight', 5, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['performance_cycle_id', 'competency_id'], 'performance_cycle_competency_unique');
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('performance_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            // The reviewing manager (an employee; admins linked to that employee act as reviewer).
            $table->foreignId('reviewer_id')->nullable()->constrained('employees')->nullOnDelete();
            // Placement when the review was created (reports group by these).
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            // App\Enums\PerformanceReviewStatus.
            $table->string('status', 30)->default('self_assessment')->index();
            // Weighted scores on the cycle's rating scale (e.g. 3.75 of 5).
            $table->decimal('self_goals_score', 5, 2)->nullable();
            $table->decimal('self_competencies_score', 5, 2)->nullable();
            $table->decimal('self_overall_score', 5, 2)->nullable();
            $table->decimal('goals_score', 5, 2)->nullable();
            $table->decimal('competencies_score', 5, 2)->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            // Final rating band (from overall_score, or set by calibration).
            $table->foreignId('final_rating_level_id')->nullable()->constrained('rating_scale_levels')->nullOnDelete();
            $table->boolean('calibrated')->default(false);
            $table->string('calibration_note', 2000)->nullable();
            $table->text('self_comments')->nullable();
            $table->text('manager_strengths')->nullable();
            $table->text('manager_improvements')->nullable();
            $table->text('manager_comments')->nullable();
            $table->text('employee_comment')->nullable();
            $table->timestamp('self_submitted_at')->nullable();
            $table->timestamp('manager_submitted_at')->nullable();
            $table->foreignId('manager_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['performance_cycle_id', 'employee_id']);
            $table->index(['employee_id', 'status']);
            $table->index('reviewer_id');
        });

        Schema::create('performance_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('performance_cycle_id')->nullable()->constrained()->restrictOnDelete();
            // App\Enums\GoalType: goal | kpi.
            $table->string('type', 10)->default('goal');
            $table->string('title', 200);
            $table->text('description')->nullable();
            // KPIs: unit, target and the latest actual value (progress = actual / target).
            $table->string('unit', 30)->nullable();
            $table->decimal('target_value', 14, 2)->nullable();
            $table->decimal('current_value', 14, 2)->nullable();
            $table->decimal('weight', 5, 2)->default(0);
            $table->decimal('progress', 5, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            // App\Enums\GoalStatus: active | completed | cancelled.
            $table->string('status', 20)->default('active')->index();
            // Ratings on the cycle's scale.
            $table->decimal('self_rating', 5, 2)->nullable();
            $table->decimal('manager_rating', 5, 2)->nullable();
            $table->string('self_comment', 2000)->nullable();
            $table->string('manager_comment', 2000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'performance_cycle_id']);
        });

        Schema::create('performance_goal_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_goal_id')->constrained()->cascadeOnDelete();
            $table->decimal('progress', 5, 2);
            $table->decimal('current_value', 14, 2)->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('performance_review_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained()->restrictOnDelete();
            $table->decimal('weight', 5, 2);
            $table->decimal('self_rating', 5, 2)->nullable();
            $table->decimal('manager_rating', 5, 2)->nullable();
            $table->string('self_comment', 2000)->nullable();
            $table->string('manager_comment', 2000)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['performance_review_id', 'competency_id'], 'performance_review_competency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_review_competencies');
        Schema::dropIfExists('performance_goal_updates');
        Schema::dropIfExists('performance_goals');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_cycle_competencies');
        Schema::dropIfExists('performance_cycles');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('rating_scale_levels');
        Schema::dropIfExists('rating_scales');
    }
};
