<?php

namespace App\Actions\Admin;

use App\Enums\MatchStatus;
use App\Enums\MobilityRequestStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MatchParticipant;
use App\Models\MobilityMatch;
use App\Models\MobilityRequest;
use App\Models\RequestDestination;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Minimal platform figures for the administration dashboard (PRD 2.2 and 15.1): accounts, active
 * requests, matches, mutual agreements, reports and covered zones. Aggregated counts only: no
 * account, request or match is ever named, and nothing leaves the platform.
 *
 * Computed on demand, which is enough at pilot scale; a nightly snapshot (PRD 12.4) can replace it
 * when volumes require it.
 */
final class ComputePlatformStatistics
{
    /**
     * @return array{
     *     generated_at: string,
     *     accounts: array{total: int, active: int, pending_verification: int, suspended: int},
     *     requests: array{published: int, paused: int, matched: int, with_match: int, by_sector: list<array{code: string, count: int}>},
     *     matches: array<string, int>,
     *     mutual_agreements: int,
     *     reports: array{pending: int, resolved: int, dismissed: int},
     *     coverage: array{origin_wilayas: int, destination_wilayas: int}
     * }
     */
    public function handle(User $actor): array
    {
        Gate::forUser($actor)->authorize('viewStatistics', User::class);

        $accounts = User::query()
            ->where('role', UserRole::User)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $requests = MobilityRequest::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $matches = MobilityMatch::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $reports = UserReport::query()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $matchCounts = [];

        foreach (MatchStatus::cases() as $status) {
            $matchCounts[$status->value] = (int) ($matches[$status->value] ?? 0);
        }

        $mutual = array_sum(array_map(
            fn (MatchStatus $status): int => $matchCounts[$status->value],
            array_filter(MatchStatus::cases(), fn (MatchStatus $status): bool => $status->isAccord() || in_array($status, [MatchStatus::Approved, MatchStatus::Rejected], true)),
        ));

        return [
            'generated_at' => now()->toIso8601String(),
            'accounts' => [
                'total' => (int) $accounts->sum(),
                'active' => (int) ($accounts[UserStatus::Active->value] ?? 0),
                'pending_verification' => (int) ($accounts[UserStatus::PendingVerification->value] ?? 0),
                'suspended' => (int) ($accounts[UserStatus::Suspended->value] ?? 0),
            ],
            'requests' => [
                'published' => $this->liveRequests()->count(),
                'paused' => (int) ($requests[MobilityRequestStatus::Paused->value] ?? 0),
                'matched' => (int) ($requests[MobilityRequestStatus::Matched->value] ?? 0),
                'with_match' => $this->liveRequests()
                    ->whereIn('mobility_requests.id', MatchParticipant::query()
                        ->select('mobility_request_id')
                        ->whereHas('mobilityMatch', fn (Builder $match) => $match->whereIn('status', [MatchStatus::Suggested, MatchStatus::Invited])))
                    ->count(),
                'by_sector' => $this->liveRequestsBySector(),
            ],
            'matches' => $matchCounts,
            'mutual_agreements' => $mutual,
            'reports' => [
                'pending' => (int) ($reports[ReportStatus::Pending->value] ?? 0),
                'resolved' => (int) ($reports[ReportStatus::Resolved->value] ?? 0),
                'dismissed' => (int) ($reports[ReportStatus::Dismissed->value] ?? 0),
            ],
            'coverage' => [
                'origin_wilayas' => (int) $this->liveRequests()
                    ->join('professional_profiles', 'professional_profiles.user_id', '=', 'mobility_requests.user_id')
                    ->join('moughataas', 'moughataas.id', '=', 'professional_profiles.moughataa_id')
                    ->distinct()
                    ->count('moughataas.wilaya_id'),
                'destination_wilayas' => RequestDestination::query()
                    ->whereIn('mobility_request_id', $this->liveRequests()->select('mobility_requests.id'))
                    ->distinct()
                    ->count('wilaya_id'),
            ],
        ];
    }

    /**
     * Published requests that have not reached their expiry date: the "demandes actives" of PRD 2.2.
     *
     * @return Builder<MobilityRequest>
     */
    private function liveRequests(): Builder
    {
        return MobilityRequest::query()
            ->where('mobility_requests.status', MobilityRequestStatus::Published)
            ->whereDate('mobility_requests.expires_at', '>=', today());
    }

    /**
     * @return list<array{code: string, count: int}>
     */
    private function liveRequestsBySector(): array
    {
        return $this->liveRequests()
            ->join('professional_profiles', 'professional_profiles.user_id', '=', 'mobility_requests.user_id')
            ->join('sectors', 'sectors.id', '=', 'professional_profiles.sector_id')
            ->toBase()
            ->selectRaw('sectors.code as code, count(*) as total')
            ->groupBy('sectors.code')
            ->orderBy('sectors.code')
            ->get()
            ->map(fn (object $row): array => ['code' => (string) $row->code, 'count' => (int) $row->total])
            ->values()
            ->all();
    }
}
