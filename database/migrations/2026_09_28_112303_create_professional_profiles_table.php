<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The professional profile of an account (one per account): sector, profession, specialty,
     * grade and current assignment (moughataa, optional establishment).
     *
     * References are restricted on delete: reference entries are deactivated, never deleted. The
     * wilaya is the moughataa's. `professional_identifier` is encrypted and never searchable.
     */
    public function up(): void
    {
        Schema::create('professional_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('profession_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('specialty_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('grade_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('moughataa_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('establishment_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->text('professional_identifier')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_profiles');
    }
};
