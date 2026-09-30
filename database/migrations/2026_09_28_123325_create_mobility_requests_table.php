<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mobility requests: where the owner wants to go, from when, until when. The current assignment
     * and the professional context come from the owner's professional profile.
     *
     * `public_id` (ULID) is the only identifier exposed. Soft-deleted: a deleted request stays
     * auditable and can be restored by an administrator.
     */
    public function up(): void
    {
        Schema::create('mobility_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('draft');
            $table->date('available_from');
            $table->date('expires_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobility_requests');
    }
};
