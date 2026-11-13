<script lang="ts">
    import { onMount } from 'svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import ChartCanvas from '@/components/charts/ChartCanvas.svelte';
    import Calendar from 'lucide-svelte/icons/calendar';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import TrendingDown from 'lucide-svelte/icons/trending-down';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import Filter from 'lucide-svelte/icons/filter';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import FileDown from 'lucide-svelte/icons/file-down';
    import WarehouseIcon from 'lucide-svelte/icons/warehouse';

    let {
        metrics,
        dailyTrend = [],
        topProducts = [],
        monthlySummary = [],
        warehouses = [],
        filters,
        permissions,
        onApplyFilters,
        onExportPdf,
    }: {
        metrics: any;
        dailyTrend: Array<{ date: string; label: string; sales: number; orders_count: number }>;
        topProducts: Array<{ product_id: number; name: string; sku: string | null; total_quantity: number; total_subtotal: number }>;
        monthlySummary: Array<{ month_key: string; label: string; sales: number; orders_count: number; average_ticket: number; revenues: number; expenses: number | null; operating_margin: number | null }>;
        warehouses: Array<{ id: number; name: string; code: string }>;
        filters: { date_from: string; date_to: string; warehouse_id: number | null; year: number };
        permissions: { can_view_margins: boolean; can_export: boolean };
        onApplyFilters: (newFilters: { date_from: string; date_to: string; warehouse_id: number | null; year: number }) => void;
        onExportPdf: () => void;
    } = $props();

    let localDateFrom = $state(filters.date_from);
    let localDateTo = $state(filters.date_to);
    let localWarehouseId = $state<string>(filters.warehouse_id ? String(filters.warehouse_id) : '');
    let localYear = $state<number>(filters.year || new Date().getFullYear());

    function handleApply() {
        onApplyFilters({
            date_from: localDateFrom,
            date_to: localDateTo,
            warehouse_id: localWarehouseId ? Number(localWarehouseId) : null,
            year: localYear,
        });
    }

    function setPreset(days: number | 'month' | 'year') {
        const today = new Date();
        const toStr = today.toISOString().split('T')[0];
        let fromStr = toStr;

        if (days === 'month') {
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            fromStr = firstDay.toISOString().split('T')[0];
        } else if (days === 'year') {
            const firstDay = new Date(today.getFullYear(), 0, 1);
            fromStr = firstDay.toISOString().split('T')[0];
        } else if (typeof days === 'number') {
            const past = new Date();
            past.setDate(today.getDate() - days);
            fromStr = past.toISOString().split('T')[0];
        }

        localDateFrom = fromStr;
        localDateTo = toStr;
        handleApply();
    }

    function handleReset() {
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        localDateFrom = firstDay.toISOString().split('T')[0];
        localDateTo = today.toISOString().split('T')[0];
        localWarehouseId = '';
        localYear = today.getFullYear();
        handleApply();
    }

    function formatMoney(val: number | null | undefined): string {
        if (val === null || val === undefined) return '-';
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(val);
    }

    // Chart 1: Line Chart Data (Daily Sales Trend)
    let dailySalesChartData = $derived({
        labels: dailyTrend.map((d) => d.label),
        datasets: [
            {
                label: 'Ventas Diarias',
                data: dailyTrend.map((d) => d.sales),
                borderColor: '#2563eb', // blue-600
                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: '#2563eb',
            },
        ],
    });

    let dailySalesChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (context: any) => `Ventas: ${formatMoney(context.parsed.y)}`,
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { family: "'Google Sans', sans-serif", size: 11 } },
            },
            y: {
                grid: { color: 'rgba(148, 163, 184, 0.15)' },
                ticks: {
                    font: { family: "'Google Sans', sans-serif", size: 11 },
                    callback: (val: any) => `$${Number(val).toLocaleString('es-AR')}`,
                },
            },
        },
    };

    let cardBorderColor = $state('#ffffff');

    onMount(() => {
        const updateCardBorderColor = () => {
            const val = getComputedStyle(document.documentElement)
                .getPropertyValue('--card')
                .trim();
            cardBorderColor = val || '#171717';
        };
        updateCardBorderColor();
        const observer = new MutationObserver(updateCardBorderColor);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class', 'data-theme'],
        });
        return () => observer.disconnect();
    });

    // Chart 2: Doughnut Chart Data (Payment Methods)
    let paymentMethodsChartData = $derived({
        labels: (metrics.revenues.by_method || []).map((m: any) => m.label),
        datasets: [
            {
                data: (metrics.revenues.by_method || []).map((m: any) => m.amount),
                backgroundColor: [
                    '#10b981', // emerald
                    '#3b82f6', // blue
                    '#06b6d4', // cyan
                    '#8b5cf6', // violet
                    '#f59e0b', // amber
                    '#64748b', // slate
                ],
                borderWidth: 2,
                borderColor: cardBorderColor,
            },
        ],
    });

    let paymentMethodsChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom' as const,
                labels: {
                    boxWidth: 12,
                    font: { family: "'Google Sans', sans-serif", size: 11 },
                },
            },
            tooltip: {
                callbacks: {
                    label: (context: any) => ` ${context.label}: ${formatMoney(context.parsed)}`,
                },
            },
        },
        cutout: '68%',
    };

    // Chart 3: Bar Chart Data (Monthly Sales vs Expenses vs Margin)
    let monthlyComparisonChartData = $derived({
        labels: monthlySummary.map((m) => m.label),
        datasets: [
            {
                label: 'Ventas Netas',
                data: monthlySummary.map((m) => m.sales),
                backgroundColor: '#3b82f6', // blue-500
                borderRadius: 4,
            },
            ...(permissions.can_view_margins
                ? [
                      {
                          label: 'Gastos en Compras',
                          data: monthlySummary.map((m) => m.expenses ?? 0),
                          backgroundColor: '#f43f5e', // rose-500
                          borderRadius: 4,
                      },
                      {
                          label: 'Margen Operativo',
                          data: monthlySummary.map((m) => m.operating_margin ?? 0),
                          backgroundColor: '#10b981', // emerald-500
                          borderRadius: 4,
                      },
                  ]
                : []),
        ],
    });

    let monthlyComparisonChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top' as const,
                labels: {
                    boxWidth: 12,
                    font: { family: "'Google Sans', sans-serif", size: 12 },
                },
            },
            tooltip: {
                callbacks: {
                    label: (context: any) => ` ${context.dataset.label}: ${formatMoney(context.parsed.y)}`,
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { family: "'Google Sans', sans-serif", size: 11 } },
            },
            y: {
                grid: { color: 'rgba(148, 163, 184, 0.15)' },
                ticks: {
                    font: { family: "'Google Sans', sans-serif", size: 11 },
                    callback: (val: any) => `$${Number(val).toLocaleString('es-AR')}`,
                },
            },
        },
    };
