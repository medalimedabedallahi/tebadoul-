<?php

namespace App\Http\Requests\Api\V1\Messaging;

use App\Actions\Messaging\SendMatchMessage;
use Illuminate\Foundation\Http\FormRequest;

class StoreMatchMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return SendMatchMessage::rules();
    }
}
