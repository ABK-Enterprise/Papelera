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
     * - Preserves the full original aspect ratio and dimensions (up to 1200px max dimension).
     * - Derives a 150x150 thumbnail using the framing coordinates (zoom, x, y) without destructive crop.
     * - Strips all EXIF metadata and auto-rotates phone images.
     *
     * @param  array{zoom?: float, x?: float, y?: float}|null  $position
     * @return array{image_url: string, thumbnail_url: string, image_position: array{zoom: float, x: float, y: float}}
     */
    public function storeAndOptimize(UploadedFile $file, int $tenantId, ?array $position = null): array
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

        $directory = "products/{$tenantId}";
        Storage::disk('public')->makeDirectory($directory);

        $normalizedPosition = [
            'zoom' => max(1.0, min(5.0, (float) ($position['zoom'] ?? 1.0))),
            'x' => max(-100.0, min(100.0, (float) ($position['x'] ?? 0.0))),
            'y' => max(-100.0, min(100.0, (float) ($position['y'] ?? 0.0))),
        ];

        // 1. Generate Derived Thumbnail (150x150) based on framing coordinates
        $thumbRelativePath = $this->renderThumbnailFromGd($source, $origWidth, $origHeight, $normalizedPosition, $directory);

        // 2. Generate Optimized Full Main Image (max 1200px maintaining original aspect ratio)
        $maxDim = 1200;
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

        $randomHash = Str::random(40);
        $optimizedFilename = "{$randomHash}.webp";
        $optimizedRelativePath = "{$directory}/{$optimizedFilename}";
        $optimizedFullPath = Storage::disk('public')->path($optimizedRelativePath);
        imagewebp($optimized, $optimizedFullPath, 80);
        imagedestroy($optimized);
        imagedestroy($source);

        return [
            'image_url' => Storage::disk('public')->url($optimizedRelativePath),
            'thumbnail_url' => Storage::disk('public')->url($thumbRelativePath),
            'image_position' => $normalizedPosition,
        ];
    }

    /**
     * Reframe the thumbnail of an already uploaded product image without re-uploading the file.
     *
     * @param  array{zoom?: float, x?: float, y?: float}  $position
     * @return array{image_url: string, thumbnail_url: string, image_position: array{zoom: float, x: float, y: float}}
     */
    public function reframeThumbnail(Product $product, array $position): array
    {
        if (! $product->image_url) {
            throw ValidationException::withMessages([
                'image' => ['El producto no tiene una imagen cargada para encuadrar.'],
            ]);
        }

        $relativePath = $this->extractRelativePath($product->image_url);
        if (! $relativePath || ! Storage::disk('public')->exists($relativePath)) {
            throw ValidationException::withMessages([
                'image' => ['El archivo de imagen original no se encuentra disponible en el almacenamiento.'],
            ]);
        }

        $fullPath = Storage::disk('public')->path($relativePath);
        $source = @imagecreatefromstring(file_get_contents($fullPath));
        if ($source === false) {
            throw ValidationException::withMessages([
                'image' => ['No se pudo procesar la imagen original del producto.'],
            ]);
        }

        $origWidth = imagesx($source);
        $origHeight = imagesy($source);
        $directory = "products/{$product->tenant_id}";

        $normalizedPosition = [
            'zoom' => max(1.0, min(5.0, (float) ($position['zoom'] ?? 1.0))),
            'x' => max(-100.0, min(100.0, (float) ($position['x'] ?? 0.0))),
            'y' => max(-100.0, min(100.0, (float) ($position['y'] ?? 0.0))),
        ];

        $newThumbRelative = $this->renderThumbnailFromGd($source, $origWidth, $origHeight, $normalizedPosition, $directory);
        imagedestroy($source);

        // Delete old thumbnail if different
        if ($product->thumbnail_url) {
            $this->deleteImages(null, $product->thumbnail_url, $product->tenant_id);
        }

        $newThumbUrl = Storage::disk('public')->url($newThumbRelative);
        $product->update([
            'thumbnail_url' => $newThumbUrl,
            'image_position' => $normalizedPosition,
        ]);

        return [
            'image_url' => $product->image_url,
            'thumbnail_url' => $newThumbUrl,
            'image_position' => $normalizedPosition,
        ];
    }

    /**
     * Renders a 150x150 WebP thumbnail using framing coordinates (zoom, x, y).
     *
     * @param  resource|\GdImage  $source
     * @param  array{zoom: float, x: float, y: float}  $position
     */
    public function renderThumbnailFromGd($source, int $origWidth, int $origHeight, array $position, string $directory): string
    {
        $thumbSize = 150;
        $thumb = imagecreatetruecolor($thumbSize, $thumbSize);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);

        $baseCropSize = min($origWidth, $origHeight);
        $zoom = max(1.0, min(5.0, (float) ($position['zoom'] ?? 1.0)));
        $cropDimension = (int) round($baseCropSize / $zoom);
        $cropDimension = max(1, min($cropDimension, $baseCropSize));

        $maxPanX = ($origWidth - $cropDimension) / 2;
        $maxPanY = ($origHeight - $cropDimension) / 2;

        $pctX = max(-100.0, min(100.0, (float) ($position['x'] ?? 0.0)));
        $pctY = max(-100.0, min(100.0, (float) ($position['y'] ?? 0.0)));

        // Panning the image right (+x) shifts the visible region to the left (-srcX)
        $offsetX = (int) round(($pctX / 100.0) * $maxPanX);
        $offsetY = (int) round(($pctY / 100.0) * $maxPanY);

        $centerX = (int) ($origWidth / 2) - $offsetX;
        $centerY = (int) ($origHeight / 2) - $offsetY;

        $srcX = (int) max(0, min($origWidth - $cropDimension, round($centerX - ($cropDimension / 2))));
        $srcY = (int) max(0, min($origHeight - $cropDimension, round($centerY - ($cropDimension / 2))));

        imagecopyresampled($thumb, $source, 0, 0, $srcX, $srcY, $thumbSize, $thumbSize, $cropDimension, $cropDimension);

        $randomHash = Str::random(40);
        $thumbFilename = "thumb_{$randomHash}.webp";
        $thumbRelativePath = "{$directory}/{$thumbFilename}";
        $thumbFullPath = Storage::disk('public')->path($thumbRelativePath);

        Storage::disk('public')->makeDirectory($directory);
        imagewebp($thumb, $thumbFullPath, 80);
        imagedestroy($thumb);

        return $thumbRelativePath;
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
