<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Departments (a tree), teams inside departments, and positions (approved
     * seats: a job title in a department, with a grade, a headcount and the
     * position it reports to). The reporting hierarchy is position based, so it
     * exists before any employee does; Phase 3b places employees in positions.
     *
     * All foreign keys restrict deletion: the application refuses to delete a
     * record that is still referenced and offers deactivation instead.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->foreignId('parent_id')->nullable()->constrained('departments')->restrictOnDelete();
            // Null = a company-wide department not tied to one branch.
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_title_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_grade_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reports_to_position_id')->nullable()->constrained('positions')->restrictOnDelete();
            // Approved number of seats.
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Managers are positions as well: the person holding the position is
        // the manager (Phase 3b resolves it).
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('head_position_id')->nullable()->after('cost_center_id')->constrained('positions')->restrictOnDelete();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('manager_position_id')->nullable()->after('timezone')->constrained('positions')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_position_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('head_position_id');
        });

        Schema::dropIfExists('positions');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('departments');
    }
};
