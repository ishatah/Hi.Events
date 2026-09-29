<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Encodes a credential identifier as a QR code for printing.
 *
 * Error correction is set high rather than low. A badge is handled, creased, worn on a
 * lanyard and scanned by a cheap reader in poor light for days; the extra modules cost a
 * little print density and buy a code that still reads when the surface is damaged.
 *
 * @see docs/arzo-master-plan/21-badge-management.md
 */
class BadgeQrCodeService
{
    private const QUIET_ZONE_MODULES = 2;

    /**
     * An SVG rather than a raster: it scales to whatever the template asks for without
     * resampling, so the printed modules stay square at any DPI.
     */
    public function svg(string $payload, int $sizePixels = 512): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($sizePixels, self::QUIET_ZONE_MODULES),
                new SvgImageBackEnd
            )
        );

        return $writer->writeString($payload, 'UTF-8', $this->errorCorrectionLevel());
    }

    /**
     * A PNG raster, for embedding in a PDF and for label printers that take bitmaps.
     *
     * dompdf silently drops an SVG data URI — the badge renders with an empty square where
     * the code should be, which is worse than failing, so the print path uses this.
     */
    public function png(string $payload, int $sizePixels = 512): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($sizePixels, self::QUIET_ZONE_MODULES),
                new ImagickImageBackEnd('png')
            )
        );

        return $writer->writeString($payload, 'UTF-8', $this->errorCorrectionLevel());
    }

    public function dataUri(string $payload, int $sizePixels = 512): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($payload, $sizePixels));
    }

    /**
     * Level H corrects roughly 30% of the code. A lanyard badge is creased, scuffed and
     * read by cheap handhelds for days, so the density cost is worth paying.
     */
    private function errorCorrectionLevel(): ErrorCorrectionLevel
    {
        return ErrorCorrectionLevel::H();
    }

    /**
     * The highest level that still fits a 40-character identifier comfortably, exposed so
     * the renderer can record what it used in the badge snapshot.
     */
    public function describeEncoding(): string
    {
        return sprintf('QR model 2, ECC H, quiet zone %d modules', self::QUIET_ZONE_MODULES);
    }
}
