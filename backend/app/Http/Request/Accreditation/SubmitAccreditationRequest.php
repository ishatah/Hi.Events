<?php

namespace HiEvents\Http\Request\Accreditation;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class SubmitAccreditationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'person_id' => [
                'required',
                'integer',
                Rule::exists('persons', 'id')->whereNull('deleted_at'),
            ],
            'accreditation_type_id' => [
                'required',
                'integer',
                Rule::exists('accreditation_types', 'id')->whereNull('deleted_at'),
            ],
            'requested_zones' => ['nullable', 'array'],
            'requested_zones.*' => [
                'integer',
                Rule::exists('zones', 'id')->whereNull('deleted_at'),
            ],
            'form_data' => ['nullable', 'array'],
        ];
    }
}
