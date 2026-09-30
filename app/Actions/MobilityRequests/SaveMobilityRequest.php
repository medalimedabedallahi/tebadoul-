<?php

namespace App\Actions\MobilityRequests;

use App\Actions\MobilityRequests\Concerns\ManagesOwnRequest;
use App\Enums\MobilityRequestStatus;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use App\Jobs\RecomputeMatches;
use App\Models\MobilityRequest;
use App\Models\User;
use App\Support\MobilityRequests\DestinationResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates a mobility request as a draft, or updates one (PATCH semantics: only the given fields
 * change; `destinations`, when given, replaces the whole list, in priority order).
 *
 * A request can be updated while {@see MobilityRequestStatus::isEditable()}; a published request
 * stays published. Destinations are checked by {@see DestinationResolver}. The expiry date,
 * when given, is between today and {@see MobilityRequest::MAX_VALIDITY_DAYS} days from now, and
 * always after the availability date.
 */
final class SaveMobilityRequest
{
    use ManagesOwnRequest;

    public function __construct(private readonly DestinationResolver $destinations) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(bool $creating = true): array
    {
        $presence = $creating ? 'required' : 'sometimes';

        return [
            'available_from' => [$presence, 'date_format:Y-m-d'],
            'expires_at' => [$presence, 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(MobilityRequest::MAX_VALIDITY_DAYS)->toDateString()],
            'destinations' => [$presence, 'array', 'list', 'min:1', 'max:'.MobilityRequest::MAX_DESTINATIONS],
            'destinations.*' => ['array:wilaya,moughataa,establishment'],
            'destinations.*.wilaya' => ['required', 'string', 'max:64'],
            'destinations.*.moughataa' => ['nullable', 'string', 'max:64'],
            'destinations.*.establishment' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws InvalidRequestTransitionException
     */
    public function handle(User $user, array $input, ?string $publicId = null): MobilityRequest
    {
        if ($publicId === null) {
            Gate::forUser($user)->authorize('create', MobilityRequest::class);
        }

        $validated = Validator::make($input, self::rules($publicId === null), attributes: $this->attributeNames())->validate();

        return DB::transaction(function () use ($user, $validated, $publicId): MobilityRequest {
            $request = $publicId === null ? new MobilityRequest : $this->lockOwnRequest($user, $publicId);

            if ($request->exists && ! $request->status->isEditable()) {
                throw new InvalidRequestTransitionException($request->status, 'update');
            }

            $request->fill(array_intersect_key($validated, array_flip(['available_from', 'expires_at'])));

            if (! $request->expires_at->isAfter($request->available_from)) {
                throw ValidationException::withMessages([
                    'expires_at' => __('validation.after', ['attribute' => $this->attributeNames()['expires_at'], 'date' => $this->attributeNames()['available_from']]),
                ]);
            }

            $rows = array_key_exists('destinations', $validated)
                ? $this->destinations->resolve($validated['destinations'], $user->professionalProfile()->first())
                : null;

            if (! $request->exists) {
                $request->user()->associate($user);
                $request->status = MobilityRequestStatus::Draft;
            }

            $request->save();

            if ($rows !== null) {
                $request->destinations()->delete();
                $request->destinations()->createMany($rows);
            }

            RecomputeMatches::forRequest($request);

            return $request->load(['destinations.wilaya', 'destinations.moughataa', 'destinations.establishment']);
        });
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            'available_from' => __('requests.fields.available_from'),
            'expires_at' => __('requests.fields.expires_at'),
            'destinations' => __('requests.fields.destinations'),
            'destinations.*.wilaya' => __('requests.fields.wilaya'),
            'destinations.*.moughataa' => __('requests.fields.moughataa'),
            'destinations.*.establishment' => __('requests.fields.establishment'),
        ];
    }
}
