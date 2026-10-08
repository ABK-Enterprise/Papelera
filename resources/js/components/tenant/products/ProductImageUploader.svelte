<script lang="ts">
    import { Button } from '@/components/ui/button';
    import Camera from 'lucide-svelte/icons/camera';
    import ImageOff from 'lucide-svelte/icons/image-off';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import UploadCloud from 'lucide-svelte/icons/upload-cloud';
    import Crop from 'lucide-svelte/icons/crop';
    import ImageCropperModal from '@/components/common/ImageCropperModal.svelte';
    import { onDestroy } from 'svelte';

    let {
        initialImageUrl = null,
        initialPosition = null,
        file = $bindable(null),
        position = $bindable(null),
        removeImage = $bindable(false),
        disabled = false,
        error = '',
    }: {
        initialImageUrl?: string | null;
        initialPosition?: { zoom?: number; x?: number; y?: number } | null;
        file: File | null;
        position?: { zoom?: number; x?: number; y?: number } | null;
        removeImage: boolean;
        disabled?: boolean;
        error?: string;
    } = $props();

    let fileInput: HTMLInputElement | undefined = $state();
    let isDragging = $state(false);
    let isOptimizing = $state(false);
    let localPreviewUrl = $state<string | null>(null);
    let optimizedSizeKb = $state<number | null>(null);
    let originalSizeKb = $state<number | null>(null);
    let localError = $state<string | null>(null);
    let isCropperOpen = $state(false);
    let cropperSourceUrl = $state<string | null>(null);

    // Derived active preview URL
    let activePreview = $derived.by(() => {
        if (localPreviewUrl) return localPreviewUrl;
        if (removeImage) return null;
        return initialImageUrl || null;
    });

    $effect(() => {
        if (!file && localPreviewUrl) {
            URL.revokeObjectURL(localPreviewUrl);
            localPreviewUrl = null;
            optimizedSizeKb = null;
            originalSizeKb = null;
        }
    });

    onDestroy(() => {
        if (localPreviewUrl) {
            URL.revokeObjectURL(localPreviewUrl);
        }
    });

    async function handleFileSelect(selectedFile: File | null) {
        if (!selectedFile) return;

        localError = null;

        // Validation: mime type
        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!validTypes.includes(selectedFile.type)) {
            localError = 'Formato no admitido. Seleccione un archivo JPG, PNG o WebP.';
            return;
        }

        // Validation: size max 15MB before optimization
        if (selectedFile.size > 15 * 1024 * 1024) {
            localError = 'El archivo original supera los 15 MB.';
            return;
        }

        originalSizeKb = Math.round(selectedFile.size / 1024);
        isOptimizing = true;

        try {
            const optimized = await optimizeImage(selectedFile);
            file = optimized;
            removeImage = false;
            optimizedSizeKb = Math.round(optimized.size / 1024);

            if (localPreviewUrl) {
                URL.revokeObjectURL(localPreviewUrl);
            }
            localPreviewUrl = URL.createObjectURL(optimized);
        } catch {
            localError = 'No se pudo procesar la imagen seleccionada. Intente con otra.';
        } finally {
            isOptimizing = false;
        }
    }

    function optimizeImage(rawFile: File): Promise<File> {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const objectUrl = URL.createObjectURL(rawFile);

            img.onload = () => {
                URL.revokeObjectURL(objectUrl);

                const maxDimension = 1200;
                let width = img.naturalWidth;
                let height = img.naturalHeight;

                if (width > maxDimension || height > maxDimension) {
                    if (width > height) {
                        height = Math.round((height * maxDimension) / width);
                        width = maxDimension;
                    } else {
                        width = Math.round((width * maxDimension) / height);
                        height = maxDimension;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    reject(new Error('Canvas context unavailable'));
                    return;
                }

                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, width, height);

                // Export to WebP with 85% quality
                canvas.toBlob(
                    (blob) => {
                        if (!blob) {
                            // Fallback to original if blob creation fails
                            resolve(rawFile);
                            return;
                        }

                        const baseName = rawFile.name.replace(/\.[^/.]+$/, '');
                        const webpFile = new File([blob], `${baseName}.webp`, {
                            type: 'image/webp',
                            lastModified: Date.now(),
                        });

                        resolve(webpFile);
                    },
                    'image/webp',
                    0.85
                );
            };

            img.onerror = () => {
                URL.revokeObjectURL(objectUrl);
                reject(new Error('Error al cargar la imagen'));
            };

            img.src = objectUrl;
        });
    }

    function onInputChange(e: Event) {
        const input = e.target as HTMLInputElement;
        if (input.files && input.files.length > 0) {
            handleFileSelect(input.files[0]);
        }
    }

    function onDragOver(e: DragEvent) {
        e.preventDefault();
        if (!disabled) isDragging = true;
    }

    function onDragLeave(e: DragEvent) {
        e.preventDefault();
        isDragging = false;
    }

    function onDrop(e: DragEvent) {
        e.preventDefault();
        isDragging = false;
        if (disabled) return;

        if (e.dataTransfer && e.dataTransfer.files.length > 0) {
            handleFileSelect(e.dataTransfer.files[0]);
        }
    }

    function triggerFileInput() {
        if (!disabled && fileInput) {
            fileInput.click();
        }
    }

    function handleRemove() {
        if (disabled) return;

        if (localPreviewUrl) {
            URL.revokeObjectURL(localPreviewUrl);
            localPreviewUrl = null;
        }

        file = null;
        optimizedSizeKb = null;
        originalSizeKb = null;
        localError = null;

        if (fileInput) {
            fileInput.value = '';
        }

        if (initialImageUrl) {
            removeImage = true;
        }
    }

    function handleRestore() {
        if (disabled) return;
        removeImage = false;
    }

    function openCropper() {
        if (!activePreview || disabled) return;
        cropperSourceUrl = activePreview;
        isCropperOpen = true;
    }

    function handleCropApply(blob: Blob, url: string, newPosition?: { zoom: number; x: number; y: number }) {
        if (newPosition) {
            position = newPosition;
        }
        if (localPreviewUrl) {
            URL.revokeObjectURL(localPreviewUrl);
        }

        removeImage = false;
        localPreviewUrl = url;
        localError = null;
    }
