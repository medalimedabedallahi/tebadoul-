<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invitations and consents (step 7): each participant's decision on the match, and whether
     * they currently consent to share their contact details with the other participant.
     * `matches.outcome_reason` keeps the optional reason of a decline.
     */
    public function up(): void
    {
        Schema::table('match_participants', function (Blueprint $table) {
            $table->string('decision', 16)->nullable()->after('user_id');
            $table->timestamp('decided_at')->nullable()->after('decision');
            $table->timestamp('contact_consented_at')->nullable()->after('decided_at');

            // An account takes part in a match once: its decision and consent are unambiguous.
            $table->unique(['match_id', 'user_id']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->string('outcome_reason', 32)->nullable()->after('invalidation_reason');

            // Hourly expiry of unanswered invitations.
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn('outcome_reason');
        });

        Schema::table('match_participants', function (Blueprint $table) {
            $table->dropUnique(['match_id', 'user_id']);
            $table->dropColumn(['decision', 'decided_at', 'contact_consented_at']);
        });
    }
};
