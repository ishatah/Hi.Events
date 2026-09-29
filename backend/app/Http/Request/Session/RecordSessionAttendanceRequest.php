<?php

namespace HiEvents\Http\Request\Session;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class RecordSessionAttendanceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'attendee_id' => [
                'required',
                'integer',
                Rule::exists('attendees', 'id')->whereNull('deleted_at'),
            ],
            'direction' => ['nullable', 'string', 'in:ENTRY,EXIT'],
            'access_point_id' => [
                'nullable',
                'integer',
                Rule::exists('access_points', 'id')->whereNull('deleted_at'),
            ],
            'client_generated_id' => ['nullable', 'uuid'],
            'scanned_at' => ['nullable', 'date'],
        ];
    }
}
