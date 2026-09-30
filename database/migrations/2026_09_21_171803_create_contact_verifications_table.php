<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_UNIQUE_INDEX = 'contact_verifications_active_unique';

    /**
     * One-time codes sent to a contact to verify it or to reset a password.
     *
     * `code_hash` is a keyed hash: the code itself is never stored. `consumed_at` is set when the
     * code is used and also when a newer code supersedes it, so a row with a null `consumed_at`
     * is the single active code of its (contact, purpose) pair. That invariant is enforced by a
     * partial unique index, which both SQLite and PostgreSQL support.
     */
    public function up(): void
    {
        Schema::create('contact_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('channel', 16);
            $table->string('contact');
            $table->string('code_hash', 64);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['contact', 'purpose']);
            $table->index('expires_at');
        });

        DB::statement(
            'CREATE UNIQUE INDEX '.self::ACTIVE_UNIQUE_INDEX
            .' ON contact_verifications (contact, purpose) WHERE consumed_at IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_verifications');
    }
};
