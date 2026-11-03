<script lang="ts">
    import {
        Chart,
        type ChartConfiguration,
        type ChartTypeRegistry,
        registerables,
    } from 'chart.js';

    // Register all Chart.js controllers, elements, scales, and plugins
    Chart.register(...registerables);

    // Apply Google Sans and design tokens globally for all charts
    Chart.defaults.font.family = "'Google Sans', sans-serif";
    Chart.defaults.color = '#64748b'; // slate-500

    let {
        type = 'line',
        data,
        options = {},
        class: className = 'w-full h-full min-h-[260px]',
    }: {
        type: keyof ChartTypeRegistry;
        data: any;
        options?: any;
        class?: string;
    } = $props();

    let canvasRef = $state<HTMLCanvasElement | null>(null);
    let chartInstance = $state<Chart | null>(null);

    // Initial chart creation and unmount cleanup
    $effect(() => {
        if (!canvasRef) return;

        const ctx = canvasRef.getContext('2d');
        if (!ctx) return;

        if (chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(ctx, {
            type,
            data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                ...options,
            },
        });

        return () => {
            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }
        };
    });

    // Reactive update when datasets, labels, or options change
    $effect(() => {
        if (!chartInstance) return;

        chartInstance.data = data;
        if (options) {
            chartInstance.options = {
                responsive: true,
                maintainAspectRatio: false,
                ...options,
            };
        }
        chartInstance.update('none'); // Update smoothly without re-triggering enter animations
    });
</script>

<div class={className}>
    <canvas bind:this={canvasRef}></canvas>
</div>
