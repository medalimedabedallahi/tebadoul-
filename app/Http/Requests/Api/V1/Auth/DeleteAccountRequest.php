<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Actions\Auth\DeleteAccount;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return DeleteAccount::rules();
    }
}
