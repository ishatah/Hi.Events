<?php

namespace HiEvents\Http\Request\Room;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;

class UpsertRoomRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'floor_id' => ['nullable', 'integer', Rule::exists('floors', 'id')->whereNull('deleted_at')],
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'name' => RulesHelper::REQUIRED_STRING,
            'code' => ['nullable', 'string', 'max:64'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'room_type' => ['nullable', 'string', 'max:32'],
        ];
    }
}
