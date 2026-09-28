<?php

namespace HiEvents\Http\Request\Zone;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;

class UpsertZoneRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'parent_zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'name' => RulesHelper::REQUIRED_STRING,
            'code' => ['required', 'string', 'max:64'],
            'zone_type' => ['nullable', 'string', 'max:32'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'colour' => ['nullable', 'string', 'max:16'],
            'requires_credential' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
