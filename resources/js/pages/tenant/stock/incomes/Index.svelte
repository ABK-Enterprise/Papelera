<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { router, page } from '@inertiajs/svelte';
    import { echo } from '@/lib/echo';
    import CreateIncomeModal from '@/pages/tenant/stock/incomes/CreateIncomeModal.svelte';
    import IncomeListDesktop from '@/pages/tenant/stock/incomes/desktop/IncomeListDesktop.svelte';
    import IncomeListMobile from '@/pages/tenant/stock/incomes/mobile/IncomeListMobile.svelte';
    import StockNavigationTabs from '@/pages/tenant/stock/StockNavigationTabs.svelte';
    import Plus from 'lucide-svelte/icons/plus';

    interface StockMovementItem {
        id: number;
        type: string;
        reason: string;
        quantity: number;
        previous_stock: number;
        current_stock: number;
        notes: string | null;
        created_at: string;
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
    }: {
        movements: StockMovementItem[];
        products: ProductItem[];
    } = $props();

    let isCreateModalOpen = $state(false);

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

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Sub-Navegación de Pestañas de Stock y Productos -->
    <StockNavigationTabs activeTab="incomes" />

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight leading-tight text-foreground">Ingresos de Stock</h1>
        </div>
        <div class="flex items-center gap-3">
            <Button
                onclick={() => (isCreateModalOpen = true)}
                class="flex items-center gap-2 font-semibold shadow-sm bg-emerald-600 hover:bg-emerald-700 text-white sm:hover:shadow-lg sm:hover:shadow-emerald-600/25 cursor-pointer"
            >
                <Plus class="size-4" />
                <span>Registrar ingreso</span>
            </Button>
        </div>
    </div>

    <!-- Desglose Responsive Desktop & Mobile -->
    <div class="hidden md:block">
        <IncomeListDesktop {movements} />
    </div>
    <div class="block md:hidden">
        <IncomeListMobile {movements} />
    </div>
</div>

<!-- Modal para Registrar Ingreso -->
<CreateIncomeModal
    bind:open={isCreateModalOpen}
    {products}
/>
