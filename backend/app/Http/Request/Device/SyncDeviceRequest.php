<?php

namespace HiEvents\Http\Request\Device;

use HiEvents\Http\Request\BaseRequest;

class SyncDeviceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string', 'max:64'],
            'pending_logs' => ['nullable', 'array', 'max:500'],
            'pending_logs.*.client_generated_id' => ['nullable', 'uuid'],
            'pending_logs.*.identifier' => ['required_with:pending_logs.*.client_generated_id', 'string'],
            'pending_logs.*.access_point_id' => ['nullable', 'integer'],
            'pending_logs.*.occurred_at' => ['nullable', 'date'],
            'pending_logs.*.result' => ['nullable', 'string', 'max:32'],
        ];
    }
}
