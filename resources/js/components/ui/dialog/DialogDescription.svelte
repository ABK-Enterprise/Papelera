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

<p class={cn('text-xs text-muted-foreground leading-relaxed', className)}>
    {@render children?.()}
</p>
