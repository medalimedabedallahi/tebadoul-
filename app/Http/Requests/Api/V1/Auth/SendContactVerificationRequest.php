<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Actions\Auth\SendContactVerification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendContactVerificationRequest extends FormRequest
{
    /**
     * Public endpoint: access is limited by the rate limiters, not by the user.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return SendContactVerification::rules();
    }
}
