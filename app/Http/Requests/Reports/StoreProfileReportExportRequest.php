<?php

namespace App\Http\Requests\Reports;

use Illuminate\Validation\Rule;

class StoreProfileReportExportRequest extends ProfileReportRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])]];
    }
}
