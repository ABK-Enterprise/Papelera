<script lang="ts">
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Eye from 'lucide-svelte/icons/eye';
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
    const perPage = 10;
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
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return dateStr;
        }
    }
</script>

<div class="flex flex-col gap-3.5">
    {#each paginatedOrders as order (order.id)}
        <div
            class="bento-card group flex flex-col md:flex-row items-start md:items-center justify-between gap-4 rounded-xl border border-border/60 bg-card p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] transition-all hover:border-primary/30 hover:shadow-md cursor-pointer"
            onclick={() => onSelectOrder(order)}
            onkeydown={(e) => e.key === 'Enter' && onSelectOrder(order)}
            role="button"
            tabindex="0"
        >
            <!-- Número de Orden & Proveedor -->
            <div class="flex items-center gap-3.5 min-w-[220px]">
                <IconContainer size="lg" variant="primary" icon="solar:clipboard-list-bold-duotone" />
                <div>
                    <span class="font-bold text-base font-sans tabular-nums text-foreground">{order.order_number}</span>
                    <div class="flex items-center gap-1 text-xs text-muted-foreground mt-0.5">
                        <Calendar class="size-3" />
                        <span>{formatDate(order.created_at)}</span>
                    </div>
                </div>
            </div>

            <!-- Proveedor -->
            <div class="flex flex-col min-w-[180px]">
                <span class="text-xs text-muted-foreground">Proveedor:</span>
                <span class="font-bold text-sm text-foreground truncate">
                    {order.supplier?.name || 'Proveedor'}
                </span>
            </div>

            <!-- Resumen de Items -->
            <div class="flex flex-col min-w-[140px]">
                <span class="text-xs text-muted-foreground">Artículos:</span>
                <span class="text-xs font-semibold text-foreground truncate">
                    {order.items?.length ?? 0} {order.items?.length === 1 ? 'producto' : 'productos'}
                </span>
            </div>

            <!-- Estado -->
            <div class="min-w-[130px]">
                {#if order.status === 'solicitado'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                        Solicitado
                    </span>
                {:else if order.status === 'recibido'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        Recibido en Stock
                    </span>
                {:else if order.status === 'cancelado'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                        <span class="size-1.5 rounded-full bg-rose-500"></span>
                        Cancelado
                    </span>
                {/if}
            </div>

            <!-- Monto Total y Botón -->
            <div class="flex items-center gap-4 shrink-0">
                <div class="text-right min-w-[100px]">
                    <span class="text-xs text-muted-foreground block">Costo Total:</span>
                    <span class="font-bold text-base font-sans tabular-nums text-foreground">
                        {Number(order.total_cost) > 0 ? formatPrice(order.total_cost) : '-'}
                    </span>
                </div>

                <Button size="sm" variant="tertiary" class="min-h-[40px] px-3.5 text-xs gap-1.5 font-semibold rounded-xl">
                    <Eye class="size-3.5" />
                    <span>Ver detalle</span>
                </Button>



            </div>
        </div>
    {/each}

    {#if orders.length === 0}
        <div class="rounded-xl border border-dashed p-12 text-center bg-card/50">
            <Package class="mx-auto size-12 text-muted-foreground/50 mb-3" />
            <h3 class="font-semibold text-lg text-foreground">No se encontraron órdenes de compra</h3>
            <p class="text-sm text-muted-foreground mt-1 max-w-sm mx-auto">
                No hay pedidos a proveedores registrados con los filtros seleccionados.
            </p>
        </div>
    {/if}

    {#if orders.length > perPage}
        <AppPagination count={orders.length} {perPage} bind:page />
    {/if}
</div>
