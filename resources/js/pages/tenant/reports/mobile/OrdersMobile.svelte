<script lang="ts">
    import { Card } from '@/components/ui/card';
    import ChartCanvas from '@/components/charts/ChartCanvas.svelte';
    import { TrendingUp } from 'lucide-svelte';

    interface Props {
        ordersData: {
            kpis: {
                total_orders: number;
                delivered_orders: number;
                cancelled_orders: number;
                collected_orders: number;
                total_revenues: number;
            };
            orders_trend: Array<{ key: string; label: string; value: number }>;
        };
    }

    let { ordersData }: Props = $props();

    function formatMoney(val: number): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 0,
        }).format(val);
    }

    const ordersChartData = $derived({
        labels: ordersData.orders_trend.map((d) => d.label),
        datasets: [
            {
                label: 'Pedidos',
                data: ordersData.orders_trend.map((d) => d.value),
                borderColor: '#0284c7',
                backgroundColor: 'rgba(2, 132, 199, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 2,
                pointRadius: 2,
            },
        ],
    });

    const ordersChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0, font: { size: 10 } },
                grid: { color: 'rgba(150, 150, 150, 0.1)' },
            },
            x: {
                ticks: { font: { size: 10 } },
                grid: { display: false },
            },
        },
    };
</script>

<div class="flex flex-col gap-4 w-full">
    <!-- KPIs Mobile -->
    <div class="grid grid-cols-2 gap-2.5">
        <Card class="p-3.5 bg-card border-border/70 shadow-xs">
            <span class="text-xs font-semibold text-muted-foreground">Realizados</span>
            <div class="text-xl font-bold tabular-nums text-foreground mt-0.5">{ordersData.kpis.total_orders}</div>
        </Card>
        <Card class="p-3.5 bg-card border-border/70 shadow-xs">
            <span class="text-xs font-semibold text-muted-foreground">Entregados</span>
            <div class="text-xl font-bold tabular-nums text-foreground mt-0.5">{ordersData.kpis.delivered_orders}</div>
        </Card>
        <Card class="p-3.5 bg-card border-border/70 shadow-xs">
            <span class="text-xs font-semibold text-muted-foreground">Cancelados</span>
            <div class="text-xl font-bold tabular-nums text-foreground mt-0.5">{ordersData.kpis.cancelled_orders}</div>
        </Card>
        <Card class="p-3.5 bg-card border-border/70 shadow-xs">
            <span class="text-xs font-semibold text-muted-foreground">Cobrados</span>
            <div class="text-xl font-bold tabular-nums text-foreground mt-0.5">{ordersData.kpis.collected_orders}</div>
        </Card>
        <Card class="p-3.5 bg-card border-border/70 shadow-xs col-span-2 flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-muted-foreground">Ingresos Totales</span>
                <div class="text-xl font-bold tabular-nums text-foreground mt-0.5">{formatMoney(ordersData.kpis.total_revenues)}</div>
            </div>
            <div class="size-7 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-700 dark:text-emerald-400">
                <TrendingUp class="size-4" />
            </div>
        </Card>
    </div>

    <!-- Gráfico Mobile -->
    <Card class="p-4 bg-card border-border/70 shadow-xs">
        <h3 class="text-base font-semibold text-foreground mb-3">Pedidos Realizados</h3>
        <div class="h-[220px] w-full">
            <ChartCanvas type="line" data={ordersChartData} options={ordersChartOptions} />
        </div>
    </Card>
</div>
