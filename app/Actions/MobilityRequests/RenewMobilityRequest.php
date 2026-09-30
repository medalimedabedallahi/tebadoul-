<?php

namespace App\Actions\MobilityRequests;

use App\Actions\MobilityRequests\Concerns\ManagesOwnRequest;
use App\Enums\MobilityRequestStatus;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use App\Exceptions\MobilityRequests\RequestNotPublishableException;
use App\Jobs\RecomputeMatches;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Publishes an expired request again, with a new expiry date. Same requirements as a first
 * publication (profile, valid destinations, no other active request).
 */
final class RenewMobilityRequest
{
    use ManagesOwnRequest;

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'expires_at' => SaveMobilityRequest::rules()['expires_at'],
        ];
    }

    /**
     * @throws ValidationException
     * @throws InvalidRequestTransitionException
     * @throws RequestNotPublishableException
     */
    public function handle(User $user, string $publicId, string $expiresAt): MobilityRequest
    {
        Validator::make(['expires_at' => $expiresAt], self::rules(), attributes: [
            'expires_at' => __('requests.fields.expires_at'),
        ])->validate();

        return DB::transaction(function () use ($user, $publicId, $expiresAt): MobilityRequest {
            $this->lockUser($user);
            $request = $this->lockOwnRequest($user, $publicId);

            if ($request->status !== MobilityRequestStatus::Expired) {
                throw new InvalidRequestTransitionException($request->status, 'renew');
            }

            $request->expires_at = Carbon::createFromFormat('Y-m-d', $expiresAt)->startOfDay();

            if (! $request->expires_at->isAfter($request->available_from)) {
                throw ValidationException::withMessages([
                    'expires_at' => __('validation.after', [
                        'attribute' => __('requests.fields.expires_at'),
                        'date' => __('requests.fields.available_from'),
                    ]),
                ]);
            }

            $this->assertPublishable($user, $request);

            $request->transitionTo(MobilityRequestStatus::Published, 'renew');
            $request->save();
            RecomputeMatches::forRequest($request);

            return $request;
        });
    }
}
