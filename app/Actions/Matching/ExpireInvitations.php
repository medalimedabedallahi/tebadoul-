<?php

namespace App\Actions\Matching;

use App\Enums\MatchStatus;
use App\Models\MobilityMatch;

/**
 * Scheduled every hour: invitations left unanswered past their deadline expire (PRD 8.2
 * "le delai d'action est depasse"). `expired` is final. A conditional UPDATE per row: safe when
 * a participant answers at the same moment.
 */
final class ExpireInvitations
{
    public function handle(): int
    {
        return MobilityMatch::query()
            ->where('status', MatchStatus::Invited)
            ->where('expires_at', '<', now())
            ->update(['status' => MatchStatus::Expired, 'updated_at' => now()]);
    }
}
