<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { router, page } from '@inertiajs/svelte';
    import { echo } from '@/lib/echo';
    import CreateOutcomeModal from '@/pages/tenant/stock/outcomes/CreateOutcomeModal.svelte';
    import OutcomeListDesktop from '@/pages/tenant/stock/outcomes/desktop/OutcomeListDesktop.svelte';
    import OutcomeListMobile from '@/pages/tenant/stock/outcomes/mobile/OutcomeListMobile.svelte';
    import StockNavigationTabs from '@/pages/tenant/stock/StockNavigationTabs.svelte';
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
        movements: StockMovementItem[];
        products: ProductItem[];
        warehouses?: WarehouseItem[];
    } = $props();

    let isCreateModalOpen = $state(false);

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

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Sub-Navegación de Pestañas de Stock y Productos -->
    <StockNavigationTabs activeTab="outcomes" />

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight leading-tight text-foreground">Egresos de Stock</h1>
        </div>
        <div class="flex items-center gap-3">
            <Button
                onclick={() => (isCreateModalOpen = true)}
                class="flex items-center gap-2 font-semibold shadow-sm bg-rose-600 hover:bg-rose-700 text-white sm:hover:shadow-lg sm:hover:shadow-rose-600/25 cursor-pointer"
            >
                <Minus class="size-4" />
                <span>Registrar egreso</span>
            </Button>
        </div>
    </div>

    <!-- Desglose Responsive Desktop & Mobile -->
    <div class="hidden md:block">
        <OutcomeListDesktop {movements} />
    </div>
    <div class="block md:hidden">
        <OutcomeListMobile {movements} />
    </div>
</div>

<!-- Modal para Registrar Egreso -->
<CreateOutcomeModal
    bind:open={isCreateModalOpen}
    {products}
    {warehouses}
/>
