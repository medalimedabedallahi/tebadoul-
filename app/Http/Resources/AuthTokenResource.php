<?php

namespace App\Http\Resources;

use App\Actions\Auth\AuthToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Response of a successful sign-in: the bearer token (shown once) and the account.
 *
 * @mixin AuthToken
 */
class AuthTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuthToken $auth */
        $auth = $this->resource;

        return [
            'token' => $auth->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $auth->abilities,
            'expires_at' => $auth->expiresAt?->toIso8601String(),
            'verification_required' => $auth->isLimited(),
            'user' => new UserResource($auth->user),
        ];
    }
}
