<?php

namespace App\Actions\Matching;

use App\Enums\MatchStatus;
use App\Models\MobilityMatch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The matches of the actor, best score first. Invalidated matches are hidden unless asked for
 * with `status=invalidated`. Also finds one match of the actor by public ULID (404 otherwise,
 * whether it does not exist or involves other accounts only).
 */
final class ListMatches
{
    /**
     * Relations a match presentation needs, for the viewer's side and the counterpart's.
     */
    public const PRESENTATION = [
        'reasons',
        'participants.mobilityRequest.user.professionalProfile.sector',
        'participants.mobilityRequest.user.professionalProfile.profession',
        'participants.mobilityRequest.user.professionalProfile.specialty',
        'participants.mobilityRequest.user.professionalProfile.grade',
        'participants.mobilityRequest.user.professionalProfile.moughataa.wilaya',
        'participants.mobilityRequest.user.blocksInitiated',
        'participants.mobilityRequest.user.blocksReceived',
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(MatchStatus::class)],
            'mobility_request' => ['sometimes', 'string', 'size:26'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, MobilityMatch>
     */
    public function handle(
        User $user,
        ?MatchStatus $status = null,
        ?string $requestPublicId = null,
        int $page = 1,
        int $perPage = 20,
    ): LengthAwarePaginator {
        Gate::forUser($user)->authorize('viewAny', MobilityMatch::class);

        return MobilityMatch::query()
            ->involving($user)
            ->when(
                $status !== null,
                fn (Builder $query) => $query->where('status', $status),
                fn (Builder $query) => $query->where('status', '!=', MatchStatus::Invalidated),
            )
            ->when($requestPublicId !== null, fn (Builder $query) => $query->whereHas(
                'participants.mobilityRequest',
                fn (Builder $request) => $request->where('user_id', $user->getKey())->where('public_id', Str::lower($requestPublicId)),
            ))
            ->with(self::PRESENTATION)
            ->orderByDesc('score')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function find(User $user, string $publicId): MobilityMatch
    {
        $match = MobilityMatch::query()
            ->involving($user)
            ->where('public_id', Str::lower($publicId))
            ->with(self::PRESENTATION)
            ->firstOrFail();

        Gate::forUser($user)->authorize('view', $match);

        return $match;
    }
}
