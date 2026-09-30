<?php

namespace HiEvents\Http\Request\Sponsor;

use HiEvents\DomainObjects\Status\SponsorshipStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateSponsorshipRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(SponsorshipStatus::valuesArray())],
            'show_on_event_page' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
