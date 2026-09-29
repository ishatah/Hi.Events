<?php

namespace HiEvents\Http\Request\Exhibitor;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class AssignBoothRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'booth_id' => [
                'required',
                'integer',
                Rule::exists('booths', 'id')->whereNull('deleted_at'),
            ],
            'event_exhibitor_id' => [
                'nullable',
                'integer',
                Rule::exists('event_exhibitors', 'id')->whereNull('deleted_at'),
            ],
            'role' => ['nullable', 'string', 'in:PRIMARY,CO_EXHIBITOR'],
            // A hold needs a deadline; an open-ended hold is a stand quietly off the market.
            'held_until' => ['nullable', 'date', 'after:now'],
        ];
    }
}
