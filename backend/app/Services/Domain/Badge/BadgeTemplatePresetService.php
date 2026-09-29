<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use HiEvents\DomainObjects\Enums\BadgeElementType;
use HiEvents\DomainObjects\Enums\BadgeField;

/**
 * Ready-made badge layouts.
 *
 * Presets rather than a design canvas, deliberately. An organizer needs a badge that prints
 * correctly far more than they need pixel control, and a canvas is a large piece of frontend
 * work that would delay every other part of badge printing. The layout is stored as the same
 * element tree either way, so a canvas can be added later without migrating templates.
 *
 * Dimensions are millimetres. A badge is a physical object and any other unit invites a
 * rounding error that only shows up on the printer.
 *
 * @see docs/arzo-master-plan/21-badge-management.md
 */
class BadgeTemplatePresetService
{
    /**
     * @return array<string, array{name: string, width_mm: float, height_mm: float, orientation: string, dpi: int, layout: array<string, mixed>}>
     */
    public function all(): array
    {
        return [
            'A6_PORTRAIT_STANDARD' => [
                'name' => __('Standard delegate badge (A6 portrait)'),
                'width_mm' => 105.0,
                'height_mm' => 148.0,
                'orientation' => 'PORTRAIT',
                'dpi' => 300,
                'layout' => $this->standardPortraitLayout(),
            ],
            'CR80_LANDSCAPE_COMPACT' => [
                'name' => __('Compact card badge (CR80 landscape)'),
                'width_mm' => 85.6,
                'height_mm' => 54.0,
                'orientation' => 'LANDSCAPE',
                'dpi' => 300,
                'layout' => $this->compactCardLayout(),
            ],
            'A7_PORTRAIT_PHOTO' => [
                'name' => __('Photo badge (A7 portrait)'),
                'width_mm' => 74.0,
                'height_mm' => 105.0,
                'orientation' => 'PORTRAIT',
                'dpi' => 300,
                'layout' => $this->photoPortraitLayout(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $preset): ?array
    {
        return $this->all()[$preset] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function standardPortraitLayout(): array
    {
        return [
            'elements' => [
                [
                    'type' => BadgeElementType::ZONE_COLOUR_BAR->value,
                    'x_mm' => 0, 'y_mm' => 0, 'width_mm' => 105, 'height_mm' => 8,
                ],
                [
                    'type' => BadgeElementType::FIELD->value,
                    'binding' => BadgeField::ACCREDITATION_TYPE->value,
                    'x_mm' => 6, 'y_mm' => 12, 'width_mm' => 93, 'height_mm' => 10,
                    'style' => ['font_size' => 14, 'align' => 'center', 'weight' => 'bold', 'transform' => 'uppercase'],
                ],
                [
                    'type' => BadgeElementType::FIELD->value,
                    'binding' => BadgeField::PERSON_FULL_NAME->value,
                    'x_mm' => 6, 'y_mm' => 34, 'width_mm' => 93, 'height_mm' => 20,
                    'style' => ['font_size' => 24, 'align' => 'center', 'weight' => 'bold'],
                ],
                [
                    'type' => BadgeElementType::FIELD->value,
                    'binding' => BadgeField::PERSON_COMPANY->value,
                    'x_mm' => 6, 'y_mm' => 56, 'width_mm' => 93, 'height_mm' => 10,
                    'style' => ['font_size' => 13, 'align' => 'center'],
                ],
                [
                    'type' => BadgeElementType::FIELD->value,
                    'binding' => BadgeField::PERSON_JOB_TITLE->value,
                    'x_mm' => 6, 'y_mm' => 66, 'width_mm' => 93, 'height_mm' => 8,
                    'style' => ['font_size' => 10, 'align' => 'center'],
                ],
                [
                    'type' => BadgeElementType::QR->value,
                    'x_mm' => 35, 'y_mm' => 82, 'width_mm' => 35, 'height_mm' => 35,
                ],
                [
                    'type' => BadgeElementType::FIELD->value,
                    'binding' => BadgeField::EVENT_NAME->value,
                    'x_mm' => 6, 'y_mm' => 126, 'width_mm' => 93, 'height_mm' => 8,
                    'style' => ['font_size' => 9, 'align' => 'center'],
                ],
                [
                    'type' => BadgeElementType::FIELD->value,
                    'binding' => BadgeField::ZONE_LIST->value,
                    'x_mm' => 6, 'y_mm' => 136, 'width_mm' => 93, 'height_mm' => 8,
                    'style' => ['font_size' => 8, 'align' => 'center'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compactCardLayout(): array
    {
        return [
            'elements' => [
                [
                    'type' => BadgeElementType::ZONE_COLOUR_BAR->value,
                    'x_mm' => 0, 'y_mm' => 0, 'width_mm' => 85.6, 'height_mm' => 5,
                ],
                [
                    'type' => BadgeElementType::FIELD->value,
                    'binding' => BadgeField::PERSON_FULL_NAME->value,
                    'x_mm' => 4, 'y_mm' => 9, 'width_mm' => 52, 'height_mm' => 12,
                    'style' => ['font_size' => 14, 'weight' => 'bold'],
                ],
                [
                    'type' => BadgeElementType::FIELD->value,
                    'binding' => BadgeField::PERSON_COMPANY->value,
                    'x_mm' => 4, 'y_mm' => 22, 'width_mm' => 52, 'height_mm' => 8,
                    'style' => ['font_size' => 9],
                ],
                [
                    'type' => BadgeElementType::FIELD->value,
                    'binding' => BadgeField::ACCREDITATION_TYPE->value,
                    'x_mm' => 4, 'y_mm' => 42, 'width_mm' => 52, 'height_mm' => 8,
                    'style' => ['font_size' => 9, 'weight' => 'bold', 'transform' => 'uppercase'],
                ],
                [
                    'type' => BadgeElementType::QR->value,
                    'x_mm' => 60, 'y_mm' => 12, 'width_mm' => 22, 'height_mm' => 22,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photoPortraitLayout(): array
    {
        return [
            'elements' => [
                [
                    'type' => BadgeElementType::ZONE_COLOUR_BAR->value,
                    'x_mm' => 0, 'y_mm' => 0, 'width_mm' => 74, 'height_mm' => 6,
                ],
                [
                    'type' => BadgeElementType::PHOTO->value,
                    'x_mm' => 22, 'y_mm' => 10, 'width_mm' => 30, 'height_mm' => 38,
                ],
                [
                    'type' => BadgeElementType::FIELD->value,
                    'binding' => BadgeField::PERSON_FULL_NAME->value,
                    'x_mm' => 4, 'y_mm' => 51, 'width_mm' => 66, 'height_mm' => 14,
                    'style' => ['font_size' => 15, 'align' => 'center', 'weight' => 'bold'],
                ],
                [
                    'type' => BadgeElementType::FIELD->value,
                    'binding' => BadgeField::ACCREDITATION_TYPE->value,
                    'x_mm' => 4, 'y_mm' => 65, 'width_mm' => 66, 'height_mm' => 8,
                    'style' => ['font_size' => 10, 'align' => 'center', 'transform' => 'uppercase'],
                ],
                [
                    'type' => BadgeElementType::QR->value,
                    'x_mm' => 24, 'y_mm' => 75, 'width_mm' => 26, 'height_mm' => 26,
                ],
            ],
        ];
    }
}
