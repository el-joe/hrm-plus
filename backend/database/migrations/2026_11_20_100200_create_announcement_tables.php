<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements and internal communication (Phase 8b, spec module 14).
 *
 * - announcements: bilingual title / body (body = sanitised allow-list HTML, see
 *   App\Services\Announcements\HtmlSanitizer), type, stored status draft | published | archived
 *   ("scheduled" and "expired" are derived from publish_at / expires_at), pinning, mandatory
 *   acknowledgement, channel switches and notified_at (notifications go out once, when visible);
 * - announcement_targets: the audience of a targeted announcement — branches, departments (with their
 *   sub-departments), employee groups, employment types; an employee matching ANY target is in it;
 * - announcement_attachments: files on the private disk "announcement_files";
 * - announcement_reads: per employee, when it was read and acknowledged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title_ar', 200)->nullable();
            $table->string('title_en', 200)->nullable();
            $table->mediumText('body_ar')->nullable();
            $table->mediumText('body_en')->nullable();
            // App\Enums\AnnouncementType: general | important | policy_update | event.
            $table->string('type', 20)->default('general');
            // App\Enums\AnnouncementStatus (stored part): draft | published | archived.
            $table->string('status', 20)->default('draft')->index();
            // all | targeted.
            $table->string('audience', 20)->default('all');
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('requires_acknowledgement')->default(false);
            $table->boolean('notify_email')->default(false);
            $table->boolean('notify_push')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->unsignedInteger('notified_count')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'publish_at', 'expires_at']);
        });

        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            // App\Enums\AnnouncementTargetType: branch | department | employee_group | employment_type.
            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id');

            $table->unique(['announcement_id', 'target_type', 'target_id'], 'announcement_targets_unique');
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('announcement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamp('acknowledged_at')->nullable();
            // portal | api.
            $table->string('acknowledged_via', 10)->nullable();

            $table->unique(['announcement_id', 'employee_id']);
            $table->index(['employee_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcement_attachments');
        Schema::dropIfExists('announcement_targets');
        Schema::dropIfExists('announcements');
    }
};
