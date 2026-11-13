<script lang="ts">
    import Monitor from 'lucide-svelte/icons/monitor';
    import Moon from 'lucide-svelte/icons/moon';
    import Sun from 'lucide-svelte/icons/sun';
    import type { Component, SvelteComponent } from 'svelte';
    import { themeState } from '@/lib/theme.svelte';
    import type { Appearance } from '@/types';

    const { appearance, updateAppearance } = themeState();

    type IconComponent =
        | Component<{ class?: string }>
        | (new (...args: any[]) => SvelteComponent<{ class?: string }>);

    const tabs: { value: Appearance; Icon: IconComponent; label: string }[] = [
        { value: 'light', Icon: Sun, label: 'Claro' },
        { value: 'dark', Icon: Moon, label: 'Oscuro' },
        { value: 'system', Icon: Monitor, label: 'Sistema' },
    ];

    function handleAppearanceChange(value: Appearance) {
        updateAppearance(value);
    }
</script>

<div
    class="inline-flex gap-1 rounded-lg bg-muted/80 dark:bg-secondary/35 border border-border/80 p-1"
>
    {#each tabs as { value, Icon, label } (value)}
        <button
            onclick={() => handleAppearanceChange(value)}
            class="flex items-center rounded-md px-3.5 py-1.5 transition-colors {appearance.value ===
            value
                ? 'bg-card dark:bg-secondary border border-border/70 text-foreground shadow-xs font-medium'
                : 'border border-transparent text-muted-foreground hover:bg-muted/60 dark:hover:bg-secondary/50 hover:text-foreground'}"
        >
            <Icon class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{label}</span>
        </button>
    {/each}
</div>
