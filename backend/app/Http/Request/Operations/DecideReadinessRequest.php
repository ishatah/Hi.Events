<?php

namespace HiEvents\Http\Request\Operations;

use HiEvents\Http\Request\BaseRequest;

class DecideReadinessRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:GO,GO_WITH_RISKS,NO_GO'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'waivers' => ['nullable', 'array'],
            'waivers.*.item_id' => ['required', 'integer'],
            // A waiver must say why the risk was accepted; an unexplained override is what a
            // post-incident review goes looking for.
            'waivers.*.reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
