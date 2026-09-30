<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail of contact sharing (ADR 0001, decision 6): every consent given, every consent
     * revoked and every reveal of the other participant's contact details, time-stamped.
     * Append-only; rows are never updated. Restricted on delete so the trail is retained.
     */
    public function up(): void
    {
        Schema::create('contact_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 16);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['match_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_events');
    }
};
