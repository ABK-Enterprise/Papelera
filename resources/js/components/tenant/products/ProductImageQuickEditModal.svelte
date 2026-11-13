<script lang="ts">
    import { onDestroy } from 'svelte';
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
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import ImageCropperModal from '@/components/common/ImageCropperModal.svelte';
    import Camera from 'lucide-svelte/icons/camera';
    import Crop from 'lucide-svelte/icons/crop';
    import ImageOff from 'lucide-svelte/icons/image-off';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import UploadCloud from 'lucide-svelte/icons/upload-cloud';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';

    export interface ProductData {
        id: number;
        name: string;
        image_url?: string | null;
        thumbnail_url?: string | null;
        image_position?: { zoom: number; x: number; y: number } | null;
    }

    interface Props {
        open?: boolean;
        product: ProductData | null;
        onSuccess?: (updatedProduct: any) => void;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        product = null,
        onSuccess = undefined,
        onclose = undefined,
    }: Props = $props();

    let fileInput: HTMLInputElement | undefined = $state();
    let selectedFile = $state<File | null>(null);
    let previewUrl = $state<string | null>(null);
    let originalObjectUrl = $state<string | null>(null);
    let isSubmitting = $state(false);
    let isDeleting = $state(false);
    let showDeleteConfirm = $state(false);
    let errorMessage = $state<string | null>(null);
    let isDragging = $state(false);
    let isCropperOpen = $state(false);
    let currentPosition = $state<{ zoom: number; x: number; y: number } | null>(null);
    let hasFramingChanged = $state(false);

    let activeDisplayImage = $derived.by(() => {
        if (previewUrl) return previewUrl;
        return product?.thumbnail_url || product?.image_url || null;
    });

    let cropperImageSrc = $derived.by(() => {
        if (originalObjectUrl) return originalObjectUrl;
        return product?.image_url || product?.thumbnail_url || null;
    });

    $effect(() => {
        if (open && product) {
            currentPosition = product.image_position
                ? {
                    zoom: Number(product.image_position.zoom ?? 1),
                    x: Number(product.image_position.x ?? 0),
                    y: Number(product.image_position.y ?? 0),
                }
                : null;
            hasFramingChanged = false;
        } else if (!open) {
            resetLocal();
        }
    });

    onDestroy(() => {
        cleanupObjectUrls();
    });

    function cleanupObjectUrls() {
        if (previewUrl && previewUrl.startsWith('blob:')) {
            URL.revokeObjectURL(previewUrl);
        }
        if (originalObjectUrl && originalObjectUrl.startsWith('blob:')) {
            URL.revokeObjectURL(originalObjectUrl);
        }
    }

    function resetLocal() {
        cleanupObjectUrls();
        selectedFile = null;
        previewUrl = null;
        originalObjectUrl = null;
        errorMessage = null;
        isDragging = false;
        isCropperOpen = false;
        hasFramingChanged = false;
        if (fileInput) {
            fileInput.value = '';
        }
    }

    function handleFileChange(e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        if (file) {
            processFile(file);
        }
    }

    function processFile(file: File) {
        errorMessage = null;
        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            errorMessage = 'Formato no admitido. Seleccione un archivo JPG, PNG o WebP.';
            return;
        }

        if (file.size > 15 * 1024 * 1024) {
            errorMessage = 'El archivo supera el límite de 15 MB.';
            return;
        }

        cleanupObjectUrls();

        selectedFile = file;
        originalObjectUrl = URL.createObjectURL(file);
        previewUrl = originalObjectUrl;
        currentPosition = { zoom: 1, x: 0, y: 0 };
        hasFramingChanged = true;
    }

    function handleDragOver(e: DragEvent) {
        e.preventDefault();
        isDragging = true;
    }

    function handleDragLeave(e: DragEvent) {
        e.preventDefault();
        isDragging = false;
    }

    function handleDrop(e: DragEvent) {
        e.preventDefault();
        isDragging = false;
        const file = e.dataTransfer?.files?.[0];
        if (file) {
            processFile(file);
        }
    }

    function handleCropApply(blob: Blob, url: string, position: { zoom: number; x: number; y: number }) {
        currentPosition = position;
        hasFramingChanged = true;
        if (previewUrl && previewUrl !== originalObjectUrl && previewUrl.startsWith('blob:')) {
            URL.revokeObjectURL(previewUrl);
        }
        previewUrl = url;
    }

    async function handleSave() {
        if (!product || isSubmitting) return;

        // Si no hay cambios ni archivo nuevo ni encuadre modificado, cerrar
        if (!selectedFile && !hasFramingChanged) {
            handleClose();
            return;
        }

        isSubmitting = true;
        errorMessage = null;

        try {
            const formData = new FormData();
            if (selectedFile) {
                formData.append('image', selectedFile);
            }
            if (currentPosition) {
                formData.append('image_position', JSON.stringify(currentPosition));
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            const res = await fetch(`/tenant/productos/${product.id}/imagen`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                errorMessage = data.message || data.error || 'Ocurrió un error al guardar la imagen.';
                return;
            }

            onSuccess?.(data.product);
            handleClose();
        } catch (err: any) {
            errorMessage = err.message || 'Error de comunicación al guardar la imagen.';
        } finally {
            isSubmitting = false;
        }
    }

    async function handleDeleteImage() {
        if (!product || isDeleting) return;

        isDeleting = true;
        errorMessage = null;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            const res = await fetch(`/tenant/productos/${product.id}/imagen`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                errorMessage = data.message || data.error || 'Ocurrió un error al eliminar la imagen.';
                return;
            }

            onSuccess?.(data.product);
            showDeleteConfirm = false;
            handleClose();
        } catch (err: any) {
            errorMessage = err.message || 'Error de red al eliminar la imagen.';
        } finally {
            isDeleting = false;
        }
    }

    function handleClose() {
        if (isSubmitting || isDeleting) return;
        resetLocal();
        open = false;
        onclose?.();
    }
