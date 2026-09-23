<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import ZoomIn from 'lucide-svelte/icons/zoom-in';
    import ZoomOut from 'lucide-svelte/icons/zoom-out';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Check from 'lucide-svelte/icons/check';
    import Loader2 from 'lucide-svelte/icons/loader-2';

    let {
        open = $bindable(false),
        imageSrc = null,
        onApply,
        onCancel,
    }: {
        open?: boolean;
        imageSrc?: string | null;
        onApply?: (blob: Blob, url: string) => void;
        onCancel?: () => void;
    } = $props();

    // Geometric constants for pixel-perfect circular crop
    const VIEWPORT_SIZE = 280;
    const CROP_SIZE = 260; // Exact diameter of the guide circle
    const CROP_RADIUS = CROP_SIZE / 2; // 130px
    const CROP_OFFSET = (VIEWPORT_SIZE - CROP_SIZE) / 2; // 10px margin around circle
    const TARGET_SIZE = 512; // High-res square output canvas

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

        // Base scale: ensure the image at zoom=1 covers at least the CROP_SIZE diameter
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
            canvas.width = TARGET_SIZE;
            canvas.height = TARGET_SIZE;
            const ctx = canvas.getContext('2d');

            if (!ctx) {
                throw new Error('No se pudo inicializar el contexto de imagen.');
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
            const factor = TARGET_SIZE / CROP_SIZE;

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
                'image/png',
                0.95
            );
        } catch (error) {
            isProcessing = false;
            console.error('Error al recortar la imagen:', error);
        }
    }
</script>

