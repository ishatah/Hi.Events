<?php

namespace HiEvents\Http\Request\AccessRule;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;

class UpsertAccessRuleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => RulesHelper::REQUIRED_STRING,
            'priority' => ['nullable', 'integer'],
            'effect' => ['required', 'string', 'in:ALLOW,DENY'],
            'subject_type' => ['required', 'string', 'max:32'],
            'subject_id' => ['nullable', 'integer'],
            'target_type' => ['required', 'string', 'in:ZONE,ROOM,SESSION,ACCESS_POINT,EVENT'],
            'target_id' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'time_from' => ['nullable', 'date_format:H:i:s'],
            'time_to' => ['nullable', 'date_format:H:i:s'],
            'max_entries' => ['nullable', 'integer', 'min:0'],
            'allow_reentry' => ['nullable', 'boolean'],
            'min_reentry_seconds' => ['nullable', 'integer', 'min:0'],
            'enforce_capacity' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
