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
            minimumFractionDigits: 0,
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

<div class="flex flex-col gap-4 w-full">
    <!-- KPIs Mobile -->
    <div class="grid grid-cols-2 gap-2.5">
        <Card class="p-3.5 bg-card border-border/70 shadow-xs">
            <span class="text-xs font-semibold text-muted-foreground">Proveedores</span>
            <div class="text-xl font-bold tabular-nums text-foreground mt-0.5">
                {suppliersData.kpis.total_suppliers}
            </div>
        </Card>

        <Card class="p-3.5 bg-card border-border/70 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-muted-foreground">Mes Actual</span>
                {#if canViewMargins}
                    <div class="text-xl font-bold tabular-nums text-foreground mt-0.5">
                        {formatMoney(suppliersData.kpis.expenses_current_month)}
                    </div>
                {:else}
                    <div class="text-xs text-muted-foreground italic mt-0.5">Restringido</div>
                {/if}
            </div>
            {#if canViewMargins}
                <div class="size-6 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-600 dark:text-rose-400">
                    <TrendingDown class="size-3.5" />
                </div>
            {/if}
        </Card>

        <Card class="p-3.5 bg-card border-border/70 shadow-xs col-span-2 flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-muted-foreground">Gastos Totales (Período)</span>
                {#if canViewMargins}
                    <div class="text-xl font-bold tabular-nums text-foreground mt-0.5">
                        {formatMoney(suppliersData.kpis.total_expenses)}
                    </div>
                {:else}
                    <div class="text-xs text-muted-foreground italic mt-0.5">Restringido</div>
                {/if}
            </div>
            {#if canViewMargins}
                <div class="size-7 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-600 dark:text-rose-400">
                    <TrendingDown class="size-4" />
                </div>
            {/if}
        </Card>
    </div>

    <!-- Proveedores más comprados Mobile -->
    <Card class="p-4 bg-card border-border/70 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-semibold text-foreground">Proveedores Más Comprados</h3>
            <select
                value={suppliersSort}
                onchange={(e) => updateSort((e.target as HTMLSelectElement).value)}
                class="min-h-[34px] px-2 py-0.5 text-xs rounded-md border border-input/80 bg-select-background text-foreground"
            >
                <option value="desc">Más</option>
                <option value="asc">Menos</option>
            </select>
        </div>

        {#if suppliersData.suppliers.data.length === 0}
            <div class="py-6 text-center text-xs text-muted-foreground">Sin registros de compras.</div>
        {:else}
            <div class="flex flex-col divide-y divide-border/40">
                {#each suppliersData.suppliers.data as s, i (s.supplier_id)}
                    {@const rank = (suppliersData.suppliers.current_page - 1) * suppliersData.suppliers.per_page + i + 1}
                    <div class="py-2.5 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="size-5 rounded-full bg-muted text-muted-foreground text-[10px] flex items-center justify-center font-bold shrink-0">
                                {rank}
                            </span>
                            <div class="min-w-0">
                                <span class="font-medium text-foreground truncate block max-w-[150px]">{s.name}</span>
                                <span class="text-muted-foreground">{s.orders_count} órdenes</span>
                            </div>
                        </div>
                        <span class="font-bold text-foreground tabular-nums text-sm shrink-0 pl-2">
                            {canViewMargins ? formatMoney(s.total_spent) : '-'}
                        </span>
                    </div>
                {/each}
            </div>

            {#if suppliersData.suppliers.last_page > 1}
                <div class="flex items-center justify-between pt-3 mt-2 border-t border-border/40 text-xs">
                    <span class="text-muted-foreground">Pág. {suppliersData.suppliers.current_page}/{suppliersData.suppliers.last_page}</span>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            disabled={suppliersData.suppliers.current_page <= 1}
                            onclick={() => changePage(suppliersData.suppliers.current_page - 1)}
                            class="p-1 rounded border border-input/70 bg-background disabled:opacity-40"
                        >
                            <ChevronLeft class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            disabled={suppliersData.suppliers.current_page >= suppliersData.suppliers.last_page}
                            onclick={() => changePage(suppliersData.suppliers.current_page + 1)}
                            class="p-1 rounded border border-input/70 bg-background disabled:opacity-40"
                        >
                            <ChevronRight class="size-3.5" />
                        </button>
                    </div>
                </div>
            {/if}
        {/if}
    </Card>
</div>
