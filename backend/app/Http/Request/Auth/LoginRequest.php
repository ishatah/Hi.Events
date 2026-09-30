<?php

namespace HiEvents\Http\Request\Auth;

use HiEvents\Http\Request\BaseRequest;

class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'account_id' => ['integer', 'nullable'],

            // A TOTP code or a recovery code, so the length range covers both.
            'mfa_code' => ['nullable', 'string', 'min:6', 'max:20'],
        ];
    }
}
