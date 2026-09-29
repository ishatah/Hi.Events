<?php

namespace HiEvents\Resources\ApiKey;

use Illuminate\Http\Resources\Json\JsonResource;

class ApiKeyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'short_id' => $this->resource->short_id,
            'name' => $this->resource->name,
            // The prefix identifies the key in a list without exposing it. The secret half
            // is never returned again after creation.
            'key_prefix' => $this->resource->key_prefix,
            'scopes' => json_decode((string) $this->resource->scopes, true) ?: [],
            'organizer_id' => $this->resource->organizer_id,
            'event_id' => $this->resource->event_id,
            'rate_limit_per_minute' => $this->resource->rate_limit_per_minute,
            'expires_at' => $this->resource->expires_at,
            'last_used_at' => $this->resource->last_used_at,
            'revoked_at' => $this->resource->revoked_at,
            'created_at' => $this->resource->created_at,
        ];
    }
}
