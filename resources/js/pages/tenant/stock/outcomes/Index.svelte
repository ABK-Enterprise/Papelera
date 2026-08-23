<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import CreateOutcomeModal from '@/pages/tenant/stock/outcomes/CreateOutcomeModal.svelte';
    import OutcomeListDesktop from '@/pages/tenant/stock/outcomes/desktop/OutcomeListDesktop.svelte';
    import OutcomeListMobile from '@/pages/tenant/stock/outcomes/mobile/OutcomeListMobile.svelte';
    import StockNavigationTabs from '@/pages/tenant/stock/StockNavigationTabs.svelte';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Minus from 'lucide-svelte/icons/minus';

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

    interface Stats {
        total_movements: number;
        total_units: number;
    }

    let {
        movements = [],
        products = [],
        stats = { total_movements: 0, total_units: 0 },
    }: {
        movements: StockMovementItem[];
        products: ProductItem[];
        stats?: Stats;
    } = $props();

    let isCreateModalOpen = $state(false);
</script>

<AppHead title="Egresos de Stock - Luminous" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Sub-Navegación de Pestañas de Stock y Productos -->
    <StockNavigationTabs activeTab="outcomes" />

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">Egresos de Stock</h1>
            <p class="mt-1.5 text-sm text-muted-foreground sm:text-base max-w-2xl">
                Registro de salidas y devoluciones de mercadería con trazabilidad garantizada.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <Button
                onclick={() => (isCreateModalOpen = true)}
                class="flex items-center gap-2 font-semibold shadow-sm bg-rose-600 hover:bg-rose-700 text-white"
            >
                <Minus class="size-4" />
                <span>Registrar Egreso</span>
            </Button>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-border/60 bg-card p-4 shadow-xs">
            <span class="text-xs text-muted-foreground font-medium">Total de Egresos Registrados</span>
            <div class="mt-1 text-2xl font-bold text-foreground">{stats.total_movements}</div>
        </div>
        <div class="rounded-xl border border-border/60 bg-card p-4 shadow-xs">
            <span class="text-xs text-muted-foreground font-medium">Unidades Egresadas</span>
            <div class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">-{stats.total_units}</div>
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
/>
