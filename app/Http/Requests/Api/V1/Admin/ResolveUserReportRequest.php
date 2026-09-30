<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Actions\Trust\ResolveUserReport;
use Illuminate\Foundation\Http\FormRequest;

class ResolveUserReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ResolveUserReport::rules();
    }
}
