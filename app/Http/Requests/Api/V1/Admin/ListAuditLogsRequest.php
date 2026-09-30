<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Actions\Admin\ListAuditLogs;
use Illuminate\Foundation\Http\FormRequest;

class ListAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ListAuditLogs::rules();
    }
}
