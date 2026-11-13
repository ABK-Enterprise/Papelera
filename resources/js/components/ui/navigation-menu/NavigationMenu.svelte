<script lang="ts">
    import { NavigationMenu as NavigationMenuPrimitive } from 'bits-ui';
    import { cn } from '@/lib/utils';
    import type { ComponentProps } from 'svelte';

    let {
        ref = $bindable(null),
        value = $bindable(''),
        class: className,
        children,
        ...restProps
    }: ComponentProps<typeof NavigationMenuPrimitive.Root> = $props();
</script>

<NavigationMenuPrimitive.Root
    bind:ref
    bind:value
    data-slot="navigation-menu"
    class={cn('group/navigation-menu relative flex max-w-max flex-1 items-center justify-center', className)}
    {...restProps}
>
    {@render children?.()}
</NavigationMenuPrimitive.Root>
