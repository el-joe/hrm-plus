<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No real face verification provider is configured yet (blocker B4), so new
 * attendance-policy versions default to "optional" instead of "required".
 * Existing versions keep their stored value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policy_versions', function (Blueprint $table) {
            $table->string('face_verification_mode', 10)->default('optional')->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_policy_versions', function (Blueprint $table) {
            $table->string('face_verification_mode', 10)->default('required')->change();
        });
    }
};
