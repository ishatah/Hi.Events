<?php

namespace HiEvents\Http\Request\Raffle;

use HiEvents\Http\Request\BaseRequest;

class CreateRaffleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'prize_description' => ['nullable', 'string', 'max:500'],
            'eligibility_window_start' => ['required', 'date'],
            'eligibility_window_end' => ['required', 'date', 'after:eligibility_window_start'],
            'zone_id' => ['nullable', 'integer'],
            'winner_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'exclude_staff' => ['nullable', 'boolean'],
            'exclude_exhibitors' => ['nullable', 'boolean'],
        ];
    }
}
