<script lang="ts">
    import type { Snippet } from 'svelte';
    import Icon from '@iconify/svelte';

    let {
        icon = '',
        size = 'md',
        variant = 'primary',
        rounded = 'xl',
        class: className = '',
        children,
    }: {
        icon?: string;
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
        variant?: 'primary' | 'success' | 'warning' | 'destructive' | 'info' | 'secondary' | 'neutral';
        rounded?: 'lg' | 'xl' | '2xl' | 'full';
        class?: string;
        children?: Snippet;
    } = $props();

    const sizeClasses = {
        xs: 'size-7 text-xs',
        sm: 'size-8 sm:size-9 text-sm',
        md: 'size-10 sm:size-11 text-base',
        lg: 'size-12 sm:size-12 text-lg',
        xl: 'size-14 text-xl',
        '2xl': 'size-16 text-2xl',
    };

    const iconSizes = {
        xs: 14,
        sm: 18,
        md: 22,
        lg: 26,
        xl: 30,
        '2xl': 34,
    };

    const roundedClasses = {
        lg: 'rounded-lg',
        xl: 'rounded-xl',
        '2xl': 'rounded-2xl',
        full: 'rounded-full',
    };

    const variantClasses = {
        primary: 'bg-primary/10 text-primary border border-primary/30 dark:bg-primary/15 dark:border-primary/40',
        success: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 dark:bg-emerald-950/40 dark:border-emerald-500/35',
        warning: 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30 dark:bg-amber-950/40 dark:border-amber-500/35',
        destructive: 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/30 dark:bg-rose-950/40 dark:border-rose-500/35',
        info: 'bg-sky-500/10 text-sky-700 dark:text-sky-400 border border-sky-500/30 dark:bg-sky-950/40 dark:border-sky-500/35',
        secondary: 'bg-secondary text-secondary-foreground border border-secondary-300 dark:border-secondary-700',
        neutral: 'bg-neutral-500/10 text-neutral-700 dark:text-neutral-300 border border-neutral-500/30 dark:bg-neutral-950/40 dark:border-neutral-500/35',
    };
</script>

<div
    class="flex shrink-0 items-center justify-center font-bold transition-colors select-none {sizeClasses[size] || sizeClasses.md} {roundedClasses[rounded] || roundedClasses.xl} {variantClasses[variant] || variantClasses.primary} {className}"
>
    {#if icon}
        <Icon icon={icon} width={iconSizes[size] || 22} height={iconSizes[size] || 22} class="shrink-0" />
    {:else if children}
        {@render children()}
    {/if}
</div>
