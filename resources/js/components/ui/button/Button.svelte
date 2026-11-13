<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    type Variant =
        | 'default'
        | 'secondary'
        | 'tertiary'
        | 'ghost'
        | 'destructive'
        | 'success'
        | 'warning'
        | 'outline'
        | 'link'
        | 'accent';
    type Size = 'default' | 'sm' | 'lg' | 'icon';
    type AsChildProps = {
        class?: string;
        onClick?: (event: MouseEvent) => void;
        [key: string]: any;
    };

    const base =
        'inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 cursor-pointer select-none active:scale-95';

    const variants: Record<Variant, string> = {
        default:
            'bg-primary-solid text-primary-foreground shadow hover:bg-primary sm:hover:shadow-lg sm:hover:shadow-primary/25 active:scale-95 transition-all duration-150',
        secondary:
            'bg-secondary text-secondary-foreground shadow-2xs hover:bg-secondary/80 dark:hover:bg-[#3a3a3a] border border-border/70 active:scale-95 transition-all duration-150',
        tertiary:
            'border border-primary/35 bg-primary/15 text-foreground hover:bg-primary/25 hover:text-foreground dark:text-sky-100 dark:border-primary/40 font-semibold shadow-2xs transition-colors',
        ghost: 'hover:bg-muted dark:hover:bg-secondary/60 hover:text-foreground active:scale-95 transition-all duration-150',
        destructive:
            'bg-destructive text-destructive-foreground shadow hover:bg-destructive/90 sm:hover:shadow-lg sm:hover:shadow-destructive/25 active:scale-95 transition-all duration-150',
        success:
            'bg-emerald-700 text-white shadow hover:bg-emerald-800 sm:hover:shadow-lg sm:hover:shadow-emerald-700/25 active:scale-95 transition-all duration-150',
        warning:
            'bg-amber-700 text-white shadow hover:bg-amber-800 sm:hover:shadow-lg sm:hover:shadow-amber-700/25 active:scale-95 transition-all duration-150',
        outline:
            'border border-input bg-card hover:bg-muted dark:bg-secondary/40 dark:hover:bg-secondary/65 hover:text-foreground active:scale-95 transition-all duration-150',
        link: 'text-primary underline-offset-4 hover:underline',
        accent: 'bg-accent/80 text-accent-foreground border border-accent/40 shadow-2xs hover:bg-accent hover:border-accent/60 sm:hover:shadow-lg sm:hover:shadow-accent/25 active:scale-95 transition-all duration-150',
    };



    const sizes: Record<Size, string> = {
        default: 'h-9 px-4 py-2',
        sm: 'h-8 rounded-md px-3 text-xs',
        lg: 'h-10 rounded-md px-8',
        icon: 'h-9 w-9',
    };

    let {
        children,
        asChild = false,
        variant = 'default',
        size = 'default',
        class: className = '',
        type = 'button',
        ...rest
    }: {
        children?: Snippet<[AsChildProps]>;
        asChild?: boolean;
        variant?: Variant;
        size?: Size;
        class?: string;
        type?: 'button' | 'submit' | 'reset';
        [key: string]: unknown;
    } = $props();

    const classes = () => cn(base, variants[variant], sizes[size], className);
</script>

{#if asChild}
    {@render children?.({ class: classes(), ...rest })}
{:else}
    <button class={classes()} type={type} {...rest}>
        {@render children?.({})}
    </button>
{/if}
