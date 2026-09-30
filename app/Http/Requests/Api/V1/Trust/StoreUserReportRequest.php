<?php

namespace App\Http\Requests\Api\V1\Trust;

use App\Actions\Trust\CreateUserReport;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return CreateUserReport::rules();
    }
}
