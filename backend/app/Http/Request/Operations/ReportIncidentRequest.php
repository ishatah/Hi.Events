<?php

namespace HiEvents\Http\Request\Operations;

use HiEvents\DomainObjects\Status\IncidentSeverity;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ReportIncidentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:32'],
            'severity' => ['required', 'string', new Enum(IncidentSeverity::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
