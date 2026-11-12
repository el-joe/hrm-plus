<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee assets (Phase 8a, spec module 12).
 *
 * - asset_categories: laptop, phone, SIM card, vehicle, uniform, ...;
 * - assets: the catalogue (tag, serial, purchase data, value DECIMAL, condition,
 *   status, location branch — the data scope);
 * - asset_assignments: custody of an asset by an employee: handover date and
 *   condition, the employee's acknowledgement (portal / API), return date and
 *   condition check. Open assignments become offboarding clearance items
 *   (offboarding_items.itemable = the assignment); returning completes the item;
 * - asset_events: the history of each asset (and, through employee_id, of each employee).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description', 1000)->nullable();
            $table->boolean('requires_serial')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 40)->unique();
            $table->foreignId('asset_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 200);
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable()->unique();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 14, 2)->nullable();
            $table->string('supplier', 150)->nullable();
            $table->date('warranty_expires_on')->nullable();
            // Where the asset is kept / managed (carries the data scope).
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            // App\Enums\AssetCondition: new | good | fair | poor | damaged.
            $table->string('condition', 20)->default('good');
            // App\Enums\AssetStatus: available | assigned | in_maintenance | lost | damaged | retired.
            $table->string('status', 20)->default('available')->index();
            $table->string('notes', 2000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_category_id', 'status']);
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('assigned_on');
            $table->date('expected_return_on')->nullable();
            $table->string('condition_on_issue', 20);
            $table->string('handover_notes', 2000)->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            // Onboarding task that asked for the asset (when issued from the checklist).
            $table->foreignId('onboarding_task_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            // portal | api | admin (recorded on paper by HR).
            $table->string('acknowledged_via', 20)->nullable();
            $table->date('returned_on')->nullable();
            $table->string('condition_on_return', 20)->nullable();
            $table->string('return_notes', 2000)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            // App\Enums\AssetAssignmentStatus: active | returned | lost.
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['asset_id', 'status']);
        });

        Schema::create('asset_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            // App\Enums\AssetEventType.
            $table->string('type', 30);
            $table->string('condition', 20)->nullable();
            $table->string('note', 2000)->nullable();
            $table->date('occurred_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'occurred_on']);
            $table->index(['employee_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_events');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
