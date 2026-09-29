<?php

namespace HiEvents\Http\Request\Operations;

use HiEvents\DomainObjects\Status\IncidentStatus;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rules\Enum;

class TransitionIncidentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', new Enum(IncidentStatus::class)],
            'note' => ['nullable', 'string', 'max:5000'],
            // Required by the service when resolving; validated there so the rule lives in
            // one place rather than being duplicated per transition.
            'resolution' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
