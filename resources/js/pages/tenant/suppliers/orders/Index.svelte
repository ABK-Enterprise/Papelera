<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Link, router } from '@inertiajs/svelte';
    import Filter from 'lucide-svelte/icons/filter';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Truck from 'lucide-svelte/icons/truck';
    import Users from 'lucide-svelte/icons/users';
    import X from 'lucide-svelte/icons/x';
    import CreateSupplierOrderModal from './CreateSupplierOrderModal.svelte';
    import SupplierOrderDetailModal from './SupplierOrderDetailModal.svelte';
    import SupplierOrderListDesktop from './desktop/SupplierOrderListDesktop.svelte';
    import SupplierOrderListMobile from './mobile/SupplierOrderListMobile.svelte';

    interface SupplierOrderItem {
        id: number;
        product_id: number;
        product_name: string;
        quantity: number;
        unit_cost: string | number;
        subtotal: string | number;
        product?: {
            id: number;
            name: string;
            sku: string | null;
            stock: number;
        };
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
            email: string | null;
        };
        items?: SupplierOrderItem[];
    }

    interface SupplierOption {
        id: number;
        name: string;
        contact_number?: string | null;
    }

    interface ProductOption {
        id: number;
        name: string;
        sku?: string | null;
        cost_price?: string | number | null;
        stock?: number;
    }

    interface Stats {
        total: number;
        solicitado: number;
        recibido: number;
        cancelado: number;
    }

    interface FilterProps {
        search?: string | null;
        status?: string | null;
    }

    let {
        orders = [],
        stats = { total: 0, solicitado: 0, recibido: 0, cancelado: 0 },
        suppliers = [],
        availableProducts = [],
        filters = {},
    }: {
        orders: SupplierOrderData[];
        stats?: Stats;
        suppliers?: SupplierOption[];
        availableProducts?: ProductOption[];
        filters?: FilterProps;
    } = $props();

    let searchTerm = $state(filters.search ?? '');
    let selectedStatus = $state(filters.status ?? 'all');
    let isCreateModalOpen = $state(false);
    let isDetailModalOpen = $state(false);
    let selectedOrder = $state<SupplierOrderData | null>(null);

    function applyFilters() {
        router.get(
            '/tenant/suppliers/orders',
            {
                search: searchTerm || undefined,
                status: selectedStatus !== 'all' ? selectedStatus : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }

    function resetFilters() {
        searchTerm = '';
        selectedStatus = 'all';
        router.get('/tenant/suppliers/orders');
    }

    function openOrderDetail(order: SupplierOrderData) {
        selectedOrder = order;
        isDetailModalOpen = true;
    }

    $effect(() => {
        if (selectedOrder && orders.length > 0) {
            const fresh = orders.find((o) => o.id === selectedOrder?.id);
            if (fresh) {
                selectedOrder = fresh;
            }
        }
    });

    let hasActiveFilters = $derived(Boolean(searchTerm || (selectedStatus && selectedStatus !== 'all')));
</script>

<AppHead title="Órdenes de Compra a Proveedores - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Pestañas de Navegación del Módulo Proveedores -->
    <div class="flex items-center gap-2 border-b pb-3">
        <Link href="/tenant/suppliers">
            <Button variant="ghost" size="sm" class="gap-2 text-xs font-semibold text-muted-foreground hover:text-foreground">
                <Users class="size-4" />
                Directorio de Proveedores
            </Button>
        </Link>
        <Link href="/tenant/suppliers/orders">
            <Button variant="secondary" size="sm" class="gap-2 text-xs font-bold text-primary bg-primary/10 shadow-2xs">
                <Truck class="size-4" />
                Órdenes de Compra ({stats.total})
            </Button>
        </Link>
    </div>

    <!-- Header de la Página -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">
                Órdenes de Compra a Proveedores
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Genera pedidos de reaprovisionamiento y recibe mercadería automáticamente en Kardex.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <Button
                onclick={() => (isCreateModalOpen = true)}
                class="gap-2 shadow-sm font-semibold"
            >
                <Plus class="size-4" />
                Hacer Pedido a Proveedor
            </Button>
        </div>
    </div>

    <!-- Bento Card: Buscador y Filtros -->
    <Card class="rounded-xl p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] border border-border/60">
        <div class="flex flex-col md:flex-row items-center justify-between gap-3">
            <div class="relative w-full md:w-80">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <input
                    type="text"
                    bind:value={searchTerm}
                    onkeydown={(e) => e.key === 'Enter' && applyFilters()}
                    placeholder="Buscar por #orden o proveedor..."
                    class="h-10 w-full rounded-lg border border-input bg-muted/20 pl-9 pr-4 text-sm placeholder:text-muted-foreground focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary transition-all"
                />
            </div>

            <div class="flex items-center gap-2.5 w-full md:w-auto flex-wrap">
                <select
                    bind:value={selectedStatus}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[160px]"
                >
                    <option value="all">Todos los estados ({stats.total})</option>
                    <option value="solicitado">Solicitado ({stats.solicitado})</option>
                    <option value="recibido">Recibido ({stats.recibido})</option>
                    <option value="cancelado">Cancelado ({stats.cancelado})</option>
                </select>

                <Button variant="secondary" size="sm" class="h-10 px-3 text-xs gap-1.5" onclick={applyFilters}>
                    <Filter class="size-3.5" />
                    Filtrar
                </Button>

                {#if hasActiveFilters}
                    <Button variant="ghost" size="sm" class="h-10 px-2 text-xs text-muted-foreground hover:text-foreground gap-1" onclick={resetFilters}>
                        <X class="size-3.5" />
                        Limpiar
                    </Button>
                {/if}
            </div>
        </div>
    </Card>

    <!-- Lista Desktop -->
    <div class="hidden md:block">
        <SupplierOrderListDesktop {orders} onSelectOrder={openOrderDetail} />
    </div>

    <!-- Lista Mobile -->
    <div class="block md:hidden">
        <SupplierOrderListMobile {orders} onSelectOrder={openOrderDetail} />
    </div>
</div>

<!-- Modal de Creación de Orden a Proveedor -->
<CreateSupplierOrderModal
    bind:open={isCreateModalOpen}
    {suppliers}
    {availableProducts}
/>

<!-- Modal de Detalle / Recepción de Orden -->
<SupplierOrderDetailModal
    bind:open={isDetailModalOpen}
    bind:order={selectedOrder}
/>
