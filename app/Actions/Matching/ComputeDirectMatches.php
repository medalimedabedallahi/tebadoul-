<?php

namespace App\Actions\Matching;

use App\Contracts\MatchingRules;
use App\Enums\MatchStatus;
use App\Enums\MobilityRequestStatus;
use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\MatchParticipant;
use App\Models\MobilityMatch;
use App\Models\MobilityRequest;
use App\Models\ProfessionalProfile;
use App\Models\UserBlock;
use App\Support\Matching\AgreementLifecycle;
use App\Support\Matching\Candidate;
use App\Support\Matching\MatchEvaluation;
use App\Support\Notifications\UserNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * (Re)computes the direct matches of one mobility request (PRD 7.3 and 7.5).
 *
 * The pairs considered are the candidates found by a database pre-filter (published requests
 * of other active accounts, same professional identity, a destination covering this request's
 * origin) plus every request already matched with this one. Each pair is evaluated by the
 * current {@see MatchingRules}:
 *
 * - compatible: the pair's single row (see {@see MobilityMatch::pairKey()}) is created as
 *   `suggested`, or refreshed (score, reasons, rules version); an invalidated match is
 *   suggested again;
 * - incompatible: a live match (suggested or engaged) is invalidated, with the failed condition
 *   as its visible reason, never deleted.
 *
 * Both participants are notified of a new suggestion, in the pair's transaction: a replayed job
 * finds the pair `updated` and notifies nobody again.
 *
 * Final matches (declined, approved...) are never changed. Idempotent: running it twice gives
 * the same state. Does nothing while `matching.enabled` is false (ADR 0001).
 */
final class ComputeDirectMatches
{
    public function __construct(
        private readonly MatchingRules $rules,
        private readonly AgreementLifecycle $agreements,
        private readonly UserNotifier $notifier,
    ) {}

    /**
     * @return array{suggested: int, updated: int, invalidated: int}
     */
    public function handle(MobilityRequest $request): array
    {
        $counts = ['suggested' => 0, 'updated' => 0, 'invalidated' => 0];

        if (! config('matching.enabled')) {
            return $counts;
        }

        $candidate = Candidate::fromRequest($request);
        $others = $this->prefilteredCandidates($candidate)
            ->merge($this->currentlyMatchedRequests($request))
            ->unique('id');

        foreach ($others as $other) {
            $result = DB::transaction(fn (): string => $this->applyPair($candidate, Candidate::fromRequest($other)));

            if (isset($counts[$result])) {
                $counts[$result]++;
            }
        }

        return $counts;
    }

    /**
     * @return 'suggested'|'updated'|'invalidated'|'unchanged'
     */
    private function applyPair(Candidate $candidate, Candidate $other): string
    {
        $pairKey = MobilityMatch::pairKey($candidate->request->id, $other->request->id);
        $match = MobilityMatch::query()->where('pair_key', $pairKey)->lockForUpdate()->first();

        if ($match?->status->isFinal()) {
            return 'unchanged';
        }

        if (UserBlock::existsBetween($candidate->request->user_id, $other->request->user_id)) {
            if ($match === null || ($match->status === MatchStatus::Invalidated && $match->invalidation_reason === 'blocked')) {
                return 'unchanged';
            }

            $wasAccord = $match->status->isAccord();
            $match->forceFill([
                'status' => MatchStatus::Invalidated,
                'invalidated_at' => now(),
                'invalidation_reason' => 'blocked',
                'expires_at' => null,
            ])->save();

            if ($wasAccord) {
                $this->agreements->end($match);
            }

            return 'invalidated';
        }

        // The requests of an agreement in progress are `matched`: they stay available to it.
        if ($match?->status->isAccord()) {
            $candidate = $candidate->inAccord();
            $other = $other->inAccord();
        }

        $evaluation = $this->rules->evaluate($candidate, $other);

        if ($match === null && ! $evaluation->compatible) {
            return 'unchanged';
        }

        $created = false;

        if ($match === null) {
            [$match, $created] = $this->createPair($pairKey, $evaluation);
        }

        if (! $evaluation->compatible) {
            if ($match->status === MatchStatus::Invalidated) {
                return 'unchanged';
            }

            $wasAccord = $match->status->isAccord();
            $match->status = MatchStatus::Invalidated;
            $match->invalidated_at = now();
            $match->invalidation_reason = $evaluation->failedCondition;
            $match->expires_at = null;
            $match->save();

            if ($wasAccord) {
                $this->agreements->end($match);
            }

            return 'invalidated';
        }

        $isNewSuggestion = $created || $match->status === MatchStatus::Invalidated;

        if ($match->status === MatchStatus::Invalidated) {
            // Suggested again: a fresh start, without the decisions and consents of before.
            $match->status = MatchStatus::Suggested;
            $match->invalidated_at = null;
            $match->invalidation_reason = null;
            $match->outcome_reason = null;
            $match->participants()->update(['decision' => null, 'decided_at' => null, 'contact_consented_at' => null]);
        }

        $match->score = $evaluation->score;
        $match->rules_version = $this->rules->version();
        $match->save();

        $this->syncParticipants($match, [$candidate, $other]);
        $this->syncReasons($match, $evaluation);

        if ($isNewSuggestion) {
            foreach ([$candidate, $other] as $participant) {
                $this->notifier->notify($participant->request->user, NotificationType::MatchSuggested, $match);
            }
        }

        return $isNewSuggestion ? 'suggested' : 'updated';
    }

