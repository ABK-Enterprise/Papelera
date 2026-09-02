<script lang="ts">
    import type { HTMLTextareaAttributes } from 'svelte/elements';
    import { cn } from '@/lib/utils';

    let {
        class: className = '',
        value = $bindable(),
        rows = 3,
        ...rest
    }: HTMLTextareaAttributes = $props();
</script>

<textarea
    {rows}
    bind:value
    class={cn(
        'flex min-h-[80px] w-full rounded-xl border border-border/80 bg-muted/30 dark:bg-black/20 dark:border-white/10 p-3 text-sm text-foreground shadow-2xs transition-colors placeholder:text-muted-foreground focus:outline-none focus:border-primary-500 focus-visible:ring-1 focus-visible:ring-primary disabled:cursor-not-allowed disabled:bg-transparent disabled:border-dashed disabled:text-muted-foreground/60 disabled:opacity-60 resize-none',
        className,
    )}
    {...rest}
></textarea>
