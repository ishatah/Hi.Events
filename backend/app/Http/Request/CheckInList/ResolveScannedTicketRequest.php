<?php

namespace HiEvents\Http\Request\CheckInList;

use HiEvents\Http\Request\BaseRequest;

class ResolveScannedTicketRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ticket_code' => ['required', 'string', 'max:255'],
        ];
    }
}
