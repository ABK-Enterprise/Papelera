<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import ChartCanvas from '@/components/charts/ChartCanvas.svelte';
    import Calendar from 'lucide-svelte/icons/calendar';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import TrendingUp from 'lucide-svelte/icons/trending-up';
    import TrendingDown from 'lucide-svelte/icons/trending-down';
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

    // Chart 1: Daily Trend
    let dailySalesChartData = $derived({
        labels: dailyTrend.map((d) => d.label),
        datasets: [
            {
                label: 'Ventas',
                data: dailyTrend.map((d) => d.sales),
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 2,
                pointRadius: 2,
            },
        ],
    });

    let dailySalesChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { family: "'Google Sans', sans-serif", size: 10 } },
            },
            y: {
                grid: { color: 'rgba(148, 163, 184, 0.15)' },
                ticks: {
                    font: { family: "'Google Sans', sans-serif", size: 10 },
                    callback: (val: any) => `$${Number(val).toLocaleString('es-AR')}`,
                },
            },
        },
    };

    // Chart 2: Payment Methods
    let paymentMethodsChartData = $derived({
        labels: (metrics.revenues.by_method || []).map((m: any) => m.label),
        datasets: [
            {
                data: (metrics.revenues.by_method || []).map((m: any) => m.amount),
                backgroundColor: ['#10b981', '#3b82f6', '#06b6d4', '#8b5cf6', '#f59e0b', '#64748b'],
                borderWidth: 2,
                borderColor: '#ffffff',
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
                    boxWidth: 10,
                    font: { family: "'Google Sans', sans-serif", size: 11 },
                },
            },
        },
        cutout: '65%',
    };

    // Chart 3: Monthly Comparison
    let monthlyComparisonChartData = $derived({
        labels: monthlySummary.map((m) => m.label.split(' ')[0]),
        datasets: [
            {
                label: 'Ventas',
                data: monthlySummary.map((m) => m.sales),
                backgroundColor: '#3b82f6',
                borderRadius: 4,
            },
            ...(permissions.can_view_margins
                ? [
                      {
                          label: 'Gastos',
                          data: monthlySummary.map((m) => m.expenses ?? 0),
                          backgroundColor: '#f43f5e',
                          borderRadius: 4,
                      },
                      {
                          label: 'Margen',
                          data: monthlySummary.map((m) => m.operating_margin ?? 0),
                          backgroundColor: '#10b981',
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
                    boxWidth: 10,
                    font: { family: "'Google Sans', sans-serif", size: 10 },
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { family: "'Google Sans', sans-serif", size: 10 } },
            },
            y: {
                grid: { color: 'rgba(148, 163, 184, 0.15)' },
                ticks: {
                    font: { family: "'Google Sans', sans-serif", size: 10 },
                    callback: (val: any) => `$${Number(val).toLocaleString('es-AR')}`,
                },
            },
        },
    };
</script>

<div class="space-y-4">
    <!-- Mobile Filter Card -->
    <Card class="p-4 bg-card border-border/60 shadow-2xs space-y-4">
        <!-- Quick Range Presets (Horizontal Scrollable) -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar -mx-1 px-1">
            <Button variant="outline" size="sm" onclick={() => setPreset(0)} class="min-h-[44px] text-xs px-3 rounded-xl shrink-0">Hoy</Button>
            <Button variant="outline" size="sm" onclick={() => setPreset(7)} class="min-h-[44px] text-xs px-3 rounded-xl shrink-0">7 días</Button>
            <Button variant="outline" size="sm" onclick={() => setPreset(30)} class="min-h-[44px] text-xs px-3 rounded-xl shrink-0">30 días</Button>
            <Button variant="outline" size="sm" onclick={() => setPreset('month')} class="min-h-[44px] text-xs px-3 rounded-xl shrink-0">Este mes</Button>
            <Button variant="outline" size="sm" onclick={() => setPreset('year')} class="min-h-[44px] text-xs px-3 rounded-xl shrink-0">Año</Button>
        </div>

        <!-- Custom Date Range -->
        <div class="grid grid-cols-2 gap-2.5">
            <div class="space-y-1">
                <Label class="text-xs font-medium text-muted-foreground">Desde</Label>
                <Input
                    type="date"
                    bind:value={localDateFrom}
                    class="min-h-[44px] text-xs rounded-xl bg-background"
                />
            </div>
            <div class="space-y-1">
                <Label class="text-xs font-medium text-muted-foreground">Hasta</Label>
                <Input
                    type="date"
                    bind:value={localDateTo}
                    class="min-h-[44px] text-xs rounded-xl bg-background"
                />
            </div>
        </div>

        <!-- Warehouse Selector -->
        <div class="space-y-1">
            <Label class="text-xs font-medium text-muted-foreground">Sucursal / Depósito</Label>
            <select
                bind:value={localWarehouseId}
                class="w-full min-h-[44px] px-3 py-2 text-sm rounded-xl border border-border bg-select-background text-foreground shadow-2xs focus:outline-none focus:ring-2 focus:ring-ring"
            >
                <option value="">Consolidado (Todos los depósitos)</option>
                {#each warehouses as wh}
                    <option value={String(wh.id)}>{wh.name} ({wh.code})</option>
                {/each}
            </select>
        </div>

        <!-- Action Buttons (Grid 2-col) -->
        <div class="grid grid-cols-2 gap-2.5 pt-2 border-t border-border/50">
            <Button
                onclick={handleApply}
                class="w-full min-h-[44px] gap-2 text-xs font-semibold rounded-xl shadow-2xs"
            >
                <Filter class="size-4" />
                <span>Aplicar</span>
            </Button>

            <Button
                variant="outline"
                onclick={handleReset}
                class="w-full min-h-[44px] gap-2 text-xs rounded-xl text-muted-foreground hover:text-foreground"
            >
                <RotateCcw class="size-4" />
                <span>Restablecer</span>
            </Button>
        </div>

        {#if permissions.can_export}
            <Button
                variant="secondary"
                onclick={onExportPdf}
                class="w-full min-h-[44px] gap-2 text-xs font-semibold rounded-xl border border-border/40 shadow-2xs hover:bg-secondary/90 transition-all"
            >
                <FileDown class="size-4 text-primary" />
                <span>Descargar Reporte en PDF</span>
            </Button>
        {/if}
    </Card>

    <!-- Operational KPI Cards (Mobile Stack) -->
    <div class="grid grid-cols-1 gap-3">
        <!-- Ventas Brutas -->
        <Card class="p-4 bg-card border-border/60 shadow-2xs flex items-center justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Ventas Brutas</div>
                <div class="text-xl font-bold tracking-tight text-foreground tabular-nums mt-0.5">
                    {formatMoney(metrics.sales.total)}
                </div>
                <div class="text-[11px] text-muted-foreground mt-0.5 tabular-nums">
                    {metrics.sales.orders_count} pedidos · Prom: {formatMoney(metrics.sales.average_ticket)}
                </div>
            </div>
            <div class="size-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-600 shrink-0">
                <ShoppingBag class="size-5" />
            </div>
        </Card>

        <!-- Ingresos Cobrados -->
        <Card class="p-4 bg-card border-border/60 shadow-2xs flex items-center justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Ingresos Cobrados</div>
                <div class="text-xl font-bold tracking-tight text-foreground tabular-nums mt-0.5">
                    {formatMoney(metrics.revenues.total)}
                </div>
                <div class="text-[11px] text-muted-foreground mt-0.5">
                    Total liquidez percibida
                </div>
            </div>
            <div class="size-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-600 shrink-0">
                <CreditCard class="size-5" />
            </div>
        </Card>

        {#if permissions.can_view_margins}
            <!-- Gastos en Compras -->
            <Card class="p-4 bg-card border-border/60 shadow-2xs flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Gastos / Compras</span>
                    <div class="text-xl font-bold tracking-tight text-foreground tabular-nums mt-0.5">
                        {formatMoney(metrics.expenses.total)}
                    </div>
                    <div class="text-[11px] text-muted-foreground mt-0.5 tabular-nums">
                        {metrics.expenses.purchases_count} compras recibidas
                    </div>
                </div>
                <div class="size-10 rounded-xl bg-rose-500/10 flex items-center justify-center text-rose-600 shrink-0">
                    <TrendingDown class="size-5" />
                </div>
            </Card>

            <!-- Margen Operativo -->
            <Card class="p-4 bg-card border-border/60 shadow-2xs flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Margen Operativo</span>
                    <div class="text-xl font-bold tracking-tight tabular-nums mt-0.5 {metrics.margin.operating_margin >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}">
                        {formatMoney(metrics.margin.operating_margin)}
                    </div>
                    <div class="text-[11px] text-muted-foreground mt-0.5 tabular-nums">
                        {metrics.margin.margin_percentage}% rendimiento
                    </div>
                </div>
                <div class="size-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-600 shrink-0">
                    <TrendingUp class="size-5" />
                </div>
            </Card>
        {/if}
    </div>

    <!-- Charts Mobile Feed -->
    <!-- 1. Daily Sales Trend -->
    <Card class="p-4 bg-card border-border/60 shadow-2xs">
        <h3 class="text-sm font-semibold text-foreground tracking-tight mb-2">Ventas Diarias</h3>
        <div class="h-[220px] w-full">
            <ChartCanvas
                type="line"
                data={dailySalesChartData}
                options={dailySalesChartOptions}
            />
        </div>
    </Card>

    <!-- 2. Payment Methods -->
    <Card class="p-4 bg-card border-border/60 shadow-2xs">
        <h3 class="text-sm font-semibold text-foreground tracking-tight mb-2">Cobranzas por Medio</h3>
        <div class="h-[220px] w-full flex items-center justify-center">
            {#if metrics.revenues.by_method.length > 0}
                <ChartCanvas
                    type="doughnut"
                    data={paymentMethodsChartData}
                    options={paymentMethodsChartOptions}
                />
            {:else}
                <div class="text-xs text-muted-foreground text-center">
                    Sin cobranzas registradas
                </div>
            {/if}
        </div>
    </Card>

    <!-- 3. Monthly Summary -->
    <Card class="p-4 bg-card border-border/60 shadow-2xs">
        <h3 class="text-sm font-semibold text-foreground tracking-tight mb-2">Resumen Mensual {filters.year}</h3>
        <div class="h-[240px] w-full">
            <ChartCanvas
                type="bar"
                data={monthlyComparisonChartData}
                options={monthlyComparisonChartOptions}
            />
        </div>
    </Card>

    <!-- Top 10 Products Feed -->
    <Card class="p-4 bg-card border-border/60 shadow-2xs">
        <h3 class="text-sm font-semibold text-foreground tracking-tight mb-3">Top 10 Productos Más Vendidos</h3>
        <div class="space-y-2.5">
            {#each topProducts as prod}
                <div class="p-3 rounded-xl bg-muted/30 border border-border/40 flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-semibold text-foreground line-clamp-2 break-words">{prod.name}</div>
                        <div class="text-[11px] text-muted-foreground mt-0.5">
                            {prod.sku ? `SKU: ${prod.sku} · ` : ''}{prod.total_quantity} unidades
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-sm font-bold text-foreground tabular-nums">{formatMoney(prod.total_subtotal)}</div>
                    </div>
                </div>
            {:else}
                <div class="py-6 text-center text-xs text-muted-foreground">
                    Sin ventas registradas en el período.
                </div>
            {/each}
        </div>
    </Card>

    <!-- Revenues Detail Feed -->
    <Card class="p-4 bg-card border-border/60 shadow-2xs">
        <h3 class="text-sm font-semibold text-foreground tracking-tight mb-3">Detalle de Cobranzas</h3>
        <div class="space-y-2">
            {#each metrics.revenues.by_method as pm}
                <div class="p-2.5 rounded-xl bg-muted/20 border border-border/30 flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-medium text-foreground">{pm.label}</div>
                        <div class="text-[11px] text-muted-foreground">{pm.count} comprobantes</div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="text-xs font-semibold text-foreground tabular-nums">{formatMoney(pm.amount)}</div>
                        <div class="text-[10px] text-muted-foreground tabular-nums">{pm.percentage}%</div>
                    </div>
                </div>
            {:else}
                <div class="py-6 text-center text-xs text-muted-foreground">
                    Sin cobranzas registradas.
                </div>
            {/each}
        </div>
    </Card>
</div>
