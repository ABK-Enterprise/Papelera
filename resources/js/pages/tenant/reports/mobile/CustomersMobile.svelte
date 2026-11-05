<script lang="ts">
    import { Card } from '@/components/ui/card';
    import { router } from '@inertiajs/svelte';
    import { ChevronLeft, ChevronRight, TrendingDown } from 'lucide-svelte';

    interface Props {
        customersData: {
            kpis: {
                total_customers: number;
                defaulter_customers: number;
            };
            top_buyers: Array<{
                customer_id: number;
                name: string;
                orders_count: number;
                total_spent: number;
            }>;
            defaulters: {
                data: Array<{
                    id: number;
                    name: string;
                    phone: string | null;
                    current_balance: number;
                }>;
                current_page: number;
                last_page: number;
                total: number;
                per_page: number;
            };
        };
        filters?: {
            buyers_sort?: string;
            defaulters_sort?: string;
            defaulters_page?: number;
        };
    }

    let { customersData, filters = {} }: Props = $props();

    function formatMoney(val: number): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 0,
        }).format(val);
    }

    let buyersSort = $state(filters.buyers_sort || 'desc');
    let defaultersSort = $state(filters.defaulters_sort || 'desc');

    function updateSort(key: 'buyers_sort' | 'defaulters_sort', val: string) {
        if (key === 'buyers_sort') buyersSort = val;
        if (key === 'defaulters_sort') defaultersSort = val;

        const url = new URL(window.location.href);
        url.searchParams.set(key, val);
        router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    function changeDefaultersPage(newPage: number) {
        const url = new URL(window.location.href);
        url.searchParams.set('defaulters_page', String(newPage));
        router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: true });
    }
</script>

<div class="flex flex-col gap-4 w-full">
    <!-- KPIs Mobile -->
    <div class="grid grid-cols-2 gap-2.5">
        <Card class="p-3.5 bg-card border-border/70 shadow-xs">
            <span class="text-xs font-semibold text-muted-foreground">Total Clientes</span>
            <div class="text-xl font-bold tabular-nums text-foreground mt-0.5">
                {customersData.kpis.total_customers}
            </div>
        </Card>
        <Card class="p-3.5 bg-card border-border/70 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-muted-foreground">Morosos</span>
                <div class="text-xl font-bold tabular-nums {customersData.kpis.defaulter_customers > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'} mt-0.5">
                    {customersData.kpis.defaulter_customers}
                </div>
            </div>
            {#if customersData.kpis.defaulter_customers > 0}
                <div class="size-6 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-600 dark:text-rose-400">
                    <TrendingDown class="size-3.5" />
                </div>
            {/if}
        </Card>
    </div>

    <!-- Top Compradores Mobile -->
    <Card class="p-4 bg-card border-border/70 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-semibold text-foreground">Top Compradores</h3>
            <select
                value={buyersSort}
                onchange={(e) => updateSort('buyers_sort', (e.target as HTMLSelectElement).value)}
                class="min-h-[34px] px-2 py-0.5 text-xs rounded-md border border-input/80 bg-select-background text-foreground"
            >
                <option value="desc">Más</option>
                <option value="asc">Menos</option>
            </select>
        </div>

        {#if customersData.top_buyers.length === 0}
            <div class="py-6 text-center text-xs text-muted-foreground">Sin registros.</div>
        {:else}
            <div class="flex flex-col divide-y divide-border/40">
                {#each customersData.top_buyers as b, i (b.customer_id)}
                    <div class="py-2.5 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="size-5 rounded-full bg-muted text-muted-foreground text-[10px] flex items-center justify-center font-bold shrink-0">
                                {i + 1}
                            </span>
                            <div class="min-w-0">
                                <span class="font-medium text-foreground truncate block max-w-[150px]">{b.name}</span>
                                <span class="text-muted-foreground">{b.orders_count} pedidos</span>
                            </div>
                        </div>
                        <span class="font-bold text-foreground tabular-nums text-sm shrink-0 pl-2">
                            {formatMoney(b.total_spent)}
                        </span>
                    </div>
                {/each}
            </div>
        {/if}
    </Card>

    <!-- Clientes Morosos Mobile -->
    <Card class="p-4 bg-card border-border/70 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-semibold text-foreground">Clientes Morosos</h3>
            <select
                value={defaultersSort}
                onchange={(e) => updateSort('defaulters_sort', (e.target as HTMLSelectElement).value)}
                class="min-h-[34px] px-2 py-0.5 text-xs rounded-md border border-input/80 bg-select-background text-foreground"
            >
                <option value="desc">Mayor deuda</option>
                <option value="asc">Menor deuda</option>
            </select>
        </div>

        {#if customersData.defaulters.data.length === 0}
            <div class="py-6 text-center text-xs text-muted-foreground">Sin clientes morosos.</div>
        {:else}
            <div class="flex flex-col divide-y divide-border/40">
                {#each customersData.defaulters.data as d (d.id)}
                    <div class="py-2.5 flex items-center justify-between text-xs">
                        <div class="min-w-0">
                            <span class="font-medium text-foreground truncate block max-w-[160px]">{d.name}</span>
                            <span class="text-muted-foreground">{d.phone || 'Sin tel.'}</span>
                        </div>
                        <span class="font-bold text-rose-600 dark:text-rose-400 tabular-nums text-sm shrink-0 pl-2">
                            {formatMoney(d.current_balance)}
                        </span>
                    </div>
                {/each}
            </div>

            {#if customersData.defaulters.last_page > 1}
                <div class="flex items-center justify-between pt-3 mt-2 border-t border-border/40 text-xs">
                    <span class="text-muted-foreground">Pág. {customersData.defaulters.current_page}/{customersData.defaulters.last_page}</span>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            disabled={customersData.defaulters.current_page <= 1}
                            onclick={() => changeDefaultersPage(customersData.defaulters.current_page - 1)}
                            class="p-1 rounded border border-input/70 bg-background disabled:opacity-40"
                        >
                            <ChevronLeft class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            disabled={customersData.defaulters.current_page >= customersData.defaulters.last_page}
                            onclick={() => changeDefaultersPage(customersData.defaulters.current_page + 1)}
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
