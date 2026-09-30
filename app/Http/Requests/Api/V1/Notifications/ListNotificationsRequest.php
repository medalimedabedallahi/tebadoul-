<?php

namespace App\Http\Requests\Api\V1\Notifications;

use App\Actions\Notifications\ListNotifications;
use Illuminate\Foundation\Http\FormRequest;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ListNotifications::rules();
    }
}
