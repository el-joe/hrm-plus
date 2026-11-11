<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables for the test-only models in tests/Fixtures. Loaded only by the test
 * suite (see Tests\TestCase), never by the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoped_records', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
        });

        Schema::create('scoped_record_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scoped_record_id')->constrained()->cascadeOnDelete();
            $table->string('body');
        });

        Schema::create('approvable_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvable_documents');
        Schema::dropIfExists('scoped_record_notes');
        Schema::dropIfExists('scoped_records');
    }
};
