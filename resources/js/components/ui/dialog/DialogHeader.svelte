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

<div class={cn('flex items-center justify-between px-5 sm:px-6 py-4 border-b border-border/70 shrink-0', className)}>
    {@render children?.()}
</div>
