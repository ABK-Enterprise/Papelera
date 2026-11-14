<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { router, page } from '@inertiajs/svelte';
    import { echo } from '@/lib/echo';
    import CreateOutcomeModal from '@/pages/tenant/stock/outcomes/CreateOutcomeModal.svelte';
    import OutcomeListDesktop from '@/pages/tenant/stock/outcomes/desktop/OutcomeListDesktop.svelte';
    import OutcomeListMobile from '@/pages/tenant/stock/outcomes/mobile/OutcomeListMobile.svelte';
    import StockNavigationTabs from '@/pages/tenant/stock/StockNavigationTabs.svelte';
    import { ModuleHeader } from '@/components/common';
    import type { PaginationMeta } from '@/components/common/AppPagination.svelte';
    import Minus from 'lucide-svelte/icons/minus';

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

<AppHead title="Egresos de Stock - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1460px] mx-auto w-full">
    <!-- Sub-Navegación de Pestañas de Stock y Header -->
    <ModuleHeader title="Egresos de Stock">
        {#snippet tabs()}
            <StockNavigationTabs activeTab="outcomes" />
        {/snippet}

        {#snippet actions()}
            {#if page.props.auth?.is_admin || page.props.auth?.roles?.includes('admin') || page.props.auth?.permissions?.includes('stock_egreso.crear')}
                <Button
                    variant="secondary"
                    onclick={() => (isCreateModalOpen = true)}
                    class="flex items-center gap-2 font-semibold shadow-sm border-transparent bg-rose-700 hover:bg-rose-800 dark:hover:bg-rose-800 text-white sm:hover:shadow-lg sm:hover:shadow-rose-700/25 cursor-pointer"
                >
                    <Minus class="size-4" />
                    <span>Nuevo egreso</span>
                </Button>
            {/if}
        {/snippet}
    </ModuleHeader>

    <!-- Desglose Responsive Desktop & Mobile -->
    <div class="hidden md:block">
        <OutcomeListDesktop movements={movementItems} pagination={movementPagination} />
    </div>
    <div class="block md:hidden">
        <OutcomeListMobile movements={movementItems} pagination={movementPagination} />
    </div>
</div>

<!-- Modal para Registrar Egreso -->
<CreateOutcomeModal
    bind:open={isCreateModalOpen}
    {products}
    {warehouses}
/>
