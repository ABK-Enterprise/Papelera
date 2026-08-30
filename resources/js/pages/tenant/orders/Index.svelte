<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { router } from '@inertiajs/svelte';
    import Filter from 'lucide-svelte/icons/filter';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import X from 'lucide-svelte/icons/x';
    import SortSelect from '@/components/common/SortSelect.svelte';
    import CreateOrderModal from './CreateOrderModal.svelte';
    import OrderDetailModal from './OrderDetailModal.svelte';
    import OrderListDesktop from './desktop/OrderListDesktop.svelte';
    import OrderListMobile from './mobile/OrderListMobile.svelte';

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

    interface ProductOption {
        id: number;
        name: string;
        sku: string | null;
        price: string | number;
        stock: number;
        units_per_package: number;
    }

    interface Stats {
        total: number;
        en_preparacion: number;
        preparado: number;
        en_camino: number;
        entregado: number;
        cancelado: number;
    }

    interface FilterProps {
        search?: string | null;
        status?: string | null;
        sort_by?: string | null;
    }

    let {
        orders = [],
        stats = { total: 0, en_preparacion: 0, preparado: 0, en_camino: 0, entregado: 0, cancelado: 0 },
        availableProducts = [],
        availableCustomers = [],
        filters = {},
    }: {
        orders: OrderData[];
        stats?: Stats;
        availableProducts?: ProductOption[];
        availableCustomers?: any[];
        filters?: FilterProps;
    } = $props();

    let searchTerm = $state(filters.search ?? '');
    let selectedStatus = $state(filters.status ?? 'all');
    let sortBy = $state(filters.sort_by ?? 'latest');
    let isCreateModalOpen = $state(false);
    let isDetailModalOpen = $state(false);
    let selectedOrder = $state<OrderData | null>(null);

    const sortOptions = [
        { value: 'latest', label: 'Más recientes primero' },
        { value: 'oldest', label: 'Más antiguos primero' },
        { value: 'amount_high', label: 'Mayor monto total' },
        { value: 'amount_low', label: 'Menor monto total' },
        { value: 'order_number_asc', label: 'Nº de pedido (Asc)' },
        { value: 'order_number_desc', label: 'Nº de pedido (Desc)' },
    ];

    function applyFilters() {
        router.get(
            '/tenant/orders',
            {
                search: searchTerm || undefined,
                status: selectedStatus !== 'all' ? selectedStatus : undefined,
                sort_by: sortBy !== 'latest' ? sortBy : undefined,
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
        sortBy = 'latest';
        router.get('/tenant/orders', {}, { preserveState: true, preserveScroll: true });
    }

    function openOrderDetail(order: OrderData) {
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

    let hasActiveFilters = $derived(Boolean(searchTerm || (selectedStatus && selectedStatus !== 'all') || (sortBy && sortBy !== 'latest')));
</script>

<AppHead title="Gestión de Pedidos - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Header de la Página -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <ShoppingBag class="size-7 text-primary" />
                Gestión de Pedidos de Clientes
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Control de estados de preparación, envíos y seguimiento logístico con validación de stock.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <Button
                onclick={() => (isCreateModalOpen = true)}
                class="gap-2 shadow-sm font-medium"
            >
                <Plus class="size-4" />
                <span>Nuevo Pedido</span>
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
                    placeholder="Buscar por #pedido, cliente o teléfono..."
                    class="h-10 w-full rounded-lg border border-input bg-muted/20 pl-9 pr-4 text-sm placeholder:text-muted-foreground focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary transition-all"
                />
            </div>

            <div class="flex items-center gap-2.5 w-full md:w-auto flex-wrap">
                <!-- Selector de Ordenamiento -->
                <SortSelect
                    value={sortBy}
                    options={sortOptions}
                    onchange={(val) => {
                        sortBy = val;
                        applyFilters();
                    }}
                />

                <!-- Filtro por Estado -->
                <select
                    bind:value={selectedStatus}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[160px]"
                >
                    <option value="all">Todos los estados ({stats.total})</option>
                    <option value="en_preparacion">En Preparación ({stats.en_preparacion})</option>
                    <option value="preparado">Preparado ({stats.preparado})</option>
                    <option value="en_camino">En Camino ({stats.en_camino})</option>
                    <option value="entregado">Entregado ({stats.entregado})</option>
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
        <OrderListDesktop {orders} onSelectOrder={openOrderDetail} />
    </div>

    <!-- Lista Mobile -->
    <div class="block md:hidden">
        <OrderListMobile {orders} onSelectOrder={openOrderDetail} />
    </div>
</div>

<!-- Modal de Creación de Pedido -->
<CreateOrderModal
    bind:open={isCreateModalOpen}
    {availableProducts}
    {availableCustomers}
/>

<!-- Modal de Detalle de Pedido -->
<OrderDetailModal
    bind:open={isDetailModalOpen}
    bind:order={selectedOrder}
/>
