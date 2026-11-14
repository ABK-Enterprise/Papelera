<script lang="ts">
    import { NavigationMenu as NavigationMenuPrimitive } from 'bits-ui';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import { cn } from '@/lib/utils';
    import { navigationMenuTriggerStyle } from './index';
    import type { ComponentProps } from 'svelte';

    let {
        ref = $bindable(null),
        class: className,
        children,
        child,
        ...restProps
    }: ComponentProps<typeof NavigationMenuPrimitive.Trigger> = $props();
</script>

{#if child}
    <NavigationMenuPrimitive.Trigger
        bind:ref
        data-slot="navigation-menu-trigger"
        class={cn(navigationMenuTriggerStyle(), 'group', className)}
        {child}
        {...restProps}
    />
{:else}
    <NavigationMenuPrimitive.Trigger
        bind:ref
        data-slot="navigation-menu-trigger"
        class={cn(navigationMenuTriggerStyle(), 'group', className)}
        {...restProps}
    >
        {@render children?.()}
        <ChevronDown
            class="relative top-[1px] ms-1 size-3.5 text-muted-foreground/70 transition-transform duration-200 group-data-[state=open]:rotate-180"
            aria-hidden="true"
        />
    </NavigationMenuPrimitive.Trigger>
{/if}
