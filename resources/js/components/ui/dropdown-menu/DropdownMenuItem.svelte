<script lang="ts">
    import type { Snippet } from 'svelte';
    import { getContext } from 'svelte';
    import { cn } from '@/lib/utils';
    import { DROPDOWN_MENU_CONTEXT, type DropdownMenuContext } from './context';

    type AsChildProps = {
        class?: string;
        onClick?: (e: MouseEvent) => void;
        [key: string]: any;
    };

    let {
        asChild = false,
        class: className = '',
        onclick,
        children,
        ...restProps
    }: {
        asChild?: boolean;
        class?: string;
        onclick?: (e: MouseEvent) => void;
        children?: Snippet<[AsChildProps]>;
        [key: string]: any;
    } = $props();

    const { setOpen } = getContext<DropdownMenuContext>(DROPDOWN_MENU_CONTEXT);

    const handleClick = (e: MouseEvent) => {
        onclick?.(e);
        setOpen(false);
    };

    const classes = () =>
        cn(
            'flex w-full cursor-pointer select-none items-center justify-start text-left rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground',
            className,
        );
</script>

{#if asChild}
    {@render children?.({ class: classes(), onClick: handleClick, ...restProps })}
{:else}
    <button type="button" class={classes()} onclick={handleClick} {...restProps}>
        {@render children?.({})}
    </button>
{/if}
