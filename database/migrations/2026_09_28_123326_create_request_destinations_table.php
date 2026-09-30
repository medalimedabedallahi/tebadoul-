<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accepted destinations of a mobility request, in priority order (1 = preferred). A
     * destination is a wilaya, optionally narrowed to a moughataa, optionally to an establishment.
     */
    public function up(): void
    {
        Schema::create('request_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobility_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('priority');
            $table->foreignId('wilaya_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('moughataa_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('establishment_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['mobility_request_id', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_destinations');
    }
};
