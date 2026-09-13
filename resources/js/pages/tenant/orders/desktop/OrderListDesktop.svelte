<script lang="ts">
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Eye from 'lucide-svelte/icons/eye';
    import Package from 'lucide-svelte/icons/package';
    import Phone from 'lucide-svelte/icons/phone';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import User from 'lucide-svelte/icons/user';

    export interface OrderItemSummary {
        id: number;
        product_id: number;
        product_name: string;
        quantity: number;
        unit_price: number | string;
        subtotal: number | string;
    }

    export interface OrderSummary {
        id: number;
        order_number: string;
        customer_name: string;
        customer_phone?: string | null;
        customer_email?: string | null;
        status: string;
        fiscal_status?: string | null;
        return_status?: string | null;
        payment_method?: string | null;
        total_amount: number | string;
        created_at: string;
        items?: OrderItemSummary[];
    }

    let {
        orders = [],
        onSelectOrder,
    }: {
        orders: OrderSummary[];
        onSelectOrder: (order: OrderSummary) => void;
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
            class="flex items-center justify-between p-4 rounded-2xl border border-border/70 bg-card shadow-xs hover:scale-105 hover:shadow-lg hover:shadow-accent/25 hover:border-border transition-all duration-200 gap-4 cursor-pointer select-none"

        >
            <!-- Lado Izquierdo: Icono + Número de Pedido y Fecha -->
            <div class="flex items-center gap-3.5 min-w-[200px]">
                <IconContainer size="lg" variant="primary" icon="solar:bill-list-bold-duotone" />

                <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-base text-foreground tabular-nums">{order.order_number}</span>
                    </div>
                    <div class="flex items-center gap-1 text-xs text-muted-foreground mt-0.5 tabular-nums">
                        <Calendar class="h-3 w-3" />
                        <span>{formatDate(order.created_at)}</span>
                    </div>
                </div>
            </div>

            <!-- Cliente y Contacto -->
            <div class="flex flex-col min-w-[180px]">
                <span class="font-semibold text-sm text-foreground flex items-center gap-1.5 truncate">
                    <User class="h-3.5 w-3.5 text-muted-foreground" />
                    {order.customer_name}
                </span>
                {#if order.customer_phone}
                    <span class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5 tabular-nums ">
                        <Phone class="h-3 w-3" />
                        {order.customer_phone}
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

            <!-- Estado Logístico -->
            <div class="min-w-[130px] flex flex-col items-start gap-1">
                {#if order.status === 'en_preparacion'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium tracking-wide bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        En Preparación
                    </span>
                {:else if order.status === 'preparado'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium tracking-wide bg-blue-500/15 text-blue-700 dark:text-blue-400 border border-blue-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                        Preparado
                    </span>
                {:else if order.status === 'en_camino'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium tracking-wide bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 border border-indigo-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                        En Camino
                    </span>
                {:else if order.status === 'entregado'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium tracking-wide bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Entregado
                    </span>
                {:else if order.status === 'cancelado'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium tracking-wide bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                        Cancelado
                    </span>
                {/if}

                {#if order.return_status === 'total'}
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                        Devolución Total
                    </span>
                {:else if order.return_status === 'parcial'}
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                        Devolución Parcial
                    </span>
                {/if}

                <!-- Badge Fiscal ARCA -->
                {#if order.fiscal_status === 'facturado'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Facturado ARCA
                    </span>
                {:else if order.fiscal_status === 'anulado_fiscal'}
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30 line-through">
                        Anulado (NC)
                    </span>
                {:else if order.fiscal_status === 'error_fiscal'}
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                        Error Fiscal
                    </span>
                {:else}
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium tracking-wide bg-muted text-muted-foreground border border-border/60">
                        Sin Facturar
                    </span>
                {/if}
            </div>

            <!-- Monto Total y Botón con Touch Target >= 40px -->
            <div class="flex items-center gap-4 shrink-0">
                <div class="text-right min-w-[125px] shrink-0">
                    <span class="text-xs text-muted-foreground block">Total:</span>
                    <span class="font-bold text-base text-foreground tabular-nums">
                        {formatPrice(order.total_amount)}
                    </span>
                </div>

                <Button size="sm" variant="tertiary" class="min-h-[40px] px-3.5 text-xs gap-1.5 font-medium rounded-xl">
                    <Eye class="h-3.5 w-3.5" />
                    <span>Ver detalle</span>
                </Button>


            </div>
        </div>
    {/each}

    {#if orders.length === 0}
        <EmptyState
            icon={Package}
            title="No se encontraron pedidos"
            description="No hay pedidos registrados con los filtros seleccionados en este momento."
        />
    {/if}

    {#if orders.length > perPage}
        <AppPagination count={orders.length} {perPage} bind:page />
    {/if}
</div>
