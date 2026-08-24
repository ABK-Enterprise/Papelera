<script lang="ts">
    import { Button } from '@/components/ui/button';
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
    {#each orders as order (order.id)}
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

                <!-- Estado -->
                {#if order.status === 'en_preparacion'}
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/15 text-amber-600">
                        En Preparación
                    </span>
                {:else if order.status === 'preparado'}
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-500/15 text-blue-600">
                        Preparado
                    </span>
                {:else if order.status === 'en_camino'}
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/15 text-indigo-600">
                        En Camino
                    </span>
                {:else if order.status === 'entregado'}
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/15 text-emerald-600">
                        Entregado
                    </span>
                {:else if order.status === 'cancelado'}
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/15 text-rose-600">
                        Cancelado
                    </span>
                {/if}
            </div>

            <div class="flex items-center justify-between text-xs pt-1 border-t">
                <span class="font-semibold text-foreground truncate flex items-center gap-1">
                    <User class="size-3 text-muted-foreground" />
                    {order.customer_name}
                </span>
                <span class="font-mono font-bold text-sm text-foreground">
                    {formatPrice(order.total_amount)}
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
            <p class="text-xs text-muted-foreground">No se encontraron pedidos.</p>
        </div>
    {/if}
</div>
