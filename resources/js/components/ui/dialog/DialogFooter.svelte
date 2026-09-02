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

<div class={cn('flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5 p-4 sm:p-6 border-t border-border shrink-0 bg-card', className)} {...restProps}>

    {@render children?.()}
</div>
