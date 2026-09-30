<?php

namespace HiEvents\Resources\Sponsor;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin object
 */
class SponsorshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'short_id' => (string) $this->resource->short_id,
            'company_id' => (int) $this->resource->company_id,
            'company_name' => (string) $this->resource->company_name,
            'display_name' => $this->resource->display_name !== null
                ? (string) $this->resource->display_name
                : null,
            'tier' => (string) $this->resource->tier,
            /** @var 'PROPOSED'|'CONTRACTED'|'ACTIVE'|'FULFILLED'|'CANCELLED' */
            'status' => (string) $this->resource->status,
            'sponsorship_package_id' => $this->resource->sponsorship_package_id !== null
                ? (int) $this->resource->sponsorship_package_id
                : null,
            'package_name' => $this->resource->package_name ?? null,
            'contract_value' => $this->resource->contract_value !== null
                ? (float) $this->resource->contract_value
                : null,
            'currency' => $this->resource->currency,
            'payment_status' => (string) $this->resource->payment_status,
            'website_url' => $this->resource->website_url,
            'show_on_event_page' => (bool) $this->resource->show_on_event_page,
            'display_order' => (int) $this->resource->display_order,
            'created_at' => $this->resource->created_at,
        ];
    }
}
