<?php

namespace HiEvents\Http\Request\Notification;

use HiEvents\Http\Request\BaseRequest;

class RegisterPushSubscriptionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:WEB,FCM,APNS'],

            // Web push carries an endpoint and its two keys; native carries a token. Which is
            // required depends on the platform, so the shape is checked rather than making all
            // three optional and hoping.
            'endpoint' => ['required_if:platform,WEB', 'nullable', 'url', 'max:2000'],
            'keys.p256dh' => ['required_if:platform,WEB', 'nullable', 'string', 'max:255'],
            'keys.auth' => ['required_if:platform,WEB', 'nullable', 'string', 'max:255'],
            'token' => ['required_if:platform,FCM', 'required_if:platform,APNS', 'nullable', 'string', 'max:512'],

            'event_id' => ['nullable', 'integer'],
            'locale' => ['nullable', 'string', 'max:12'],
            'declined_categories' => ['nullable', 'array'],
            'declined_categories.*' => ['string', 'max:64'],
        ];
    }
}
