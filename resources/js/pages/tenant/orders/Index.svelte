<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { router, page } from '@inertiajs/svelte';
    import { echo } from '@/lib/echo';
    import Filter from 'lucide-svelte/icons/filter';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import { ClearFilterButton, ModuleHeader } from '@/components/common';
    import { type PaginationMeta } from '@/components/common/AppPagination.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import SortSelect from '@/components/common/SortSelect.svelte';
    import { hasPermission } from '@/lib/permissions';
    import CreateOrderModal from './CreateOrderModal.svelte';
    import OrderDetailModal from './OrderDetailModal.svelte';
    import OrderFilterModalMobile from './mobile/OrderFilterModalMobile.svelte';
    import OrderListDesktop from './desktop/OrderListDesktop.svelte';
    import OrderListMobile from './mobile/OrderListMobile.svelte';
    import OrderProductsPriceEditModal from './components/OrderProductsPriceEditModal.svelte';
    import ChooseOrderEditActionModal from './components/ChooseOrderEditActionModal.svelte';
    import AddOrderProductsModal from './components/AddOrderProductsModal.svelte';
    import CollectOrderModal from './components/CollectOrderModal.svelte';

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
        fiscal_error_message?: string | null;
        active_fiscal_invoice?: any;
        customer?: any;
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
        warehouses = [],
        shippingZones = [],
        filters = {},
    }: {
        orders: (PaginationMeta & { data: OrderData[] }) | OrderData[];
        stats?: Stats;
        availableProducts?: ProductOption[];
        availableCustomers?: any[];
        warehouses?: any[];
        shippingZones?: any[];
        filters?: FilterProps;
    } = $props();

    let orderItems = $derived(Array.isArray(orders) ? orders : (orders?.data ?? []));
    let orderPagination = $derived(Array.isArray(orders) ? null : orders);

    let searchTerm = $state('');
    let selectedStatus = $state('all');
    let sortBy = $state('latest');
    let isCreateModalOpen = $state(false);
    let isDetailModalOpen = $state(false);
    let isFilterModalOpen = $state(false);
    let selectedOrder = $state<OrderData | null>(null);
    let isPriceEditModalOpen = $state(false);
    let editingPriceOrder = $state<OrderData | null>(null);
    let isChooseEditActionOpen = $state(false);
    let isAddProductsModalOpen = $state(false);
    let orderToModify = $state<OrderData | null>(null);
    let isCollectModalOpen = $state(false);
    let collectingOrder = $state<OrderData | null>(null);
    let hasLoadedCatalog = $state(false);
    let isLoadingCatalog = $state(false);

    $effect(() => {
        if ((isCreateModalOpen || isAddProductsModalOpen) && !hasLoadedCatalog && !isLoadingCatalog) {
            isLoadingCatalog = true;
            router.reload({
                only: ['availableProducts', 'availableCustomers'],
                onFinish: () => {
                    hasLoadedCatalog = true;
                    isLoadingCatalog = false;
                },
            });
        }
    });

    function openOrderPriceEdit(order: OrderData) {
        editingPriceOrder = order;
        isPriceEditModalOpen = true;
    }

    function openOrderEditActions(order: OrderData) {
        orderToModify = order;
        isChooseEditActionOpen = true;
    }

    function openCollectOrder(order: OrderData) {
        collectingOrder = order;
        isCollectModalOpen = true;
    }

    let tenantId = $derived(
        Number(page.props.auth?.tenant?.id ?? page.props.auth?.user?.tenant_id ?? 1)
    );

    // Sincronización en tiempo real vía Laravel Reverb
    $effect(() => {
        const channelName = `tenant.${tenantId}.orders`;
        echo.private(channelName)
            .listen('.CustomerOrderCreated', () => {
                router.reload({ only: ['orders', 'stats'], preserveScroll: true, preserveState: true });
            })
            .listen('.CustomerOrderStatusUpdated', () => {
                router.reload({ only: ['orders', 'stats'], preserveScroll: true, preserveState: true });
            })
            .listen('.OrderRescheduled', () => {
                router.reload({ only: ['orders', 'stats'], preserveScroll: true, preserveState: true });
            })
            .listen('.CustomerOrderPaymentReceived', () => {
                router.reload({ only: ['orders', 'stats'], preserveScroll: true, preserveState: true });
            });

        return () => {
            echo.leave(channelName);
        };
    });

    import { untrack } from 'svelte';

    $effect(() => {
        const s = filters?.search ?? '';
        const st = filters?.status ?? 'all';
        const sb = filters?.sort_by ?? 'latest';
        untrack(() => {
            searchTerm = s;
            selectedStatus = st;
            sortBy = sb;
        });
    });

    $effect(() => {
        if (typeof window !== 'undefined') {
            const params = new URLSearchParams(window.location.search);
            if (params.get('crear') === '1') {
                const timer = setTimeout(() => {
                    isCreateModalOpen = true;
                    params.delete('crear');
                    const newUrl = window.location.pathname + (params.toString() ? `?${params.toString()}` : '');
                    window.history.replaceState({}, '', newUrl);
                }, 300);
                return () => clearTimeout(timer);
            }
        }
    });



    function formatPrice(amount: string | number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    }

    function getStatusBadge(status: string) {
        switch (status) {
            case 'en_preparacion':
                return { label: 'En Preparación', class: 'bg-amber-500/10 text-amber-600 border-amber-500/20' };
            case 'preparado':
                return { label: 'Preparado', class: 'bg-blue-500/10 text-blue-600 border-blue-500/20' };
            case 'en_camino':
                return { label: 'En Camino', class: 'bg-purple-500/10 text-purple-600 border-purple-500/20' };
            case 'entregado':
                return { label: 'Entregado', class: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' };
            case 'cancelado':
                return { label: 'Cancelado', class: 'bg-destructive/10 text-destructive border-destructive/20' };
            default:
                return { label: status, class: 'bg-muted text-muted-foreground border-border' };
        }
    }

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
            '/tenant/pedidos',
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
        router.get('/tenant/pedidos', {}, { preserveState: true, preserveScroll: true });
    }


    function openOrderDetail(order: OrderData) {
        selectedOrder = order;
        isDetailModalOpen = true;
    }

    $effect(() => {
        if (selectedOrder && orderItems.length > 0) {
            const fresh = orderItems.find((o) => o.id === selectedOrder?.id);
            if (fresh) {
                selectedOrder = fresh;
            }
        }
    });

    let hasActiveFilters = $derived(Boolean(searchTerm || (selectedStatus && selectedStatus !== 'all') || (sortBy && sortBy !== 'latest')));
    let activeFiltersCount = $derived(
        (selectedStatus && selectedStatus !== 'all' ? 1 : 0) + (sortBy && sortBy !== 'latest' ? 1 : 0)
    );
