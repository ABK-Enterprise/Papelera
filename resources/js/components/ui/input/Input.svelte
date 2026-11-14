<script lang="ts">
    import type { HTMLInputAttributes } from 'svelte/elements';
    import { cn } from '@/lib/utils';

    let {
        class: className = '',
        type = 'text',
        value = $bindable(),
        ...rest
    }: HTMLInputAttributes = $props();
</script>

<input
    {type}
    bind:value
    class={cn(
        'flex h-10 w-full rounded-xl border border-input bg-field px-3.5 py-2 text-sm text-foreground shadow-2xs transition-colors placeholder:text-muted-foreground focus:outline-none focus:border-primary focus-visible:ring-1 focus-visible:ring-primary disabled:cursor-not-allowed disabled:bg-muted/40 disabled:border-dashed disabled:text-muted-foreground disabled:opacity-65',
        className,
    )}
    {...rest}
/>


