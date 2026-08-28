<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    let {
        class: className = '',
        children,
    }: {
        class?: string;
        children?: Snippet;
    } = $props();
</script>

<div class={cn('flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5 px-5 sm:px-6 py-3.5 border-t border-border/70 bg-muted/20 shrink-0', className)}>
    {@render children?.()}
</div>
