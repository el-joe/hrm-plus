<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5b: attendance capture. Fingerprint devices (adapters, mappings,
     * sync logs, the device side of raw events), mobile GPS attempts with
     * their location data, work-location rules, and face verification
     * (consent, encrypted enrolments, verification results). Every captured
     * punch still becomes an attendance_raw_events row through
     * RawEventRecorder; the tables here hold the source-specific details,
     * linked by attendance_raw_event_id.
     */
    public function up(): void
    {
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('serial_number', 100)->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_location_id')->nullable()->constrained()->nullOnDelete();
            // Key of config('attendance_devices.adapters'); "simulated" until a verified vendor adapter exists.
            $table->string('adapter', 40);
            // App\Enums\DeviceConnectionMode: push | pull | file.
            $table->string('connection_mode', 10);
            $table->string('host', 255)->nullable();
            $table->unsignedInteger('port')->nullable();
            // Pull credentials (device password / comm key), encrypted at rest.
            $table->text('credentials')->nullable();
            // SHA-256 of the push token (the token itself is shown once).
            $table->char('push_token_hash', 64)->nullable()->unique();
            $table->string('push_token_hint', 8)->nullable();
            // Null: the branch timezone.
            $table->string('timezone', 64)->nullable();
            $table->unsignedSmallInteger('pull_interval_minutes')->default(15);
            // Adapter options (small, adapter-specific).
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true)->index();
            // Sync state.
            $table->string('sync_cursor', 191)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status', 20)->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Device user id (enrolment number on the device) -> employee. device_scope = device id, or 0 for every device.
        Schema::create('attendance_device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('device_scope')->default(0);
            $table->string('device_user_id', 64);
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('name_on_device', 150)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['device_scope', 'device_user_id']);
            $table->index('employee_id');
        });

        Schema::create('attendance_device_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained()->cascadeOnDelete();
            // App\Enums\DeviceSyncType: pull | push | historical | file | test.
            $table->string('type', 20);
            // App\Enums\DeviceSyncStatus: running | success | partial | failed.
            $table->string('status', 20);
            $table->boolean('is_simulated')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->dateTime('range_from')->nullable();
            $table->dateTime('range_to')->nullable();
            $table->string('cursor_before', 191)->nullable();
            $table->string('cursor_after', 191)->nullable();
            $table->unsignedInteger('fetched')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('unmatched')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('message', 1000)->nullable();
            $table->string('file_name')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attendance_device_id', 'started_at'], 'device_sync_logs_device_started_index');
            $table->index(['status', 'started_at'], 'device_sync_logs_status_started_index');
        });

        // The device side of a raw event (one row per raw event that came from a device).
        Schema::create('attendance_device_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_raw_event_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_device_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_device_sync_log_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_user_id', 64);
            $table->string('device_log_id', 100)->nullable();
            $table->string('verify_mode', 30)->nullable();
            $table->timestamps();

            $table->index(['attendance_device_id', 'device_user_id'], 'device_events_device_user_index');
        });

        // Which locations an employee may check in at, beyond the default (the active locations of their branch).
        Schema::create('attendance_work_location_rules', function (Blueprint $table) {
            $table->id();
            // App\Enums\WorkLocationScope: employee | department.
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id');
            // Null together with allow_anywhere: remote work (no geofence, every other check still applies).
            $table->foreignId('branch_location_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('allow_anywhere')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('face_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('version', 20);
            $table->timestamp('given_at');
            $table->timestamp('withdrawn_at')->nullable();
            // api | portal | admin
            $table->string('source', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'withdrawn_at']);
        });

        Schema::create('face_enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('face_consent_id')->constrained()->restrictOnDelete();
            $table->string('provider', 40);
            $table->boolean('is_simulated')->default(false);
            // App\Enums\FaceEnrolmentStatus: active | deleted.
            $table->string('status', 20)->index();
            // Provider-side subject id (encrypted). Templates / images: encrypted files on the private biometric disk.
            $table->text('provider_reference')->nullable();
            $table->string('template_path')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('quality_score', 5, 4)->nullable();
            $table->timestamp('enrolled_at');
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            // consent_withdrawn | termination | retention | admin | re_enrolment
            $table->string('deletion_reason', 30)->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('attendance_mobile_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 10);
            // App\Enums\MobileAttemptOutcome: accepted | rejected | flagged.
            $table->string('outcome', 20);
            // App\Enums\ReviewStatus for flagged attempts: pending | approved | rejected.
            $table->string('review_status', 20)->nullable()->index();
            // Reason codes (App\Services\Attendance\Mobile\AttemptReason), e.g. ["outside_geofence"].
            $table->json('reasons')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 8, 2);
            $table->foreignId('branch_location_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->unsignedInteger('allowed_radius_meters')->nullable();
            $table->boolean('within_geofence')->default(false);
            $table->boolean('allow_anywhere')->default(false);
            $table->boolean('is_mock_location')->default(false);
            $table->dateTime('device_time')->nullable();
            $table->dateTime('server_time');
            $table->integer('time_drift_seconds')->nullable();
            $table->string('device_identifier', 191)->nullable();
            $table->string('platform', 20)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->date('work_date')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('within_shift_window')->default(false);
            $table->string('timezone', 64);
            // off | optional | required, as applied to this attempt.
            $table->string('face_mode', 20);
            $table->foreignId('attendance_raw_event_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 1000)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'server_time']);
            $table->index(['outcome', 'server_time']);
        });

        Schema::create('face_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('face_enrolment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_mobile_attempt_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->boolean('is_simulated')->default(false);
            $table->string('direction', 10);
            // App\Enums\FaceVerificationResult.
            $table->string('result', 30);
            $table->decimal('score', 5, 4)->nullable();
            $table->boolean('liveness_passed')->nullable();
            $table->decimal('liveness_score', 5, 4)->nullable();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('provider_reference', 191)->nullable();
            $table->string('error_code', 60)->nullable();
            $table->date('work_date');
            $table->timestamp('verified_at');
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
        });

        // Mobile check-in and face rules, per attendance-policy version (not part of the day calculation).
        Schema::table('attendance_policy_versions', function (Blueprint $table) {
            $table->boolean('mobile_checkin_enabled')->default(true);
            $table->unsignedSmallInteger('gps_max_accuracy_meters')->default(50);
            $table->unsignedSmallInteger('gps_accuracy_tolerance_meters')->default(20);
            $table->unsignedSmallInteger('gps_checkout_extra_meters')->default(0);
            $table->unsignedInteger('gps_max_time_drift_seconds')->default(300);
            $table->string('gps_mock_location_action', 10)->default('review');
            $table->string('gps_outside_window_action', 10)->default('review');
            $table->unsignedSmallInteger('gps_repeated_attempts_threshold')->default(3);
            $table->string('face_verification_mode', 10)->default('required');
            $table->unsignedSmallInteger('face_max_attempts')->default(3);
            $table->string('face_failure_action', 10)->default('review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_policy_versions', function (Blueprint $table) {
            $table->dropColumn([
                'mobile_checkin_enabled', 'gps_max_accuracy_meters', 'gps_accuracy_tolerance_meters', 'gps_checkout_extra_meters',
                'gps_max_time_drift_seconds', 'gps_mock_location_action', 'gps_outside_window_action', 'gps_repeated_attempts_threshold',
                'face_verification_mode', 'face_max_attempts', 'face_failure_action',
            ]);
        });

        foreach ([
            'face_verifications', 'attendance_mobile_attempts', 'face_enrolments', 'face_consents', 'attendance_work_location_rules',
            'attendance_device_events', 'attendance_device_sync_logs', 'attendance_device_users', 'attendance_devices',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
