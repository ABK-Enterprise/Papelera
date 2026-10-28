<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { RotateCcw } from 'lucide-svelte';

    interface Props {
        show?: boolean;
        onclick: () => void;
        title?: string;
        label?: string;
        class?: string;
    }

    let {
        show = true,
        onclick,
        title = 'Limpiar filtros activos',
        label = 'Limpiar',
        class: className = '',
    }: Props = $props();
</script>

{#if show}
    <Button
        variant="outline"
        size="sm"
        class="h-10 px-3 text-sm font-medium gap-1.5 shrink-0 rounded-xl border shadow-2xs transition-colors bg-emerald-500/10 text-emerald-700 border-emerald-500/30 hover:bg-emerald-500/20 dark:bg-blue-500/15 dark:text-blue-400 dark:border-blue-500/30 dark:hover:bg-blue-500/25 animate-in fade-in zoom-in-95 duration-200 {className}"
        {onclick}
        {title}
    >
        <RotateCcw class="size-3.5 shrink-0" />
        <span>{label}</span>
    </Button>
{/if}
