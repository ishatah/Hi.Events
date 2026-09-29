<?php

namespace HiEvents\Http\Request\Device;

use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'device_type' => ['required', 'string', 'in:SCANNER,KIOSK,PRINT_HOST,PRINTER,TABLET'],
            'access_point_id' => [
                'nullable',
                'integer',
                Rule::exists('access_points', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
