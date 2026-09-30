<?php

namespace App\Support\Matching;

use App\Contracts\MatchingRules;
use App\Models\RequestDestination;
use Illuminate\Support\Carbon;

/**
 * Rules `v1-strict`, used until the compatibility matrices are validated by the experts
 * (ADR 0001). Professional compatibility is identity: same sector, profession, grade and
 * specialty. No administrable compatibility table is consulted yet.
 *
 * Mandatory conditions (PRD 7.1), in this order: both requests available (published, not
 * expired, active owner with a profile, different owners), same sector, same profession, same
 * grade, same specialty, mutual geography (each request has a destination covering the other's
 * current assignment), overlapping availability periods (with the configured tolerance).
 * Blocking between users comes with the trust features (step 8).
 *
 * Score out of 100 (PRD 7.2 weights): destination 35, professional compatibility 25,
 * requested establishment 15, availability 10, verified profile 5 (no verification exists yet:
 * always 0), profile completeness 5, recent activity 5.
 */
final class StrictRulesV1 implements MatchingRules
{
    public const VERSION = 'v1-strict';

    private const RECENT_ACTIVITY_DAYS = 30;

    public function version(): string
    {
        return self::VERSION;
    }

    public function evaluate(Candidate $a, Candidate $b): MatchEvaluation
    {
        $failed = $this->failedProfessionalCondition($a, $b);

        if ($failed !== null) {
            return MatchEvaluation::incompatible($failed);
        }

        $aCoversB = $a->destinationCovering($b);
        $bCoversA = $b->destinationCovering($a);

        if ($aCoversB === null || $bCoversA === null) {
            return MatchEvaluation::incompatible('geography');
        }

        if (! $this->periodsOverlap($a, $b)) {
            return MatchEvaluation::incompatible('availability');
        }

        return MatchEvaluation::compatible([
            $this->shared('destination', 35, [$this->destinationPrecision($aCoversB), $this->destinationPrecision($bCoversA)], ['wilaya_only', 'exact_one', 'exact_both']),
            ['criterion' => 'professional', 'points' => 25, 'max_points' => 25, 'detail' => 'identical'],
            $this->shared('establishment', 15, [$aCoversB->establishment_id !== null ? 1.0 : 0.0, $bCoversA->establishment_id !== null ? 1.0 : 0.0], ['none', 'one', 'both']),
            $this->availability($a->request->available_from, $b->request->available_from),
            ['criterion' => 'verified_profile', 'points' => 0, 'max_points' => 5, 'detail' => 'not_available'],
            $this->shared('profile_completeness', 5, [$a->profile?->establishment_id !== null ? 1.0 : 0.0, $b->profile?->establishment_id !== null ? 1.0 : 0.0], ['none', 'one', 'both']),
            $this->shared('recent_activity', 5, [$this->isRecent($a) ? 1.0 : 0.0, $this->isRecent($b) ? 1.0 : 0.0], ['none', 'one', 'both']),
        ]);
    }

    /**
     * Availability of both requests, then the professional identity (v1: no compatibility table).
     */
    private function failedProfessionalCondition(Candidate $a, Candidate $b): ?string
    {
        if (! $a->isAvailable() || ! $b->isAvailable() || $a->request->user_id === $b->request->user_id) {
            return 'request_unavailable';
        }

        $pa = $a->profile;
        $pb = $b->profile;

        return match (true) {
            $pa === null || $pb === null => 'request_unavailable',
            $pa->sector_id !== $pb->sector_id => 'sector',
            $pa->profession_id !== $pb->profession_id => 'profession',
            $pa->grade_id !== $pb->grade_id => 'grade',
            $pa->specialty_id !== $pb->specialty_id => 'specialty',
            default => null,
        };
    }

    private function periodsOverlap(Candidate $a, Candidate $b): bool
    {
        $tolerance = (int) config('matching.availability_tolerance_days', 0);

        return $a->request->available_from->lte($b->request->expires_at->copy()->addDays($tolerance))
            && $b->request->available_from->lte($a->request->expires_at->copy()->addDays($tolerance));
    }

    /**
     * 1 when the destination names the moughataa (or the establishment), 0.5 for a whole wilaya.
     */
    private function destinationPrecision(RequestDestination $destination): float
    {
        return $destination->moughataa_id !== null ? 1.0 : 0.5;
    }

    private function isRecent(Candidate $candidate): bool
    {
        $updatedAt = $candidate->request->updated_at;

        return $updatedAt !== null && $updatedAt->gte(now()->subDays(self::RECENT_ACTIVITY_DAYS));
    }

    /**
     * @return array{criterion: string, points: int, max_points: int, detail: string}
     */
    private function availability(Carbon $a, Carbon $b): array
    {
        $gap = (int) abs($a->diffInDays($b));

        return match (true) {
            $gap <= 30 => ['criterion' => 'availability', 'points' => 10, 'max_points' => 10, 'detail' => 'close'],
            $gap <= 90 => ['criterion' => 'availability', 'points' => 5, 'max_points' => 10, 'detail' => 'near'],
            default => ['criterion' => 'availability', 'points' => 0, 'max_points' => 10, 'detail' => 'distant'],
        };
    }

    /**
     * A criterion both sides contribute to equally: the points are the maximum times the
     * average of the two factors (each between 0 and 1). The detail is picked from `$details`
     * (none / one / both) by how many sides reached a full factor.
     *
     * @param  array{float, float}  $factors
     * @param  array{string, string, string}  $details
     * @return array{criterion: string, points: int, max_points: int, detail: string}
     */
    private function shared(string $criterion, int $max, array $factors, array $details): array
    {
        $full = count(array_filter($factors, fn (float $factor): bool => $factor >= 1.0));

        return [
            'criterion' => $criterion,
            'points' => (int) round($max * array_sum($factors) / 2),
            'max_points' => $max,
            'detail' => $details[$full],
        ];
    }
}
