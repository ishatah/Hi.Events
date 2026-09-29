<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use Barryvdh\DomPDF\Facade\Pdf;
use HiEvents\DomainObjects\Enums\BadgeElementType;
use HiEvents\DomainObjects\Enums\BadgeField;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Badge\DTO\BadgeRenderDataDTO;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\DatabaseManager;

/**
 * Renders a badge to PDF at exact physical dimensions.
 *
 * Server-side and authoritative: a browser cannot bypass its own print dialog, so
 * badge-on-demand at a desk cannot be done client-side.
 *
 * The rendered field values are snapshotted onto the badge row. A reprint must reproduce
 * what was originally printed rather than what the template says today, otherwise a
 * reprinted badge looks different from its neighbours — and the template version alone is
 * not enough, because the person's own details may have been corrected since.
 *
 * @see docs/arzo-master-plan/21-badge-management.md
 */
class BadgeRenderService
{
    private const MM_PER_POINT = 0.352777778;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly BadgeQrCodeService $badgeQrCodeService,
        private readonly FilesystemFactory $filesystemFactory,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function gatherData(int $credentialId): BadgeRenderDataDTO
    {
        $credential = $this->databaseManager->table('credentials')
            ->where('id', $credentialId)
            ->first();

        if ($credential === null) {
            throw new ResourceConflictException(__('The credential could not be found.'));
        }

        $person = $credential->person_id !== null
            ? $this->databaseManager->table('persons')->where('id', $credential->person_id)->first()
            : null;

        $attendee = $credential->attendee_id !== null
            ? $this->databaseManager->table('attendees')->where('id', $credential->attendee_id)->first()
            : null;

        $event = $this->databaseManager->table('events')
            ->where('id', $credential->event_id)
            ->first();

        $accreditationTypeName = null;

        if ($credential->accreditation_id !== null) {
            $accreditationTypeName = $this->databaseManager->table('accreditations')
                ->join(
                    'accreditation_types',
                    'accreditation_types.id',
                    '=',
                    'accreditations.accreditation_type_id'
                )
                ->where('accreditations.id', $credential->accreditation_id)
                ->value('accreditation_types.name');
        }

        $zones = $this->databaseManager->table('access_grants')
            ->join('zones', 'zones.id', '=', 'access_grants.zone_id')
            ->where('access_grants.credential_id', $credentialId)
            ->where('access_grants.status', 'ACTIVE')
            ->whereNull('zones.deleted_at')
            ->distinct()
            ->select(['zones.name', 'zones.code', 'zones.colour'])
            ->get();

        return new BadgeRenderDataDTO(
            credentialId: $credentialId,
            identifier: (string) $credential->identifier,
            credentialType: (string) $credential->credential_type,
            firstName: (string) ($person->first_name ?? $attendee->first_name ?? ''),
            lastName: (string) ($person->last_name ?? $attendee->last_name ?? ''),
            company: $person->company ?? null,
            jobTitle: $person->job_title ?? null,
            accreditationTypeName: $accreditationTypeName,
            eventName: (string) ($event->title ?? ''),
            eventStartDate: $event->start_date ?? null,
            eventEndDate: $event->end_date ?? null,
            zoneNames: $zones->pluck('code')->map(static fn ($c): string => (string) $c)->all(),
            zoneColours: $zones->pluck('colour')
                ->filter()
                ->map(static fn ($c): string => (string) $c)
                ->values()
                ->all(),
            photoUrl: $this->photoDataUriFor($person),
        );
    }

