<?php

namespace HiEvents\Http\Request\Rsvp;

use HiEvents\DomainObjects\Status\RsvpResponseStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class RespondToInvitationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'response' => ['required', 'string', Rule::in(RsvpResponseStatus::valuesArray())],
            'party_size' => ['nullable', 'integer', 'min:0', 'max:50'],
            'form_data' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
