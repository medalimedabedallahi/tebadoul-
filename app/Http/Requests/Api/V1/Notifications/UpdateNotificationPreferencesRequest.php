<?php

namespace App\Http\Requests\Api\V1\Notifications;

use App\Actions\Notifications\UpdateNotificationPreferences;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return UpdateNotificationPreferences::rules();
    }
}
