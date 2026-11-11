<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branches and their physical locations. A location carries the GPS point
     * and geofence radius that the attendance engine (Phase 5) validates
     * mobile check-ins against.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->boolean('is_headquarters')->default(false);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('address_ar', 500)->nullable();
            $table->string('address_en', 500)->nullable();
            // Overrides localisation.timezone for this branch when set.
            $table->string('timezone', 64)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('branch_locations', function (Blueprint $table) {
            $table->id();
            // Locations belong to their branch; a branch can only be deleted when nothing uses it.
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_locations');
        Schema::dropIfExists('branches');
    }
};
