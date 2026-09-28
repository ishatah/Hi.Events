<?php

namespace HiEvents\Http\Request\AccreditationType;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;

class UpsertAccreditationTypeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'name' => RulesHelper::REQUIRED_STRING,
            'description' => ['nullable', 'string', 'max:2000'],
            'colour' => ['nullable', 'string', 'max:16'],
            'requires_approval' => ['nullable', 'boolean'],
            'requires_photo' => ['nullable', 'boolean'],
            'requires_id_document' => ['nullable', 'boolean'],
            'max_issuable' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
