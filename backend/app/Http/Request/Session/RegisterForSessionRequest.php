<?php

namespace HiEvents\Http\Request\Session;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class RegisterForSessionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'attendee_id' => [
                'required',
                'integer',
                Rule::exists('attendees', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
