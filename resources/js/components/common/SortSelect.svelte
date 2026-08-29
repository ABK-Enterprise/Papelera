<script lang="ts">
    import ArrowUpDown from 'lucide-svelte/icons/arrow-up-down';

    interface SortOption {
        value: string;
        label: string;
    }

    let {
        value = 'latest',
        options = [],
        onchange,
        class: className = '',
    }: {
        value?: string;
        options: SortOption[];
        onchange?: (value: string) => void;
        class?: string;
    } = $props();

    function handleChange(e: Event) {
        const target = e.target as HTMLSelectElement;
        onchange?.(target.value);
    }
</script>

<div class="relative flex items-center {className}">
    <ArrowUpDown class="pointer-events-none absolute left-3 size-3.5 text-muted-foreground" />
    <select
        value={value}
        onchange={handleChange}
        class="h-10 min-h-[40px] appearance-none rounded-xl border border-border/80 bg-background pl-8 pr-7 text-xs font-medium text-foreground transition-all hover:bg-muted/30 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
        aria-label="Criterio de ordenamiento"
    >
        {#each options as opt}
            <option value={opt.value}>{opt.label}</option>
        {/each}
    </select>
</div>
