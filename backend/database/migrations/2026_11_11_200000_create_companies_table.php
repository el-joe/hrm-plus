<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The company profile: a single row (assumption A1, one company with many
     * branches). Localisation defaults (currency, timezone, date format) stay in
     * the "localisation" settings group so they have one source of truth.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('legal_name_ar', 200)->nullable();
            $table->string('legal_name_en', 200)->nullable();
            $table->string('commercial_registration_no', 50)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website', 200)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('address_ar', 500)->nullable();
            $table->string('address_en', 500)->nullable();
            $table->date('founded_on')->nullable();
            // Path on the "public" disk; served through the company.logo route.
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
