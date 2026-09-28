<?php

namespace HiEvents\Http\Request\Credential;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class IssueCredentialRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'attendee_id' => [
                'required_without:accreditation_id',
                'nullable',
                'integer',
                Rule::exists('attendees', 'id')->whereNull('deleted_at'),
            ],
            'accreditation_id' => [
                'required_without:attendee_id',
                'nullable',
                'integer',
                Rule::exists('accreditations', 'id')->whereNull('deleted_at'),
            ],
            'person_id' => ['nullable', 'integer', Rule::exists('persons', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'attendee_id.required_without' => __('A credential must come from either an attendee or an accreditation.'),
            'accreditation_id.required_without' => __('A credential must come from either an attendee or an accreditation.'),
        ];
    }
}