    /**
     * dompdf reads a local path, not a URL, so the badge embeds the stored file directly.
     * A missing file yields null and the template falls back to its placeholder rather than
     * failing the render with somebody waiting at the desk.
     */
    private function photoDataUriFor(?object $person): ?string
    {
        if ($person === null || $person->photo_image_id === null) {
            return null;
        }

        $path = $this->databaseManager->table('images')
            ->where('id', $person->photo_image_id)
            ->value('path');

        if ($path === null) {
            return null;
        }

        $disk = $this->filesystemFactory->disk(config('filesystems.default'));

        if (! $disk->exists($path)) {
            return null;
        }

        // A badge photo lives on a private disk and may not be on local storage at all, so
        // it is embedded as a data URI rather than referenced by path. dompdf would
        // otherwise need filesystem access to a location it is deliberately denied.
        return 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'
            .base64_encode((string) $disk->get($path));
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array{pdf: string, snapshot: array<string, mixed>}
     *
     * @throws ResourceConflictException
     */
    public function render(int $credentialId, array $template): array
    {
        $data = $this->gatherData($credentialId);
        $resolved = $this->resolveFields($data);

        $html = $this->buildHtml($template, $data, $resolved);

        $widthMm = (float) ($template['width_mm'] ?? 105);
        $heightMm = (float) ($template['height_mm'] ?? 148);

        $pdf = Pdf::loadHTML($html)->setPaper([
            0,
            0,
            $widthMm / self::MM_PER_POINT,
            $heightMm / self::MM_PER_POINT,
        ]);

        $output = $pdf->output();

        // dompdf retains roughly 6MB per document in its own static caches, regardless of
        // how the instance is created — reproduced against a bare Dompdf object with none
        // of this service involved. Nothing here can free it, so badge rendering must run
        // on a queue worker with a bounded job count rather than in a long-lived process.
        // Tracked as ARZ-339.
        unset($pdf);

        return [
            'pdf' => $output,
            'snapshot' => [
                'fields' => $resolved,
                'zone_colours' => $data->zoneColours,
                'qr_encoding' => $this->badgeQrCodeService->describeEncoding(),
                'width_mm' => $widthMm,
                'height_mm' => $heightMm,
                'rendered_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolveFields(BadgeRenderDataDTO $data): array
    {
        $fullName = trim($data->firstName.' '.$data->lastName);

        return [
            BadgeField::PERSON_FULL_NAME->value => $fullName,
            BadgeField::PERSON_FIRST_NAME->value => $data->firstName,
            BadgeField::PERSON_LAST_NAME->value => $data->lastName,
            BadgeField::PERSON_COMPANY->value => (string) ($data->company ?? ''),
            BadgeField::PERSON_JOB_TITLE->value => (string) ($data->jobTitle ?? ''),
            BadgeField::ACCREDITATION_TYPE->value => (string) ($data->accreditationTypeName ?? $data->credentialType),
            BadgeField::CREDENTIAL_IDENTIFIER->value => $data->identifier,
            BadgeField::CREDENTIAL_TYPE->value => $data->credentialType,
            BadgeField::EVENT_NAME->value => $data->eventName,
            BadgeField::EVENT_DATES->value => $this->formatDates($data),
            BadgeField::ZONE_LIST->value => implode(' · ', $data->zoneNames),
        ];
    }

    private function formatDates(BadgeRenderDataDTO $data): string
    {
        if ($data->eventStartDate === null) {
            return '';
        }

        $start = date('j M Y', strtotime((string) $data->eventStartDate));

        if ($data->eventEndDate === null) {
            return $start;
        }

        return $start.' – '.date('j M Y', strtotime((string) $data->eventEndDate));
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $resolved
     */
    private function buildHtml(array $template, BadgeRenderDataDTO $data, array $resolved): string
    {
        $widthMm = (float) ($template['width_mm'] ?? 105);
        $heightMm = (float) ($template['height_mm'] ?? 148);
        $layout = $template['layout'] ?? [];
        $elements = $layout['elements'] ?? [];

        $body = '';

        foreach ($elements as $element) {
            $body .= $this->renderElement($element, $data, $resolved);
        }

        // DejaVu Sans is bundled with dompdf and carries Arabic glyphs, which the default
        // font does not. Without it an Arabic name prints as empty boxes — finding F8.
        return <<<HTML
        <!doctype html>
        <html><head><meta charset="utf-8"><style>
            @page { margin: 0; }
            body {
                margin: 0;
                width: {$widthMm}mm;
                height: {$heightMm}mm;
                font-family: 'DejaVu Sans', sans-serif;
            }
            .el { position: absolute; overflow: hidden; }
        </style></head><body>{$body}</body></html>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $element
     * @param  array<string, string>  $resolved
     */
    private function renderElement(array $element, BadgeRenderDataDTO $data, array $resolved): string
    {
        $type = BadgeElementType::tryFrom((string) ($element['type'] ?? ''));

        if ($type === null) {
            return '';
        }

        $box = sprintf(
            'left:%smm;top:%smm;width:%smm;height:%smm;',
            $element['x_mm'] ?? 0,
            $element['y_mm'] ?? 0,
            $element['width_mm'] ?? 10,
            $element['height_mm'] ?? 10
        );

        return match ($type) {
            BadgeElementType::FIELD => $this->renderText(
                $resolved[(string) ($element['binding'] ?? '')] ?? '',
                $box,
                $element['style'] ?? []
            ),
            BadgeElementType::TEXT => $this->renderText(
                (string) ($element['text'] ?? ''),
                $box,
                $element['style'] ?? []
            ),
            BadgeElementType::QR => sprintf(
                '<div class="el" style="%s"><img src="%s" style="width:100%%;height:100%%"></div>',
                $box,
                $this->badgeQrCodeService->dataUri($data->identifier)
            ),
            BadgeElementType::ZONE_COLOUR_BAR => $this->renderZoneBar($data->zoneColours, $element),
            BadgeElementType::PHOTO => $data->photoUrl !== null
                ? sprintf(
                    '<div class="el" style="%s"><img src="%s" style="width:100%%;height:100%%;object-fit:cover"></div>',
                    $box,
                    $data->photoUrl
                )
                // A photo template used before a photo is captured leaves a placeholder
                // rather than collapsing the layout around the gap.
                : sprintf('<div class="el" style="%sbackground:#eeeeee"></div>', $box),
            BadgeElementType::SHAPE => sprintf(
                '<div class="el" style="%sbackground-color:%s"></div>',
                $box,
                e((string) ($element['style']['fill'] ?? '#000000'))
            ),
            BadgeElementType::IMAGE, BadgeElementType::BARCODE => '',
        };
    }

    /**
     * @param  array<string, mixed>  $style
     */
    private function renderText(string $value, string $box, array $style): string
    {
        if ($value === '') {
            return '';
        }

        if (($style['transform'] ?? null) === 'uppercase') {
            $value = mb_strtoupper($value);
        }

        $css = sprintf(
            'font-size:%spt;text-align:%s;font-weight:%s;line-height:1.15;',
            $style['font_size'] ?? 11,
            $style['align'] ?? 'left',
            ($style['weight'] ?? 'normal') === 'bold' ? 'bold' : 'normal'
        );

        return sprintf('<div class="el" style="%s%s">%s</div>', $box, $css, e($value));
    }

    /**
     * Absolutely positioned segments in millimetres rather than a table of percentages.
     * dompdf leaves an empty table cell unfilled however its background is set, so the bar
     * came out blank — verified by rasterising the PDF and sampling the pixels.
     *
     * @param  array<int, string>  $colours
     */
    private function renderZoneBar(array $colours, array $element): string
    {
        if ($colours === []) {
            return '';
        }

        $barX = (float) ($element['x_mm'] ?? 0);
        $barY = (float) ($element['y_mm'] ?? 0);
        $barWidth = (float) ($element['width_mm'] ?? 10);
        $barHeight = (float) ($element['height_mm'] ?? 5);
        $segmentWidth = $barWidth / count($colours);

        $segments = '';

        foreach (array_values($colours) as $index => $colour) {
            $segments .= sprintf(
                '<div class="el" style="left:%smm;top:%smm;width:%smm;height:%smm;background-color:%s"></div>',
                round($barX + ($index * $segmentWidth), 4),
                $barY,
                // The last segment absorbs the rounding remainder so the bar has no seam at
                // its right edge.
                round($index === count($colours) - 1 ? $barWidth - ($index * $segmentWidth) : $segmentWidth, 4),
                $barHeight,
                e($colour)
            );
        }

        return $segments;
    }
}
