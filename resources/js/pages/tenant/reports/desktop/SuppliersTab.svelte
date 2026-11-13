<script lang="ts">
    import { Card } from '@/components/ui/card';
    import { router } from '@inertiajs/svelte';
    import { ChevronLeft, ChevronRight, TrendingDown, Truck, Phone, Mail, MapPin } from 'lucide-svelte';

    interface Props {
        suppliersData: {
            kpis: {
                total_suppliers: number;
                expenses_current_month: number;
                total_expenses: number;
            };
            suppliers: {
                data: Array<{
                    supplier_id: number;
                    name: string;
                    tax_id?: string | null;
                    contact_number?: string | null;
                    email?: string | null;
                    address?: string | null;
                    orders_count: number;
                    total_spent: number;
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

    let {
        suppliersData,
        filters = {},
        canViewMargins = true,
    }: Props = $props();

    function formatMoney(val: number): string {
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
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
                    <div class="size-7 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-700 dark:text-rose-400">
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
                    <div class="size-7 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-700 dark:text-rose-400">
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
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold tracking-tight text-foreground">Proveedores más comprados</h3>
                <p class="text-sm text-muted-foreground leading-normal">
                    Ordenado por cantidad de órdenes emitidas en el período
                </p>
            </div>

            <!-- Selector de ordenamiento estandarizado -->
            <select
                value={suppliersSort}
                onchange={(e) => updateSort((e.target as HTMLSelectElement).value)}
                aria-label="Ordenar proveedores"
                class="w-full sm:w-[210px] min-h-[40px] px-3.5 py-1.5 text-sm rounded-lg border border-input bg-field text-foreground shadow-xs transition-colors focus:border-primary focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer truncate font-medium shrink-0"
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
                    <div class="bento-card group flex items-center justify-between gap-4 rounded-xl border border-border/80 dark:border-border/90 bg-card p-4.5 shadow-xs transition-all duration-200 hover:border-primary/40 hover:bg-muted/40 dark:hover:bg-secondary/35 hover:shadow-md">
                        <!-- Lado Izquierdo: Ranking + Info Principal -->
                        <div class="flex items-center gap-3.5 min-w-0 flex-1">
                            <span class="size-8 rounded-full bg-muted/40 dark:bg-secondary/35 text-foreground text-xs flex items-center justify-center font-bold shrink-0 border border-border/80 dark:border-border/90 tabular-nums">
                                #{rank}
                            </span>

                            <div class="flex flex-col gap-1.5 min-w-0 flex-1">
                                <!-- Fila 1: Nombre Destacado -->
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="font-bold text-base text-foreground group-hover:text-primary transition-colors truncate" title={s.name}>
                                        {s.name}
                                    </h4>
                                </div>

                                <!-- Fila 2: Badges CUIT, Contacto y Órdenes -->
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary border border-primary/20 tabular-nums">
                                        <Truck class="size-3.5" />
                                        <strong>{s.orders_count}</strong> órdenes
                                    </span>

                                    {#if s.tax_id}
                                        <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 dark:bg-secondary/35 px-2 py-0.5 text-xs font-medium text-foreground/90 border border-border/80 dark:border-border/90 tabular-nums">
                                            CUIT: {s.tax_id}
                                        </span>
                                    {/if}

                                    {#if s.contact_number}
                                        <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 dark:bg-secondary/35 px-2 py-0.5 text-xs font-medium text-foreground/90 border border-border/80 dark:border-border/90 tabular-nums">
                                            <Phone class="size-3 text-muted-foreground" />
                                            {s.contact_number}
                                        </span>
                                    {/if}

                                    {#if s.email}
                                        <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 dark:bg-secondary/35 px-2 py-0.5 text-xs font-medium text-foreground/90 border border-border/80 dark:border-border/90">
                                            <Mail class="size-3 text-muted-foreground" />
                                            {s.email}
                                        </span>
                                    {/if}
                                </div>

                                <!-- Fila 3: Dirección si existe -->
                                {#if s.address}
                                    <div class="flex items-center gap-1 text-xs text-muted-foreground truncate max-w-md">
                                        <MapPin class="size-3 shrink-0 text-muted-foreground/70" />
                                        <span class="truncate">{s.address}</span>
                                    </div>
                                {/if}
                            </div>
                        </div>

                        <!-- Lado Derecho: Gasto Total -->
                        <div class="flex flex-col items-end text-right shrink-0 pl-3">
                            <span class="text-xs text-muted-foreground font-medium uppercase tracking-wider">Gasto Total</span>
                            <span class="text-lg sm:text-xl font-bold tabular-nums text-foreground">
                                {canViewMargins ? formatMoney(s.total_spent) : 'Restringido'}
                            </span>
                        </div>
                    </div>
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
                            class="p-1.5 rounded-lg border border-input bg-field text-foreground hover:bg-muted/60 dark:hover:bg-secondary/45 disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
                            title="Página anterior"
                        >
                            <ChevronLeft class="size-4" />
                        </button>
                        <button
                            type="button"
                            disabled={suppliersData.suppliers.current_page >= suppliersData.suppliers.last_page}
                            onclick={() => changePage(suppliersData.suppliers.current_page + 1)}
                            class="p-1.5 rounded-lg border border-input bg-field text-foreground hover:bg-muted/60 dark:hover:bg-secondary/45 disabled:opacity-40 cursor-pointer disabled:cursor-not-allowed"
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