</script>

<Dialog bind:open onOpenChange={(val) => { if (!val) handleClose(); }}>
    <DialogContent class="max-w-md w-full p-5 sm:p-6 space-y-4 rounded-2xl bg-card border-border shadow-2xl">
        <DialogHeader class="space-y-1">
            <div class="flex items-center gap-2.5">
                <div class="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Camera class="size-4.5" />
                </div>
                <div class="min-w-0 flex-1">
                    <DialogTitle class="text-base sm:text-lg font-bold text-foreground leading-snug">
                        Editar foto del producto
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground line-clamp-1">
                        {product?.name ?? 'Ajustar encuadre o actualizar imagen'}
                    </DialogDescription>
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="space-y-4 pt-1">
            <!-- Visualizador y Enfoque de Imagen -->
            <div class="flex flex-col items-center justify-center gap-3">
                <div class="relative size-36 sm:size-40 rounded-2xl border-2 border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 shadow-xs flex items-center justify-center overflow-hidden shrink-0">
                    {#if activeDisplayImage}
                        <img
                            src={activeDisplayImage}
                            alt={product?.name ?? 'Foto del producto'}
                            class="w-full h-full object-cover"
                        />
                    {:else}
                        <div class="flex flex-col items-center justify-center gap-1.5 text-center select-none px-3">
                            <ImageOff class="size-8 text-muted-foreground" />
                            <span class="text-xs font-semibold text-muted-foreground tracking-tight">
                                Sin imagen
                            </span>
                        </div>
                    {/if}
                </div>

                <!-- Botones de Acción sobre la Foto -->
                {#if activeDisplayImage}
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onclick={() => (isCropperOpen = true)}
                            disabled={isSubmitting || isDeleting}
                            class="min-h-[36px] text-xs font-semibold gap-1.5 px-3 rounded-lg"
                            title="Ajustar encuadre sin recortar la foto original"
                        >
                            <Crop class="size-3.5 text-primary" />
                            <span>Ajustar encuadre</span>
                        </Button>

                        {#if product?.image_url || selectedFile}
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                onclick={() => {
                                    if (selectedFile && !product?.image_url) {
                                        resetLocal();
                                    } else {
                                        showDeleteConfirm = true;
                                    }
                                }}
                                disabled={isSubmitting || isDeleting}
                                class="min-h-[36px] text-xs font-semibold gap-1.5 px-3 rounded-lg"
                                title="Eliminar imagen"
                            >
                                <Trash2 class="size-3.5" />
                                <span>Quitar</span>
                            </Button>
                        {/if}
                    </div>
                {/if}
            </div>

            <!-- Zona de Carga / Drag & Drop -->
            <div
                class="relative border-2 border-dashed rounded-xl p-4 transition-all text-center flex flex-col items-center justify-center gap-2 cursor-pointer
                {isDragging ? 'border-primary bg-primary/10 dark:bg-primary/15 ring-2 ring-primary/20' : 'border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 hover:border-primary/50 hover:bg-muted/70 dark:hover:bg-secondary/55'}"
                ondragover={handleDragOver}
                ondragleave={handleDragLeave}
                ondrop={handleDrop}
                onclick={() => fileInput?.click()}
                onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') fileInput?.click(); }}
                role="button"
                tabindex="0"
                aria-label="Cargar nueva imagen de producto"
            >
                <input
                    bind:this={fileInput}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    onchange={handleFileChange}
                />

                <div class="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <UploadCloud class="size-5" />
                </div>

                <div class="space-y-0.5">
                    <p class="text-xs sm:text-sm font-semibold text-foreground">
                        {activeDisplayImage ? 'Reemplazar con otra imagen' : 'Subir imagen o foto'}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Arrastrá un archivo o hacé clic para explorar (JPG, PNG, WebP hasta 15 MB)
                    </p>
                </div>
            </div>

            {#if errorMessage}
                <p class="text-xs text-destructive font-medium flex items-center gap-1.5">
                    <span>•</span>
                    <span>{errorMessage}</span>
                </p>
            {/if}
        </DialogBody>

        <DialogFooter class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 pt-2">
            <Button
                type="button"
                variant="secondary"
                class="min-h-[44px] text-xs font-semibold rounded-xl"
                onclick={handleClose}
                disabled={isSubmitting || isDeleting}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                variant="default"
                class="min-h-[44px] text-xs font-semibold rounded-xl gap-2"
                onclick={handleSave}
                disabled={isSubmitting || isDeleting || (!selectedFile && !hasFramingChanged)}
            >
                {#if isSubmitting}
                    <Loader2 class="size-4 animate-spin" />
                    <span>Guardando...</span>
                {:else}
                    <span>Guardar cambios</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Diálogo de Confirmación para Eliminar Imagen -->
<ConfirmActionModal
    bind:open={showDeleteConfirm}
    title="¿Eliminar la imagen del producto?"
    description={`Se eliminará permanentemente la foto asociada a "${product?.name}". Esta acción no se puede deshacer.`}
    confirmText="Eliminar foto"
    variant="destructive"
    loading={isDeleting}
    onconfirm={handleDeleteImage}
    oncancel={() => (showDeleteConfirm = false)}
/>

<!-- Modal de Encuadre No Destructivo -->
<ImageCropperModal
    bind:open={isCropperOpen}
    imageSrc={cropperImageSrc}
    cropShape="square"
    outputFormat="image/webp"
    targetSize={512}
    title="Ajustar encuadre"
    description="Arrastrá la foto y ajustá el zoom para centrar el producto sin recortar su archivo original."
    initialPosition={currentPosition}
    onApply={handleCropApply}
/>
