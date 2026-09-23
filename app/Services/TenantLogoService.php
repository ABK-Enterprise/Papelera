<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantLogoService
{
    /**
     * Store and optimize an uploaded tenant logo using Zero-Trust architecture with native GD.
     *
     * - Re-encodes strictly to WebP at 85% quality.
     * - Strips all EXIF metadata and embedded payloads.
     * - Auto-rotates according to camera orientation before stripping EXIF.
     * - Proportional resize up to max 800x800 preserving PNG/WebP transparency.
     * - Dual tenant isolation: stored in tenants/{tenantId}/logo_{hash}.webp.
     */
    public function storeAndOptimize(UploadedFile $file, int $tenantId): string
    {
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                'logo' => ['No se pudo leer el archivo de logo subido.'],
            ]);
        }

        $imageContent = file_get_contents($realPath);
        $source = @imagecreatefromstring($imageContent);
        if ($source === false) {
            throw ValidationException::withMessages([
                'logo' => ['El archivo no es una imagen válida o está dañado.'],
            ]);
        }

        // Auto-orient based on EXIF orientation if available (mobile phone cameras)
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($realPath);
            if (! empty($exif['Orientation'])) {
                $source = match ((int) $exif['Orientation']) {
                    3 => imagerotate($source, 180, 0),
                    6 => imagerotate($source, -90, 0),
                    8 => imagerotate($source, 90, 0),
                    default => $source,
                };
            }
        }

        $origWidth = imagesx($source);
        $origHeight = imagesy($source);

        if ($origWidth <= 0 || $origHeight <= 0) {
            imagedestroy($source);
            throw ValidationException::withMessages([
                'logo' => ['Las dimensiones de la imagen son inválidas.'],
            ]);
        }

        $randomHash = Str::random(40);
        $directory = "tenants/{$tenantId}";

        Storage::disk('public')->makeDirectory($directory);

        // Generate Optimized Main Image (max 800x800 maintaining aspect ratio)
        $maxDim = 800;
        if ($origWidth > $maxDim || $origHeight > $maxDim) {
            if ($origWidth >= $origHeight) {
                $newWidth = $maxDim;
                $newHeight = (int) round(($origHeight / $origWidth) * $maxDim);
            } else {
                $newHeight = $maxDim;
                $newWidth = (int) round(($origWidth / $origHeight) * $maxDim);
            }
        } else {
            $newWidth = $origWidth;
            $newHeight = $origHeight;
        }

        $optimized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($optimized, false);
        imagesavealpha($optimized, true);

        // Transparent fill for PNG/WebP alpha channel
        $transparent = imagecolorallocatealpha($optimized, 0, 0, 0, 127);
        imagefilledrectangle($optimized, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($optimized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        $optimizedFilename = "logo_{$randomHash}.webp";
        $optimizedRelativePath = "{$directory}/{$optimizedFilename}";
        $optimizedFullPath = Storage::disk('public')->path($optimizedRelativePath);
        imagewebp($optimized, $optimizedFullPath, 85);

        imagedestroy($optimized);
        imagedestroy($source);

        return "/storage/{$optimizedRelativePath}";
    }

    /**
     * Remove physical logo file from disk with strict tenant path validation.
     */
    public function deleteLogo(?string $logoUrl, ?int $tenantId = null): void
    {
        if (! $logoUrl) {
            return;
        }

        $path = $this->extractRelativePath($logoUrl);
        if (! $path) {
            return;
        }

        $expectedPrefix = $tenantId ? "tenants/{$tenantId}/" : 'tenants/';

        if ($tenantId && ! str_starts_with($path, $expectedPrefix)) {
            Log::warning('Security alert: Attempted unauthorized tenant logo deletion across tenants or invalid path', [
                'tenant_id' => $tenantId,
                'path' => $path,
                'url' => $logoUrl,
            ]);

            return;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    /**
     * Resolve Base64 Data URI for deterministic embedding in PDFs without CORS or network latency.
     */
    public function getBase64Logo(?Tenant $tenant): ?string
    {
        if (! $tenant || ! $tenant->logo_url) {
            return null;
        }

        $relativePath = $this->extractRelativePath($tenant->logo_url);
        if (! $relativePath) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            return null;
        }

        $contents = $disk->get($relativePath);
        if (! $contents) {
            return null;
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => 'image/webp',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function extractRelativePath(string $url): ?string
    {
        $parsed = parse_url($url, PHP_URL_PATH);
        if (! $parsed) {
            return null;
        }

        // Strip leading /storage/ if present
        $trimmed = preg_replace('#^/?storage/#', '', $parsed);

        return $trimmed ? ltrim($trimmed, '/') : null;
    }
}
