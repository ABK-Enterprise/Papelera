<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import ModuleListDesktop from './desktop/ModuleListDesktop.svelte';
    import ModuleListMobile from './mobile/ModuleListMobile.svelte';
    import Boxes from 'lucide-svelte/icons/boxes';
    import Search from 'lucide-svelte/icons/search';
    import X from 'lucide-svelte/icons/x';

    interface PermissionItem {
        id: number;
        name: string;
        module_id: number;
    }

    interface ModuleItem {
        id: number;
        name: string;
        code: string;
        description: string | null;
        tenants_count?: number;
        permissions: PermissionItem[];
    }

    let { modules = [] }: { modules: ModuleItem[] } = $props();

    let searchQuery = $state('');

    let filteredModules = $derived.by(() => {
        const q = searchQuery.toLowerCase().trim();
        if (!q) return modules;

        return modules.filter((m) => {
            const matchName = m.name.toLowerCase().includes(q);
            const matchCode = m.code.toLowerCase().includes(q);
            const matchDesc = m.description?.toLowerCase().includes(q);
            const matchPerms = m.permissions?.some((p) => p.name.toLowerCase().includes(q));
            return matchName || matchCode || matchDesc || matchPerms;
        });
    });
</script>

<AppHead title="Módulos del sistema - Plataforma" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Header Minimalista -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-border/50 pb-5">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">
                    Módulos del sistema
                </h1>
                <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium tracking-wide tabular-nums text-primary border border-primary/20">
                    {modules.length} {modules.length === 1 ? 'módulo' : 'módulos'}
                </span>
            </div>
        </div>

        <!-- Buscador Rápido -->
        <div class="relative w-full sm:w-80">
            <Search class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
            <input
                type="text"
                bind:value={searchQuery}
                placeholder="Buscar por módulo o permiso..."
                class="w-full rounded-xl border border-border/70 bg-background pl-10 pr-9 py-2 text-xs sm:text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
            />
            {#if searchQuery}
                <button
                    type="button"
                    onclick={() => (searchQuery = '')}
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    aria-label="Limpiar búsqueda"
                >
                    <X class="size-4" />
                </button>
            {/if}
        </div>
    </div>

    <!-- Contenido Modular: Desktop vs Mobile -->
    {#if filteredModules.length > 0}
        <div class="hidden md:block">
            <ModuleListDesktop modules={filteredModules} />
        </div>
        <div class="block md:hidden">
            <ModuleListMobile modules={filteredModules} />
        </div>
    {:else}
        <EmptyState
            icon={Boxes}
            title="No se encontraron módulos"
            description={searchQuery ? `No hay módulos que coincidan con el término "${searchQuery}".` : 'No hay módulos configurados en la plataforma.'}
            actionLabel={searchQuery ? 'Limpiar búsqueda' : undefined}
            onaction={() => (searchQuery = '')}
        />
    {/if}
</div>
