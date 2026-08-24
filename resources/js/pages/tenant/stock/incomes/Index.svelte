<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
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
</script>

<AppHead title="Ingresos de Stock - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Sub-Navegación de Pestañas de Stock y Productos -->
    <StockNavigationTabs activeTab="incomes" />

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">Ingresos de Stock</h1>
            <p class="mt-1.5 text-sm text-muted-foreground sm:text-base max-w-2xl">
                Historial de entradas y abastecimiento de mercadería a tu inventario.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <Button
                onclick={() => (isCreateModalOpen = true)}
                class="flex items-center gap-2 font-semibold shadow-sm bg-emerald-600 hover:bg-emerald-700 text-white"
            >
                <Plus class="size-4" />
                <span>Registrar Ingreso</span>
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
