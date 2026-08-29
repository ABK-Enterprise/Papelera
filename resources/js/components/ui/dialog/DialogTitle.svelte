<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    let {
        class: className = '',
        children,
        ...restProps
    }: {
        class?: string;
        children?: Snippet;
        [key: string]: any;
    } = $props();
</script>

<h2 class={cn('text-lg font-semibold leading-none tracking-tight text-foreground', className)} {...restProps}>
    {@render children?.()}
</h2>
