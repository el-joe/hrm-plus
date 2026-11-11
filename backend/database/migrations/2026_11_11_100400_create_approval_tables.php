<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            // Stable identifier used by modules, e.g. "leave_request".
            $table->string('key', 100)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            // App\Enums\ApproverType: user | role | permission. Exactly one of the
            // three approver columns below is filled, matching the type.
            $table->string('approver_type', 20);
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->string('approver_permission')->nullable();
            $table->timestamps();

            $table->unique(['approval_workflow_id', 'sequence']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained()->restrictOnDelete();
            $table->morphs('approvable');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            // App\Enums\ApprovalStatus: pending | approved | rejected | cancelled.
            $table->string('status', 20)->default('pending');
            $table->foreignId('current_step_id')->nullable()->constrained('approval_workflow_steps')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'current_step_id']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_workflow_step_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot of the step so history survives later workflow edits.
            $table->unsignedSmallInteger('step_sequence')->nullable();
            $table->string('step_name')->nullable();
            // App\Enums\ApprovalAction: submitted | approved | rejected | cancelled.
            $table->string('action', 20);
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['approval_request_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
