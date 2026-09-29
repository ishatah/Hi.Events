<?php

namespace HiEvents\Http\Request\Exhibitor;

use HiEvents\Http\Request\BaseRequest;

class CaptureLeadRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'identifier_type' => ['nullable', 'string', 'in:QR,RFID,NFC,MANUAL'],
            // Generated on the booth phone so a queued capture replays as a no-op.
            'client_generated_id' => ['nullable', 'uuid'],
            'captured_at' => ['nullable', 'date'],
            'exhibitor_staff_id' => ['nullable', 'integer'],
        ];
    }
}
