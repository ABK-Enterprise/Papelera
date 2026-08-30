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
    import Eye from 'lucide-svelte/icons/eye';
    import ModuleDetailDialog from '../ModuleDetailDialog.svelte';

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

    let selectedModule = $state<ModuleItem | null>(null);
    let isDialogOpen = $state(false);

    function openDetail(mod: ModuleItem) {
        selectedModule = mod;
        isDialogOpen = true;
    }

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

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    {#each modules as mod (mod.id)}
        {@const ModuleIcon = getModuleIcon(mod.code)}

        <div class="bg-card rounded-2xl p-5 shadow-2xs border border-border/70 flex flex-col justify-between transition-all hover:border-border hover:shadow-xs">
            <div>
                <div class="flex justify-between items-start mb-3.5">
                    <div class="size-11 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
                        <ModuleIcon class="size-5 stroke-[1.75]" />
                    </div>

                    <Badge variant="outline" class="text-xs tracking-wide tabular-nums bg-muted/50 px-2 py-0.5">
                        {mod.code}
                    </Badge>
                </div>

                <div class="space-y-1.5">
                    <h3 class="text-base font-semibold leading-snug text-foreground">
                        {mod.name}
                    </h3>

                    {#if mod.description}
                        <p class="text-xs text-muted-foreground line-clamp-2 leading-relaxed">
                            {mod.description}
                        </p>
                    {/if}
                </div>
            </div>

            <!-- Footer con métrica y botón Ver -->
            <div class="mt-5 pt-3 border-t border-border/40 flex items-center justify-between">
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-medium tabular-nums">
                    <Shield class="size-3.5 text-muted-foreground/80" />
                    <span>{mod.tenants_count ?? 0} {mod.tenants_count === 1 ? 'comercio' : 'comercios'}</span>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="min-h-[38px] gap-1.5 rounded-xl text-xs font-medium border-border/70"
                    onclick={() => openDetail(mod)}
                >
                    <Eye class="size-4" />
                    <span>Ver detalle</span>
                </Button>
            </div>
        </div>
    {/each}
</div>

<ModuleDetailDialog bind:open={isDialogOpen} module={selectedModule} />
