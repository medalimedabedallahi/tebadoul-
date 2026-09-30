<?php

namespace App\Actions\MobilityRequests\Concerns;

use App\Enums\MobilityRequestStatus;
use App\Exceptions\MobilityRequests\RequestNotPublishableException;
use App\Models\MobilityRequest;
use App\Models\User;
use App\Support\MobilityRequests\DestinationResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared by the mobility request actions. Call inside a transaction.
 */
trait ManagesOwnRequest
{
    /**
     * The actor's own request, locked for update. A request of another account, or a deleted
     * one, is reported exactly like an unknown one (404): its existence is never revealed.
     *
     * @throws ModelNotFoundException
     */
    protected function lockOwnRequest(User $user, string $publicId): MobilityRequest
    {
        $request = MobilityRequest::query()
            ->whereBelongsTo($user)
            ->where('public_id', Str::lower($publicId))
            ->lockForUpdate()
            ->firstOrFail();

        Gate::forUser($user)->authorize('update', $request);

        return $request;
    }

    /**
     * Checks everything publication (or renewal) requires, and reports every problem at once:
     * a professional profile, at least one valid destination, an expiry date not passed, and no
     * other active request of the account (FR 007). Lock the user row first: two requests of the
     * same account must not be published concurrently.
     *
     * @throws RequestNotPublishableException
     */
    protected function assertPublishable(User $user, MobilityRequest $request): void
    {
        $errors = [];
        $profile = $user->professionalProfile()->first();

        if ($profile === null) {
            $errors['profile'] = [__('requests.errors.profile_required')];
        }

        $resolver = app(DestinationResolver::class);
        $destinations = $resolver->toInput($request);

        if ($destinations === []) {
            $errors['destinations'] = [__('requests.errors.destinations_required')];
        } elseif ($profile !== null) {
            try {
                $resolver->resolve($destinations, $profile);
            } catch (ValidationException $exception) {
                $errors['destinations'] = array_values(array_unique(array_merge(...array_values($exception->errors()))));
            }
        }

        if ($request->expires_at->isBefore(today())) {
            $errors['expires_at'] = [__('requests.errors.expired')];
        }

        $hasOtherActiveRequest = $user->mobilityRequests()
            ->whereKeyNot($request->getKey())
            ->whereIn('status', MobilityRequestStatus::activeStatuses())
            ->exists();

        if ($hasOtherActiveRequest) {
            $errors['status'] = [__('requests.errors.active_request_exists')];
        }

        if ($errors !== []) {
            throw new RequestNotPublishableException($errors);
        }
    }

    protected function lockUser(User $user): void
    {
        User::query()->whereKey($user->getKey())->lockForUpdate()->first();
    }
}
