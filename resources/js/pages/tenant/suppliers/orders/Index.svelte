<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Link, router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Search from 'lucide-svelte/icons/search';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import Truck from 'lucide-svelte/icons/truck';
    import Users from 'lucide-svelte/icons/users';
    import CreateSupplierOrderModal from './CreateSupplierOrderModal.svelte';
    import SupplierOrderDetailModal from './SupplierOrderDetailModal.svelte';
    import SupplierOrderFilterModalMobile from './mobile/SupplierOrderFilterModalMobile.svelte';
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

    import { untrack } from 'svelte';

    let searchTerm = $state('');
    let selectedStatus = $state('all');
    let isCreateModalOpen = $state(false);
    let isDetailModalOpen = $state(false);
    let isFilterModalOpen = $state(false);
    let selectedOrder = $state<SupplierOrderData | null>(null);

    $effect(() => {
        const s = filters?.search ?? '';
        const st = filters?.status ?? 'all';
        untrack(() => {
            searchTerm = s;
            selectedStatus = st;
        });
    });


    function applyFilters() {
        router.get(
            '/tenant/proveedores/ordenes',
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
        router.get('/tenant/proveedores/ordenes');
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
    let activeFiltersCount = $derived(selectedStatus && selectedStatus !== 'all' ? 1 : 0);
</script>

<AppHead title="Órdenes de Compra a Proveedores - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Pestañas de Navegación del Módulo Proveedores -->
    <div class="flex items-center gap-2 border-b pb-3">
        <Link href="/tenant/proveedores">
            <Button variant="ghost" size="sm" class="gap-2 text-xs font-semibold text-muted-foreground hover:text-foreground">
                <Users class="size-4" />
                Directorio de Proveedores
            </Button>
        </Link>
        <Link href="/tenant/proveedores/ordenes">
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
        </div>

        <div class="flex items-center justify-end gap-2.5">
            <Button
                onclick={() => (isCreateModalOpen = true)}
                class="min-h-[40px] px-4 gap-2 rounded-xl text-sm font-semibold shadow-xs"
            >
                <Plus class="size-4" />
                <span>Hacer Pedido a Proveedor</span>
            </Button>
        </div>
    </div>

    <!-- Bento Card: Buscador y Filtros -->
    <Card class="rounded-xl p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] border border-border/60">
        <div class="flex flex-col md:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2 w-full md:w-[448px]">
                <div class="relative flex-1 min-w-0">

                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                    <input
                        type="text"
                        bind:value={searchTerm}
                        onkeydown={(e) => e.key === 'Enter' && applyFilters()}
                        placeholder="Buscar por #orden o proveedor..."
                        class="h-10 w-full rounded-lg border border-input bg-muted/20 pl-9 pr-4 text-sm placeholder:text-muted-foreground focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary transition-all"
                    />
                </div>

                <!-- Disparador de Filtros Mobile -->
                <div class="block md:hidden">
                    <Button
                        type="button"
                        variant={activeFiltersCount > 0 ? 'secondary' : 'outline'}
                        size="icon"
                        onclick={() => (isFilterModalOpen = true)}
                        class="relative h-10 w-10 shrink-0 rounded-xl border border-border/70 shadow-2xs"
                        aria-label="Abrir filtros"
                    >
                        <SlidersHorizontal class="h-4 w-4 text-foreground" />
                        {#if activeFiltersCount > 0}
                            <span class="absolute -top-1 -right-1 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-primary-foreground tabular-nums shadow-xs">
                                {activeFiltersCount}
                            </span>
                        {/if}
                    </Button>
                </div>
            </div>

            <!-- Filtros Desktop -->
            <div class="hidden md:flex items-center gap-2.5 w-auto flex-wrap">
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

                {#if hasActiveFilters}
                    <Button variant="ghost" size="sm" class="h-10 px-2.5 text-xs text-muted-foreground hover:text-foreground hover:bg-muted/50 gap-1.5 rounded-xl transition-all" onclick={resetFilters}>
                        <RotateCcw class="size-3.5" />
                        <span>Limpiar</span>
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

<!-- Modal de Filtros Mobile -->
<SupplierOrderFilterModalMobile
    bind:open={isFilterModalOpen}
    {selectedStatus}
    {stats}
    onapply={(f) => {
        selectedStatus = f.status;
        applyFilters();
    }}
    onreset={resetFilters}
/>
