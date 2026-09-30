<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Actions\Profiles\SaveProfessionalProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfessionalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return SaveProfessionalProfile::rules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('profile.fields');
    }
}
