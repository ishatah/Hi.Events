<?php

namespace HiEvents\Http\Request\AccessPoint;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;

class UpsertAccessPointRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => RulesHelper::REQUIRED_STRING,
            'code' => ['required', 'string', 'max:64'],

            // Direction is what makes anti-passback expressible: without knowing a reader
            // is an exit, you cannot tell that somebody left.
            'direction' => ['nullable', 'string', 'in:ENTRY,EXIT,BIDIRECTIONAL'],

            'access_point_type' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
