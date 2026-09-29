<?php

namespace HiEvents\Resources\Exhibitor;

use Illuminate\Http\Resources\Json\JsonResource;

class EventExhibitorResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'short_id' => $this->resource->short_id,
            'event_id' => (int) $this->resource->event_id,
            'company_id' => (int) $this->resource->company_id,
            'company_name' => $this->resource->company_name ?? null,
            'status' => $this->resource->status,
            'package_name' => $this->resource->package_name,
            'staff_pass_quota' => $this->resource->staff_pass_quota,
            'listing_published' => (bool) $this->resource->listing_published,
            'payment_status' => $this->resource->payment_status,
            'created_at' => $this->resource->created_at,
        ];
    }
}
