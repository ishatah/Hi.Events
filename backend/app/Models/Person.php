<?php

namespace HiEvents\Models;

use HiEvents\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'persons';

    /**
     * Identity documents and dates of birth are encrypted at rest.
     *
     * These are the fields an accreditation type can demand — a passport number, a national
     * ID, a date of birth — and they are the ones that cause real harm if a backup, a
     * replica or a support query exposes them. Nothing else on this record is as sensitive:
     * a name and an email are already visible on the badge.
     *
     * The columns are text rather than varchar because ciphertext is much longer than the
     * value it carries, and they were sized for it. Encrypting here rather than in a service
     * means no write path can bypass it.
     *
     * @see docs/arzo-master-plan/65-privacy-gdpr.md
     */
    protected function getCastMap(): array
    {
        return [
            'id_document_number' => 'encrypted',
            'id_document_type' => 'encrypted',
            'date_of_birth' => 'encrypted',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }
}
