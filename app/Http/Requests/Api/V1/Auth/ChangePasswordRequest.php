<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Actions\Auth\ChangePassword;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    /**
     * Authentication and abilities are enforced by the route middleware.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ChangePassword::rules();
    }
}
