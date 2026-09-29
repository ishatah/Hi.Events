<?php

namespace HiEvents\Http\Request\Badge;

use HiEvents\Http\Request\BaseRequest;

class CapturePersonPhotoRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ];
    }
}
