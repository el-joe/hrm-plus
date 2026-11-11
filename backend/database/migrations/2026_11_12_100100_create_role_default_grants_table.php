<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remembers which default permissions `php artisan permissions:sync` has
     * already granted to each default role. A permission granted once and later
     * removed by an administrator is therefore never granted again, while a
     * permission added to the catalogue later is (see App\Services\Authorization\PermissionSynchroniser).
     */
    public function up(): void
    {
        Schema::create('role_default_grants', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permission', 125);
            $table->timestamp('granted_at')->nullable();

            $table->primary(['role_id', 'permission']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_default_grants');
    }
};
