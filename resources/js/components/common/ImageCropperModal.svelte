<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogFooter,
        DialogHeader,
        DialogTitle,
        DialogDescription,
    } from '@/components/ui/dialog';
    import ZoomIn from 'lucide-svelte/icons/zoom-in';
    import ZoomOut from 'lucide-svelte/icons/zoom-out';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Check from 'lucide-svelte/icons/check';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import Crop from 'lucide-svelte/icons/crop';

    interface Props {
        open?: boolean;
        imageSrc?: string | null;
        cropShape?: 'circle' | 'square';
        aspectRatio?: number;
        outputFormat?: 'image/webp' | 'image/png' | 'image/jpeg';
        outputQuality?: number;
        targetSize?: number;
        title?: string;
        description?: string;
        onApply?: (blob: Blob, url: string) => void;
        onCancel?: () => void;
    }

    let {
        open = $bindable(false),
        imageSrc = null,
        cropShape = 'square',
        aspectRatio = 1,
        outputFormat = 'image/webp',
        outputQuality = 0.92,
        targetSize = 512,
        title = 'Ajustar encuadre',
        description = 'Arrastrá la imagen para posicionarla y usá el zoom para ajustar el tamaño.',
        onApply = undefined,
        onCancel = undefined,
    }: Props = $props();

    // Geometric constants for universal safe size (compatible with 320px+ viewports)
    const VIEWPORT_SIZE = 240;
    const CROP_SIZE = 220; // Exact width/height of the guide crop region
    const CROP_RADIUS = CROP_SIZE / 2; // 110px for circle
    const CROP_OFFSET = (VIEWPORT_SIZE - CROP_SIZE) / 2; // 10px margin

    let zoom = $state(1);
    let panX = $state(0);
    let panY = $state(0);
    let isDragging = $state(false);
    let startX = $state(0);
    let startY = $state(0);
    let imageElement: HTMLImageElement | null = $state(null);
    let isProcessing = $state(false);
    let imageLoaded = $state(false);
    let naturalWidth = $state(0);
    let naturalHeight = $state(0);
    let baseWidth = $state(0);
    let baseHeight = $state(0);

    // Reset coordinates whenever modal opens or image changes
    $effect(() => {
        if (open && imageSrc) {
            zoom = 1;
            panX = 0;
            panY = 0;
            imageLoaded = false;
        }
    });

    function handleImageLoad(e: Event) {
        const img = e.currentTarget as HTMLImageElement;
        imageElement = img;
        naturalWidth = img.naturalWidth;
        naturalHeight = img.naturalHeight;

        // Base scale: ensure the image covers at least the CROP_SIZE boundary at zoom=1
        const baseScale = Math.max(CROP_SIZE / naturalWidth, CROP_SIZE / naturalHeight);
        baseWidth = Math.round(naturalWidth * baseScale);
        baseHeight = Math.round(naturalHeight * baseScale);

        imageLoaded = true;
        panX = 0;
        panY = 0;
        zoom = 1;
    }

    function handlePointerDown(e: PointerEvent) {
        isDragging = true;
        startX = e.clientX - panX;
        startY = e.clientY - panY;
        (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
    }

    function handlePointerMove(e: PointerEvent) {
        if (!isDragging) return;
        panX = e.clientX - startX;
        panY = e.clientY - startY;
    }

    function handlePointerUp(e: PointerEvent) {
        isDragging = false;
        try {
            (e.currentTarget as HTMLElement).releasePointerCapture(e.pointerId);
        } catch {
            // Ignore capture release errors
        }
    }

    function handleWheel(e: WheelEvent) {
        e.preventDefault();
        const delta = e.deltaY < 0 ? 0.1 : -0.1;
        zoom = Math.min(3, Math.max(1, Number((zoom + delta).toFixed(2))));
    }

    function resetTransform() {
        zoom = 1;
        panX = 0;
        panY = 0;
    }

    function handleClose() {
        if (isProcessing) return;
        open = false;
        onCancel?.();
    }

    async function applyCrop() {
        if (!imageElement || isProcessing) return;
        isProcessing = true;

        try {
            const canvas = document.createElement('canvas');
            canvas.width = targetSize;
            canvas.height = Math.round(targetSize / aspectRatio);
            const ctx = canvas.getContext('2d');

            if (!ctx) {
                throw new Error('No se pudo inicializar el contexto 2D del lienzo.');
            }

            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';

            // Dimensions on screen during preview
            const displayW = baseWidth * zoom;
            const displayH = baseHeight * zoom;

            // Offset of image center relative to viewport center
            const screenCenterX = VIEWPORT_SIZE / 2;
            const screenCenterY = VIEWPORT_SIZE / 2;

            const imageTopLeftX = screenCenterX - displayW / 2 + panX;
            const imageTopLeftY = screenCenterY - displayH / 2 + panY;

            // Map the exact CROP_SIZE bounding box to TARGET_SIZE canvas
            const factor = targetSize / CROP_SIZE;

            const destX = (imageTopLeftX - CROP_OFFSET) * factor;
            const destY = (imageTopLeftY - CROP_OFFSET) * factor;
            const destW = displayW * factor;
            const destH = displayH * factor;

            ctx.drawImage(imageElement, destX, destY, destW, destH);

            canvas.toBlob(
                (blob) => {
                    isProcessing = false;
                    if (blob) {
                        const croppedUrl = URL.createObjectURL(blob);
                        open = false;
                        onApply?.(blob, croppedUrl);
                    }
                },
                outputFormat,
                outputQuality
            );
        } catch (error) {
            isProcessing = false;
            console.error('Error al recortar la imagen:', error);
        }
    }
</script>

<Dialog bind:open onOpenChange={(val) => { if (!val) handleClose(); }}>
    <DialogContent class="max-w-md w-full p-5 sm:p-6 space-y-4 rounded-2xl bg-card border-border shadow-2xl">
        <DialogHeader class="space-y-1">
            <div class="flex items-center gap-2.5">
                <div class="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Crop class="size-4.5" />
                </div>
                <div class="min-w-0 flex-1">
                    <DialogTitle class="text-base font-bold text-foreground leading-snug">{title}</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground line-clamp-1">{description}</DialogDescription>
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="flex flex-col items-center justify-center gap-4 p-0 py-1 overflow-x-hidden">
            <!-- Viewport de recorte con máscara (240x240 universal) -->
            <div
                class="relative size-[240px] rounded-2xl overflow-hidden bg-neutral-950 select-none shadow-inner border border-border/80 cursor-grab active:cursor-grabbing touch-none shrink-0"
                onpointerdown={handlePointerDown}
                onpointermove={handlePointerMove}
                onpointerup={handlePointerUp}
                onpointercancel={handlePointerUp}
                onwheel={handleWheel}
                role="region"
                aria-label="Área interactiva para encuadrar la imagen"
            >
                {#if imageSrc}
                    <div
                        class="absolute inset-0 flex items-center justify-center pointer-events-none transition-transform will-change-transform"
                        style="transform: translate({panX}px, {panY}px) scale({zoom});"
                    >
                        <img
                            src={imageSrc}
                            alt="Imagen a recortar"
                            crossorigin="anonymous"
                            onload={handleImageLoad}
                            class="pointer-events-none max-w-none select-none"
                            style="width: {baseWidth}px; height: {baseHeight}px; min-width: {baseWidth}px; min-height: {baseHeight}px; object-fit: fill;"
                        />
                    </div>
                {/if}

                <!-- Máscara SVG con recorte circular o cuadrado transparente y bordes oscurecidos -->
                <svg
                    class="absolute inset-0 size-full pointer-events-none"
                    viewBox="0 0 240 240"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <defs>
                        <mask id="image-cropper-cutout">
                            <rect width="240" height="240" fill="white" />
                            {#if cropShape === 'circle'}
                                <circle cx="120" cy="120" r={CROP_RADIUS} fill="black" />
                            {:else}
                                <rect x={CROP_OFFSET} y={CROP_OFFSET} width={CROP_SIZE} height={CROP_SIZE} rx="12" fill="black" />
                            {/if}
                        </mask>
                    </defs>
                    <!-- Zona exterior oscurecida -->
                    <rect
                        width="240"
                        height="240"
                        fill="rgba(0, 0, 0, 0.65)"
                        mask="url(#image-cropper-cutout)"
                    />
                    <!-- Guía visual con borde punteado -->
                    {#if cropShape === 'circle'}
                        <circle
                            cx="120"
                            cy="120"
                            r={CROP_RADIUS}
                            stroke="rgba(255, 255, 255, 0.85)"
                            stroke-width="2"
                            stroke-dasharray="4 4"
                        />
                    {:else}
                        <rect
                            x={CROP_OFFSET}
                            y={CROP_OFFSET}
                            width={CROP_SIZE}
                            height={CROP_SIZE}
                            rx="12"
                            stroke="rgba(255, 255, 255, 0.85)"
                            stroke-width="2"
                            stroke-dasharray="4 4"
                        />
                    {/if}
                </svg>

                {#if !imageLoaded && imageSrc}
                    <div class="absolute inset-0 flex items-center justify-center bg-neutral-950/80">
                        <Loader2 class="size-6 text-white animate-spin" />
                    </div>
                {/if}
            </div>

            <!-- Controles de Zoom y Reset (Touch targets de al menos 44px) -->
            <div class="flex items-center justify-center gap-2 w-full max-w-[280px]">
                <button
                    type="button"
                    class="size-11 rounded-xl border border-border/80 bg-background/80 hover:bg-muted text-foreground flex items-center justify-center cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    onclick={() => zoom = Math.max(1, Number((zoom - 0.1).toFixed(2)))}
                    disabled={zoom <= 1 || !imageLoaded}
                    aria-label="Alejar imagen"
                >
                    <ZoomOut class="size-4" />
                </button>

                <!-- Slider accesible de zoom -->
                <div class="flex-1 px-1 flex items-center">
                    <input
                        type="range"
                        min="1"
                        max="3"
                        step="0.05"
                        bind:value={zoom}
                        disabled={!imageLoaded}
                        aria-label="Nivel de acercamiento"
                        class="w-full accent-primary h-2 bg-muted rounded-lg cursor-pointer disabled:cursor-not-allowed"
                    />
                </div>

                <button
                    type="button"
                    class="size-11 rounded-xl border border-border/80 bg-background/80 hover:bg-muted text-foreground flex items-center justify-center cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    onclick={() => zoom = Math.min(3, Number((zoom + 0.1).toFixed(2)))}
                    disabled={zoom >= 3 || !imageLoaded}
                    aria-label="Acercar imagen"
                >
                    <ZoomIn class="size-4" />
                </button>

                <button
                    type="button"
                    class="size-11 rounded-xl border border-border/80 bg-background/80 hover:bg-muted text-foreground flex items-center justify-center cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    onclick={resetTransform}
                    disabled={zoom === 1 && panX === 0 && panY === 0}
                    aria-label="Restablecer posición y zoom"
                    title="Restablecer"
                >
                    <RotateCcw class="size-4" />
                </button>
            </div>
        </DialogBody>

        <DialogFooter class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:justify-end pt-2 border-t border-border/60">
            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] w-full sm:w-auto rounded-xl text-xs font-semibold"
                onclick={handleClose}
                disabled={isProcessing}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                class="min-h-[44px] w-full sm:w-auto rounded-xl text-xs font-semibold gap-2 shadow-xs"
                onclick={applyCrop}
                disabled={!imageLoaded || isProcessing}
            >
                {#if isProcessing}
                    <Loader2 class="size-4 animate-spin" />
                    <span>Recortando...</span>
                {:else}
                    <Check class="size-4" />
                    <span>Aplicar encuadre</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
