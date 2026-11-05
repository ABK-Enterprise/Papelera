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
            minimumFractionDigits: 2,
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

<div class="flex flex-col gap-6 w-full">
    <!-- 1. KPIs Clientes -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Total de Clientes -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <span class="text-base font-semibold tracking-normal text-muted-foreground">Total de Clientes</span>
            <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                {customersData.kpis.total_customers}
            </div>
        </Card>

        <!-- Clientes Morosos -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-base font-semibold tracking-normal text-muted-foreground">Clientes Morosos</span>
                {#if customersData.kpis.defaulter_customers > 0}
                    <div class="size-7 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-600 dark:text-rose-400">
                        <TrendingDown class="size-4" />
                    </div>
                {/if}
            </div>
            <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight {customersData.kpis.defaulter_customers > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'}">
                {customersData.kpis.defaulter_customers}
            </div>
        </Card>
    </div>

    <!-- 2. Cuerpo Bicolumna -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        <!-- Columna Izquierda: Top 10 Clientes compradores (Wide-Cards) -->
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold tracking-tight text-foreground">Top 10 Clientes más compradores</h3>
                <!-- Selector de ordenamiento -->
                <select
                    value={buyersSort}
                    onchange={(e) => updateSort('buyers_sort', (e.target as HTMLSelectElement).value)}
                    class="min-h-[36px] px-3 py-1 text-sm rounded-lg border border-input/80 bg-background text-foreground shadow-xs cursor-pointer"
                >
                    <option value="desc">Más compradores</option>
                    <option value="asc">Menos compradores</option>
                </select>
            </div>

            {#if customersData.top_buyers.length === 0}
                <Card class="p-8 text-center text-sm text-muted-foreground">
                    No se registran clientes compradores en este período.
                </Card>
            {:else}
                <div class="flex flex-col gap-2.5">
                    {#each customersData.top_buyers as b, i (b.customer_id)}
                        <Card class="p-4 flex items-center justify-between bg-card border-border/70 shadow-xs hover:border-border transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="size-7 rounded-full bg-muted text-muted-foreground text-xs flex items-center justify-center font-bold shrink-0">
                                    {i + 1}
                                </span>
                                <div class="min-w-0">
                                    <div class="text-[17px] font-medium leading-snug text-foreground truncate max-w-[240px]" title={b.name}>
                                        {b.name}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {b.orders_count} pedidos registrados
                                    </div>
                                </div>
                            </div>
                            <div class="text-right shrink-0 pl-3">
                                <div class="text-lg font-bold tabular-nums text-foreground">
                                    {formatMoney(b.total_spent)}
                                </div>
                            </div>
                        </Card>
                    {/each}
                </div>
            {/if}
        </div>

        <!-- Columna Derecha: Clientes Morosos (Tabla Paginada de a 10) -->
        <Card class="p-6 bg-card border-border/70 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold tracking-tight text-foreground">Clientes Morosos</h3>
                        <p class="text-sm text-muted-foreground leading-normal">Cartera con saldo deudor pendiente</p>
                    </div>
                    <!-- Selector de ordenamiento -->
                    <select
                        value={defaultersSort}
                        onchange={(e) => updateSort('defaulters_sort', (e.target as HTMLSelectElement).value)}
                        class="min-h-[36px] px-3 py-1 text-sm rounded-lg border border-input/80 bg-background text-foreground shadow-xs cursor-pointer"
                    >
                        <option value="desc">Más deudor</option>
                        <option value="asc">Menos deudor</option>
                    </select>
                </div>

                {#if customersData.defaulters.data.length === 0}
                    <div class="py-12 text-center text-sm text-muted-foreground">
                        No hay clientes con saldo deudor en cuenta corriente.
                    </div>
                {:else}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border/60 text-muted-foreground text-xs uppercase tracking-wider font-semibold">
                                    <th class="text-left py-2.5 px-3">Cliente</th>
                                    <th class="text-left py-2.5 px-3">Teléfono</th>
                                    <th class="text-right py-2.5 px-3">Monto Deudor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/40">
                                {#each customersData.defaulters.data as d (d.id)}
                                    <tr class="hover:bg-muted/40 transition-colors">
                                        <td class="py-3 px-3 font-medium text-foreground truncate max-w-[200px]" title={d.name}>
                                            {d.name}
                                        </td>
                                        <td class="py-3 px-3 text-muted-foreground">{d.phone || '-'}</td>
                                        <td class="py-3 px-3 text-right font-bold text-rose-600 dark:text-rose-400 tabular-nums">
                                            {formatMoney(d.current_balance)}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}
            </div>

            <!-- Paginación de Clientes Morosos -->
            {#if customersData.defaulters.last_page > 1}
                <div class="flex items-center justify-between pt-4 mt-4 border-t border-border/50 text-sm">
                    <span class="text-xs text-muted-foreground">
                        Página {customersData.defaulters.current_page} de {customersData.defaulters.last_page} ({customersData.defaulters.total} registros)
                    </span>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            disabled={customersData.defaulters.current_page <= 1}
                            onclick={() => changeDefaultersPage(customersData.defaulters.current_page - 1)}
                            class="p-1.5 rounded-lg border border-input/70 bg-background text-foreground hover:bg-muted disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
                            title="Página anterior"
                        >
                            <ChevronLeft class="size-4" />
                        </button>
                        <button
                            type="button"
                            disabled={customersData.defaulters.current_page >= customersData.defaulters.last_page}
                            onclick={() => changeDefaultersPage(customersData.defaulters.current_page + 1)}
                            class="p-1.5 rounded-lg border border-input/70 bg-background text-foreground hover:bg-muted disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
                            title="Página siguiente"
                        >
                            <ChevronRight class="size-4" />
                        </button>
                    </div>
                </div>
            {/if}
        </Card>
    </div>
</div>
