<?php

namespace HiEvents\Http\Request\Credential;

use HiEvents\Http\Request\BaseRequest;

class RevokeCredentialRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Revocation is contestable, so a reason is required rather than optional.
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
