<?php

namespace App\Http\Requests\Api\V1\Matches;

use App\Actions\Matching\UpdateMatchProgress;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return UpdateMatchProgress::rules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => __('matches.fields.status')];
    }
}
