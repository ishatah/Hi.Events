<?php

namespace HiEvents\Resources\Sponsor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public sponsor strip. Deliberately narrow: contract values, payment status and
 * fulfilment are commercial detail that has no business on an attendee-facing page.
 *
 * @mixin object
 */
class PublicSponsorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'short_id' => (string) $this->resource->short_id,
            'name' => (string) $this->resource->name,
            'tier' => (string) $this->resource->tier,
            'website_url' => $this->resource->website_url,
            'logo_path' => $this->resource->logo_path,
        ];
    }
}
