<?php

namespace HiEvents\Http\Request\Sponsor;

use HiEvents\DomainObjects\Enums\EntitlementType;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class UpsertSponsorshipPackageRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'tier' => ['required', 'string', 'max:64'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'max_sponsors' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'entitlements' => ['nullable', 'array'],
            'entitlements.*.type' => ['required', 'string', Rule::in(EntitlementType::valuesArray())],
            'entitlements.*.quantity' => ['nullable', 'integer', 'min:1'],
            'entitlements.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