</script>

<div class="space-y-6">
    <!-- Operational Filter Bar -->
    <Card class="p-4 bg-card/60 backdrop-blur-xs border-border/60 shadow-2xs">
        <div class="flex flex-col gap-4">
            <!-- Top Controls Row -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <!-- Preset Buttons Group -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-xs font-medium text-muted-foreground mr-1">Rango rápido:</span>
                    <Button variant="outline" size="sm" onclick={() => setPreset(0)} class="min-h-[36px] text-xs px-2.5 rounded-lg">Hoy</Button>
                    <Button variant="outline" size="sm" onclick={() => setPreset(7)} class="min-h-[36px] text-xs px-2.5 rounded-lg">7 días</Button>
                    <Button variant="outline" size="sm" onclick={() => setPreset(30)} class="min-h-[36px] text-xs px-2.5 rounded-lg">30 días</Button>
                    <Button variant="outline" size="sm" onclick={() => setPreset('month')} class="min-h-[36px] text-xs px-2.5 rounded-lg">Este mes</Button>
                    <Button variant="outline" size="sm" onclick={() => setPreset('year')} class="min-h-[36px] text-xs px-2.5 rounded-lg">Año actual</Button>
                </div>

                <!-- Export PDF Action Button -->
                {#if permissions.can_export}
                    <Button
                        variant="secondary"
                        onclick={onExportPdf}
                        class="min-h-[36px] px-3.5 gap-2 text-xs font-semibold rounded-xl shadow-2xs transition-all ml-auto"
                    >
                        <FileDown class="size-4 text-primary" />
                        <span>Exportar PDF</span>
                    </Button>
                {/if}
            </div>

            <!-- Custom Date Inputs & Warehouse Selection -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 pt-3 border-t border-border/50 items-end">
                <div class="space-y-1.5">
                    <Label class="text-xs font-medium text-muted-foreground flex items-center gap-1">
                        <Calendar class="size-3.5" />
                        <span>Fecha desde</span>
                    </Label>
                    <Input
                        type="date"
                        bind:value={localDateFrom}
                        class="min-h-[38px] text-sm rounded-xl bg-field border-input focus:border-primary focus-visible:border-primary dark:[color-scheme:dark]"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label class="text-xs font-medium text-muted-foreground flex items-center gap-1">
                        <Calendar class="size-3.5" />
                        <span>Fecha hasta</span>
                    </Label>
                    <Input
                        type="date"
                        bind:value={localDateTo}
                        class="min-h-[38px] text-sm rounded-xl bg-field border-input focus:border-primary focus-visible:border-primary dark:[color-scheme:dark]"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label class="text-xs font-medium text-muted-foreground flex items-center gap-1">
                        <WarehouseIcon class="size-3.5" />
                        <span>Sucursal / Depósito</span>
                    </Label>
                    <select
                        bind:value={localWarehouseId}
                        class="w-full min-h-[38px] px-3 py-1.5 text-sm rounded-xl border border-input bg-field text-foreground shadow-2xs focus:outline-none focus:border-primary focus-visible:border-primary focus:ring-2 focus:ring-ring"
                    >
                        <option value="">Consolidado (Todos los depósitos)</option>
                        {#each warehouses as wh}
                            <option value={String(wh.id)}>{wh.name} ({wh.code})</option>
                        {/each}
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        onclick={handleApply}
                        class="flex-1 min-h-[38px] gap-2 text-sm font-medium rounded-xl shadow-2xs"
                    >
                        <Filter class="size-3.5" />
                        <span>Filtrar</span>
                    </Button>

                    <Button
                        variant="outline"
                        onclick={handleReset}
                        title="Restablecer filtros"
                        class="min-h-[38px] px-3 text-muted-foreground hover:text-foreground rounded-xl"
                    >
                        <RotateCcw class="size-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </Card>

    <!-- Executive Metric Cards (Operational Numbers) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Ventas Brutas (Devengadas) -->
        <Card class="p-4 bg-card border-border/60 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Ventas Brutas</span>
                <div class="size-8 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-700 dark:text-blue-400">
                    <ShoppingBag class="size-4" />
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                    {formatMoney(metrics.sales.total)}
                </div>
                <div class="text-xs text-muted-foreground mt-1 tabular-nums">
                    {metrics.sales.orders_count} pedidos · Prom: {formatMoney(metrics.sales.average_ticket)}
                </div>
            </div>
        </Card>

        <!-- Ingresos Percibidos (Cobranzas) -->
        <Card class="p-4 bg-card border-border/60 shadow-2xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Ingresos Cobrados</span>
                <div class="size-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-700 dark:text-emerald-400">
                    <CreditCard class="size-4" />
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                    {formatMoney(metrics.revenues.total)}
                </div>
                <div class="text-xs text-muted-foreground mt-1">
                    Total liquidez percibida en caja
                </div>
            </div>
        </Card>

        {#if permissions.can_view_margins}
            <!-- Gastos en Compras (Costos de reaprovisionamiento) -->
            <Card class="p-4 bg-card border-border/60 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Gastos / Compras</span>
                    <div class="size-8 rounded-lg bg-rose-500/10 flex items-center justify-center text-rose-700 dark:text-rose-400">
                        <TrendingDown class="size-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-bold tracking-tight text-foreground tabular-nums">
                        {formatMoney(metrics.expenses.total)}
                    </div>
                    <div class="text-xs text-muted-foreground mt-1 tabular-nums">
                        {metrics.expenses.purchases_count} compras a proveedores recibidas
                    </div>
                </div>
            </Card>

            <!-- Margen Operativo Bruto -->
            <Card class="p-4 bg-card border-border/60 shadow-2xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Margen Operativo</span>
                    <div class="size-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-700 dark:text-emerald-400">
                        <TrendingUp class="size-4" />
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-bold tracking-tight tabular-nums {metrics.margin.operating_margin >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'}">
                        {formatMoney(metrics.margin.operating_margin)}
                    </div>
                    <div class="text-xs text-muted-foreground mt-1 tabular-nums">
                        {metrics.margin.margin_percentage}% rendimiento sobre ventas
                    </div>
                </div>
            </Card>
        {/if}
    </div>

    <!-- Charts Row 1: Daily Trend + Payment Methods -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Daily Sales Trend (Line Chart) -->
        <Card class="p-5 bg-card border-border/60 shadow-2xs lg:col-span-2 flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-semibold text-foreground tracking-tight">Evolución de Ventas Diarias</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Ventas facturadas día por día en el período seleccionado</p>
                </div>
            </div>
            <div class="h-[280px] w-full">
                <ChartCanvas
                    type="line"
                    data={dailySalesChartData}
                    options={dailySalesChartOptions}
                />
            </div>
        </Card>

        <!-- Payment Methods (Doughnut Chart) -->
        <Card class="p-5 bg-card border-border/60 shadow-2xs flex flex-col">
            <div class="mb-3">
                <h3 class="text-base font-semibold text-foreground tracking-tight">Cobranzas por Medio</h3>
                <p class="text-xs text-muted-foreground mt-0.5">Distribución porcentual de liquidez ingresada</p>
            </div>
            <div class="h-[280px] w-full flex items-center justify-center">
                {#if metrics.revenues.by_method.length > 0}
                    <ChartCanvas
                        type="doughnut"
                        data={paymentMethodsChartData}
                        options={paymentMethodsChartOptions}
                    />
                {:else}
                    <div class="text-xs text-muted-foreground text-center">
                        Sin cobranzas en el período
                    </div>
                {/if}
            </div>
        </Card>
    </div>

    <!-- Charts Row 2: Annual Monthly Summary (Bar Chart) -->
    <Card class="p-5 bg-card border-border/60 shadow-2xs">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-semibold text-foreground tracking-tight">Comparativa Mensual del Año {filters.year}</h3>
                <p class="text-xs text-muted-foreground mt-0.5">Consolidado mensual de ventas, gastos operativos y margen bruto</p>
            </div>
        </div>
        <div class="h-[300px] w-full">
            <ChartCanvas
                type="bar"
                data={monthlyComparisonChartData}
                options={monthlyComparisonChartOptions}
            />
        </div>
    </Card>

    <!-- Operational Tables Row: Top 10 Products + Revenues Detail -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Top 10 Products Table -->
        <Card class="p-5 bg-card border-border/60 shadow-2xs lg:col-span-2">
            <h3 class="text-base font-semibold text-foreground tracking-tight mb-4">Top 10 Productos Más Vendidos</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 dark:bg-secondary/35">
                        <tr class="border-b border-border/60 text-xs font-semibold text-muted-foreground uppercase">
                            <th class="py-2.5 px-2.5">Código</th>
                            <th class="py-2.5 px-2.5">Producto</th>
                            <th class="py-2.5 px-2.5 text-right">Cantidad</th>
                            <th class="py-2.5 px-2.5 text-right">Total Facturado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        {#each topProducts as prod}
                            <tr class="hover:bg-muted/40 dark:hover:bg-secondary/35 transition-colors">
                                <td class="py-2.5 px-2.5 font-mono text-xs text-muted-foreground tabular-nums">{prod.sku || '-'}</td>
                                <td class="py-2.5 px-2.5 font-medium text-foreground">{prod.name}</td>
                                <td class="py-2.5 px-2.5 text-right tabular-nums text-foreground">{prod.total_quantity}</td>
                                <td class="py-2.5 px-2.5 text-right tabular-nums font-semibold text-foreground">{formatMoney(prod.total_subtotal)}</td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="4" class="py-8 text-center text-xs text-muted-foreground">
                                    No se encontraron ventas de productos en este período.
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Revenues Breakdown Table -->
        <Card class="p-5 bg-card border-border/60 shadow-2xs">
            <h3 class="text-base font-semibold text-foreground tracking-tight mb-4">Detalle de Cobranzas</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 dark:bg-secondary/35">
                        <tr class="border-b border-border/60 text-xs font-semibold text-muted-foreground uppercase">
                            <th class="py-2.5 px-2.5">Medio</th>
                            <th class="py-2.5 px-2.5 text-center">Cant.</th>
                            <th class="py-2.5 px-2.5 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        {#each metrics.revenues.by_method as pm}
                            <tr class="hover:bg-muted/40 dark:hover:bg-secondary/35 transition-colors">
                                <td class="py-2.5 px-2.5 font-medium text-foreground">{pm.label}</td>
                                <td class="py-2.5 px-2.5 text-center tabular-nums text-muted-foreground">{pm.count}</td>
                                <td class="py-2.5 px-2.5 text-right tabular-nums font-semibold text-foreground">
                                    <div>{formatMoney(pm.amount)}</div>
                                    <div class="text-[11px] font-normal text-muted-foreground">{pm.percentage}%</div>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="3" class="py-8 text-center text-xs text-muted-foreground">
                                    Sin movimientos de cobranza registrados.
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </Card>
    </div>
</div>
