<script lang="ts">
    import { NavigationMenu as NavigationMenuPrimitive } from 'bits-ui';
    import { cn } from '@/lib/utils';
    import type { ComponentProps } from 'svelte';

    let {
        ref = $bindable(null),
        class: className,
        children,
        child,
        ...restProps
    }: ComponentProps<typeof NavigationMenuPrimitive.Link> = $props();
</script>

{#if child}
    <NavigationMenuPrimitive.Link
        bind:ref
        data-slot="navigation-menu-link"
        class={cn(className)}
        {child}
        {...restProps}
    />
{:else}
    <NavigationMenuPrimitive.Link
        bind:ref
        data-slot="navigation-menu-link"
        class={cn(
            'inline-flex items-center transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring',
            className,
        )}
        {...restProps}
    >
        {@render children?.()}
    </NavigationMenuPrimitive.Link>
{/if}
