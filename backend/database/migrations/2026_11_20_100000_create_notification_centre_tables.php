<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notification centre (Phase 8b, spec module 17).
 *
 * - notification_templates: the text of each event (config/notification_events.php) per channel
 *   (database = in-app, mail, push) and language; seeded from the default lang texts, editable,
 *   "reset to default" copies the default back. Placeholders are {{name}} from the event's whitelist.
 * - notification_preferences: a user's choice per event and channel (no row = the event's default);
 *   mandatory events ignore it.
 * - notification_deliveries: the delivery log of every queued channel send (attempts, status, error).
 * - device_tokens: FCM registration tokens of the mobile app (per user, optionally tied to the Sanctum
 *   token that registered it so logging out removes it).
 * - notification_dispatches: idempotency keys of scheduled automations (e.g. "birthday:2026:12"), so a
 *   reminder is sent once however often the scheduler runs.
 * - role_default_grants: remembers whether permissions:sync actually granted a permission (and whether an
 *   administrator re-granted it later), so a permission dropped from a default role can be revoked safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event', 80);
            // database | mail | push.
            $table->string('channel', 20);
            $table->string('locale', 5);
            // In-app title, e-mail subject, push title.
            $table->string('subject', 255);
            $table->text('body');
            // false while it equals the default text of the lang files.
            $table->boolean('is_customised')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event', 'channel', 'locale']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 80);
            $table->string('channel', 20);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['user_id', 'event', 'channel']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            // The Laravel notification id (shared by the channels of one notification).
            $table->uuid('notification_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 80)->index();
            $table->string('notification_type', 255);
            $table->string('channel', 20);
            // App\Enums\NotificationDeliveryStatus: sending | sent | simulated | failed | skipped.
            $table->string('status', 20)->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            // Recipient address / device count (never the message body).
            $table->string('recipient', 255)->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->string('error', 1000)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status', 'created_at']);
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512);
            $table->char('token_hash', 64)->unique();
            // android | ios | web.
            $table->string('platform', 10);
            $table->string('device_name', 100)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('locale', 5)->nullable();
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
        });

        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->string('event', 80)->index();
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_inbox_index');
        });

        Schema::table('role_default_grants', function (Blueprint $table) {
            // true: permissions:sync gave the role this permission; false: the role already held it.
            $table->boolean('granted_by_sync')->default(true);
            // Set when an administrator adds the permission to the role by hand after sync recorded it.
            $table->timestamp('manually_granted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('role_default_grants', function (Blueprint $table) {
            $table->dropColumn(['granted_by_sync', 'manually_granted_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_inbox_index');
        });

        Schema::dropIfExists('notification_dispatches');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
    }
};
