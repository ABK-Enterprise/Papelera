<script lang="ts">
    import { Card } from '@/components/ui/card';
    import ChartCanvas from '@/components/charts/ChartCanvas.svelte';
    import { TrendingUp, TrendingDown } from 'lucide-svelte';

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
            minimumFractionDigits: 2,
        }).format(val);
    }

    const ordersChartData = $derived({
        labels: ordersData.orders_trend.map((d) => d.label),
        datasets: [
            {
                label: 'Pedidos',
                data: ordersData.orders_trend.map((d) => d.value),
                borderColor: '#0284c7', // sky-600
                backgroundColor: 'rgba(2, 132, 199, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: '#0284c7',
            },
        ],
    });

    const ordersChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (context: any) => `Pedidos: ${context.parsed.y}`,
                },
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0,
                    font: { size: 12 },
                },
                grid: { color: 'rgba(150, 150, 150, 0.1)' },
            },
            x: {
                ticks: { font: { size: 12 } },
                grid: { display: false },
            },
        },
    };
</script>

<div class="flex flex-col gap-6 w-full">
    <!-- 1. KPIs Operativos de Pedidos -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <!-- Pedidos Realizados -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <span class="text-base font-semibold tracking-normal text-muted-foreground">Pedidos Realizados</span>
            <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                {ordersData.kpis.total_orders}
            </div>
        </Card>

        <!-- Pedidos Entregados -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <span class="text-base font-semibold tracking-normal text-muted-foreground">Pedidos Entregados</span>
            <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                {ordersData.kpis.delivered_orders}
            </div>
        </Card>

        <!-- Pedidos Cancelados -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <span class="text-base font-semibold tracking-normal text-muted-foreground">Pedidos Cancelados</span>
            <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                {ordersData.kpis.cancelled_orders}
            </div>
        </Card>

        <!-- Pedidos Cobrados -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <span class="text-base font-semibold tracking-normal text-muted-foreground">Pedidos Cobrados</span>
            <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                {ordersData.kpis.collected_orders}
            </div>
        </Card>

        <!-- Ingresos Totales (Con icono flecha verde) -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs col-span-2 md:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-base font-semibold tracking-normal text-muted-foreground">Ingresos Totales</span>
                <div class="size-7 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                    <TrendingUp class="size-4" />
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                {formatMoney(ordersData.kpis.total_revenues)}
            </div>
        </Card>
    </div>

    <!-- 2. Gráfico: Pedidos Realizados -->
    <Card class="p-6 bg-card border-border/70 shadow-xs">
        <div class="flex flex-col gap-1 mb-5">
            <h3 class="text-lg font-semibold tracking-tight text-foreground">Pedidos Realizados</h3>
            <p class="text-sm text-muted-foreground leading-normal">
                Curva temporal de pedidos creados en el rango seleccionado
            </p>
        </div>
        <div class="h-[280px] w-full">
            <ChartCanvas type="line" data={ordersChartData} options={ordersChartOptions} />
        </div>
    </Card>
</div>
