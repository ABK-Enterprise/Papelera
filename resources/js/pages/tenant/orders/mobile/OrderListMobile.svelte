<script lang="ts">
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Eye from 'lucide-svelte/icons/eye';
    import Package from 'lucide-svelte/icons/package';
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
        total_amount: string | number;
        notes: string | null;
        created_at: string;
        items?: OrderItem[];
    }

    let {
        orders = [],
        onSelectOrder,
    }: {
        orders: OrderData[];
        onSelectOrder: (order: OrderData) => void;
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
            class="flex flex-col min-h-[56px] gap-3 rounded-2xl border border-border/70 bg-card p-4 shadow-2xs transition-all active:scale-[0.99] cursor-pointer select-none"
            onclick={() => onSelectOrder(order)}
            onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelectOrder(order)}
            role="button"
            tabindex="0"
        >
            <!-- Header Card Mobile -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary font-bold shrink-0">
                        <ShoppingBag class="h-5 w-5 stroke-[1.75]" />
                    </div>
                    <div>
                        <span class="font-semibold text-sm text-foreground tabular-nums">{order.order_number}</span>
                        <span class="text-xs text-muted-foreground flex items-center gap-1 tabular-nums">
                            <Calendar class="h-3 w-3" />
                            {formatDate(order.created_at)}
                        </span>
                    </div>
                </div>

                <!-- Estado -->
                <div>
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
                </div>
            </div>

            <!-- Cliente y Artículos -->
            <div class="flex items-center justify-between text-xs pt-1.5 border-t border-border/40">
                <span class="font-medium text-foreground flex items-center gap-1 truncate max-w-[180px]">
                    <User class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                    {order.customer_name}
                </span>
                <span class="text-muted-foreground tabular-nums text-xs">
                    {order.items?.length ?? 0} {order.items?.length === 1 ? 'ítem' : 'ítems'}
                </span>
            </div>

            <!-- Footer: Total & Botón con Touch Target >= 44px -->
            <div class="flex items-center justify-between pt-1">
                <span class="font-bold text-base text-primary tabular-nums">
                    {formatPrice(order.total_amount)}
                </span>
                <Button size="sm" variant="outline" class="min-h-[44px] px-3.5 text-xs gap-1.5 font-medium rounded-xl border-border/70">
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
            description="No hay pedidos registrados con los filtros seleccionados."
        />
    {/if}

    {#if orders.length > perPage}
        <AppPagination count={orders.length} {perPage} bind:page />
    {/if}
</div>
