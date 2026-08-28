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

<div class={cn('flex-1 overflow-y-auto p-5 sm:p-6 space-y-4', className)} {...restProps}>
    {@render children?.()}
</div>
