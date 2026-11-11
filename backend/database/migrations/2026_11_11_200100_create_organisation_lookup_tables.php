<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Company-wide lookup lists of the organisation structure. Every row has a
     * unique code, an Arabic and an English name and an active flag: rows that
     * are in use are deactivated, never deleted.
     */
    public function up(): void
    {
        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('job_grades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            // Seniority order: a higher level is a more senior grade.
            $table->unsignedSmallInteger('level')->index();
            // Salary band in the company currency (setting localisation.currency_code).
            $table->decimal('min_salary', 14, 2)->nullable();
            $table->decimal('max_salary', 14, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('employment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            // Fixed-term types (contracts, internships) require a contract end date on the employee.
            $table->boolean('is_fixed_term')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->foreignId('parent_id')->nullable()->constrained('cost_centers')->restrictOnDelete();
            $table->string('description', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('employment_types');
        Schema::dropIfExists('job_grades');
        Schema::dropIfExists('job_titles');
    }
};
