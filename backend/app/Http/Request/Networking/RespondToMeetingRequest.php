<?php

namespace HiEvents\Http\Request\Networking;

use HiEvents\Http\Request\BaseRequest;

class RespondToMeetingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer'],
            'accept' => ['required', 'boolean'],
        ];
    }
}