</script>

<div class="space-y-3 w-full">
    <!-- Hidden File Input -->
    <input
        bind:this={fileInput}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        class="hidden"
        onchange={onInputChange}
        {disabled}
    />

    <!-- Main Container -->
    <div
        role="button"
        tabindex="0"
        aria-label="Cargar foto del producto"
        onkeydown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                triggerFileInput();
            }
        }}
        class="relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed p-4 sm:p-6 transition-all duration-200 outline-none
            {isDragging ? 'border-primary bg-primary/5 ring-2 ring-primary/20 scale-[0.99]' : 'border-border/80 bg-card hover:border-border hover:bg-muted/30'}
            {disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}"
        ondragover={onDragOver}
        ondragleave={onDragLeave}
        ondrop={onDrop}
        onclick={triggerFileInput}
    >
        {#if isOptimizing}
            <div class="flex flex-col items-center justify-center py-8 space-y-2">
                <RefreshCw class="size-8 text-primary animate-spin" />
                <p class="text-sm font-semibold text-foreground">Optimizando imagen...</p>
                <p class="text-xs text-muted-foreground">Redimensionando a WebP para máxima velocidad</p>
            </div>
        {:else if activePreview}
            <!-- Image Loaded State -->
            <div class="relative w-full flex flex-col items-center">
                <div class="relative w-44 h-44 sm:w-52 sm:h-52 rounded-xl overflow-hidden bg-background border border-border/70 shadow-2xs flex items-center justify-center">
                    <img
                        src={activePreview}
                        alt="Vista previa del producto"
                        class="w-full h-full object-contain"
                    />
                </div>

                <!-- Size Metrics Pill -->
                {#if optimizedSizeKb}
                    <div class="mt-2.5 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xs font-semibold tabular-nums">
                        <span>WebP optimizado:</span>
                        <span>{optimizedSizeKb} KB</span>
                        {#if originalSizeKb && originalSizeKb > optimizedSizeKb}
                            <span class="text-muted-foreground line-through text-xs tabular-nums">{originalSizeKb} KB</span>
                        {/if}
                    </div>
                {/if}
            </div>
        {:else if removeImage}
            <!-- Marked for Removal State -->
            <div class="flex flex-col items-center justify-center py-6 text-center space-y-2">
                <div class="size-14 rounded-2xl bg-destructive/10 text-destructive flex items-center justify-center border border-destructive/20">
                    <ImageOff class="size-7" />
                </div>
                <p class="text-sm font-semibold text-foreground">Imagen marcada para eliminar</p>
                <p class="text-xs text-muted-foreground max-w-xs">
                    Al guardar los cambios, la foto actual se borrará definitivamente del catálogo.
                </p>
            </div>
        {:else}
            <!-- Empty State -->
            <div class="flex flex-col items-center justify-center py-6 text-center space-y-2">
                <div class="size-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20 group-hover:scale-105 transition-transform">
                    <UploadCloud class="size-7" />
                </div>
                <div class="space-y-0.5">
                    <p class="text-sm font-semibold text-foreground">
                        Subir imagen o capturar foto
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Arrastrá un archivo o hacé clic para explorar
                    </p>
                </div>
                <div class="inline-flex items-center gap-2 pt-1 text-xs font-medium text-muted-foreground">
                    <span class="px-2 py-0.5 rounded-md bg-muted border border-border/60">JPG</span>
                    <span class="px-2 py-0.5 rounded-md bg-muted border border-border/60">PNG</span>
                    <span class="px-2 py-0.5 rounded-md bg-muted border border-border/60">WebP</span>
                    <span>Hasta 15 MB</span>
                </div>
            </div>
        {/if}
    </div>

    <!-- Actions Toolbar -->
    {#if activePreview || removeImage}
        <div class="flex items-center gap-2.5 pt-1" onclick={(e) => e.stopPropagation()}>
            {#if removeImage}
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-[44px] flex-1 rounded-xl text-xs font-semibold gap-2 border-border/80"
                    onclick={handleRestore}
                    {disabled}
                >
                    <RefreshCw class="size-4" />
                    <span>Conservar foto actual</span>
                </Button>
            {:else}
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-[44px] flex-1 rounded-xl text-xs font-semibold gap-2 border-border/80 hover:bg-muted/70"
                    onclick={openCropper}
                    {disabled}
                    title="Ajustar encuadre y zoom de la foto"
                >
                    <Crop class="size-4 text-primary" />
                    <span>Ajustar encuadre</span>
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    class="min-h-[44px] flex-1 rounded-xl text-xs font-semibold gap-2 border-border/80 hover:bg-muted/70"
                    onclick={triggerFileInput}
                    {disabled}
                    title="Seleccionar otra imagen"
                >
                    <Camera class="size-4 text-primary" />
                    <span>Reemplazar</span>
                </Button>

                <Button
                    type="button"
                    variant="destructive"
                    class="min-h-[44px] px-3.5 rounded-xl text-xs font-semibold gap-2 shadow-2xs"
                    onclick={handleRemove}
                    {disabled}
                    title="Quitar imagen"
                >
                    <Trash2 class="size-4" />
                    <span class="sr-only sm:not-sr-only">Quitar</span>
                </Button>
            {/if}
        </div>
    {/if}

    <!-- Validation & Processing Errors -->
    {#if localError || error}
        <p class="text-xs text-destructive font-medium flex items-center gap-1.5 pt-0.5">
            <span>•</span>
            <span>{localError || error}</span>
        </p>
    {/if}

    <!-- Modal Universal de Recorte y Ajuste de Encuadre -->
    <ImageCropperModal
        bind:open={isCropperOpen}
        imageSrc={cropperSourceUrl}
        cropShape="square"
        outputFormat="image/webp"
        targetSize={800}
        title="Ajustar encuadre del producto"
        description="Arrastrá la foto para centrar el producto y usá el zoom para un encuadre 1:1."
        initialPosition={position || initialPosition}
        onApply={handleCropApply}
    />
</div>
