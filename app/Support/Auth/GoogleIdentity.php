<?php

namespace App\Support\Auth;

use App\Rules\AsciiEmail;
use App\Support\ContactNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User;

final readonly class GoogleIdentity
{
    public function __construct(public string $subject, public string $email, public string $name) {}

    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public static function fromProvider(User $user): self
    {
        if (($user->getRaw()['email_verified'] ?? false) !== true) {
            throw ValidationException::withMessages(['google' => __('auth.google.unverified')]);
        }

        $data = Validator::make([
            'subject' => $user->getId(),
            'email' => ContactNormalizer::email($user->getEmail()),
        ], [
            'subject' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', new AsciiEmail, 'max:255'],
        ])->validate();

        return new self($data['subject'], $data['email'], Str::limit($user->getName() ?: $data['email'], 120, ''));
    }
}