<Dialog bind:open onOpenChange={(val) => { if (!val) handleClose(); }}>
    <DialogContent class="max-w-md w-full p-5 sm:p-6 space-y-4 rounded-2xl">
        <!-- Accessible hidden title for screen readers -->
        <DialogTitle class="sr-only">Ajustar encuadre</DialogTitle>

        <!-- Espacio en blanco de 10px con separador horizontal -->
        <div class="pt-2.5 border-b border-border/60 w-full"></div>

        <DialogBody class="flex flex-col items-center justify-center gap-4 py-1">
            <!-- Viewport de recorte con máscara circular -->
            <div
                class="relative size-[280px] rounded-2xl overflow-hidden bg-neutral-950 select-none shadow-inner border border-border/80 cursor-grab active:cursor-grabbing touch-none shrink-0"
                onpointerdown={handlePointerDown}
                onpointermove={handlePointerMove}
                onpointerup={handlePointerUp}
                onpointercancel={handlePointerUp}
                onwheel={handleWheel}
                role="region"
                aria-label="Área interactiva para encuadrar la imagen del logo"
            >
                {#if imageSrc}
                    <div
                        class="absolute inset-0 flex items-center justify-center pointer-events-none transition-transform will-change-transform"
                        style="transform: translate({panX}px, {panY}px) scale({zoom});"
                    >
                        <img
                            src={imageSrc}
                            alt="Logo a recortar"
                            crossorigin="anonymous"
                            onload={handleImageLoad}
                            class="pointer-events-none max-w-none select-none"
                            style="width: {baseWidth}px; height: {baseHeight}px; min-width: {baseWidth}px; min-height: {baseHeight}px; object-fit: fill;"
                        />
                    </div>
                {/if}

                <!-- Máscara SVG con corte circular transparente y bordes sombreados -->
                <svg
                    class="absolute inset-0 size-full pointer-events-none"
                    viewBox="0 0 280 280"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <defs>
                        <mask id="logo-circle-cutout">
                            <rect width="280" height="280" fill="white" />
                            <circle cx="140" cy="140" r={CROP_RADIUS} fill="black" />
                        </mask>
                    </defs>
                    <!-- Zona exterior oscurecida -->
                    <rect
                        width="280"
                        height="280"
                        fill="rgba(0, 0, 0, 0.65)"
                        mask="url(#logo-circle-cutout)"
                    />
                    <!-- Anillo circular guía -->
                    <circle
                        cx="140"
                        cy="140"
                        r={CROP_RADIUS}
                        stroke="rgba(255, 255, 255, 0.85)"
                        stroke-width="2"
                        stroke-dasharray="4 4"
                    />
                </svg>

                {#if !imageLoaded && imageSrc}
                    <div class="absolute inset-0 flex items-center justify-center bg-background/80 backdrop-blur-xs">
                        <Loader2 class="size-6 text-primary animate-spin" />
                    </div>
                {/if}
            </div>

            <!-- Controles de Zoom -->
            <div class="w-full max-w-[280px] sm:max-w-[300px] space-y-2">
                <div class="flex items-center justify-between text-xs text-muted-foreground font-medium px-1">
                    <span class="flex items-center gap-1.5">
                        <ZoomIn class="size-3.5 text-primary" />
                        <span>Zoom ({Math.round(zoom * 100)}%)</span>
                    </span>
                </div>

                <div class="flex items-center gap-2.5 w-full">
                    <button
                        type="button"
                        onclick={() => (zoom = Math.max(1, Number((zoom - 0.1).toFixed(2))))}
                        aria-label="Reducir zoom"
                        class="size-8 rounded-lg border border-border/80 bg-background hover:bg-muted flex items-center justify-center text-muted-foreground hover:text-foreground shrink-0 cursor-pointer transition-colors"
                    >
                        <ZoomOut class="size-3.5" />
                    </button>

                    <input
                        type="range"
                        min="1"
                        max="3"
                        step="0.05"
                        bind:value={zoom}
                        class="flex-1 min-w-0 accent-primary h-1.5 bg-muted rounded-lg cursor-pointer"
                    />

                    <button
                        type="button"
                        onclick={() => (zoom = Math.min(3, Number((zoom + 0.1).toFixed(2))))}
                        aria-label="Aumentar zoom"
                        class="size-8 rounded-lg border border-border/80 bg-background hover:bg-muted flex items-center justify-center text-muted-foreground hover:text-foreground shrink-0 cursor-pointer transition-colors"
                    >
                        <ZoomIn class="size-3.5" />
                    </button>
                </div>
            </div>
        </DialogBody>

        <DialogFooter class="pt-2 w-full">
            <!-- Mobile Actions Layout (< sm) -->
            <div class="flex flex-col gap-2 w-full sm:hidden">
                <div class="grid grid-cols-2 gap-2 w-full">
                    <Button
                        type="button"
                        variant="outline"
                        onclick={resetTransform}
                        disabled={isProcessing}
                        class="min-h-[44px] rounded-xl font-semibold text-xs justify-center border-border/80"
                    >
                        Restablecer
                    </Button>
                    <Button
                        type="button"
                        onclick={applyCrop}
                        disabled={isProcessing || !imageLoaded}
                        class="min-h-[44px] rounded-xl font-semibold text-xs justify-center gap-1.5"
                    >
                        {#if isProcessing}
                            <Loader2 class="size-3.5 animate-spin" />
                            <span>Aplicando...</span>
                        {:else}
                            <span>Aplicar</span>
                        {/if}
                    </Button>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onclick={handleClose}
                    disabled={isProcessing}
                    class="w-full min-h-[44px] rounded-xl font-semibold text-xs justify-center border-border/80"
                >
                    Cancelar
                </Button>
            </div>

            <!-- Desktop Actions Layout (>= sm) -->
            <div class="hidden sm:flex sm:items-center sm:justify-end sm:gap-2.5 w-full">
                <Button
                    type="button"
                    variant="outline"
                    onclick={handleClose}
                    disabled={isProcessing}
                    class="min-h-[40px] px-4 rounded-xl font-semibold text-xs justify-center border-border/80"
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onclick={resetTransform}
                    disabled={isProcessing}
                    class="min-h-[40px] px-4 rounded-xl font-semibold text-xs gap-1.5 justify-center border-border/80"
                >
                    <RotateCcw class="size-3.5" />
                    <span>Restablecer</span>
                </Button>
                <Button
                    type="button"
                    onclick={applyCrop}
                    disabled={isProcessing || !imageLoaded}
                    class="min-h-[40px] px-5 rounded-xl font-semibold text-xs gap-2 justify-center"
                >
                    {#if isProcessing}
                        <Loader2 class="size-4 animate-spin" />
                        <span>Recortando...</span>
                    {:else}
                        <Check class="size-4" />
                        <span>Aplicar ajuste</span>
                    {/if}
                </Button>
            </div>
        </DialogFooter>
    </DialogContent>
</Dialog>
