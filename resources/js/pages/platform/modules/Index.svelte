<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import ModuleListDesktop from './desktop/ModuleListDesktop.svelte';
    import ModuleListMobile from './mobile/ModuleListMobile.svelte';
    import { router } from '@inertiajs/svelte';
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
        is_active: boolean;
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

    let togglingId = $state<number | null>(null);

    function toggleModuleActive(mod: ModuleItem) {
        togglingId = mod.id;
        router.patch(
            `/platform/modules/${mod.id}/toggle-active`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    togglingId = null;
                },
            }
        );
    }
</script>

<AppHead title="Gestión de Módulos - Plataforma" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                    Gestión de Módulos
                </h1>
                <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                    {modules.length} {modules.length === 1 ? 'módulo' : 'módulos'}
                </span>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Configura la disponibilidad global y visualiza los permisos asignados a cada componente del sistema.
            </p>
        </div>

        <!-- Buscador Rápido -->
        <div class="relative w-full sm:w-80">
            <Search class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
                type="text"
                bind:value={searchQuery}
                placeholder="Buscar por módulo o permiso..."
                class="w-full rounded-xl border border-border/70 bg-background pl-10 pr-9 py-2.5 text-xs sm:text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-hidden focus:ring-2 focus:ring-ring shadow-2xs"
            />
            {#if searchQuery}
                <button
                    type="button"
                    onclick={() => (searchQuery = '')}
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    aria-label="Limpiar búsqueda"
                >
                    <X class="h-4 w-4" />
                </button>
            {/if}
        </div>
    </div>

    <!-- Contenido Modular: Desktop vs Mobile -->
    {#if filteredModules.length > 0}
        <div class="hidden md:block">
            <ModuleListDesktop
                modules={filteredModules}
                {togglingId}
                ontoggle={toggleModuleActive}
            />
        </div>
        <div class="block md:hidden">
            <ModuleListMobile
                modules={filteredModules}
                {togglingId}
                ontoggle={toggleModuleActive}
            />
        </div>
    {:else}
        <EmptyState
            icon={Boxes}
            title="No se encontraron módulos"
            description={searchQuery ? `No hay módulos que coincidan con el término "${searchQuery}".` : 'No hay módulos disponibles en la plataforma.'}
            actionLabel={searchQuery ? 'Limpiar Búsqueda' : undefined}
            onaction={() => (searchQuery = '')}
        />
    {/if}
</div>
