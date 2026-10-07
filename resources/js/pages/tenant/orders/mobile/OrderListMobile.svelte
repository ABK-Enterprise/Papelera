<script lang="ts">
    import AppPagination from '@/components/common/AppPagination.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Eye from 'lucide-svelte/icons/eye';
    import Package from 'lucide-svelte/icons/package';
    import Pencil from 'lucide-svelte/icons/pencil';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import User from 'lucide-svelte/icons/user';

    interface OrderItem {
        id: number;
        product_id: number;
        packaging_type: string;
        units_per_package: number;
        quantity: number;
        unit_price: string | number;
        subtotal: string | number;
        product?: {
            id: number;
            name: string;
            sku: string | null;
        };
    }

    interface OrderData {
        id: number;
        order_number: string;
        customer_name: string;
        customer_phone: string | null;
        delivery_address: string | null;
        status: string;
        fiscal_status?: string;
        return_status?: string;
        price_review_pending?: boolean;
        total_amount: string | number;
        notes: string | null;
        created_at: string;
        items?: OrderItem[];
    }

    let {
        orders = [],
        onSelectOrder,
        onEditPrices = undefined,
    }: {
        orders: OrderData[];
        onSelectOrder: (order: OrderData) => void;
        onEditPrices?: (order: OrderData) => void;
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

    function canEditPrices(order: OrderData): boolean {
        return !(order.status === 'entregado' || order.status === 'cancelado' || order.fiscal_status === 'facturado');
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
            class="relative flex flex-col min-h-[56px] gap-3 rounded-2xl border bg-card p-4 shadow-2xs transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-accent/25 hover:z-10 has-[[data-state=open]]:z-30 active:scale-[0.99] cursor-pointer select-none {order.price_review_pending ? 'border-amber-500/80 bg-amber-500/5 ring-1 ring-amber-500/30' : 'border-border/70'}"
            onclick={() => onSelectOrder(order)}
            onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelectOrder(order)}
            role="button"
            tabindex="0"
        >
            {#if order.price_review_pending}
                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-800 dark:text-amber-300 text-xs font-semibold">
                    <AlertTriangle class="size-3.5 text-amber-600 shrink-0" />
                    <span>Precios de catálogo desactualizados</span>
                </div>
            {/if}

            <!-- Header Card Mobile (Alineación dinámica y +4px eje-y de padding) -->
            <div class="flex {order.return_status ? 'items-start' : 'items-center'} justify-between py-0.5 gap-2">
                <div class="flex items-center gap-3 min-w-0">
                    <IconContainer size="lg" variant="primary" icon="solar:bill-list-bold-duotone" />
                    <div class="min-w-0">
                        <span class="font-semibold text-sm text-foreground tabular-nums block truncate">{order.order_number}</span>
                        <span class="text-xs text-muted-foreground flex items-center gap-1 tabular-nums mt-0.5">
                            <Calendar class="h-3 w-3 shrink-0" />
                            {formatDate(order.created_at)}
                        </span>
                    </div>
                </div>

                <!-- Estado y Devolución en Stack Vertical -->
                <div class="flex flex-col items-end gap-1 shrink-0">
                    {#if order.status === 'en_preparacion'}
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            En Prep.
                        </span>
                    {:else if order.status === 'preparado'}
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-blue-500/15 text-blue-700 dark:text-blue-400 border border-blue-500/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                            Preparado
                        </span>
                    {:else if order.status === 'en_camino'}
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 border border-indigo-500/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                            En Camino
                        </span>
                    {:else if order.status === 'entregado'}
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Entregado
                        </span>
                    {:else if order.status === 'cancelado'}
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            Cancelado
                        </span>
                    {/if}

                    {#if order.return_status === 'total'}
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                            Devuelto
                        </span>
                    {:else if order.return_status === 'parcial'}
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                            Dev. Parcial
                        </span>
                    {/if}

                    <!-- Badge Fiscal ARCA -->
                    {#if order.fiscal_status === 'facturado'}
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium tracking-wide bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Facturado
                        </span>
                    {:else if order.fiscal_status === 'anulado_fiscal'}
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium tracking-wide bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30 line-through">
                            Anulado
                        </span>
                    {:else if order.fiscal_status === 'error_fiscal'}
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium tracking-wide bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                            Error Fiscal
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium tracking-wide bg-muted text-muted-foreground border border-border/60">
                            Sin Facturar
                        </span>
                    {/if}
                </div>
            </div>

            <!-- Cliente y Artículos -->
            <div class="flex items-center justify-between text-xs pt-1.5 border-t border-border/40">
                <span class="font-medium text-sm text-foreground flex items-center gap-1.5 truncate max-w-[200px]">
                    <User class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                    {order.customer_name}
                </span>
                <span class="text-muted-foreground tabular-nums text-xs">
                    {order.items?.length ?? 0} {order.items?.length === 1 ? 'ítem' : 'ítems'}
                </span>
            </div>

            <!-- Footer: Total & Botones de Acción Táctiles Directos (min-h-[44px] min-w-[44px]) -->
            <div class="flex items-center justify-between pt-1 gap-2" onclick={(e) => e.stopPropagation()}>
                <span class="font-bold text-base text-primary tabular-nums">
                    {formatPrice(order.total_amount)}
                </span>
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        onclick={() => onSelectOrder(order)}
                        class="size-11 min-h-[44px] min-w-[44px] rounded-xl border border-border/80 bg-background/80 shadow-2xs hover:bg-muted hover:border-border text-foreground transition-colors cursor-pointer flex items-center justify-center shrink-0"
                        aria-label="Ver detalle"
                        title="Ver detalle"
                    >
                        <Eye class="size-4.5 text-muted-foreground" />
                    </button>

                    <button
                        type="button"
                        disabled={!canEditPrices(order)}
                        onclick={() => onEditPrices?.(order)}
                        class="size-11 min-h-[44px] min-w-[44px] rounded-xl border border-border/80 bg-background/80 shadow-2xs hover:bg-muted hover:border-border text-foreground transition-colors flex items-center justify-center shrink-0 {!canEditPrices(order) ? 'opacity-50 cursor-not-allowed pointer-events-none' : 'cursor-pointer'}"
                        aria-label="Editar precios de productos"
                        title={!canEditPrices(order) ? 'No se pueden editar precios en pedidos entregados, cancelados o facturados' : 'Editar precios de productos'}
                    >
                        <Pencil class="size-4.5 text-muted-foreground" />
                    </button>
                </div>
            </div>
        </div>
    {/each}

    {#if orders.length === 0}
        <EmptyState
            icon={Package}
            title="No se encontraron pedidos"
            description="No hay pedidos registrados con los filtros seleccionados."
        />
    {/if}

    {#if orders.length > perPage}
        <AppPagination count={orders.length} {perPage} bind:page />
    {/if}
</div>
