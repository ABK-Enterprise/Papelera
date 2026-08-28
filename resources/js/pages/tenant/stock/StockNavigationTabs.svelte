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
            gridClass: 'col-span-1',
        },
        {
            id: 'outcomes',
            name: 'Egresos de stock',
            href: '/tenant/stock/egresos',
            icon: ArrowUpRight,
            gridClass: 'col-span-1',
        },
        {
            id: 'kardex',
            name: 'Kardex e historial de auditoría',
            href: '/tenant/stock/kardex',
            icon: History,
            gridClass: 'col-span-2',
        },
    ];
</script>

<!-- Mobile: Grid 2x1 responsivo sin scrollbars horizontales -->
<div class="grid grid-cols-2 gap-2 md:hidden mb-4">
    {#each tabs as tab}
        {@const Icon = tab.icon}
        {@const isActive = activeTab === tab.id}
        <Link
            href={tab.href}
            class="flex items-center justify-center gap-2 px-3 py-2.5 min-h-[44px] text-xs font-semibold rounded-xl border transition-all {tab.gridClass} {isActive
                ? 'border-primary/40 bg-primary/10 text-primary shadow-xs'
                : 'border-border/60 bg-card/60 text-muted-foreground hover:bg-muted/60 hover:text-foreground'}"
        >
            <Icon class="size-4 shrink-0" />
            <span class="truncate">{tab.name}</span>
        </Link>
    {/each}
</div>

<!-- Desktop: Pestañas horizontales limpias -->
<div class="hidden md:flex items-center border-b border-border/80 pb-px gap-2">
    {#each tabs as tab}
        {@const Icon = tab.icon}
        {@const isActive = activeTab === tab.id}
        <Link
            href={tab.href}
            class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-t-xl transition-colors border-b-2 whitespace-nowrap {isActive
                ? 'border-primary text-primary bg-primary/5 font-semibold'
                : 'border-transparent text-muted-foreground hover:text-foreground hover:bg-muted/40'}"
        >
            <Icon class="size-4 shrink-0" />
            <span>{tab.name}</span>
        </Link>
    {/each}
</div>
