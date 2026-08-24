<script lang="ts">
    import { Button } from '@/components/ui/button';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Eye from 'lucide-svelte/icons/eye';
    import Package from 'lucide-svelte/icons/package';
    import Phone from 'lucide-svelte/icons/phone';
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
    {#each orders as order (order.id)}
        <div
            class="bento-card group flex flex-col md:flex-row items-start md:items-center justify-between gap-4 rounded-xl border border-border/60 bg-card p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] transition-all hover:border-primary/30 hover:shadow-md cursor-pointer"
            onclick={() => onSelectOrder(order)}
            onkeydown={(e) => e.key === 'Enter' && onSelectOrder(order)}
            role="button"
            tabindex="0"
        >
            <!-- Número de Pedido & Icono -->
            <div class="flex items-center gap-3.5 min-w-[200px]">
                <div class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary font-mono font-bold shrink-0 group-hover:scale-105 transition-transform">
                    <ShoppingBag class="size-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-base font-mono text-foreground">{order.order_number}</span>
                    </div>
                    <div class="flex items-center gap-1 text-xs text-muted-foreground mt-0.5">
                        <Calendar class="size-3" />
                        <span>{formatDate(order.created_at)}</span>
                    </div>
                </div>
            </div>

            <!-- Cliente y Contacto -->
            <div class="flex flex-col min-w-[180px]">
                <span class="font-semibold text-sm text-foreground flex items-center gap-1.5 truncate">
                    <User class="size-3.5 text-muted-foreground" />
                    {order.customer_name}
                </span>
                {#if order.customer_phone}
                    <span class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                        <Phone class="size-3" />
                        {order.customer_phone}
                    </span>
                {/if}
            </div>

            <!-- Resumen de Items -->
            <div class="flex flex-col min-w-[160px]">
                <span class="text-xs text-muted-foreground">Artículos:</span>
                <span class="text-xs font-semibold text-foreground truncate">
                    {order.items?.length ?? 0} {order.items?.length === 1 ? 'producto' : 'productos'}
                </span>
            </div>

            <!-- Estado Logístico -->
            <div class="min-w-[130px]">
                {#if order.status === 'en_preparacion'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-600 border border-amber-500/30">
                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                        En Preparación
                    </span>
                {:else if order.status === 'preparado'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-500/15 text-blue-600 border border-blue-500/30">
                        <span class="size-1.5 rounded-full bg-blue-500"></span>
                        Preparado
                    </span>
                {:else if order.status === 'en_camino'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-500/15 text-indigo-600 border border-indigo-500/30">
                        <span class="size-1.5 rounded-full bg-indigo-500"></span>
                        En Camino
                    </span>
                {:else if order.status === 'entregado'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-600 border border-emerald-500/30">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        Entregado
                    </span>
                {:else if order.status === 'cancelado'}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-600 border border-rose-500/30">
                        <span class="size-1.5 rounded-full bg-rose-500"></span>
                        Cancelado
                    </span>
                {/if}
            </div>

            <!-- Monto Total y Botón -->
            <div class="flex items-center gap-4 shrink-0">
                <div class="text-right min-w-[100px]">
                    <span class="text-xs text-muted-foreground block">Total:</span>
                    <span class="font-bold text-base font-mono text-foreground">
                        {formatPrice(order.total_amount)}
                    </span>
                </div>

                <Button size="sm" variant="outline" class="h-8 text-xs gap-1.5 font-semibold">
                    <Eye class="size-3.5" />
                    <span>Ver</span>
                </Button>
            </div>
        </div>
    {/each}

    {#if orders.length === 0}
        <div class="rounded-xl border border-dashed p-12 text-center bg-card/50">
            <Package class="mx-auto size-12 text-muted-foreground/50 mb-3" />
            <h3 class="font-semibold text-lg text-foreground">No se encontraron pedidos</h3>
            <p class="text-sm text-muted-foreground mt-1 max-w-sm mx-auto">
                No hay pedidos registrados con los filtros seleccionados.
            </p>
        </div>
    {/if}
</div>
