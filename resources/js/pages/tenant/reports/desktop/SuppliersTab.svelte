<script lang="ts">
    import { Card } from '@/components/ui/card';
    import { router } from '@inertiajs/svelte';
    import { ChevronLeft, ChevronRight, TrendingDown } from 'lucide-svelte';

    interface Props {
        suppliersData: {
            kpis: {
                total_suppliers: number;
                expenses_current_month: number | null;
                total_expenses: number | null;
            };
            suppliers: {
                data: Array<{
                    supplier_id: number;
                    name: string;
                    orders_count: number;
                    total_spent: number | null;
                }>;
                current_page: number;
                last_page: number;
                total: number;
                per_page: number;
            };
        };
        filters?: {
            suppliers_sort?: string;
            suppliers_page?: number;
        };
        canViewMargins?: boolean;
    }

    let { suppliersData, filters = {}, canViewMargins = true }: Props = $props();

    function formatMoney(val: number | null | undefined): string {
        if (val === null || val === undefined) return '-';
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(val);
    }

    let suppliersSort = $state(filters.suppliers_sort || 'desc');

    function updateSort(val: string) {
        suppliersSort = val;
        const url = new URL(window.location.href);
        url.searchParams.set('suppliers_sort', val);
        router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    function changePage(newPage: number) {
        const url = new URL(window.location.href);
        url.searchParams.set('suppliers_page', String(newPage));
        router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: true });
    }
</script>

<div class="flex flex-col gap-6 w-full">
    <!-- 1. KPIs Proveedores -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Total de Proveedores -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <span class="text-base font-semibold tracking-normal text-muted-foreground">Total de Proveedores</span>
            <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                {suppliersData.kpis.total_suppliers}
            </div>
        </Card>

        <!-- Gastos (Mes Actual) -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-base font-semibold tracking-normal text-muted-foreground">Gastos (Mes Actual)</span>
                {#if canViewMargins}
                    <div class="size-7 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-600 dark:text-rose-400">
                        <TrendingDown class="size-4" />
                    </div>
                {/if}
            </div>
            <div>
                {#if canViewMargins}
                    <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                        {formatMoney(suppliersData.kpis.expenses_current_month)}
                    </div>
                {:else}
                    <div class="text-sm text-muted-foreground italic">Restringido</div>
                {/if}
            </div>
        </Card>

        <!-- Gastos Totales -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-base font-semibold tracking-normal text-muted-foreground">Gastos Totales</span>
                {#if canViewMargins}
                    <div class="size-7 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-600 dark:text-rose-400">
                        <TrendingDown class="size-4" />
                    </div>
                {/if}
            </div>
            <div>
                {#if canViewMargins}
                    <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                        {formatMoney(suppliersData.kpis.total_expenses)}
                    </div>
                {:else}
                    <div class="text-sm text-muted-foreground italic">Restringido</div>
                {/if}
            </div>
        </Card>
    </div>

    <!-- 2. Listado Paginado Wide-Cards: Proveedores más comprados -->
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold tracking-tight text-foreground">Proveedores más comprados</h3>
                <p class="text-sm text-muted-foreground leading-normal">
                    Ordenado por cantidad de órdenes emitidas en el período
                </p>
            </div>

            <!-- Selector de Ordenamiento estricto por Cant. Órdenes -->
            <select
                value={suppliersSort}
                onchange={(e) => updateSort((e.target as HTMLSelectElement).value)}
                class="min-h-[36px] px-3 py-1 text-sm rounded-lg border border-input/80 bg-background text-foreground shadow-xs cursor-pointer"
            >
                <option value="desc">Más comprado</option>
                <option value="asc">Menos comprado</option>
            </select>
        </div>

        {#if suppliersData.suppliers.data.length === 0}
            <Card class="p-12 text-center text-sm text-muted-foreground">
                No hay órdenes de compra hacia proveedores registradas en este período.
            </Card>
        {:else}
            <div class="flex flex-col gap-2.5">
                {#each suppliersData.suppliers.data as s, i (s.supplier_id)}
                    {@const rank = (suppliersData.suppliers.current_page - 1) * suppliersData.suppliers.per_page + i + 1}
                    <Card class="p-4.5 flex items-center justify-between bg-card border-border/70 shadow-xs hover:border-border transition-colors">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <span class="size-7 rounded-full bg-muted text-muted-foreground text-xs flex items-center justify-center font-bold shrink-0">
                                {rank}
                            </span>
                            <div class="min-w-0">
                                <div class="text-[17px] font-medium leading-snug text-foreground truncate max-w-[320px]" title={s.name}>
                                    {s.name}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {s.orders_count} órdenes registradas
                                </div>
                            </div>
                        </div>

                        <div class="text-right shrink-0 pl-3">
                            <div class="text-xs text-muted-foreground font-medium">Gasto Total</div>
                            <div class="text-lg font-bold tabular-nums text-foreground">
                                {canViewMargins ? formatMoney(s.total_spent) : 'Restringido'}
                            </div>
                        </div>
                    </Card>
                {/each}
            </div>

            <!-- Paginación de a 10 -->
            {#if suppliersData.suppliers.last_page > 1}
                <div class="flex items-center justify-between pt-4 mt-2 border-t border-border/50 text-sm">
                    <span class="text-xs text-muted-foreground">
                        Página {suppliersData.suppliers.current_page} de {suppliersData.suppliers.last_page} ({suppliersData.suppliers.total} proveedores)
                    </span>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            disabled={suppliersData.suppliers.current_page <= 1}
                            onclick={() => changePage(suppliersData.suppliers.current_page - 1)}
                            class="p-1.5 rounded-lg border border-input/70 bg-background text-foreground hover:bg-muted disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
                            title="Página anterior"
                        >
                            <ChevronLeft class="size-4" />
                        </button>
                        <button
                            type="button"
                            disabled={suppliersData.suppliers.current_page >= suppliersData.suppliers.last_page}
                            onclick={() => changePage(suppliersData.suppliers.current_page + 1)}
                            class="p-1.5 rounded-lg border border-input/70 bg-background text-foreground hover:bg-muted disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
                            title="Página siguiente"
                        >
                            <ChevronRight class="size-4" />
                        </button>
                    </div>
                </div>
            {/if}
        {/if}
    </div>
</div>
