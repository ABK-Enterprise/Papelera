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
        'flex min-h-[80px] w-full rounded-xl border border-input bg-field p-3 text-sm text-foreground shadow-2xs transition-colors placeholder:text-muted-foreground focus:outline-none focus:border-primary focus-visible:ring-1 focus-visible:ring-primary disabled:cursor-not-allowed disabled:bg-muted/40 disabled:border-dashed disabled:text-muted-foreground disabled:opacity-65 resize-none',
        className,
    )}
    {...rest}
></textarea>

