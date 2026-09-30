<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matches between mobility requests (PRD sections 7 and 8.2). One row per pair of requests:
     * `pair_key` ("<smaller request id>:<larger request id>") is unique, so A-B and B-A can never
     * both exist. A match whose conditions stop holding is invalidated with a reason, never
     * deleted; it is suggested again if they hold again later.
     *
     * `rules_version` records which rules produced the score, so historical results stay
     * explainable after the rules change.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('type', 16)->default('direct');
            $table->string('pair_key', 64)->unique();
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('score');
            $table->string('rules_version', 32);
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
