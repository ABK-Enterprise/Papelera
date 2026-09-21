<?php

namespace App\Services\Arca;

use App\Models\FiscalInvoice;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class ArcaQrService
{
    /**
     * Generate clean SVG string (removing unnecessary XML declarations).
     */
    public function generateSvg(string $qrUrl, int $size = 140): string
    {
        if (trim($qrUrl) === '') {
            return '';
        }

        $rendererStyle = new RendererStyle(
            size: $size,
            margin: 1,
            fill: Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0))
        );

        $renderer = new ImageRenderer($rendererStyle, new SvgImageBackEnd);
        $writer = new Writer($renderer);

        $svg = $writer->writeString($qrUrl);

        // Strip XML declarations and DOCTYPE if present
        $cleanSvg = preg_replace('/<\?xml.*?\?>/i', '', $svg) ?? $svg;
        $cleanSvg = preg_replace('/<!DOCTYPE.*?>/i', '', $cleanSvg) ?? $cleanSvg;

        return trim($cleanSvg);
    }

    /**
     * Return base64 data URI (data:image/svg+xml;base64,...) for safe embedding in HTML/PDF.
     */
    public function generateBase64Svg(string $qrUrl, int $size = 140): string
    {
        $svg = $this->generateSvg($qrUrl, $size);

        if ($svg === '') {
            return '';
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Generate QR base64 data URI from FiscalInvoice qr_data.
     */
    public function generateForInvoice(FiscalInvoice $invoice, int $size = 140): string
    {
        $qrUrl = (string) ($invoice->qr_data ?? '');

        return $this->generateBase64Svg($qrUrl, $size);
    }
}
