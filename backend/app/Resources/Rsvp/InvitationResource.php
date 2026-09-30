<?php

namespace HiEvents\Resources\Rsvp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin object
 */
class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->id,
            'short_id' => (string) $this->resource->short_id,
            'first_name' => (string) $this->resource->first_name,
            'last_name' => $this->resource->last_name !== null ? (string) $this->resource->last_name : null,
            'email' => (string) $this->resource->email,
            'phone' => $this->resource->phone !== null ? (string) $this->resource->phone : null,
            'max_party_size' => (int) $this->resource->max_party_size,
            /** @var 'PENDING'|'SENT'|'ATTENDING'|'NOT_ATTENDING'|'TENTATIVE'|'REVOKED' */
            'status' => (string) $this->resource->status,
            'sent_at' => $this->resource->sent_at,
            'expires_at' => $this->resource->expires_at,
            'party_size' => $this->resource->party_size !== null ? (int) $this->resource->party_size : null,
            'responded_at' => $this->resource->responded_at ?? null,
            'created_at' => $this->resource->created_at,
        ];
    }
}
