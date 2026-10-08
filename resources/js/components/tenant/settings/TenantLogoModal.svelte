<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { router } from '@inertiajs/svelte';
    import Camera from 'lucide-svelte/icons/camera';
    import ImageOff from 'lucide-svelte/icons/image-off';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import Store from 'lucide-svelte/icons/store';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import UploadCloud from 'lucide-svelte/icons/upload-cloud';
    import Crop from 'lucide-svelte/icons/crop';
    import TenantLogoCropModal from '@/components/tenant/settings/TenantLogoCropModal.svelte';
    import { onDestroy } from 'svelte';

    let {
        open = $bindable(false),
        currentLogoUrl = null,
        onSuccess,
    }: {
        open?: boolean;
        currentLogoUrl?: string | null;
        onSuccess?: (newLogoUrl: string | null) => void;
    } = $props();

    let fileInput: HTMLInputElement | undefined = $state();
    let selectedFile = $state<File | null>(null);
    let previewUrl = $state<string | null>(null);
    let isSubmitting = $state(false);
    let isDeleting = $state(false);
    let showDeleteConfirm = $state(false);
    let showCropModal = $state(false);
    let errorMessage = $state<string | null>(null);
    let isDragging = $state(false);
    let imageFailed = $state(false);

    // Derived active display URL: selected file preview > server current logo > null
    let activeImage = $derived(previewUrl || currentLogoUrl || null);

    $effect(() => {
        if (activeImage) {
            imageFailed = false;
        }
    });

    onDestroy(() => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
    });

    function handleFileChange(event: Event) {
        const target = event.target as HTMLInputElement;
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

        if (file.size > 5 * 1024 * 1024) {
            errorMessage = 'El archivo supera el límite de 5 MB.';
            return;
        }

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        selectedFile = file;
        previewUrl = URL.createObjectURL(file);
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

    function handleSave() {
        if (!selectedFile || isSubmitting) return;

        isSubmitting = true;
        errorMessage = null;

        const formData = new FormData();
        formData.append('logo', selectedFile);

        router.post('/tenant/configuracion/comercio/logo', formData, {
            preserveScroll: true,
            onSuccess: (page) => {
                isSubmitting = false;
                open = false;
                const newLogo = (page.props as any)?.auth?.tenant?.logo_url || null;
                onSuccess?.(newLogo);
                resetLocal();
            },
            onError: (errors) => {
                isSubmitting = false;
                errorMessage = errors.logo || 'Ocurrió un error al guardar el logo.';
            },
        });
    }

    function confirmDelete() {
        if (selectedFile && !currentLogoUrl) {
            // Only local selection, discard it
            resetLocal();
            showDeleteConfirm = false;
            return;
        }

        showDeleteConfirm = true;
    }

    function handleDeleteServerLogo() {
        if (isDeleting) return;

        isDeleting = true;
        errorMessage = null;

        router.delete('/tenant/configuracion/comercio/logo', {
            preserveScroll: true,
            onSuccess: () => {
                isDeleting = false;
                showDeleteConfirm = false;
                open = false;
                resetLocal();
                onSuccess?.(null);
            },
            onError: (errors) => {
                isDeleting = false;
                showDeleteConfirm = false;
                errorMessage = (errors as any)?.message || 'Ocurrió un error al eliminar el logo.';
            },
        });
    }

    function handleCropApply(blob: Blob, url: string) {
        if (previewUrl && previewUrl.startsWith('blob:')) {
            URL.revokeObjectURL(previewUrl);
        }
        selectedFile = new File([blob], 'logo.png', { type: 'image/png' });
        previewUrl = url;
        imageFailed = false;
        errorMessage = null;
    }

    function resetLocal() {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        selectedFile = null;
        previewUrl = null;
        errorMessage = null;
        if (fileInput) {
            fileInput.value = '';
        }
    }

    function handleClose() {
        if (isSubmitting || isDeleting) return;
        resetLocal();
        open = false;
    }
</script>

<Dialog bind:open onOpenChange={(val) => { if (!val) handleClose(); }}>
    <DialogContent class="max-w-md w-full p-5 sm:p-6 space-y-4 rounded-2xl">
        <DialogHeader>
            <DialogTitle class="text-xl font-bold tracking-tight">Editar logo</DialogTitle>
        </DialogHeader>

        <DialogBody class="space-y-4 pt-1">
            <!-- Imagen actual centrada -->
            <div class="flex flex-col items-center justify-center gap-3">
                <div class="relative size-32 sm:size-36 rounded-full border-2 border-border/80 bg-muted/30 shadow-inner flex items-center justify-center overflow-hidden shrink-0">
                    {#if activeImage && !imageFailed}
                        <img
                            src={activeImage}
                            alt="Logo del comercio"
                            onerror={() => (imageFailed = true)}
                            class="w-full h-full object-cover"
                        />
                    {:else}
                        <div class="flex flex-col items-center justify-center gap-1.5 text-center select-none px-3">
                            <ImageOff class="size-7 text-muted-foreground/60" />
                            <span class="text-xs font-semibold text-muted-foreground/80 tracking-tight">
                                Sin imagen
                            </span>
                        </div>
                    {/if}
                </div>

                {#if activeImage && !imageFailed}
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onclick={() => (showCropModal = true)}
                            disabled={isSubmitting || isDeleting}
                            class="min-h-[36px] text-xs font-semibold text-foreground hover:bg-muted gap-1.5 px-3 rounded-lg border-border/80"
                        >
                            <Crop class="size-3.5 text-primary" />
                            <span>Ajustar</span>
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onclick={confirmDelete}
                            disabled={isSubmitting || isDeleting}
                            class="min-h-[36px] text-xs font-semibold gap-1.5 px-3 rounded-lg"
                        >
                            <Trash2 class="size-3.5" />
                            <span>Quitar</span>
                        </Button>
                    </div>
                {/if}
            </div>

            <!-- Zona de Carga / Drag & Drop -->
            <div
                class="relative border-2 border-dashed rounded-xl p-4 transition-all text-center flex flex-col items-center justify-center gap-2 cursor-pointer
                {isDragging ? 'border-primary bg-primary/5' : 'border-border/80 hover:border-primary/50 hover:bg-muted/30'}"
                ondragover={handleDragOver}
                ondragleave={handleDragLeave}
                ondrop={handleDrop}
                onclick={() => fileInput?.click()}
                onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') fileInput?.click(); }}
                role="button"
                tabindex="0"
                aria-label="Cargar nueva imagen de logo"
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
                    <p class="text-xs font-semibold text-foreground">
                        {selectedFile ? selectedFile.name : 'Haz clic o arrastra para cargar'}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        JPG, PNG o WebP (máx. 5 MB)
                    </p>
                </div>
            </div>

            {#if errorMessage}
                <div class="p-2.5 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs font-medium">
                    {errorMessage}
                </div>
            {/if}
        </DialogBody>

        <DialogFooter class="grid grid-cols-2 gap-2.5 sm:flex sm:justify-end sm:gap-3 pt-2">
            <Button
                type="button"
                variant="outline"
                onclick={handleClose}
                disabled={isSubmitting || isDeleting}
                class="w-full sm:w-auto min-h-[44px] px-5 rounded-xl font-semibold justify-center"
            >
                Cerrar
            </Button>
            <Button
                type="button"
                onclick={handleSave}
                disabled={!selectedFile || isSubmitting || isDeleting}
                class="w-full sm:w-auto min-h-[44px] px-5 rounded-xl font-semibold gap-2 justify-center"
            >
                {#if isSubmitting}
                    <Loader2 class="size-4 animate-spin" />
                    <span>Guardando...</span>
                {:else}
                    <span>Guardar</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal de Confirmación para Eliminar Logo -->
<ConfirmActionModal
    bind:open={showDeleteConfirm}
    title="¿Eliminar logo del comercio?"
    description="Esta acción quitará el logo del comercio de la plataforma y de los comprobantes PDF oficiales."
    confirmText="Eliminar"
    cancelText="Cancelar"
    variant="destructive"
    isLoading={isDeleting}
    onConfirm={handleDeleteServerLogo}
    onCancel={() => (showDeleteConfirm = false)}
/>

<!-- Modal de Ajuste y Recorte Circular -->
<TenantLogoCropModal
    bind:open={showCropModal}
    imageSrc={activeImage}
    onApply={handleCropApply}
    onCancel={() => (showCropModal = false)}
/>
