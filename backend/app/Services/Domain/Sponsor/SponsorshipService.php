<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Sponsor;

use HiEvents\DomainObjects\Enums\EntitlementType;
use HiEvents\DomainObjects\Status\EntitlementStatus;
use HiEvents\DomainObjects\Status\SponsorshipStatus;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Sponsorships and what they entitle the sponsor to.
 *
 * A package is a price list; a sponsorship is the deal that was actually struck. Entitlements
 * are instantiated onto the sponsorship rather than read back from the package, because real
 * deals are negotiated away from the list and reading the package would report what was
 * advertised instead of what was agreed.
 *
 * @see docs/arzo-master-plan/34-sponsor-management.md
 */
class SponsorshipService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function createPackage(
        int $eventId,
        string $name,
        string $tier,
        ?float $price = null,
        ?string $currency = null,
        ?array $entitlements = null,
        ?int $maxSponsors = null,
        int $sortOrder = 0,
    ): int {
        foreach ($entitlements ?? [] as $entitlement) {
            $this->assertEntitlementTemplate($entitlement);
        }

        return (int) $this->databaseManager->table('sponsorship_packages')->insertGetId([
            'short_id' => 'sp_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'name' => $name,
            'tier' => $tier,
            'sort_order' => $sortOrder,
            'price' => $price,
            'currency' => $currency,
            'entitlements' => $entitlements !== null ? json_encode($entitlements) : null,
            'max_sponsors' => $maxSponsors,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Signs a company up, instantiating the package's entitlements onto the sponsorship.
     *
     * @throws ResourceConflictException
     */
    public function createSponsorship(
        int $eventId,
        int $companyId,
        string $tier,
        ?int $packageId = null,
        ?float $contractValue = null,
        ?string $currency = null,
        ?string $displayName = null,
        ?string $websiteUrl = null,
    ): int {
        return $this->databaseManager->transaction(function () use (
            $eventId,
            $companyId,
            $tier,
            $packageId,
            $contractValue,
            $currency,
            $displayName,
            $websiteUrl
        ): int {
            $package = null;

            if ($packageId !== null) {
                $package = $this->databaseManager->table('sponsorship_packages')
                    ->where('id', $packageId)
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if ($package === null) {
                    throw new ResourceConflictException(__('That sponsorship package could not be found.'));
                }

                $this->assertPackageHasRoom($package);
            }

            try {
                $sponsorshipId = (int) $this->databaseManager->table('sponsorships')->insertGetId([
                    'short_id' => 'ss_'.Str::lower(Str::random(20)),
                    'event_id' => $eventId,
                    'company_id' => $companyId,
                    'sponsorship_package_id' => $packageId,
                    'tier' => $tier,
                    'status' => SponsorshipStatus::PROPOSED->value,
                    'contract_value' => $contractValue,
                    'currency' => $currency,
                    'payment_status' => 'UNPAID',
                    'display_name' => $displayName,
                    'website_url' => $websiteUrl,
                    'show_on_event_page' => false,
                    'display_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new ResourceConflictException(
                    __('That company already sponsors this event.')
                );
            }

            if ($package !== null) {
                $this->instantiateEntitlements($sponsorshipId, $package);
            }

            return $sponsorshipId;
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function addEntitlement(
        int $sponsorshipId,
        string $entitlementType,
        int $quantity = 1,
        ?string $description = null,
        ?string $dueAt = null,
    ): int {
        if (EntitlementType::tryFrom($entitlementType) === null) {
            throw new ResourceConflictException(__('That is not a known entitlement type.'));
        }

        if ($quantity < 1) {
            throw new ResourceConflictException(__('An entitlement must be for at least one.'));
        }

        return (int) $this->databaseManager->table('sponsorship_entitlements')->insertGetId([
            'short_id' => 'se_'.Str::lower(Str::random(20)),
            'sponsorship_id' => $sponsorshipId,
            'entitlement_type' => $entitlementType,
            'description' => $description,
            'quantity' => $quantity,
            'fulfilled_quantity' => 0,
            'status' => EntitlementStatus::PENDING->value,
            'due_at' => $dueAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Records delivery against an entitlement.
     *
     * Evidence is appended rather than replaced: three logo placements delivered on three
     * dates are three pieces of proof, and overwriting would leave the last one looking
     * like the whole story.
     *
     * @throws ResourceConflictException
     */
    public function recordFulfilment(
        int $entitlementId,
        int $quantity = 1,
        ?array $evidence = null,
    ): void {
        $this->databaseManager->transaction(function () use ($entitlementId, $quantity, $evidence): void {
            $entitlement = $this->databaseManager->table('sponsorship_entitlements')
                ->where('id', $entitlementId)
                ->lockForUpdate()
                ->first();

            if ($entitlement === null) {
                throw new ResourceConflictException(__('That entitlement could not be found.'));
            }

            if ((string) $entitlement->status === EntitlementStatus::WAIVED->value) {
                throw new ResourceConflictException(
                    __('That entitlement was waived and cannot be fulfilled.')
                );
            }

            $fulfilled = (int) $entitlement->fulfilled_quantity + $quantity;

            if ($fulfilled > (int) $entitlement->quantity) {
                throw new ResourceConflictException(
                    __('That would deliver :fulfilled of :quantity promised.', [
                        'fulfilled' => $fulfilled,
                        'quantity' => (int) $entitlement->quantity,
                    ])
                );
            }

            $existingEvidence = $entitlement->evidence !== null
                ? (json_decode((string) $entitlement->evidence, true) ?: [])
                : [];

            if ($evidence !== null) {
                $existingEvidence[] = $evidence + ['recorded_at' => now()->toIso8601String()];
            }

            $isComplete = $fulfilled === (int) $entitlement->quantity;

            $this->databaseManager->table('sponsorship_entitlements')
                ->where('id', $entitlementId)
                ->update([
                    'fulfilled_quantity' => $fulfilled,
                    'status' => $isComplete
                        ? EntitlementStatus::FULFILLED->value
                        : EntitlementStatus::IN_PROGRESS->value,
                    'fulfilled_at' => $isComplete ? now() : null,
                    'evidence' => $existingEvidence !== [] ? json_encode($existingEvidence) : null,
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function waiveEntitlement(int $entitlementId, string $reason): void
    {
        $entitlement = $this->databaseManager->table('sponsorship_entitlements')
            ->where('id', $entitlementId)
            ->first();

        if ($entitlement === null) {
            throw new ResourceConflictException(__('That entitlement could not be found.'));
        }

        if ((string) $entitlement->status === EntitlementStatus::FULFILLED->value) {
            throw new ResourceConflictException(
                __('That entitlement was already delivered and cannot be waived.')
            );
        }

        $evidence = $entitlement->evidence !== null
            ? (json_decode((string) $entitlement->evidence, true) ?: [])
            : [];

        $evidence[] = ['waived_reason' => $reason, 'recorded_at' => now()->toIso8601String()];

        $this->databaseManager->table('sponsorship_entitlements')
            ->where('id', $entitlementId)
            ->update([
                'status' => EntitlementStatus::WAIVED->value,
                'evidence' => json_encode($evidence),
                'updated_at' => now(),
            ]);
    }

    /**
     * @throws ResourceConflictException
     */
    public function transitionStatus(int $sponsorshipId, int $eventId, string $status): void
    {
        $target = SponsorshipStatus::tryFrom($status);

        if ($target === null) {
            throw new ResourceConflictException(__('That is not a valid sponsorship status.'));
        }

        $changes = ['status' => $target->value, 'updated_at' => now()];

        // A sponsor whose deal fell through comes off the public page with the status
        // change, not when somebody remembers the second switch.
        if (! $target->isPubliclyDisplayable()) {
            $changes['show_on_event_page'] = false;
        }

        $updated = $this->databaseManager->table('sponsorships')
            ->where('id', $sponsorshipId)
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->update($changes);

        if ($updated === 0) {
            throw new ResourceConflictException(__('That sponsorship could not be found.'));
        }
    }

    /**
     * @throws ResourceConflictException
     */
    public function setPublicDisplay(
        int $sponsorshipId,
        int $eventId,
        bool $show,
        int $displayOrder = 0,
    ): void {
        $sponsorship = $this->databaseManager->table('sponsorships')
            ->where('id', $sponsorshipId)
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->first();

        if ($sponsorship === null) {
            throw new ResourceConflictException(__('That sponsorship could not be found.'));
        }

        $status = SponsorshipStatus::from((string) $sponsorship->status);

        if ($show && ! $status->isPubliclyDisplayable()) {
            throw new ResourceConflictException(
                __('A :status sponsorship cannot be shown publicly.', ['status' => $status->value])
            );
        }

        $this->databaseManager->table('sponsorships')
            ->where('id', $sponsorshipId)
            ->update([
                'show_on_event_page' => $show,
                'display_order' => $displayOrder,
                'updated_at' => now(),
            ]);
    }

    /**
     * The organizer's view: every sponsorship of the event, whatever its status.
     *
     * @return Collection<int, object>
     */
    public function listForEvent(int $eventId, ?string $status = null): Collection
    {
        return $this->databaseManager->table('sponsorships')
            ->join('companies', 'companies.id', '=', 'sponsorships.company_id')
            ->leftJoin(
                'sponsorship_packages',
                'sponsorship_packages.id',
                '=',
                'sponsorships.sponsorship_package_id'
            )
            ->where('sponsorships.event_id', $eventId)
            ->whereNull('sponsorships.deleted_at')
            ->when($status !== null, static fn ($query) => $query->where('sponsorships.status', $status))
            ->orderBy('sponsorships.display_order')
            ->orderBy('companies.name')
            ->select([
                'sponsorships.id',
                'sponsorships.short_id',
                'sponsorships.company_id',
                'sponsorships.sponsorship_package_id',
                'sponsorships.tier',
                'sponsorships.status',
                'sponsorships.contract_value',
                'sponsorships.currency',
                'sponsorships.payment_status',
                'sponsorships.display_name',
                'sponsorships.website_url',
                'sponsorships.show_on_event_page',
                'sponsorships.display_order',
                'sponsorships.created_at',
                'companies.name as company_name',
                'sponsorship_packages.name as package_name',
            ])
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function listPackages(int $eventId): Collection
    {
        return $this->databaseManager->table('sponsorship_packages')
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Confirms a sponsorship belongs to the event before anything is changed through it,
     * because the id arrives in the URL and the event is what authorization was checked on.
     *
     * @throws ResourceConflictException
     */
    public function assertBelongsToEvent(int $sponsorshipId, int $eventId): void
    {
        $exists = $this->databaseManager->table('sponsorships')
            ->where('id', $sponsorshipId)
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            throw new ResourceConflictException(__('That sponsorship could not be found.'));
        }
    }

    /**
     * @throws ResourceConflictException
     */
    public function assertEntitlementBelongsToEvent(int $entitlementId, int $eventId): void
    {
        $exists = $this->databaseManager->table('sponsorship_entitlements')
            ->join('sponsorships', 'sponsorships.id', '=', 'sponsorship_entitlements.sponsorship_id')
            ->where('sponsorship_entitlements.id', $entitlementId)
            ->where('sponsorships.event_id', $eventId)
            ->whereNull('sponsorships.deleted_at')
            ->exists();

        if (! $exists) {
            throw new ResourceConflictException(__('That entitlement could not be found.'));
        }
    }

    /**
     * The public sponsor strip. Only signed sponsors with a logo, in the order the organizer set.
     *
     * @return Collection<int, object>
     */
    public function publicSponsors(int $eventId): Collection
    {
        return $this->databaseManager->table('sponsorships')
            ->join('companies', 'companies.id', '=', 'sponsorships.company_id')
            ->leftJoin('images', 'images.id', '=', 'sponsorships.logo_image_id')
            ->where('sponsorships.event_id', $eventId)
            ->where('sponsorships.show_on_event_page', true)
            ->whereIn('sponsorships.status', [
                SponsorshipStatus::CONTRACTED->value,
                SponsorshipStatus::ACTIVE->value,
                SponsorshipStatus::FULFILLED->value,
            ])
            ->whereNull('sponsorships.deleted_at')
            ->whereNull('companies.deleted_at')
            ->orderBy('sponsorships.display_order')
            ->orderBy('companies.name')
            ->select([
                'sponsorships.short_id',
                'sponsorships.tier',
                'sponsorships.website_url',
                'sponsorships.display_order',
                $this->databaseManager->raw(
                    'coalesce(sponsorships.display_name, companies.name) as name'
                ),
                'images.path as logo_path',
            ])
            ->get();
    }

    /**
     * Promised against delivered, which is what a renewal conversation and a dispute both
     * turn on.
     *
     * @return array<string, mixed>
     */
    public function fulfilmentSummary(int $sponsorshipId): array
    {
        $entitlements = $this->databaseManager->table('sponsorship_entitlements')
            ->where('sponsorship_id', $sponsorshipId)
            ->orderBy('entitlement_type')
            ->get();

        $promised = 0;
        $delivered = 0;
        $outstanding = 0;
        $waived = 0;

        $rows = [];

        foreach ($entitlements as $entitlement) {
            $status = EntitlementStatus::from((string) $entitlement->status);
            $type = EntitlementType::tryFrom((string) $entitlement->entitlement_type);

            $quantity = (int) $entitlement->quantity;
            $fulfilledQuantity = (int) $entitlement->fulfilled_quantity;

            if ($status === EntitlementStatus::WAIVED) {
                $waived++;
            } else {
                $promised += $quantity;
                $delivered += $fulfilledQuantity;

                if ($status->isOutstanding()) {
                    $outstanding++;
                }
            }

            $rows[] = [
                'entitlement_id' => (int) $entitlement->id,
                'entitlement_type' => (string) $entitlement->entitlement_type,
                'description' => $entitlement->description,
                'quantity' => $quantity,
                'fulfilled_quantity' => $fulfilledQuantity,
                'status' => $status->value,
                'due_at' => $entitlement->due_at,
                'is_overdue' => $this->isOverdue($entitlement, $status),
                'has_system_evidence' => $type?->hasSystemEvidence() ?? false,
                'evidence_count' => $entitlement->evidence !== null
                    ? count(json_decode((string) $entitlement->evidence, true) ?: [])
                    : 0,
            ];
        }

        return [
            'entitlements' => $rows,
            'promised_units' => $promised,
            'delivered_units' => $delivered,
            'outstanding_entitlements' => $outstanding,
            'waived_entitlements' => $waived,
            'completion' => $promised > 0 ? round($delivered / $promised, 3) : null,
        ];
    }

    private function isOverdue(object $entitlement, EntitlementStatus $status): bool
    {
        if (! $status->isOutstanding() || $entitlement->due_at === null) {
            return false;
        }

        return now()->gt($entitlement->due_at);
    }

    private function instantiateEntitlements(int $sponsorshipId, object $package): void
    {
        $template = $package->entitlements !== null
            ? (json_decode((string) $package->entitlements, true) ?: [])
            : [];

        if ($template === []) {
            return;
        }

        $rows = [];

        foreach ($template as $entitlement) {
            $type = EntitlementType::tryFrom((string) ($entitlement['type'] ?? ''));

            if ($type === null) {
                continue;
            }

            $rows[] = [
                'short_id' => 'se_'.Str::lower(Str::random(20)),
                'sponsorship_id' => $sponsorshipId,
                'entitlement_type' => $type->value,
                'description' => $entitlement['description'] ?? null,
                'quantity' => max(1, (int) ($entitlement['quantity'] ?? 1)),
                'fulfilled_quantity' => 0,
                'status' => EntitlementStatus::PENDING->value,
                'due_at' => null,
                'evidence' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            $this->databaseManager->table('sponsorship_entitlements')->insert($rows);
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function assertPackageHasRoom(object $package): void
    {
        if ($package->max_sponsors === null) {
            return;
        }

        $taken = $this->databaseManager->table('sponsorships')
            ->where('sponsorship_package_id', $package->id)
            ->whereNull('deleted_at')
            ->where('status', '!=', SponsorshipStatus::CANCELLED->value)
            ->count();

        if ($taken >= (int) $package->max_sponsors) {
            throw new ResourceConflictException(
                __('The :name package is limited to :max sponsor(s) and is full.', [
                    'name' => (string) $package->name,
                    'max' => (int) $package->max_sponsors,
                ])
            );
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function assertEntitlementTemplate(mixed $entitlement): void
    {
        if (! is_array($entitlement) || ! isset($entitlement['type'])) {
            throw new ResourceConflictException(
                __('Each entitlement in a package needs a type.')
            );
        }

        if (EntitlementType::tryFrom((string) $entitlement['type']) === null) {
            throw new ResourceConflictException(
                __(':type is not a known entitlement type.', ['type' => (string) $entitlement['type']])
            );
        }
    }
}
