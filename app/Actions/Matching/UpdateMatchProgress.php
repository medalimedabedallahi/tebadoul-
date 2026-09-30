<?php

namespace App\Actions\Matching;

use App\Actions\Matching\Concerns\ManagesParticipation;
use App\Enums\MatchStatus;
use App\Exceptions\Matching\InvalidMatchTransitionException;
use App\Models\MobilityMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Records the administrative progress of an agreement (PRD 5.2, step 5): in discussion, file
 * prepared, submitted, then approved or rejected. Steps only move forward
 * ({@see MatchStatus::nextSteps()}); either participant may record them.
 *
 * `approved` closes both requests as `permutation_completed`; `rejected` releases them. Both
 * end the contact consents.
 */
final class UpdateMatchProgress
{
    use ManagesParticipation;

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                MatchStatus::InDiscussion->value,
                MatchStatus::FilePrepared->value,
                MatchStatus::Submitted->value,
                MatchStatus::Approved->value,
                MatchStatus::Rejected->value,
            ])],
        ];
    }

    /**
     * @throws InvalidMatchTransitionException
     */
    public function handle(User $user, string $publicId, MatchStatus $step): MobilityMatch
    {
        return DB::transaction(function () use ($user, $publicId, $step): MobilityMatch {
            [$match] = $this->lockParticipation($user, $publicId);

            if (! in_array($step, $match->status->nextSteps(), true)) {
                throw new InvalidMatchTransitionException($match->status, 'progress');
            }

            $match->status = $step;
            $match->save();

            if ($step === MatchStatus::Approved || $step === MatchStatus::Rejected) {
                $this->agreements()->end($match, permutationCompleted: $step === MatchStatus::Approved);
            }

            return $match;
        });
    }
}
