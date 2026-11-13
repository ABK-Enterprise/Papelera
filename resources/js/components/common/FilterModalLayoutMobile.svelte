<script lang="ts">
    import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogBody, DialogFooter } from '@/components/ui/dialog';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import type { Snippet, Component } from 'svelte';

    export interface FilterTabItem {
        id: string;
        label: string;
        icon?: Component<any>;
        isActive?: boolean;
        badge?: string | number | null;
    }

    let {
        open = $bindable(false),
        title = 'Filtros y ordenamiento',
        description = 'Selecciona las opciones para filtrar los registros.',
        tabs = [],
        activeTab = $bindable(''),
        activeCount = 0,
        onapply,
        onreset,
        children,
    }: {
        open?: boolean;
        title?: string;
        description?: string;
        tabs?: FilterTabItem[];
        activeTab?: string;
        activeCount?: number;
        onapply?: () => void;
        onreset?: () => void;
        children?: Snippet;
    } = $props();
</script>

<Dialog bind:open>
    <DialogContent class="max-w-xl p-0 gap-0 overflow-hidden rounded-2xl sm:rounded-3xl border-border/80 shadow-xl flex flex-col max-h-[85vh]">
        <DialogHeader class="p-4 sm:p-5 border-b border-border/50 shrink-0">
            <div class="flex items-center justify-between pr-6">
                <div class="flex items-center gap-2">
                    <DialogTitle class="text-base sm:text-lg font-bold text-foreground">{title}</DialogTitle>
                    {#if activeCount > 0}
                        <Badge variant="default" class="h-5 px-2 text-xs font-semibold tabular-nums">
                            {activeCount} {activeCount === 1 ? 'activo' : 'activos'}
                        </Badge>
                    {/if}
                </div>
            </div>
            <DialogDescription class="sr-only">{description || title}</DialogDescription>
        </DialogHeader>

        <DialogBody class="p-0 flex-1 min-h-0 overflow-hidden flex flex-row bg-card">
            <!-- Columna Izquierda: Pestañas de Navegación de Filtros -->
            <nav class="w-[36%] sm:w-[32%] shrink-0 border-r border-border/80 bg-muted/50 dark:bg-background/60 p-1.5 flex flex-col gap-1 overflow-y-auto custom-scrollbar">
                {#each tabs as tab (tab.id)}
                    {@const TabIcon = tab.icon}
                    <button
                        type="button"
                        onclick={() => (activeTab = tab.id)}
                        class="w-full flex items-center justify-between gap-1.5 px-2.5 py-3 rounded-xl text-left text-xs font-medium transition-all {activeTab === tab.id
                            ? 'bg-card dark:bg-secondary/70 text-foreground shadow-xs font-semibold border border-border/80'
                            : 'text-muted-foreground hover:bg-muted dark:hover:bg-secondary/40 hover:text-foreground'}"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            {#if TabIcon}
                                <TabIcon class="size-4 shrink-0 {activeTab === tab.id ? 'text-primary' : 'text-muted-foreground'}" />
                            {/if}
                            <span class="truncate">{tab.label}</span>
                        </div>
                        {#if tab.isActive}
                            <span class="h-1.5 w-1.5 rounded-full bg-primary shrink-0"></span>
                        {/if}
                    </button>
                {/each}
            </nav>

            <!-- Columna Derecha: Paneles de Contenido de Filtros -->
            <div class="flex-1 min-h-0 overflow-y-auto p-3 sm:p-4 space-y-1.5 custom-scrollbar">
                {@render children?.()}
            </div>
        </DialogBody>

        <DialogFooter class="p-3 sm:p-4 border-t border-border/50 bg-card flex flex-row items-center justify-between gap-2 shrink-0">

            <Button
                type="button"
                variant={activeCount > 0 ? 'outline' : 'ghost'}
                size="sm"
                onclick={onreset}
                disabled={activeCount === 0}
                class="min-h-[44px] px-3.5 rounded-xl text-xs font-semibold gap-1.5 transition-colors {activeCount > 0
                    ? 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30 hover:bg-emerald-500/20 dark:bg-blue-500/15 dark:text-blue-400 dark:border-blue-500/30 dark:hover:bg-blue-500/25 shadow-2xs'
                    : 'text-muted-foreground border-transparent cursor-not-allowed opacity-50'}"
            >
                <RotateCcw class="size-3.5" />
                <span>Limpiar</span>
            </Button>
            <Button
                type="button"
                size="sm"
                onclick={onapply}
                class="min-h-[44px] px-5 rounded-xl text-xs font-semibold shadow-sm"
            >
                <span>Aplicar filtros</span>
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
