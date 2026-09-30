<?php

namespace HiEvents\Http\Request\Rsvp;

use HiEvents\Http\Request\BaseRequest;

class CreateInvitationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:128'],
            'last_name' => ['nullable', 'string', 'max:128'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'max_party_size' => ['nullable', 'integer', 'min:1', 'max:50'],
            'expires_at' => ['nullable', 'date'],
            'person_id' => ['nullable', 'integer'],
        ];
    }
}
