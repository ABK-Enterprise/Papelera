<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import History from 'lucide-svelte/icons/history';

    let { activeTab = 'incomes' }: { activeTab?: 'incomes' | 'outcomes' | 'kardex' } = $props();

    const tabs = [
        {
            id: 'incomes',
            name: 'Ingresos de stock',
            href: '/tenant/stock/ingresos',
            icon: ArrowDownLeft,
        },
        {
            id: 'outcomes',
            name: 'Egresos de stock',
            href: '/tenant/stock/egresos',
            icon: ArrowUpRight,
        },
        {
            id: 'kardex',
            name: 'Kardex e historial',
            href: '/tenant/stock/kardex',
            icon: History,
        },
    ];
</script>

<div class="flex items-center overflow-x-auto border-b border-border/80 pb-px gap-1 sm:gap-2">
    {#each tabs as tab}
        {@const Icon = tab.icon}
        {@const isActive = activeTab === tab.id}
        <Link
            href={tab.href}
            class="inline-flex items-center gap-2 px-3.5 py-2.5 text-xs sm:text-sm font-medium rounded-t-lg transition-colors border-b-2 whitespace-nowrap {isActive
                ? 'border-primary text-primary bg-primary/5 font-semibold'
                : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/40'}"
        >
            <Icon class="size-4 shrink-0" />
            <span>{tab.name}</span>
        </Link>
    {/each}
</div>
