<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import PlaceholderPattern from '@/components/PlaceholderPattern.svelte';
    import LowStockAlertsDesktop from './dashboard/desktop/LowStockAlertsDesktop.svelte';
    import LowStockAlertsMobile from './dashboard/mobile/LowStockAlertsMobile.svelte';
    import { page } from '@inertiajs/svelte';

    interface CategorySummary {
        id: number;
        name: string;
    }

    interface LowStockProduct {
        id: number;
        name: string;
        sku?: string | null;
        barcode?: string | null;
        stock: number;
        min_stock: number;
        image_url?: string | null;
        category?: CategorySummary | null;
    }

    let {
        lowStockProducts = [],
    }: {
        lowStockProducts?: LowStockProduct[];
    } = $props();

    let auth = $derived(page.props.auth);
    let hasInventoryModule = $derived(
        Boolean(auth?.modules?.includes('inventario_stock') || auth?.tenant)
    );
</script>

<AppHead title="Dashboard" />

<div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Tarjetas Superiores / Métricas -->
    <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <div
            class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
        <div
            class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
        <div
            class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>

    <!-- Sección de Alertas de Stock Mínimo (Responsive Desktop & Mobile) -->
    {#if hasInventoryModule}
        <div class="hidden md:block">
            <LowStockAlertsDesktop products={lowStockProducts} />
        </div>
        <div class="block md:hidden">
            <LowStockAlertsMobile products={lowStockProducts} />
        </div>
    {:else}
        <div
            class="relative min-h-[300px] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    {/if}
</div>
