<?php

namespace HiEvents\Http\Request\Raffle;

use HiEvents\Http\Request\BaseRequest;

class RecordRaffleClaimRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'claimed' => ['required', 'boolean'],
        ];
    }
}
