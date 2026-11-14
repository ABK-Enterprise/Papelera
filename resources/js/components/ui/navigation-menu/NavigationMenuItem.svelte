<script lang="ts">
    import { NavigationMenu as NavigationMenuPrimitive } from 'bits-ui';
    import { cn } from '@/lib/utils';
    import type { ComponentProps } from 'svelte';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: ComponentProps<typeof NavigationMenuPrimitive.Item> = $props();
</script>

<NavigationMenuPrimitive.Item
    bind:ref
    data-slot="navigation-menu-item"
    class={cn('relative list-none', className)}
    {...restProps}
>
    {@render children?.()}
</NavigationMenuPrimitive.Item>
