<script lang="ts">
    import { NavigationMenu as NavigationMenuPrimitive } from 'bits-ui';
    import { cn } from '@/lib/utils';
    import type { ComponentProps } from 'svelte';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: ComponentProps<typeof NavigationMenuPrimitive.Content> = $props();
</script>

<NavigationMenuPrimitive.Content
    bind:ref
    data-slot="navigation-menu-content"
    class={cn(
        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 z-50',
        className,
    )}
    {...restProps}
>
    {@render children?.()}
</NavigationMenuPrimitive.Content>
