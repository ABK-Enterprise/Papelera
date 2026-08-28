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

<div class={cn('flex-1 min-h-0 overflow-y-auto px-5 sm:px-6 py-5 space-y-4', className)}>
    {@render children?.()}
</div>
