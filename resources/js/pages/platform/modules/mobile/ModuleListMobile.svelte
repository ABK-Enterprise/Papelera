<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
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

<div class="flex flex-col gap-3">
    {#each modules as mod (mod.id)}
        {@const ModuleIcon = getModuleIcon(mod.code)}

        <Card class="rounded-2xl border-border/70 bg-card p-4 shadow-2xs transition-all active:scale-[0.99] {mod.is_active ? '' : 'opacity-75 bg-muted/20'}">
            <CardContent class="p-0 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <ModuleIcon class="h-6 w-6 stroke-[1.75]" />
                        </div>
                        <div class="flex flex-col">
                            <span class="font-semibold text-foreground text-sm leading-snug">{mod.name}</span>
                            <span class="text-xs tabular-nums text-muted-foreground">{mod.code}</span>
                        </div>
                    </div>

                    <span
                        class="px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide border {mod.is_active
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/25'
                            : 'bg-muted text-muted-foreground border-border/60'}"
                    >
                        {mod.is_active ? 'Activo' : 'Inactivo'}
                    </span>
                </div>

                {#if mod.description}
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        {mod.description}
                    </p>
                {/if}

                <div class="space-y-1.5 pt-1">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Permisos</span>
                        <span class="text-xs font-medium tabular-nums">{mod.permissions.length} disponibles</span>
                    </div>
                    <div class="flex flex-wrap gap-1 max-h-20 overflow-y-auto pr-1">
                        {#each mod.permissions as perm (perm.id)}
                            <span class="inline-flex items-center text-xs tabular-nums bg-muted/50 border border-border/50 text-foreground/80 px-2 py-0.5 rounded">
                                {perm.name}
                            </span>
                        {/each}
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-border/40 pt-3 gap-2">
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-medium tabular-nums">
                        <Shield class="h-3.5 w-3.5 text-muted-foreground/80" />
                        <span>{mod.tenants_count ?? 0} {mod.tenants_count === 1 ? 'comercio' : 'comercios'}</span>
                    </div>

                    <Button
                        type="button"
                        variant={mod.is_active ? 'outline' : 'default'}
                        class="min-h-[44px] gap-2 rounded-xl text-xs font-medium justify-center"
                        onclick={() => ontoggle(mod)}
                        disabled={togglingId === mod.id}
                    >
                        <Power class="h-4 w-4" />
                        <span>{mod.is_active ? 'Desactivar' : 'Activar'}</span>
                    </Button>
                </div>
            </CardContent>
        </Card>
    {/each}
</div>
