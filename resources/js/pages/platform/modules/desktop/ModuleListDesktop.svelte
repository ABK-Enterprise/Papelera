<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import Boxes from 'lucide-svelte/icons/boxes';
    import Package from 'lucide-svelte/icons/package';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Truck from 'lucide-svelte/icons/truck';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import FileText from 'lucide-svelte/icons/file-text';
    import Layers from 'lucide-svelte/icons/layers';
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

    let {
        modules = [],
        togglingId = null,
        ontoggle,
    }: {
        modules: ModuleItem[];
        togglingId?: number | null;
        ontoggle: (mod: ModuleItem) => void;
    } = $props();

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
</script>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    {#each modules as mod (mod.id)}
        {@const ModuleIcon = getModuleIcon(mod.code)}

        <div
            class="bg-card rounded-2xl p-6 shadow-xs border border-border/70 relative overflow-hidden flex flex-col justify-between transition-all duration-200 hover:shadow-md hover:border-border {mod.is_active ? '' : 'opacity-70 bg-muted/20'}"
        >
            <!-- Cabecera de la Card -->
            <div>
                <div class="flex justify-between items-start mb-4">
                    <div class="h-12 w-12 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
                        <ModuleIcon class="h-6 w-6 stroke-[1.75]" />
                    </div>

                    <div class="flex items-center gap-2">
                        <span
                            class="px-2.5 py-1 rounded-full text-xs font-semibold border {mod.is_active
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
                        <h3 class="text-base font-bold text-foreground">
                            {mod.name}
                        </h3>
                        <Badge variant="outline" class="font-mono text-[11px] bg-muted/50 px-2">
                            {mod.code}
                        </Badge>
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
                        Permisos Granulares ({mod.permissions.length})
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
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-medium">
                    <Shield class="h-3.5 w-3.5 text-muted-foreground/80" />
                    <span>{mod.tenants_count ?? 0} {mod.tenants_count === 1 ? 'comercio' : 'comercios'}</span>
                </div>

                <Button
                    type="button"
                    variant={mod.is_active ? 'outline' : 'default'}
                    size="sm"
                    onclick={() => ontoggle(mod)}
                    disabled={togglingId === mod.id}
                    class="min-h-[40px] gap-1.5 rounded-xl text-xs font-semibold transition-colors"
                >
                    <Power class="h-3.5 w-3.5" />
                    <span>{mod.is_active ? 'Desactivar' : 'Activar'}</span>
                </Button>
            </div>
        </div>
    {/each}
</div>
