<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Check-in / check-out from the employee web portal: the attempt's channel
 * (mobile app | web portal), optional coordinates (a policy may let web
 * check-ins skip the geofence) and two per-policy-version switches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_mobile_attempts', function (Blueprint $table) {
            // App\Enums\CheckInChannel: mobile | web.
            $table->string('channel', 10)->default('mobile')->after('request_uuid');
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
            $table->decimal('accuracy_meters', 8, 2)->nullable()->change();

            $table->index(['channel', 'server_time']);
        });

        Schema::table('attendance_policy_versions', function (Blueprint $table) {
            $table->boolean('web_checkin_enabled')->default(true);
            $table->boolean('web_checkin_requires_geofence')->default(true);
        });

        // A policy that switched mobile check-in off does not silently open the web portal.
        DB::table('attendance_policy_versions')->where('mobile_checkin_enabled', false)->update(['web_checkin_enabled' => false]);
    }

    public function down(): void
    {
        Schema::table('attendance_policy_versions', function (Blueprint $table) {
            $table->dropColumn(['web_checkin_enabled', 'web_checkin_requires_geofence']);
        });

        Schema::table('attendance_mobile_attempts', function (Blueprint $table) {
            $table->dropIndex(['channel', 'server_time']);
            $table->dropColumn('channel');
        });
    }
};
