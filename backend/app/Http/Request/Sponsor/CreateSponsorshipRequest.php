<?php

namespace HiEvents\Http\Request\Sponsor;

use HiEvents\Http\Request\BaseRequest;

class CreateSponsorshipRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer'],
            'tier' => ['required', 'string', 'max:64'],
            'sponsorship_package_id' => ['nullable', 'integer'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'display_name' => ['nullable', 'string', 'max:191'],
            'website_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
