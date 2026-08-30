<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import ModuleListDesktop from './desktop/ModuleListDesktop.svelte';
    import ModuleListMobile from './mobile/ModuleListMobile.svelte';
    import ModuleDetailDialog from './ModuleDetailDialog.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import Boxes from 'lucide-svelte/icons/boxes';

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
    let selectedDetailModule = $state<ModuleItem | null>(null);
    let isDetailModalOpen = $state(false);

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

    let comboboxSuggestions = $derived.by(() => {
        const q = searchQuery.toLowerCase().trim();
        if (!q) return [];
        return filteredModules;
    });

    function handleSelectModule(mod: ModuleItem) {
        selectedDetailModule = mod;
        isDetailModalOpen = true;
    }
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

        <!-- Buscador Rápido con SearchCombobox -->
        <div class="w-full sm:w-80">
            <SearchCombobox
                bind:value={searchQuery}
                items={comboboxSuggestions}
                placeholder="Buscar por módulo o permiso..."
                showDropdown={true}
                onsearch={(q) => (searchQuery = q)}
                onselect={handleSelectModule}
                onclear={() => (searchQuery = '')}
            >
                {#snippet itemSnippet(item)}
                    <div class="flex items-center justify-between gap-2 w-full">
                        <div class="min-w-0">
                            <span class="font-semibold text-foreground truncate block">{item.name}</span>
                            <span class="text-[11px] text-muted-foreground">{item.permissions?.length ?? 0} permisos asignados</span>
                        </div>
                        <span class="text-[10px] bg-muted/70 px-1.5 py-0.5 rounded text-muted-foreground tabular-nums shrink-0 font-medium">
                            {item.code}
                        </span>
                    </div>
                {/snippet}
            </SearchCombobox>
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

<!-- Modal Detalle de Módulo -->
<ModuleDetailDialog
    bind:open={isDetailModalOpen}
    module={selectedDetailModule}
/>

