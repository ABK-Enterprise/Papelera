<script lang="ts">
    import AppPagination from '@/components/common/AppPagination.svelte';
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
            class="flex flex-col gap-2.5 rounded-xl border border-border/70 bg-card p-3.5 shadow-sm cursor-pointer hover:border-primary/40 transition-colors"
            onclick={() => onSelectOrder(order)}
            onkeydown={(e) => e.key === 'Enter' && onSelectOrder(order)}
            role="button"
            tabindex="0"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-sm font-mono text-foreground">{order.order_number}</span>
                    <span class="text-[11px] text-muted-foreground">{formatDate(order.created_at)}</span>
                </div>

                {#if order.status === 'solicitado'}
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/15 text-amber-600">
                        Solicitado
                    </span>
                {:else if order.status === 'recibido'}
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/15 text-emerald-600">
                        Recibido
                    </span>
                {:else if order.status === 'cancelado'}
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/15 text-rose-600">
                        Cancelado
                    </span>
                {/if}
            </div>

            <div class="flex items-center justify-between text-xs pt-1 border-t">
                <span class="font-semibold text-foreground truncate flex items-center gap-1">
                    <Truck class="size-3 text-muted-foreground" />
                    {order.supplier?.name || 'Proveedor'}
                </span>
                <span class="font-mono font-bold text-sm text-foreground">
                    {Number(order.total_cost) > 0 ? formatPrice(order.total_cost) : '-'}
                </span>
            </div>

            <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                <span>{order.items?.length ?? 0} {order.items?.length === 1 ? 'producto' : 'productos'}</span>
                <span class="text-primary font-medium flex items-center gap-0.5">
                    Ver detalle &rarr;
                </span>
            </div>
        </div>
    {/each}

    {#if orders.length === 0}
        <div class="rounded-xl border border-dashed p-8 text-center bg-card/40">
            <Package class="mx-auto size-10 text-muted-foreground/50 mb-2" />
            <p class="text-xs text-muted-foreground">No se encontraron órdenes de compra.</p>
        </div>
    {/if}

    {#if orders.length > perPage}
        <AppPagination count={orders.length} {perPage} bind:page />
    {/if}
</div>
