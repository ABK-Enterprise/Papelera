<script lang="ts">
    import { Card } from '@/components/ui/card';
    import { router } from '@inertiajs/svelte';
    import { ChevronLeft, ChevronRight, ChevronDown, TrendingDown, ShoppingBag, Phone, MapPin } from 'lucide-svelte';

    interface Props {
        customersData: {
            kpis: {
                total_customers: number;
                defaulter_customers: number;
            };
            top_buyers: Array<{
                customer_id: number;
                name: string;
                tax_id?: string | null;
                phone?: string | null;
                tax_condition?: string;
                is_defaulter?: boolean;
                address?: string | null;
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

    function formatTaxCondition(cond?: string) {
        switch (cond) {
            case 'responsable_inscripto':
                return 'Resp. Inscripto';
            case 'monotributo':
                return 'Monotributo';
            case 'exento':
                return 'Exento IVA';
            default:
                return 'Consumidor Final';
        }
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
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-lg font-semibold tracking-tight text-foreground">Top 10 Clientes más compradores</h3>
                
                <!-- Selector de ordenamiento estandarizado con ChevronDown -->
                <div class="relative w-full sm:w-[210px] shrink-0">
                    <select
                        value={buyersSort}
                        onchange={(e) => updateSort('buyers_sort', (e.target as HTMLSelectElement).value)}
                        aria-label="Ordenar clientes compradores"
                        class="w-full min-h-[40px] px-3.5 py-1.5 text-sm rounded-lg border border-input/80 bg-background dark:bg-card text-foreground shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer appearance-none pr-8 truncate font-medium"
                    >
                        <option value="desc" class="bg-card text-foreground">Más compradores</option>
                        <option value="asc" class="bg-card text-foreground">Menos compradores</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-muted-foreground">
                        <ChevronDown class="size-4 opacity-50" />
                    </div>
                </div>
            </div>

            {#if customersData.top_buyers.length === 0}
                <Card class="p-8 text-center text-sm text-muted-foreground">
                    No se registran clientes compradores en este período.
                </Card>
            {:else}
                <div class="flex flex-col gap-2.5">
                    {#each customersData.top_buyers as b, i (b.customer_id)}
                        <div class="bento-card group flex items-center justify-between gap-4 rounded-xl border border-border/70 bg-card p-4.5 shadow-xs transition-all duration-200 hover:border-primary/40 hover:shadow-md">
                            <!-- Lado Izquierdo: Ranking + Info Principal -->
                            <div class="flex items-center gap-3.5 min-w-0 flex-1">
                                <span class="size-8 rounded-full bg-muted/80 text-foreground text-xs flex items-center justify-center font-bold shrink-0 border border-border/60 tabular-nums">
                                    #{i + 1}
                                </span>

                                <div class="flex flex-col gap-1.5 min-w-0 flex-1">
                                    <!-- Fila 1: Nombre + Badges -->
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="font-bold text-base text-foreground group-hover:text-primary transition-colors truncate" title={b.name}>
                                            {b.name}
                                        </h4>

                                        {#if b.is_defaulter}
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 border border-rose-200/60 shadow-2xs">
                                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                                Moroso
                                            </span>
                                        {/if}

                                        {#if b.tax_condition}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-muted text-muted-foreground border border-border/50">
                                                {formatTaxCondition(b.tax_condition)}
                                            </span>
                                        {/if}
                                    </div>

                                    <!-- Fila 2: Badges CUIT, Teléfono y Cantidad de Pedidos -->
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary border border-primary/20 tabular-nums">
                                            <ShoppingBag class="size-3.5" />
                                            <strong>{b.orders_count}</strong> pedidos
                                        </span>

                                        {#if b.tax_id}
                                            <span class="inline-flex items-center gap-1 rounded-md bg-muted/60 px-2 py-0.5 text-xs font-medium text-foreground/90 border border-border/40 tabular-nums">
                                                CUIT: {b.tax_id}
                                            </span>
                                        {/if}

                                        {#if b.phone}
                                            <span class="inline-flex items-center gap-1 rounded-md bg-muted/60 px-2 py-0.5 text-xs font-medium text-foreground/90 border border-border/40 tabular-nums">
                                                <Phone class="size-3 text-muted-foreground" />
                                                {b.phone}
                                            </span>
                                        {/if}
                                    </div>

                                    <!-- Fila 3: Dirección si existe -->
                                    {#if b.address}
                                        <div class="flex items-center gap-1 text-xs text-muted-foreground truncate max-w-md">
                                            <MapPin class="size-3 shrink-0 text-muted-foreground/70" />
                                            <span class="truncate">{b.address}</span>
                                        </div>
                                    {/if}
                                </div>
                            </div>

                            <!-- Lado Derecho: Ingresos Totales Generados -->
                            <div class="flex flex-col items-end text-right shrink-0 pl-3">
                                <span class="text-xs text-muted-foreground font-medium uppercase tracking-wider">Ingresos Totales</span>
                                <span class="text-lg sm:text-xl font-bold tabular-nums text-foreground">{formatMoney(b.total_spent)}</span>
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
        </div>

        <!-- Columna Derecha: Clientes Morosos (Tabla Paginada de a 10) -->
        <Card class="p-6 bg-card border-border/70 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-lg font-semibold tracking-tight text-foreground">Clientes Morosos</h3>
                        <p class="text-sm text-muted-foreground leading-normal">Cartera con saldo deudor pendiente</p>
                    </div>

                    <!-- Selector de ordenamiento estandarizado con ChevronDown -->
                    <div class="relative w-full sm:w-[210px] shrink-0">
                        <select
                            value={defaultersSort}
                            onchange={(e) => updateSort('defaulters_sort', (e.target as HTMLSelectElement).value)}
                            aria-label="Ordenar clientes morosos"
                            class="w-full min-h-[40px] px-3.5 py-1.5 text-sm rounded-lg border border-input/80 bg-background dark:bg-card text-foreground shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer appearance-none pr-8 truncate font-medium"
                        >
                            <option value="desc" class="bg-card text-foreground">Más deudor</option>
                            <option value="asc" class="bg-card text-foreground">Menos deudor</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-muted-foreground">
                            <ChevronDown class="size-4 opacity-50" />
                        </div>
                    </div>
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
                                    <th class="text-right py-2.5 px-3">Saldo Deudor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/40">
                                {#each customersData.defaulters.data as d (d.id)}
                                    <tr class="hover:bg-muted/40 transition-colors">
                                        <td class="py-3 px-3 font-medium text-foreground">
                                            {d.name}
                                        </td>
                                        <td class="py-3 px-3 text-muted-foreground tabular-nums">
                                            {d.phone || '—'}
                                        </td>
                                        <td class="py-3 px-3 text-right font-bold tabular-nums text-rose-600 dark:text-rose-400">
                                            {formatMoney(d.current_balance)}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}
            </div>

            <!-- Paginación de a 10 -->
            {#if customersData.defaulters.last_page > 1}
                <div class="flex items-center justify-between pt-4 mt-4 border-t border-border/50 text-sm">
                    <span class="text-xs text-muted-foreground">
                        Página {customersData.defaulters.current_page} de {customersData.defaulters.last_page} ({customersData.defaulters.total} morosos)
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
