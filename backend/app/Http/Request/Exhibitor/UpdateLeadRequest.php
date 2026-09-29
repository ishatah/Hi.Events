<?php

namespace HiEvents\Http\Request\Exhibitor;

use HiEvents\DomainObjects\Enums\LeadRating;
use HiEvents\DomainObjects\Status\LeadStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateLeadRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'rating' => ['nullable', 'string', new Enum(LeadRating::class)],
            'status' => ['nullable', 'string', new Enum(LeadStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'qualification' => ['nullable', 'array'],
        ];
    }
}
