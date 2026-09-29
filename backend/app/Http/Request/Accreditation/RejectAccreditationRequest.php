<?php

namespace HiEvents\Http\Request\Accreditation;

use HiEvents\Http\Request\BaseRequest;

class RejectAccreditationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // A rejected applicant may escalate, so the reason is mandatory and recorded.
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
