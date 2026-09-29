<?php

namespace HiEvents\Http\Request\Accreditation;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class ApproveAccreditationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'approved_zones' => ['nullable', 'array'],
            'approved_zones.*' => [
                'integer',
                Rule::exists('zones', 'id')->whereNull('deleted_at'),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
