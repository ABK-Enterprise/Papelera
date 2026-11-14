<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    type Variant = 'default' | 'destructive';

    const base =
        'relative w-full rounded-lg border px-4 py-3 text-sm grid has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] grid-cols-[0_1fr] has-[>svg]:gap-x-3 gap-y-0.5 items-start [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current';

    const variantClasses: Record<Variant, string> = {
        default: 'bg-muted/60 dark:bg-secondary/35 border-border/80 text-card-foreground',
        destructive:
            'border-destructive/30 bg-destructive/10 text-destructive dark:text-rose-400 [&>svg]:text-current *:data-[slot=alert-description]:text-destructive/90 dark:*:data-[slot=alert-description]:text-rose-300',
    };

    let {
        variant = 'default',
        class: className = '',
        children,
    }: {
        variant?: Variant;
        class?: string;
        children?: Snippet;
    } = $props();
</script>

<div data-slot="alert" class={cn(base, variantClasses[variant], className)} role="alert">
    {@render children?.()}
</div>
