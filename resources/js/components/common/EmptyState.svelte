<script lang="ts">
    import type { Component, Snippet } from 'svelte';
    import { Button } from '@/components/ui/button';
    import { Link } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';

    let {
        icon: Icon,
        title = 'No hay registros disponibles',
        description = 'Comienza agregando un nuevo elemento para comenzar a operar.',
        actionLabel,
        actionHref,
        onaction,
        class: className = '',
        action,
    }: {
        icon?: Component<any>;
        title?: string;
        description?: string;
        actionLabel?: string;
        actionHref?: string;
        onaction?: () => void;
        class?: string;
        action?: Snippet;
    } = $props();
</script>

<div
    class={cn(
        'flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/80 bg-card/80 dark:bg-secondary/20 p-8 md:p-12 text-center transition-all',
        className
    )}
>
    {#if Icon}
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted dark:bg-secondary/50 text-muted-foreground shadow-xs ring-1 ring-border/60">
            <Icon class="h-7 w-7 stroke-[1.75]" />
        </div>
    {/if}

    <h3 class="text-base md:text-lg font-semibold tracking-tight text-foreground">
        {title}
    </h3>

    {#if description}
        <p class="mt-1.5 max-w-sm text-sm text-muted-foreground leading-relaxed">
            {description}
        </p>
    {/if}

    {#if action}
        <div class="mt-5">
            {@render action()}
        </div>
    {:else if actionLabel}
        <div class="mt-5">
            {#if actionHref}
                <Link href={actionHref}>
                    <Button class="min-h-[44px] px-5 rounded-xl font-medium shadow-sm transition-all hover:scale-[1.01] active:scale-[0.99]">
                        {actionLabel}
                    </Button>
                </Link>
            {:else if onaction}
                <Button
                    onclick={onaction}
                    class="min-h-[44px] px-5 rounded-xl font-medium shadow-sm transition-all hover:scale-[1.01] active:scale-[0.99]"
                >
                    {actionLabel}
                </Button>
            {/if}
        </div>
    {/if}
</div>
