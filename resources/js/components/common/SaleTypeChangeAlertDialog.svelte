<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Package from 'lucide-svelte/icons/package';
    import Scale from 'lucide-svelte/icons/scale';
    import X from 'lucide-svelte/icons/x';

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

<Dialog bind:open>
    <DialogContent size="default" class="max-w-lg">
        <DialogHeader>
            <div class="flex items-center gap-3 min-w-0">
                <!-- Icono de Alerta -->
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                    <AlertTriangle class="size-5" />
                </div>
                <DialogTitle class="text-base font-semibold leading-snug">
                    {fromType === 'unit'
                        ? 'Cambio de modalidad: Unidad a Pesaje (Kg)'
                        : 'Cambio de modalidad: Pesaje (Kg) a Unidad'}
                </DialogTitle>
            </div>
            <button
                type="button"
                onclick={handleCancel}
                class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted dark:hover:bg-secondary/60 hover:text-foreground transition-colors cursor-pointer shrink-0"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </DialogHeader>

        <DialogBody class="space-y-3.5">
            <DialogDescription class="text-xs sm:text-sm text-muted-foreground leading-relaxed">
                Estás modificando la modalidad de venta de <strong class="text-foreground font-medium">{productName}</strong>.
            </DialogDescription>

            <!-- Comparativa Visual X -> Y -->
            <div class="flex items-center justify-between p-3 rounded-xl bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 text-xs">
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
        </DialogBody>

        <DialogFooter>
            <Button
                type="button"
                variant="secondary"
                onclick={handleCancel}
                disabled={loading}
                class="min-h-[44px] px-4 rounded-xl text-sm font-semibold"
            >
                Cancelar
            </Button>
            <Button
                type="button"
                variant="warning"
                onclick={handleConfirm}
                disabled={loading}
                class="min-h-[44px] px-4 rounded-xl text-sm font-semibold min-w-32 shadow-xs"
            >
                {loading ? 'Guardando...' : 'Confirmar cambio'}
            </Button>
        </DialogFooter>

    </DialogContent>
</Dialog>
