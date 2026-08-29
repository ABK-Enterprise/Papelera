<script lang="ts">
    let {
        title,
        description = '',
        variant = 'default',
    }: {
        title: string;
        description?: string;
        variant?: 'default' | 'small';
    } = $props();
</script>

<header class={variant === 'small' ? '' : 'mb-8 space-y-0.5'}>
    <h2
        class={variant === 'small'
            ? 'mb-0.5 text-base font-semibold leading-snug text-foreground'
            : 'text-xl font-bold tracking-tight leading-tight text-foreground'}
    >
        {title}
    </h2>
    {#if description}
        <p class="text-sm text-muted-foreground leading-relaxed">{description}</p>
    {/if}
</header>
