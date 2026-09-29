<?php

namespace HiEvents\Resources\Exhibitor;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'short_id' => $this->resource->short_id,
            'event_exhibitor_id' => (int) $this->resource->event_exhibitor_id,
            // The snapshot taken at capture time, not the live person row. What the
            // exhibitor holds is what was transferred.
            'shared_fields' => json_decode((string) $this->resource->shared_fields, true) ?: [],
            'first_captured_at' => $this->resource->first_captured_at,
            'last_captured_at' => $this->resource->last_captured_at,
            'capture_count' => (int) $this->resource->capture_count,
            'rating' => $this->resource->rating,
            'status' => $this->resource->status,
            'notes' => $this->resource->notes,
        ];
    }
}
