<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explanation of a match score: one row per criterion, with the points obtained, the
     * maximum, and a short machine-readable detail (never personal data).
     */
    public function up(): void
    {
        Schema::create('match_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('criterion', 32);
            $table->unsignedSmallInteger('points');
            $table->unsignedSmallInteger('max_points');
            $table->string('detail', 64);
            $table->timestamps();

            $table->unique(['match_id', 'criterion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_reasons');
    }
};
