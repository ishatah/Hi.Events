<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use HiEvents\DomainObjects\Status\BadgePrintJobStatus;
use HiEvents\DomainObjects\Status\BadgeStatus;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Creates, reprints and voids badges.
 *
 * A badge pins the template version it was rendered from and snapshots the field values,
 * so a reprint reproduces the original rather than whatever the template says now. A
 * reprinted badge that looks different from its neighbours reads as a forgery at a door.
 *
 * @see docs/arzo-master-plan/21-badge-management.md
 */
class BadgeIssuanceService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly BadgeRenderService $badgeRenderService,
        private readonly FilesystemFactory $filesystemFactory,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function issue(int $credentialId, ?int $badgeTemplateId = null): int
    {
        $credential = $this->databaseManager->table('credentials')
            ->where('id', $credentialId)
            ->first();

        if ($credential === null) {
            throw new ResourceConflictException(__('The credential could not be found.'));
        }

        if ($credential->status !== 'ACTIVE') {
            throw new ResourceConflictException(
                __('A badge can only be issued for an active credential.')
            );
        }

        $existing = $this->databaseManager->table('badges')
            ->where('credential_id', $credentialId)
            ->whereIn('status', [BadgeStatus::PENDING->value, BadgeStatus::PRINTED->value])
            ->first();

        if ($existing !== null) {
            throw new ResourceConflictException(
                __('This credential already has a badge. Reprint it instead.')
            );
        }

        $template = $this->resolveTemplate((int) $credential->event_id, $badgeTemplateId);

        return $this->createBadge($credentialId, (int) $credential->event_id, $template);
    }

    /**
     * A reprint is a new badge that points back at the one it replaces, rather than an
     * edit. The print count on the old row would otherwise be the only record that a second
     * physical badge exists, and two badges in circulation is exactly what needs tracing.
     *
     * @throws ResourceConflictException
     */
    public function reprint(int $badgeId, ?int $actorUserId = null): int
    {
        return $this->databaseManager->transaction(function () use ($badgeId, $actorUserId): int {
            $badge = $this->databaseManager->table('badges')
                ->where('id', $badgeId)
                ->lockForUpdate()
                ->first();

            if ($badge === null) {
                throw new ResourceConflictException(__('The badge could not be found.'));
            }

            $status = BadgeStatus::tryFrom((string) $badge->status);

            if ($status === null || ! $status->isUsable()) {
                throw new ResourceConflictException(
                    __('A voided or replaced badge cannot be reprinted.')
                );
            }

            $template = $this->databaseManager->table('badge_templates')
                ->where('id', $badge->badge_template_id)
                ->first();

            if ($template === null) {
                throw new ResourceConflictException(__('The badge template could not be found.'));
            }

            $this->databaseManager->table('badges')
                ->where('id', $badgeId)
                ->update([
                    'status' => BadgeStatus::REPLACED->value,
                    'updated_at' => now(),
                ]);

            return $this->createBadge(
                credentialId: (int) $badge->credential_id,
                eventId: (int) $badge->event_id,
                template: (array) $template,
                replacesBadgeId: $badgeId,
                actorUserId: $actorUserId,
            );
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function void(int $badgeId, ?int $actorUserId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new ResourceConflictException(__('A void reason is required.'));
        }

        $badge = $this->databaseManager->table('badges')->where('id', $badgeId)->first();

        if ($badge === null) {
            throw new ResourceConflictException(__('The badge could not be found.'));
        }

        if ($badge->status === BadgeStatus::VOIDED->value) {
            throw new ResourceConflictException(__('This badge is already voided.'));
        }

        $this->databaseManager->transaction(function () use ($badgeId, $actorUserId, $reason): void {
            $this->databaseManager->table('badges')
                ->where('id', $badgeId)
                ->update([
                    'status' => BadgeStatus::VOIDED->value,
                    'voided_at' => now(),
                    'voided_by' => $actorUserId,
                    'void_reason' => $reason,
                    'updated_at' => now(),
                ]);

            // A queued job for a voided badge must not reach a printer.
            $this->databaseManager->table('badge_print_jobs')
                ->where('badge_id', $badgeId)
                ->whereIn('status', [BadgePrintJobStatus::QUEUED->value, BadgePrintJobStatus::SENT->value])
                ->update([
                    'status' => BadgePrintJobStatus::CANCELLED->value,
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * @return array<int, object>
     */
    public function historyFor(int $credentialId): array
    {
        return $this->databaseManager->table('badges')
            ->where('credential_id', $credentialId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $template
     *
     * @throws ResourceConflictException
     */
    private function createBadge(
        int $credentialId,
        int $eventId,
        array $template,
        ?int $replacesBadgeId = null,
        ?int $actorUserId = null,
    ): int {
        $rendered = $this->badgeRenderService->render($credentialId, $this->normaliseTemplate($template));

        $path = sprintf('badges/%d/%s.pdf', $eventId, Str::lower(Str::random(32)));

        $this->filesystemFactory->disk(config('filesystems.default'))->put($path, $rendered['pdf']);

        return (int) $this->databaseManager->table('badges')->insertGetId([
            'short_id' => 'bd_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'credential_id' => $credentialId,
            'badge_template_id' => $template['id'],
            'template_version' => $template['version'] ?? 1,
            'status' => BadgeStatus::PENDING->value,
            'rendered_pdf_path' => $path,
            'rendered_at' => now(),
            'print_count' => 0,
            'replaces_badge_id' => $replacesBadgeId,
            'snapshot' => json_encode($rendered['snapshot']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function normaliseTemplate(array $template): array
    {
        $layout = $template['layout'] ?? [];

        return [
            'width_mm' => $template['width_mm'] ?? 105,
            'height_mm' => $template['height_mm'] ?? 148,
            'layout' => is_string($layout) ? (json_decode($layout, true) ?? []) : $layout,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ResourceConflictException
     */
    private function resolveTemplate(int $eventId, ?int $badgeTemplateId): array
    {
        $query = $this->databaseManager->table('badge_templates')->whereNull('deleted_at');

        if ($badgeTemplateId !== null) {
            $template = $query->where('id', $badgeTemplateId)->first();
        } else {
            $template = $query->where('event_id', $eventId)
                ->where('is_default', true)
                ->first();
        }

        if ($template === null) {
            throw new ResourceConflictException(
                __('No badge template is available for this event.')
            );
        }

        return (array) $template;
    }
}
