<?php

namespace HiEvents\Http\Request\Sponsor;

use HiEvents\Http\Request\BaseRequest;

class RecordFulfilmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:1'],
            'evidence' => ['nullable', 'array'],
            'waived_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
