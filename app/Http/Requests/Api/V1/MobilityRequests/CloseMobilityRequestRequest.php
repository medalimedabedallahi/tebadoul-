<?php

namespace App\Http\Requests\Api\V1\MobilityRequests;

use App\Actions\MobilityRequests\CloseMobilityRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CloseMobilityRequestRequest extends FormRequest
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
        return CloseMobilityRequest::rules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'available_from' => __('requests.fields.available_from'),
            'expires_at' => __('requests.fields.expires_at'),
            'destinations' => __('requests.fields.destinations'),
            'destinations.*.wilaya' => __('requests.fields.wilaya'),
            'destinations.*.moughataa' => __('requests.fields.moughataa'),
            'destinations.*.establishment' => __('requests.fields.establishment'),
            'reason' => __('requests.fields.reason'),
        ];
    }
}
