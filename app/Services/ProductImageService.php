<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductImageService
{
    /**
     * Store and optimize an uploaded image using Zero-Trust architecture with native GD.
     *
     * - Re-encodes strictly to WebP at 80% quality.
     * - Strips all EXIF metadata and embedded payloads.
     * - Auto-rotates according to camera orientation before stripping EXIF.
     * - Dual output: 150x150 thumbnail and max 800x800 optimized image.
     *
     * @return array{image_url: string, thumbnail_url: string}
     */
    public function storeAndOptimize(UploadedFile $file, int $tenantId): array
    {
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                'image' => ['No se pudo leer el archivo de imagen subido.'],
            ]);
        }

        $imageContent = file_get_contents($realPath);
        $source = @imagecreatefromstring($imageContent);
        if ($source === false) {
            throw ValidationException::withMessages([
                'image' => ['El archivo no es una imagen válida o está dañado.'],
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
                'image' => ['Las dimensiones de la imagen son inválidas.'],
            ]);
        }

        $randomHash = Str::random(40);
        $directory = "products/{$tenantId}";

        Storage::disk('public')->makeDirectory($directory);

        // 1. Generate Thumbnail (150x150 square crop)
        $thumbSize = 150;
        $thumb = imagecreatetruecolor($thumbSize, $thumbSize);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);

        $minDim = min($origWidth, $origHeight);
        $srcX = (int) (($origWidth - $minDim) / 2);
        $srcY = (int) (($origHeight - $minDim) / 2);

        imagecopyresampled($thumb, $source, 0, 0, $srcX, $srcY, $thumbSize, $thumbSize, $minDim, $minDim);

        $thumbFilename = "thumb_{$randomHash}.webp";
        $thumbRelativePath = "{$directory}/{$thumbFilename}";
        $thumbFullPath = Storage::disk('public')->path($thumbRelativePath);
        imagewebp($thumb, $thumbFullPath, 80);
        imagedestroy($thumb);

        // 2. Generate Optimized Main Image (max 800x800 maintaining aspect ratio)
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

        imagecopyresampled($optimized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        $optimizedFilename = "{$randomHash}.webp";
        $optimizedRelativePath = "{$directory}/{$optimizedFilename}";
        $optimizedFullPath = Storage::disk('public')->path($optimizedRelativePath);
        imagewebp($optimized, $optimizedFullPath, 80);
        imagedestroy($optimized);
        imagedestroy($source);

        return [
            'image_url' => Storage::disk('public')->url($optimizedRelativePath),
            'thumbnail_url' => Storage::disk('public')->url($thumbRelativePath),
        ];
    }

    /**
     * Remove physical files associated with a product image from disk with strict tenant path validation.
     */
    public function deleteImages(?string $imageUrl, ?string $thumbnailUrl, ?int $tenantId = null): void
    {
        $disk = Storage::disk('public');
        $expectedPrefix = $tenantId ? "products/{$tenantId}/" : 'products/';

        foreach ([$imageUrl, $thumbnailUrl] as $url) {
            if (! $url) {
                continue;
            }

            $path = $this->extractRelativePath($url);
            if (! $path) {
                continue;
            }

            if ($tenantId && ! str_starts_with($path, $expectedPrefix)) {
                Log::warning('Security alert: Attempted unauthorized image deletion across tenants or invalid path', [
                    'tenant_id' => $tenantId,
                    'path' => $path,
                    'url' => $url,
                ]);

                continue;
            }

            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    private function extractRelativePath(string $url): ?string
    {
        // URL can be "/storage/products/1/hash.webp" or "http://.../storage/products/1/hash.webp"
        $parsed = parse_url($url, PHP_URL_PATH);
        if (! $parsed) {
            return null;
        }

        // Strip leading /storage/ if present
        $trimmed = preg_replace('#^/?storage/#', '', $parsed);

        return $trimmed ? ltrim($trimmed, '/') : null;
    }
}
