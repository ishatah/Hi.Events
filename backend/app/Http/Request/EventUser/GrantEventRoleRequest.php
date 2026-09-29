<?php

namespace HiEvents\Http\Request\EventUser;

use HiEvents\DomainObjects\Enums\SystemRole;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GrantEventRoleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'role' => ['required', 'string', new Enum(SystemRole::class)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
