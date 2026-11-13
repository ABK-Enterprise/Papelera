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
    import Download from 'lucide-svelte/icons/download';
    import ExternalLink from 'lucide-svelte/icons/external-link';
    import FileText from 'lucide-svelte/icons/file-text';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import Printer from 'lucide-svelte/icons/printer';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import X from 'lucide-svelte/icons/x';

    let {
        open = $bindable(false),
        title = 'Comprobante de Pedido',
        orderNumber = '',
        previewUrl = '',
        viewerUrl = '',
        downloadUrl = '',
    }: {
        open: boolean;
        title?: string;
        orderNumber?: string;
        previewUrl: string;
        viewerUrl?: string;
        downloadUrl: string;
    } = $props();

    let isGenerated = $state(false);
    let isGenerating = $state(false);
    let iframeLoading = $state(true);

    $effect(() => {
        if (!open) {
            // Reset al cerrar el modal
            isGenerated = false;
            isGenerating = false;
            iframeLoading = true;
        }
    });

    function handleGenerate() {
        isGenerating = true;
        iframeLoading = true;
        // Simular o dar paso al iframe que disparará el endpoint
        setTimeout(() => {
            isGenerated = true;
            isGenerating = false;
        }, 300);
    }

    function handleReload() {
        iframeLoading = true;
        const iframe = document.getElementById('pdf-preview-frame') as HTMLIFrameElement | null;
        if (iframe) {
            iframe.src = previewUrl + (previewUrl.includes('?') ? '&' : '?') + 't=' + Date.now();
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-4xl max-h-[90vh] sm:max-w-4xl p-0 flex flex-col font-sans overflow-hidden">
        <!-- Header del Modal -->
        <div class="px-5 py-4 sm:px-6 border-b border-border/80 flex items-center justify-between shrink-0 bg-card">
            <div class="flex items-center gap-3 min-w-0">
                <div class="size-9 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                    <Printer class="size-4.5" />
                </div>
                <div class="flex items-center gap-2 min-w-0 flex-wrap">
                    <h2 class="text-base sm:text-lg font-bold tracking-tight text-foreground">{title}</h2>
                    {#if orderNumber}
                        <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary tabular-nums font-sans shrink-0">
                            {orderNumber}
                        </span>
                    {/if}
                </div>
            </div>

            <button
                type="button"
                onclick={() => (open = false)}
                class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted dark:hover:bg-secondary/60 transition-colors"
                aria-label="Cerrar modal"
            >
                <X class="size-4.5" />
            </button>
        </div>

        <!-- Cuerpo del Modal -->
        <div class="p-5 sm:p-6 flex-1 overflow-y-auto min-h-[380px] sm:min-h-[500px] flex flex-col bg-muted/30 dark:bg-background/50">
            {#if !isGenerated}
                <!-- Estado Inicial: Empty State -->
                <div class="flex-1 flex flex-col items-center justify-center text-center p-8 border-2 border-dashed border-border/80 rounded-2xl bg-card my-auto">
                    <div class="size-16 rounded-2xl bg-primary/10 flex items-center justify-center text-primary mb-4 shadow-2xs">
                        <FileText class="size-8" />
                    </div>
                    <h3 class="text-lg font-bold text-foreground mb-1.5">No hay PDF generado</h3>
                    <p class="text-xs sm:text-sm text-muted-foreground max-w-md mb-6 leading-relaxed">
                        Compila el documento oficial con membrete del comercio, detalle desglosado de ítems y totales.
                    </p>
                    <Button
                        type="button"
                        onclick={handleGenerate}
                        disabled={isGenerating}
                        class="min-h-[44px] px-6 gap-2 rounded-xl text-sm font-medium shadow-xs"
                    >
                        {#if isGenerating}
                            <Loader2 class="size-4.5 animate-spin" />
                            <span>Generando documento...</span>
                        {:else}
                            <Printer class="size-4.5" />
                            <span>Generar PDF</span>
                        {/if}
                    </Button>
                </div>
            {:else}
                <!-- Estado Generado: Visor Iframe Embebido -->
                <div class="relative flex-1 w-full h-full min-h-[480px] rounded-xl overflow-hidden border border-border bg-card shadow-xs">
                    {#if iframeLoading}
                        <div class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-card/90 backdrop-blur-xs gap-3">
                            <Loader2 class="size-8 text-primary animate-spin" />
                            <span class="text-xs font-semibold text-muted-foreground">Cargando previsualización del PDF...</span>
                        </div>
                    {/if}
                    <iframe
                        id="pdf-preview-frame"
                        src={previewUrl}
                        title="Visor PDF"
                        class="w-full h-full min-h-[480px] border-0 rounded-xl"
                        onload={() => (iframeLoading = false)}
                    ></iframe>
                </div>
            {/if}
        </div>

        <!-- Footer del Modal con Acciones Simétricas y Layout Responsive Mobile / Desktop -->
        <div class="px-5 py-3.5 sm:px-6 border-t border-border/80 flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between shrink-0 bg-card">
            {#if isGenerated}
                <div class="w-full sm:w-auto flex justify-center sm:justify-start order-1">
                    <Button
                        type="button"
                        variant="ghost"
                        onclick={handleReload}
                        class="min-h-[44px] w-full sm:w-auto px-4 text-sm font-medium text-muted-foreground hover:text-foreground gap-2 rounded-xl justify-center"
                        title="Recargar documento"
                    >
                        <RefreshCw class="size-4" />
                        <span>Recargar documento</span>
                    </Button>
                </div>
            {/if}

            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-2.5 w-full sm:w-auto order-2">
                {#if isGenerated}
                    <!-- Fila 1 en Mobile / Acciones principales en Desktop -->
                    <div class="grid grid-cols-[1fr_1.25fr] sm:flex sm:items-center gap-2 sm:gap-2.5 w-full sm:w-auto order-1 sm:order-2">
                        <a href={viewerUrl || previewUrl} target="_blank" rel="noreferrer" class="w-full sm:w-auto">
                            <Button
                                type="button"
                                variant="secondary"
                                class="w-full sm:w-auto min-h-[44px] px-3 sm:px-4 gap-2 rounded-xl text-sm font-medium justify-center"
                            >
                                <ExternalLink class="size-4 text-primary shrink-0" />
                                <span class="sm:hidden">Abrir</span>
                                <span class="hidden sm:inline">Abrir en pestaña</span>
                            </Button>
                        </a>

                        <a href={downloadUrl} download class="w-full sm:w-auto">
                            <Button
                                type="button"
                                class="w-full sm:w-auto min-h-[44px] px-4 sm:px-5 gap-2 rounded-xl text-sm font-medium shadow-xs justify-center"
                            >
                                <Download class="size-4 shrink-0" />
                                <span class="sm:hidden">Descargar</span>
                                <span class="hidden sm:inline">Descargar pdf</span>
                            </Button>
                        </a>
                    </div>
                {/if}

                <!-- Fila 2 en Mobile (100% ancho) / Primer botón a la izquierda del grupo en Desktop -->
                <Button
                    type="button"
                    variant="secondary"
                    class="w-full sm:w-auto min-h-[44px] px-4 rounded-xl text-sm font-medium justify-center order-2 sm:order-1"
                    onclick={() => (open = false)}
                >
                    Cerrar
                </Button>
            </div>
        </div>
    </DialogContent>
</Dialog>
