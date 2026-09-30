<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schools and health facilities, located in a moughataa.
     *
     * Reference entries are never deleted (profiles and requests will point to them): an entry
     * that no longer applies is deactivated. `code` is the stable identifier used by imports and
     * exposed by the API.
     */
    public function up(): void
    {
        Schema::create('establishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->foreignId('moughataa_id')->constrained()->restrictOnDelete();
            $table->string('code', 64)->unique();
            $table->string('type', 64);
            $table->string('name_fr');
            $table->string('name_ar');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['sector_id', 'moughataa_id']);
            $table->index('moughataa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('establishments');
    }
};
