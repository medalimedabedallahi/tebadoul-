<?php

namespace App\Http\Requests\Api\V1\References;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional parent filters of the small reference lists (`?sector=` for professions,
 * `?profession=` for specialties and grades). An unknown code yields an empty list.
 */
class ListReferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sector' => ['sometimes', 'required', 'string', 'max:64'],
            'profession' => ['sometimes', 'required', 'string', 'max:64'],
        ];
    }
}
