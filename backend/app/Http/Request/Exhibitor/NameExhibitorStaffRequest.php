<?php

namespace HiEvents\Http\Request\Exhibitor;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class NameExhibitorStaffRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'person_id' => [
                'required',
                'integer',
                Rule::exists('persons', 'id')->whereNull('deleted_at'),
            ],
            'role' => ['nullable', 'string', 'in:ADMIN,STAFF'],
        ];
    }
}
