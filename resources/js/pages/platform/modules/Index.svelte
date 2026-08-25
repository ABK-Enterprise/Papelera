<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { router } from '@inertiajs/svelte';
    import Boxes from 'lucide-svelte/icons/boxes';
    import Package from 'lucide-svelte/icons/package';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Truck from 'lucide-svelte/icons/truck';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import FileText from 'lucide-svelte/icons/file-text';
    import Layers from 'lucide-svelte/icons/layers';
    import Search from 'lucide-svelte/icons/search';
    import Shield from 'lucide-svelte/icons/shield';
    import Power from 'lucide-svelte/icons/power';

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

    function getModuleIcon(code: string) {
        switch (code) {
            case 'productos':
                return Package;
            case 'stock':
                return Boxes;
            case 'pedidos':
                return ShoppingBag;
            case 'proveedores':
                return Truck;
            case 'clientes':
                return UserCheck;
            case 'facturacion_arca':
                return FileText;
            default:
                return Layers;
        }
    }

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
    <!-- Header Minimalista y Directo -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border/40 pb-5">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">
                Gestión de Módulos
            </h1>
            <p class="text-sm text-muted-foreground mt-1 max-w-2xl">
                Configura la disponibilidad global y visualiza los permisos granulares asignados a cada componente del sistema.
            </p>
        </div>

        <!-- Buscador Rápido -->
        <div class="relative w-full sm:w-72">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
                type="text"
                bind:value={searchQuery}
                placeholder="Buscar módulo o permiso..."
                class="h-9 w-full rounded-lg border border-input bg-muted/30 pl-9 pr-3 text-xs placeholder:text-muted-foreground focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary transition-all"
            />
        </div>
    </div>

    <!-- Grid de Cards Minimalistas (Inspirado en stitch design sin sobrecarga) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {#each filteredModules as mod (mod.id)}
            {@const ModuleIcon = getModuleIcon(mod.code)}

            <div
                class="bg-card rounded-[24px] p-6 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] border border-border/60 relative overflow-hidden flex flex-col justify-between transition-all duration-300 hover:shadow-[0px_15px_50px_rgba(0,0,0,0.05)] hover:border-border {mod.is_active ? '' : 'opacity-70 bg-muted/20'}"
            >
                <!-- Cabecera de la Card -->
                <div>
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-muted/80 border border-border/50 flex items-center justify-center text-foreground shrink-0">
                            <ModuleIcon class="size-6 stroke-[1.75]" />
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                class="px-3 py-1 rounded-full text-xs font-semibold border {mod.is_active
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/25'
                                    : 'bg-muted text-muted-foreground border-border/60'}"
                            >
                                {mod.is_active ? 'Activo' : 'Desactivado'}
                            </span>
                        </div>
                    </div>

                    <!-- Título y Descripción -->
                    <div class="mt-2">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-lg font-bold text-foreground">
                                {mod.name}
                            </h3>
                            <span class="font-mono text-[11px] text-muted-foreground bg-muted/60 px-2 py-0.5 rounded border border-border/40">
                                {mod.code}
                            </span>
                        </div>

                        {#if mod.description}
                            <p class="text-xs text-muted-foreground mt-2 line-clamp-2 leading-relaxed">
                                {mod.description}
                            </p>
                        {/if}
                    </div>

                    <!-- Permisos Granulares Asociados -->
                    <div class="mt-4 pt-3 border-t border-border/40">
                        <span class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider block mb-2">
                            Permisos del Módulo ({mod.permissions.length})
                        </span>

                        <div class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto pr-1">
                            {#each mod.permissions as perm (perm.id)}
                                <span class="inline-flex items-center font-mono text-[11px] bg-muted/40 border border-border/50 text-foreground/85 px-2 py-0.5 rounded-md">
                                    {perm.name}
                                </span>
                            {/each}

                            {#if mod.permissions.length === 0}
                                <span class="text-xs italic text-muted-foreground">
                                    Sin permisos definidos
                                </span>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Footer de la Card con Control de Activación/Desactivación -->
                <div class="mt-5 pt-3.5 border-t border-border/40 flex items-center justify-between">
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Shield class="size-3.5 text-muted-foreground/80" />
                        <span>{mod.tenants_count ?? 0} {mod.tenants_count === 1 ? 'comercio' : 'comercios'}</span>
                    </div>

                    <button
                        type="button"
                        onclick={() => toggleModuleActive(mod)}
                        disabled={togglingId === mod.id}
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl transition-colors border {mod.is_active
                            ? 'bg-muted/40 hover:bg-destructive/10 text-muted-foreground hover:text-destructive border-border/60 hover:border-destructive/30'
                            : 'bg-primary/10 hover:bg-primary/20 text-primary border-primary/25'}"
                    >
                        <Power class="size-3" />
                        {mod.is_active ? 'Desactivar' : 'Activar'}
                    </button>
                </div>
            </div>
        {/each}

        {#if filteredModules.length === 0}
            <div class="col-span-full rounded-2xl border border-dashed border-border/60 p-12 text-center bg-muted/10">
                <Boxes class="mx-auto h-10 w-10 text-muted-foreground/40 mb-3" />
                <h3 class="font-semibold text-base text-foreground">No se encontraron módulos</h3>
                <p class="text-xs text-muted-foreground mt-1 max-w-sm mx-auto">
                    No hay módulos que coincidan con el término "{searchQuery}".
                </p>
            </div>
        {/if}
    </div>
</div>
