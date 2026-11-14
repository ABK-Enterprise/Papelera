<script lang="ts">
    import { onDestroy, onMount } from 'svelte';
    import {
        Chart,
        type ChartTypeRegistry,
        registerables,
    } from 'chart.js';

    // Register all Chart.js controllers, elements, scales, and plugins
    Chart.register(...registerables);

    // Apply Google Sans and design tokens globally for all charts
    Chart.defaults.font.family = "'Google Sans', sans-serif";
    Chart.defaults.color = '#64748b'; // slate-500 fallback

    function syncChartThemeDefaults() {
        if (typeof document === 'undefined') return;
        const mutedFg = getComputedStyle(document.documentElement)
            .getPropertyValue('--muted-foreground')
            .trim();
        if (mutedFg) {
            Chart.defaults.color = mutedFg;
        }
        if (chart) {
            chart.update('none');
        }
    }

    onMount(() => {
        syncChartThemeDefaults();
        const observer = new MutationObserver(() => {
            syncChartThemeDefaults();
        });
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class', 'data-theme'],
        });
        return () => observer.disconnect();
    });

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
    let chart: Chart | null = null;

    function safeDestroy() {
        if (canvasRef) {
            const registered = Chart.getChart(canvasRef);
            if (registered) {
                registered.destroy();
            }
        }
        if (chart) {
            try {
                chart.destroy();
            } catch (_) {
                // already destroyed
            }
            chart = null;
        }
    }

    // Effect manages initial creation, in-place updates, and cleanup
    $effect(() => {
        if (!canvasRef) return;

        // If chart already exists and type is the same, update datasets in-place
        if (chart && chart.config.type === type) {
            chart.data = data;
            if (options) {
                chart.options = {
                    responsive: true,
                    maintainAspectRatio: false,
                    ...options,
                };
            }
            chart.update('none');
            return;
        }

        // First mount or type changed: ensure canvas is completely free
        safeDestroy();

        const ctx = canvasRef.getContext('2d');
        if (!ctx) return;

        chart = new Chart(ctx, {
            type,
            data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                ...options,
            },
        });

        return () => {
            safeDestroy();
        };
    });

    onDestroy(() => {
        safeDestroy();
    });
</script>

<div class={className}>
    <canvas bind:this={canvasRef}></canvas>
</div>
