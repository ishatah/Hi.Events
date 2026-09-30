<?php

namespace HiEvents\Http\Request\Sponsor;

use HiEvents\DomainObjects\Enums\EntitlementType;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class AddEntitlementRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'entitlement_type' => ['required', 'string', Rule::in(EntitlementType::valuesArray())],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
