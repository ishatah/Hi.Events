<?php

namespace HiEvents\Http\Request\Operations;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class AssignShiftRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer', Rule::exists('persons', 'id')->whereNull('deleted_at')],
            'accreditation_id' => ['nullable', 'integer', Rule::exists('accreditations', 'id')->whereNull('deleted_at')],
        ];
    }
}
