<?php

namespace HiEvents\Http\Request\Mfa;

use HiEvents\Http\Request\BaseRequest;

class ConfirmMfaRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:6', 'max:20'],
        ];
    }
}
