<?php

namespace HiEvents\Http\Request\ApiKey;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class CreateApiKeyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'string'],
            'organizer_id' => [
                'nullable',
                'integer',
                Rule::exists('organizers', 'id')->whereNull('deleted_at'),
            ],
            'event_id' => [
                'nullable',
                'integer',
                Rule::exists('events', 'id')->whereNull('deleted_at'),
            ],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['ip'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
