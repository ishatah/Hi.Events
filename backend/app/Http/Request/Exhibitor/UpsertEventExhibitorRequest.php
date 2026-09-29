<?php

namespace HiEvents\Http\Request\Exhibitor;

use HiEvents\DomainObjects\Status\ExhibitorStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpsertEventExhibitorRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->whereNull('deleted_at'),
            ],
            'status' => ['required', 'string', new Enum(ExhibitorStatus::class)],
            'package_name' => ['nullable', 'string', 'max:120'],
            'staff_pass_quota' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
