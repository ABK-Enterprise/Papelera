<script lang="ts">
    import type { Snippet } from 'svelte';
    import { getContext } from 'svelte';
    import { cn } from '@/lib/utils';
    import { DIALOG_CONTEXT, type DialogContext } from './context';

    let {
        asChild = false,
        class: className = '',
        children,
        ...restProps
    }: {
        asChild?: boolean;
        class?: string;
        children?: Snippet<[Record<string, unknown>]>;
        [key: string]: any;
    } = $props();

    const { setOpen } = getContext<DialogContext>(DIALOG_CONTEXT);

    const handleClick = () => setOpen(false);
</script>

{#if asChild}
    {@render children?.({ onClick: handleClick })}
{:else}
    <button
        type="button"
        class={cn(
            'size-9 rounded-xl inline-flex items-center justify-center text-muted-foreground hover:bg-muted dark:hover:bg-secondary/60 hover:text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring shrink-0 cursor-pointer',
            className
        )}
        onclick={handleClick}
        aria-label="Cerrar ventana"
        {...restProps}
    >
        {@render children?.({})}
    </button>
{/if}