    /**
     * Creates the row of a pair. When another worker created it concurrently (unique
     * `pair_key`), that row is locked and used instead.
     *
     * @return array{MobilityMatch, bool}
     */
    private function createPair(string $pairKey, MatchEvaluation $evaluation): array
    {
        try {
            $match = DB::transaction(function () use ($pairKey, $evaluation): MobilityMatch {
                $match = new MobilityMatch;
                $match->forceFill([
                    'type' => 'direct',
                    'pair_key' => $pairKey,
                    'status' => MatchStatus::Suggested,
                    'score' => $evaluation->score,
                    'rules_version' => $this->rules->version(),
                ])->save();

                return $match;
            });

            return [$match, true];
        } catch (UniqueConstraintViolationException) {
            return [MobilityMatch::query()->where('pair_key', $pairKey)->lockForUpdate()->firstOrFail(), false];
        }
    }

    /**
     * @param  list<Candidate>  $candidates
     */
    private function syncParticipants(MobilityMatch $match, array $candidates): void
    {
        foreach ($candidates as $candidate) {
            $match->participants()->firstOrCreate(
                ['mobility_request_id' => $candidate->request->id],
                ['user_id' => $candidate->request->user_id],
            );
        }
    }

    private function syncReasons(MobilityMatch $match, MatchEvaluation $evaluation): void
    {
        $match->reasons()->delete();
        $match->reasons()->createMany($evaluation->reasons);
    }

    /**
     * Published requests of other active accounts with the same professional identity, having
     * a destination that covers this request's origin. The rules make the final decision.
     *
     * @return Collection<int, MobilityRequest>
     */
    private function prefilteredCandidates(Candidate $candidate): Collection
    {
        $profile = $candidate->profile;

        if (! $candidate->isAvailable() || $profile === null) {
            return collect();
        }

        $profile->loadMissing('moughataa');

        return MobilityRequest::query()
            ->where('status', MobilityRequestStatus::Published)
            ->whereDate('expires_at', '>=', today())
            ->where('user_id', '!=', $candidate->request->user_id)
            ->whereHas('user', fn (Builder $user) => $user->where('status', UserStatus::Active))
            ->whereDoesntHave('user.blocksInitiated', fn (Builder $blocks) => $blocks->where('blocked_id', $candidate->request->user_id))
            ->whereDoesntHave('user.blocksReceived', fn (Builder $blocks) => $blocks->where('blocker_id', $candidate->request->user_id))
            ->whereHas('user.professionalProfile', fn (Builder $other) => $this->sameIdentity($other, $profile))
            ->whereHas('destinations', fn (Builder $destination) => $destination
                ->where('wilaya_id', $profile->moughataa->wilaya_id)
                ->where(fn (Builder $moughataa) => $moughataa->whereNull('moughataa_id')->orWhere('moughataa_id', $profile->moughataa_id))
                ->where(fn (Builder $establishment) => $establishment->whereNull('establishment_id')->orWhere('establishment_id', $profile->establishment_id)))
            ->with(['destinations', 'user.professionalProfile'])
            ->get()
            ->toBase();
    }

    /**
     * @param  Builder<ProfessionalProfile>  $query
     */
    private function sameIdentity(Builder $query, ProfessionalProfile $profile): void
    {
        $query->where('sector_id', $profile->sector_id)->where('profession_id', $profile->profession_id);

        foreach (['grade_id', 'specialty_id'] as $column) {
            $profile->{$column} === null ? $query->whereNull($column) : $query->where($column, $profile->{$column});
        }
    }

    /**
     * The other requests of the live (non-final) matches of `$request`, deleted ones included:
     * they must be re-evaluated, and invalidated if they no longer qualify.
     *
     * @return Collection<int, MobilityRequest>
     */
    private function currentlyMatchedRequests(MobilityRequest $request): Collection
    {
        $otherIds = MatchParticipant::query()
            ->whereIn('match_id', MatchParticipant::query()->select('match_id')->where('mobility_request_id', $request->id))
            ->whereHas('mobilityMatch', fn (Builder $match) => $match->whereIn('status', MatchStatus::reevaluable()))
            ->where('mobility_request_id', '!=', $request->id)
            ->pluck('mobility_request_id');

        return MobilityRequest::withTrashed()
            ->whereKey($otherIds)
            ->with(['destinations', 'user.professionalProfile'])
            ->get()
            ->toBase();
    }
}
