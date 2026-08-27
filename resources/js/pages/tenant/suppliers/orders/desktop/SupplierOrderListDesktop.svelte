<script lang="ts">
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Eye from 'lucide-svelte/icons/eye';
    import Package from 'lucide-svelte/icons/package';
    import Truck from 'lucide-svelte/icons/truck';

    export interface SupplierOrderItemSummary {
        id: number;
        product_id: number;
        product_name: string;
        quantity: number;
        unit_cost: number | string;
        subtotal: number | string;
    }

    export interface SupplierOrderSummary {
        id: number;
        order_number: string;
        supplier_id: number;
        supplier_name: string;
        supplier_contact?: string | null;
        status: string;
        total_cost: number | string;
        expected_delivery_date?: string | null;
        received_at?: string | null;
        notes?: string | null;
        created_at: string;
        items?: SupplierOrderItemSummary[];
    }

    let {
        orders = [],
        onSelectOrder,
    }: {
        orders: SupplierOrderSummary[];
        onSelectOrder: (order: SupplierOrderSummary) => void;
    } = $props();

    let page = $state(1);
    const perPage = 10;
    let paginatedOrders = $derived(orders.slice((page - 1) * perPage, page * perPage));

    function formatPrice(amount: string | number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    }

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('es-AR', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return dateStr;
        }
    }
</script>

<div class="flex flex-col gap-3">
    {#each paginatedOrders as order (order.id)}
        <div
            role="button"
            tabindex="0"
            onclick={() => onSelectOrder(order)}
            onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelectOrder(order)}
            class="flex items-center justify-between p-4 rounded-2xl border border-border/70 bg-card shadow-xs hover:shadow-md hover:border-border transition-all duration-200 gap-4 cursor-pointer select-none"
        >
            <!-- Lado Izquierdo: Icono + Número de Pedido y Fecha -->
            <div class="flex items-center gap-3.5 min-w-[200px]">
                <div class="h-12 w-12 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
                    <Truck class="h-6 w-6 stroke-[1.75]" />
                </div>

                <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-base font-mono text-foreground tabular-nums">{order.order_number}</span>
                    </div>
                    <div class="flex items-center gap-1 text-xs text-muted-foreground mt-0.5 tabular-nums">
                        <Calendar class="h-3 w-3" />
                        <span>{formatDate(order.created_at)}</span>
                    </div>
                </div>
            </div>

            <!-- Proveedor -->
            <div class="flex flex-col min-w-[180px]">
                <span class="font-semibold text-sm text-foreground flex items-center gap-1.5 truncate">
                    <Truck class="h-3.5 w-3.5 text-muted-foreground" />
                    {order.supplier_name}
                </span>
                {#if order.supplier_contact}
                    <span class="text-xs text-muted-foreground truncate font-mono mt-0.5 tabular-nums">
                        {order.supplier_contact}
                    </span>
                {/if}
            </div>

            <!-- Resumen de Items -->
            <div class="flex flex-col min-w-[140px]">
                <span class="text-xs text-muted-foreground">Artículos:</span>
                <span class="text-xs font-semibold text-foreground truncate tabular-nums">
                    {order.items?.length ?? 0} {order.items?.length === 1 ? 'producto' : 'productos'}
                </span>
            </div>

            <!-- Estado -->
            <div class="min-w-[130px]">
                {#if order.status === 'solicitado'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium tracking-wide bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        Solicitado
                    </span>
                {:else if order.status === 'recibido'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium tracking-wide bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Recibido en Stock
                    </span>
                {:else if order.status === 'cancelado'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium tracking-wide bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                        Cancelado
                    </span>
                {/if}
            </div>

            <!-- Monto Total y Botón con Touch Target >= 40px -->
            <div class="flex items-center gap-4 shrink-0">
                <div class="text-right min-w-[110px]">
                    <span class="text-xs text-muted-foreground block">Costo Total:</span>
                    <span class="font-bold text-base font-mono text-foreground tabular-nums">
                        {Number(order.total_cost) > 0 ? formatPrice(order.total_cost) : '-'}
                    </span>
                </div>

                <Button size="sm" variant="outline" class="min-h-[40px] px-3.5 text-xs gap-1.5 font-medium rounded-xl border-border/70 hover:bg-muted/60">
                    <Eye class="h-3.5 w-3.5" />
                    <span>Ver detalle</span>
                </Button>
            </div>
        </div>
    {/each}

    {#if orders.length === 0}
        <EmptyState
            icon={Package}
            title="No se encontraron órdenes de compra"
            description="No hay pedidos a proveedores registrados con los filtros seleccionados."
        />
    {/if}

    {#if orders.length > perPage}
        <AppPagination count={orders.length} {perPage} bind:page />
    {/if}
</div>
