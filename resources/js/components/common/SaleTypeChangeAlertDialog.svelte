<script lang="ts">
    import { AlertDialog } from 'bits-ui';
    import { Button } from '@/components/ui/button';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Package from 'lucide-svelte/icons/package';
    import Scale from 'lucide-svelte/icons/scale';

    let {
        open = $bindable(false),
        productName = 'este producto',
        fromType = 'unit',
        toType = 'weight',
        currentStock = 0,
        loading = false,
        onconfirm,
        oncancel,
    }: {
        open?: boolean;
        productName?: string;
        fromType?: 'unit' | 'weight';
        toType?: 'unit' | 'weight';
        currentStock?: number;
        loading?: boolean;
        onconfirm?: () => void;
        oncancel?: () => void;
    } = $props();

    function handleConfirm() {
        if (loading) return;
        onconfirm?.();
    }

    function handleCancel() {
        open = false;
        oncancel?.();
    }
</script>

<AlertDialog.Root bind:open>
    <AlertDialog.Portal>
        <AlertDialog.Overlay class="fixed inset-0 z-50 bg-black/70 backdrop-blur-xs data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
        <AlertDialog.Content class="fixed left-1/2 top-1/2 z-50 w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-3xl bg-card p-6 sm:p-7 shadow-2xl border border-border data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95">
            <div class="flex items-start gap-4">
                <!-- Icono de Alerta -->
                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                    <AlertTriangle class="size-6" />
                </div>

                <div class="space-y-2 flex-1 min-w-0">
                    <AlertDialog.Title class="text-base sm:text-lg font-semibold text-foreground leading-snug">
                        {fromType === 'unit'
                            ? 'Cambio de modalidad: Unidad a Pesaje (Kg)'
                            : 'Cambio de modalidad: Pesaje (Kg) a Unidad'}
                    </AlertDialog.Title>
                    
                    <AlertDialog.Description class="text-xs text-muted-foreground leading-relaxed space-y-2.5">
                        <p>
                            Estás modificando la modalidad de venta de <strong class="text-foreground font-medium">{productName}</strong>.
                        </p>

                        <!-- Comparativa Visual X -> Y -->
                        <div class="flex items-center justify-between p-3 rounded-xl bg-muted/50 border border-border/80 text-xs">
                            <div class="flex items-center gap-1.5 font-medium text-foreground">
                                {#if fromType === 'unit'}
                                    <Package class="size-4 text-primary" />
                                    <span>Por unidad (u)</span>
                                {:else}
                                    <Scale class="size-4 text-sky-600 dark:text-sky-400" />
                                    <span>Por pesaje (Kg)</span>
                                {/if}
                            </div>

                            <ArrowRight class="size-4 text-muted-foreground" />

                            <div class="flex items-center gap-1.5 font-semibold text-primary">
                                {#if toType === 'unit'}
                                    <Package class="size-4 text-primary" />
                                    <span>Por unidad (u)</span>
                                {:else}
                                    <Scale class="size-4 text-sky-600 dark:text-sky-400" />
                                    <span>Por pesaje (Kg)</span>
                                {/if}
                            </div>
                        </div>

                        {#if fromType === 'unit' && toType === 'weight'}
                            <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-200 text-xs space-y-1">
                                <p class="font-semibold">⚠️ Impacto en inventario y pedidos:</p>
                                <ul class="list-disc list-inside space-y-0.5 text-xs text-amber-800 dark:text-amber-300">
                                    <li>El stock actual (<strong class="tabular-nums font-semibold">{currentStock}</strong>) pasará a interpretarse como <strong class="tabular-nums font-semibold">{currentStock} Kg</strong>.</li>
                                    <li>Los precios se considerarán por kilogramo (<strong>$ / Kg</strong>).</li>
                                    <li>En los pedidos se habilitará la venta fraccionada en gramos (g) y kilos (Kg).</li>
                                </ul>
                            </div>
                        {:else}
                            <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-200 text-xs space-y-1">
                                <p class="font-semibold">⚠️ Impacto en inventario y pedidos:</p>
                                <ul class="list-disc list-inside space-y-0.5 text-xs text-amber-800 dark:text-amber-300">
                                    <li>El stock actual (<strong class="tabular-nums font-semibold">{currentStock} Kg</strong>) pasará a interpretarse como <strong class="tabular-nums font-semibold">{currentStock} unidades enteras</strong>.</li>
                                    <li>Los precios se considerarán por unidad individual.</li>
                                </ul>
                            </div>
                        {/if}

                        <p class="text-xs text-muted-foreground pt-1">
                            ℹ️ Los pedidos registrados previamente mantendrán sus valores y registros históricos sin alteraciones.
                        </p>
                    </AlertDialog.Description>
                </div>
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 pt-5 mt-4 border-t border-border/60">
                <AlertDialog.Cancel
                    onclick={handleCancel}
                    disabled={loading}
                    class="inline-flex min-h-[40px] items-center justify-center rounded-xl border border-input bg-background px-4 py-2 text-xs font-medium text-foreground hover:bg-muted transition-colors disabled:opacity-50"
                >
                    Cancelar
                </AlertDialog.Cancel>

                <AlertDialog.Action
                    onclick={handleConfirm}
                    disabled={loading}
                    class="inline-flex min-h-[40px] items-center justify-center rounded-xl bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 text-xs font-medium shadow-xs transition-colors disabled:opacity-50"
                >
                    {loading ? 'Guardando...' : 'Confirmar cambio'}
                </AlertDialog.Action>
            </div>
        </AlertDialog.Content>
    </AlertDialog.Portal>
</AlertDialog.Root>