</script>

<AppHead title="Gestión de Pedidos - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Header de la Página -->
    <ModuleHeader title="Pedidos de Clientes">
        {#snippet actions()}
            {#if hasPermission('pedidos.crear')}
                <Button
                    onclick={() => (isCreateModalOpen = true)}
                    class="min-h-[40px] px-4 gap-2 rounded-xl text-sm font-medium shadow-xs"
                    data-test="create-order-button"
                >
                    <Plus class="size-4" />
                    <span>Nuevo Pedido</span>
                </Button>
            {/if}
        {/snippet}
    </ModuleHeader>

    <!-- Bento Card: Buscador y Filtros -->
    <Card class="rounded-xl p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] border border-border/60">
        <div class="flex flex-col md:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2 w-full md:w-[448px]">
                <div class="flex-1 min-w-0">

                    <SearchCombobox
                        bind:value={searchTerm}
                        items={orderItems}
                        placeholder="Buscar por #pedido, cliente o teléfono..."
                        onsearch={(q) => {
                            searchTerm = q;
                            applyFilters();
                        }}
                        onselect={openOrderDetail}
                        onclear={resetFilters}
                    >
                        {#snippet itemSnippet(ord)}
                            {@const badge = getStatusBadge(ord.status)}
                            <div class="flex items-center justify-between gap-3 w-full min-w-0">
                                <div class="flex flex-col min-w-0">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="font-bold text-foreground tabular-nums">#{ord.order_number}</span>
                                        <span class="truncate text-muted-foreground">· {ord.customer_name}</span>
                                    </div>
                                    <span class="text-xs text-muted-foreground/80">{ord.created_at}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold border {badge.class}">
                                        {badge.label}
                                    </span>
                                    <span class="tabular-nums font-semibold text-foreground text-xs">{formatPrice(ord.total_amount)}</span>
                                </div>
                            </div>
                        {/snippet}
                    </SearchCombobox>
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
                            <span class="absolute -top-1 -right-1 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-primary px-1 text-xs font-bold text-primary-foreground tabular-nums shadow-xs">
                                {activeFiltersCount}
                            </span>
                        {/if}
                    </Button>
                </div>
            </div>

            <!-- Barra de Filtros Desktop -->
            <div class="hidden md:flex items-center gap-2.5 w-auto flex-wrap">
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
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-sm font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[160px]"
                >
                    <option value="all">Todos los estados ({stats.total})</option>
                    <option value="en_preparacion">En Preparación ({stats.en_preparacion})</option>
                    <option value="preparado">Preparado ({stats.preparado})</option>
                    <option value="en_camino">En Camino ({stats.en_camino})</option>
                    <option value="entregado">Entregado ({stats.entregado})</option>
                    <option value="cancelado">Cancelado ({stats.cancelado})</option>
                </select>

                <ClearFilterButton show={hasActiveFilters} onclick={resetFilters} />
            </div>
        </div>
    </Card>

    <!-- Lista Desktop -->
    <div class="hidden md:block">
        <OrderListDesktop orders={orderItems} pagination={orderPagination} onSelectOrder={openOrderDetail} onEditPrices={openOrderEditActions} onCollectOrder={openCollectOrder} />
    </div>

    <!-- Lista Mobile -->
    <div class="block md:hidden">
        <OrderListMobile orders={orderItems} pagination={orderPagination} onSelectOrder={openOrderDetail} onEditPrices={openOrderEditActions} onCollectOrder={openCollectOrder} />
    </div>
</div>

<!-- Modal Unificado de Cobro de Pedido -->
<CollectOrderModal
    bind:open={isCollectModalOpen}
    order={collectingOrder as any}
    onSuccess={(updatedOrder) => {
        if (selectedOrder && collectingOrder && selectedOrder.id === collectingOrder.id) {
            selectedOrder = { ...selectedOrder, ...updatedOrder };
        }
    }}
/>

<!-- Diálogo Intermediario de Selección de Acción de Edición -->
<ChooseOrderEditActionModal
    bind:open={isChooseEditActionOpen}
    order={orderToModify}
    onEditPrices={() => {
        editingPriceOrder = orderToModify;
        isPriceEditModalOpen = true;
    }}
    onAddProducts={() => {
        isAddProductsModalOpen = true;
    }}
/>

<!-- Modal de Adición de Nuevos Productos al Pedido -->
<AddOrderProductsModal
    bind:open={isAddProductsModalOpen}
    order={orderToModify as any}
    {availableProducts}
    onSuccess={(updatedOrder) => {
        if (selectedOrder && selectedOrder.id === updatedOrder.id) {
            selectedOrder = updatedOrder;
        }
    }}
/>

<!-- Modal de Edición de Precios de Productos del Pedido -->
<OrderProductsPriceEditModal
    bind:open={isPriceEditModalOpen}
    order={editingPriceOrder as any}
    onPriceUpdated={(updatedOrder) => {
        if (selectedOrder && selectedOrder.id === updatedOrder.id) {
            selectedOrder = updatedOrder;
        }
    }}
/>

<!-- Modal de Creación de Pedido -->
<CreateOrderModal
    bind:open={isCreateModalOpen}
    {availableProducts}
    {availableCustomers}
    {warehouses}
    {shippingZones}
/>


<!-- Modal de Detalle de Pedido -->
<OrderDetailModal
    bind:open={isDetailModalOpen}
    bind:order={selectedOrder}
/>

<!-- Modal de Filtros para Mobile -->
<OrderFilterModalMobile
    bind:open={isFilterModalOpen}
    {selectedStatus}
    {sortBy}
    {stats}
    onapply={(f) => {
        selectedStatus = f.status;
        sortBy = f.sortBy;
        applyFilters();
    }}
    onreset={resetFilters}
/>
