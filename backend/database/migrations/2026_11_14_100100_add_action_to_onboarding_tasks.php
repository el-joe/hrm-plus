<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional link of a checklist task to the admin screen that does the work
     * (App\Enums\OnboardingTaskAction, e.g. "assign_schedule").
     */
    public function up(): void
    {
        Schema::table('onboarding_template_tasks', function (Blueprint $table) {
            $table->string('action', 40)->nullable()->after('assignee');
        });

        Schema::table('onboarding_tasks', function (Blueprint $table) {
            $table->string('action', 40)->nullable()->after('assignee');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_tasks', fn (Blueprint $table) => $table->dropColumn('action'));
        Schema::table('onboarding_template_tasks', fn (Blueprint $table) => $table->dropColumn('action'));
    }
};
