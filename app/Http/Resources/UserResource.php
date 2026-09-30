<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\ContactMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of an account: an allow-list, never the model attributes.
 *
 * The internal id, the contacts in clear text and the password are never included. A contact is only shown masked ("m***@e***.org", "+222*****11") so a person can recognise it.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'public_id' => $user->public_id,
            'name' => $user->name,
            'status' => $user->status->value,
            'locale' => $user->locale,
            'email_notifications' => $user->email_notifications,
            'email_verified' => $user->email_verified_at !== null,
            'phone_verified' => $user->phone_verified_at !== null,
            'email_masked' => ContactMasker::email($user->email),
            'phone_masked' => ContactMasker::phone($user->phone, (string) config('app.default_phone_country_code', '222')),
        ];
    }
}
