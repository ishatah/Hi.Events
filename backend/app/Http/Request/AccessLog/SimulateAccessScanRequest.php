<?php

namespace HiEvents\Http\Request\AccessLog;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class SimulateAccessScanRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'access_point_id' => [
                'required',
                'integer',
                Rule::exists('access_points', 'id')->whereNull('deleted_at'),
            ],
            'direction' => ['nullable', 'string', 'in:ENTRY,EXIT'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
