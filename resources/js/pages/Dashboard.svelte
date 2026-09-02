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
    import { Button } from '@/components/ui/button';
    import { Link, page } from '@inertiajs/svelte';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import Boxes from 'lucide-svelte/icons/boxes';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Package from 'lucide-svelte/icons/package';
    import Plus from 'lucide-svelte/icons/plus';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import LowStockAlertsDesktop from './dashboard/desktop/LowStockAlertsDesktop.svelte';
    import LowStockAlertsMobile from './dashboard/mobile/LowStockAlertsMobile.svelte';

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
        lowStockProducts = null,
    }: {
        lowStockProducts?: LowStockProduct[] | null;
    } = $props();

    let auth = $derived(page.props.auth);
    let user = $derived(auth?.user);
    let tenant = $derived(auth?.tenant);
    let modules = $derived(auth?.modules ?? []);
    let canShowLowStockAlerts = $derived(lowStockProducts !== null && lowStockProducts !== undefined);
</script>

<AppHead title="Dashboard" />

<div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Header Operativo Limpio -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/50">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">
                {#if tenant}
                    Panel de control de {tenant.name}
                {:else if user?.is_superadmin}
                    Panel de control
                {:else}
                    Panel de control
                {/if}
            </h1>
        </div>

        <!-- Accesos Rápidos Operativos -->
        {#if tenant}
            <div class="flex items-center gap-2 flex-wrap">
                {#if modules.includes('pedidos')}
                    <Link href="/tenant/pedidos">
                        <Button class="min-h-[40px] px-4 gap-2 text-sm font-medium rounded-xl shadow-xs">
                            <Plus class="size-4" />
                            <span>Nuevo Pedido</span>
                        </Button>
                    </Link>
                {/if}

                {#if modules.includes('stock')}
                    <Link href="/tenant/stock/ingresos">
                        <Button variant="secondary" class="min-h-[40px] px-4 gap-2 text-sm font-medium rounded-xl">
                            <ArrowDownLeft class="size-4 text-emerald-600 dark:text-emerald-400" />
                            <span>Ingreso de Stock</span>
                        </Button>
                    </Link>
                {/if}
            </div>
        {:else if user?.is_superadmin}
            <div class="flex items-center gap-2">
                <Link href="/platform/tenants">
                    <Button class="min-h-[40px] px-4 gap-2 text-sm font-medium rounded-xl shadow-xs">
                        <Building2 class="size-4" />
                        <span>Gestionar Comercios</span>
                    </Button>
                </Link>
            </div>
        {/if}
    </div>

    <!-- Sección de Alertas de Stock Mínimo (Responsive Desktop & Mobile) -->
    {#if canShowLowStockAlerts}
        <div class="space-y-3">
            <div class="hidden md:block">
                <LowStockAlertsDesktop products={lowStockProducts} />
            </div>
            <div class="block md:hidden">
                <LowStockAlertsMobile products={lowStockProducts} />
            </div>
        </div>
    {/if}
</div>
