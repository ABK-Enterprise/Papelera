<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { router, page } from '@inertiajs/svelte';
    import { echo } from '@/lib/echo';
    import CreateIncomeModal from '@/pages/tenant/stock/incomes/CreateIncomeModal.svelte';
    import IncomeListDesktop from '@/pages/tenant/stock/incomes/desktop/IncomeListDesktop.svelte';
    import IncomeListMobile from '@/pages/tenant/stock/incomes/mobile/IncomeListMobile.svelte';
    import StockNavigationTabs from '@/pages/tenant/stock/StockNavigationTabs.svelte';
    import { ModuleHeader } from '@/components/common';
    import type { PaginationMeta } from '@/components/common/AppPagination.svelte';
    import Plus from 'lucide-svelte/icons/plus';

    interface WarehouseItem {
        id: number;
        name: string;
        code?: string | null;
        is_default?: boolean;
    }

    interface StockMovementItem {
        id: number;
        type: string;
        reason: string;
        quantity: number;
        previous_stock: number;
        current_stock: number;
        notes: string | null;
        created_at: string;
        warehouse?: {
            id: number;
            name: string;
            code: string | null;
        } | null;
        product?: {
            id: number;
            name: string;
            sku: string | null;
            barcode: string | null;
        } | null;
        user?: {
            id: number;
            name: string;
        } | null;
    }

    interface ProductItem {
        id: number;
        name: string;
        sku: string | null;
        stock: number;
    }

    let {
        movements = [],
        products = [],
        warehouses = [],
    }: {
        movements: (PaginationMeta & { data: StockMovementItem[] }) | StockMovementItem[];
        products: ProductItem[];
        warehouses?: WarehouseItem[];
    } = $props();

    let movementItems = $derived(Array.isArray(movements) ? movements : (movements?.data ?? []));
    let movementPagination = $derived(Array.isArray(movements) ? null : movements);

    let isCreateModalOpen = $state(false);
    let hasLoadedProducts = $state(false);
    let isLoadingProducts = $state(false);

    $effect(() => {
        if (isCreateModalOpen && !hasLoadedProducts && products.length === 0 && !isLoadingProducts) {
            isLoadingProducts = true;
            router.reload({
                only: ['products'],
                onFinish: () => {
                    hasLoadedProducts = true;
                    isLoadingProducts = false;
                },
            });
        }
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
    let tenantId = $derived(
        Number(page.props.auth?.tenant?.id ?? page.props.auth?.user?.tenant_id ?? 1)
    );

    // Sincronización en tiempo real vía Laravel Reverb
    $effect(() => {
        const channelName = `tenant.${tenantId}.stock`;
        echo.private(channelName)
            .listen('.StockMovementRecorded', () => {
                router.reload({ only: ['movements'], preserveScroll: true, preserveState: true });
            });

        return () => {
            echo.leave(channelName);
        };
    });
</script>

<AppHead title="Ingresos de Stock - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1460px] mx-auto w-full">
    <!-- Sub-Navegación de Pestañas de Stock y Header -->
    <ModuleHeader title="Ingresos de Stock">
        {#snippet tabs()}
            <StockNavigationTabs activeTab="incomes" />
        {/snippet}

        {#snippet actions()}
            {#if page.props.auth?.is_admin || page.props.auth?.roles?.includes('admin') || page.props.auth?.permissions?.includes('stock_ingreso.crear')}
                <Button
                    onclick={() => (isCreateModalOpen = true)}
                    class="flex items-center gap-2 font-semibold shadow-sm bg-emerald-700 hover:bg-emerald-800 text-white sm:hover:shadow-lg sm:hover:shadow-emerald-700/25 cursor-pointer"
                >
                    <Plus class="size-4" />
                    <span>Nuevo ingreso</span>
                </Button>
            {/if}
        {/snippet}
    </ModuleHeader>

    <!-- Desglose Responsive Desktop & Mobile -->
    <div class="hidden md:block">
        <IncomeListDesktop movements={movementItems} pagination={movementPagination} />
    </div>
    <div class="block md:hidden">
        <IncomeListMobile movements={movementItems} pagination={movementPagination} />
    </div>
</div>

<!-- Modal para Registrar Ingreso -->
<CreateIncomeModal
    bind:open={isCreateModalOpen}
    {products}
    {warehouses}
/>
