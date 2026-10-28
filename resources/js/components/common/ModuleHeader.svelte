<script lang="ts">
    import type { Snippet } from 'svelte';

    interface Props {
        title: string;
        badge?: string | number | null;
        badgeVariant?: 'default' | 'secondary' | 'outline';
        class?: string;
        tabs?: Snippet;
        actions?: Snippet;
        children?: Snippet;
    }

    let {
        title,
        badge = null,
        badgeVariant = 'secondary',
        class: className = '',
        tabs,
        actions,
        children,
    }: Props = $props();
</script>

<header class="flex flex-col gap-4 sm:gap-6 mb-6 {className}">
    <!-- Pestañas de sub-navegación opcionales (Proveedores, Stock, Cobranzas, etc.) -->
    {#if tabs}
        <div class="w-full overflow-x-auto">
            {@render tabs()}
        </div>
    {/if}

    <!-- Fila Principal: Título a la izquierda, Acciones a la derecha -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <!-- Título H1 Estándar (Sin iconos decorativos ni subtítulos redundantes) -->
        <div class="flex items-center gap-2.5 min-w-0">
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground truncate">
                {title}
            </h1>
            {#if badge !== null && badge !== undefined}
                <span class="rounded-full bg-primary/10 text-primary px-2.5 py-0.5 text-xs font-semibold tabular-nums border border-primary/20 shrink-0">
                    {badge}
                </span>
            {/if}
        </div>

        <!-- Acciones: Botones con touch target simétrico -->
        {#if actions || children}
            <div class="flex items-center justify-end gap-2.5 w-full sm:w-auto flex-wrap sm:flex-nowrap shrink-0">
                {@render actions?.()}
                {@render children?.()}
            </div>
        {/if}
    </div>
</header>
