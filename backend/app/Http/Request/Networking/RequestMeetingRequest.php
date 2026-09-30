<?php

namespace HiEvents\Http\Request\Networking;

use HiEvents\Http\Request\BaseRequest;

class RequestMeetingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'requester_person_id' => ['required', 'integer'],
            'invitee_person_ids' => ['required', 'array', 'min:1'],
            'invitee_person_ids.*' => ['integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'event_exhibitor_id' => ['nullable', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'location_label' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
