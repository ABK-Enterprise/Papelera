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

<div class={cn('flex items-center justify-between p-5 sm:p-6 border-b border-border shrink-0 bg-card', className)} {...restProps}>

    {@render children?.()}
</div>
