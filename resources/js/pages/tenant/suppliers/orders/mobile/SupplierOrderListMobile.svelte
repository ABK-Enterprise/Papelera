<script lang="ts">
    import AppPagination from '@/components/common/AppPagination.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Package from 'lucide-svelte/icons/package';
    import Truck from 'lucide-svelte/icons/truck';

    interface SupplierOrderItem {
        id: number;
        product_id: number;
        product_name: string;
        quantity: number;
        unit_cost: string | number;
        subtotal: string | number;
    }

    interface SupplierOrderData {
        id: number;
        order_number: string;
        supplier_id: number;
        status: string;
        total_cost: string | number;
        notes: string | null;
        created_at: string;
        supplier?: {
            id: number;
            name: string;
            contact_number: string | null;
        };
        items?: SupplierOrderItem[];
    }

    let {
        orders = [],
        onSelectOrder,
    }: {
        orders: SupplierOrderData[];
        onSelectOrder: (order: SupplierOrderData) => void;
    } = $props();

    let page = $state(1);
    const perPage = 6;
    let paginatedOrders = $derived(orders.slice((page - 1) * perPage, page * perPage));

    function formatPrice(amount: string | number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    }

    function formatDate(dateStr: string) {
        if (!dateStr) return '-';
        try {
            return new Date(dateStr).toLocaleDateString('es-AR', {
                day: '2-digit',
                month: 'short',
            });
        } catch {
            return dateStr;
        }
    }
</script>

<div class="flex flex-col gap-3">
    {#each paginatedOrders as order (order.id)}
        <div
            class="flex flex-col min-h-[56px] gap-2.5 rounded-2xl border border-border/70 bg-card p-4 shadow-2xs cursor-pointer active:bg-muted/40 transition-colors select-none"
            onclick={() => onSelectOrder(order)}
            onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelectOrder(order)}
            role="button"
            tabindex="0"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-sm font-mono text-foreground tabular-nums">{order.order_number}</span>
                    <span class="text-xs text-muted-foreground tabular-nums flex items-center gap-1">
                        <Calendar class="h-3 w-3" />
                        {formatDate(order.created_at)}
                    </span>
                </div>

                {#if order.status === 'solicitado'}
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                        Solicitado
                    </span>
                {:else if order.status === 'recibido'}
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                        Recibido
                    </span>
                {:else if order.status === 'cancelado'}
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                        Cancelado
                    </span>
                {/if}
            </div>

            <div class="flex items-center justify-between text-xs pt-1.5 border-t border-border/40">
                <span class="font-medium text-foreground truncate flex items-center gap-1">
                    <Truck class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                    {order.supplier?.name || 'Proveedor'}
                </span>
                <span class="font-mono font-bold text-sm text-foreground tabular-nums">
                    {Number(order.total_cost) > 0 ? formatPrice(order.total_cost) : '-'}
                </span>
            </div>

            <div class="flex items-center justify-between text-xs text-muted-foreground">
                <span class="tabular-nums">{order.items?.length ?? 0} {order.items?.length === 1 ? 'producto' : 'productos'}</span>
                <span class="text-primary font-medium flex items-center gap-0.5">
                    Ver detalle &rarr;
                </span>
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
