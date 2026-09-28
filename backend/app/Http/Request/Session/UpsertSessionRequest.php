<?php

namespace HiEvents\Http\Request\Session;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;

class UpsertSessionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title' => RulesHelper::REQUIRED_STRING,
            'description' => ['nullable', 'string', 'max:5000'],
            'track_id' => ['nullable', 'integer', Rule::exists('tracks', 'id')->whereNull('deleted_at')],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->whereNull('deleted_at')],
            'session_type' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'max:32'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'requires_registration' => ['nullable', 'boolean'],
            'allow_waitlist' => ['nullable', 'boolean'],
            'check_in_enabled' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }
}
