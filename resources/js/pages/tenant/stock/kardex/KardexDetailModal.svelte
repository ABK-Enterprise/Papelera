<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Calendar from 'lucide-svelte/icons/calendar';
    import History from 'lucide-svelte/icons/history';
    import Mail from 'lucide-svelte/icons/mail';
    import Package from 'lucide-svelte/icons/package';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';

    interface StockMovementItem {
        id: number;
        type: string;
        reason: string;
        quantity: number;
        previous_stock: number;
        current_stock: number;
        notes: string | null;
        created_at: string;
        product?: {
            id: number;
            name: string;
            sku: string | null;
            barcode: string | null;
        } | null;
        user?: {
            id: number;
            name: string;
            email?: string;
        } | null;
    }

    let {
        open = $bindable(false),
        movement = null,
    }: {
        open?: boolean;
        movement?: StockMovementItem | null;
    } = $props();

    function formatFullDate(dateStr: string): string {
        if (!dateStr) return '-';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('es-AR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        } catch {
            return dateStr;
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-lg p-6">
        {#if movement}
            <div class="flex items-center justify-between pb-4 border-b border-border">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <History class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-bold text-foreground">Detalle de Movimiento de Kardex</DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground">
                            Registro de auditoría #MOV-{String(movement.id).padStart(6, '0')}
                        </DialogDescription>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={() => (open = false)}
                    class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                    aria-label="Cerrar"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div class="space-y-4 pt-3 text-sm">
                <!-- Información del Producto -->
                <div class="p-3.5 rounded-xl bg-muted/40 border border-border/60">
                    <span class="text-xs text-muted-foreground font-semibold uppercase tracking-wider block mb-1">
                        Producto Afectado
                    </span>
                    <div class="flex items-center gap-2.5">
                        <Package class="size-4 text-primary shrink-0" />
                        <div class="min-w-0">
                            <div class="font-bold text-foreground truncate">
                                {movement.product?.name ?? 'Producto eliminado'}
                            </div>
                            <div class="text-xs text-muted-foreground font-mono">
                                {movement.product?.sku ? `SKU: ${movement.product.sku}` : ''}
                                {movement.product?.barcode ? ` • Barra: ${movement.product.barcode}` : ''}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tipo, Cantidad y Balance de Stock -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 rounded-xl bg-muted/30 border border-border/60">
                        <span class="text-xs text-muted-foreground block mb-1">Tipo de Operación</span>
                        {#if movement.type === 'income'}
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <ArrowDownLeft class="size-3.5" />
                                Ingreso (+{movement.quantity})
                            </span>
                        {:else}
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                <ArrowUpRight class="size-3.5" />
                                Egreso (-{movement.quantity})
                            </span>
                        {/if}
                    </div>

                    <div class="p-3 rounded-xl bg-muted/30 border border-border/60">
                        <span class="text-xs text-muted-foreground block mb-1">Balance de Stock</span>
                        <div class="font-mono font-bold text-foreground text-sm">
                            {movement.previous_stock} ➔ {movement.current_stock}
                        </div>
                        <span class="text-[11px] text-muted-foreground">
                            {movement.type === 'income' ? `(+${movement.quantity} unidades)` : `(-${movement.quantity} unidades)`}
                        </span>
                    </div>
                </div>

                <!-- Motivo / Concepto -->
                <div class="p-3 rounded-xl bg-muted/30 border border-border/60">
                    <span class="text-xs text-muted-foreground block mb-1">Motivo / Concepto</span>
                    <span class="font-semibold text-foreground capitalize">
                        {movement.reason ? movement.reason.replace(/_/g, ' ') : 'Movimiento estándar'}
                    </span>
                </div>

                <!-- Usuario Responsable y Fecha -->
                <div class="p-3.5 rounded-xl bg-muted/40 border border-border/60 space-y-2">
                    <span class="text-xs text-muted-foreground font-semibold uppercase tracking-wider block">
                        Auditoría y Ejecución
                    </span>
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5 text-foreground font-medium">
                            <User class="size-3.5 text-muted-foreground" />
                            <span>{movement.user?.name ?? 'Sistema'}</span>
                        </div>
                        {#if movement.user?.email}
                            <div class="flex items-center gap-1.5 text-muted-foreground font-mono text-[11px]">
                                <Mail class="size-3" />
                                <span>{movement.user.email}</span>
                            </div>
                        {/if}
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground pt-1 border-t border-border/40">
                        <Calendar class="size-3.5 text-muted-foreground" />
                        <span>Fecha y hora: {formatFullDate(movement.created_at)}</span>
                    </div>
                </div>

                <!-- Observaciones -->
                {#if movement.notes}
                    <div class="p-3 rounded-xl bg-muted/30 border border-border/60">
                        <span class="text-xs text-muted-foreground block mb-1">Notas / Observaciones</span>
                        <p class="text-xs text-foreground whitespace-pre-wrap">{movement.notes}</p>
                    </div>
                {/if}

                <DialogFooter class="pt-3 border-t border-border">
                    <Button
                        type="button"
                        variant="outline"
                        onclick={() => (open = false)}
                        class="text-xs"
                    >
                        Cerrar Detalle
                    </Button>
                </DialogFooter>
            </div>
        {/if}
    </DialogContent>
</Dialog>
