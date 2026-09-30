<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Specialties or subjects of a profession.
     *
     * Reference entries are never deleted (profiles and requests will point to them): an entry
     * that no longer applies is deactivated. `code` is the stable identifier used by imports and
     * exposed by the API.
     */
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profession_id')->index()->constrained()->restrictOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name_fr');
            $table->string('name_ar');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};
