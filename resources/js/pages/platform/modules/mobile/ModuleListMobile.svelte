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

<div class="flex flex-col gap-3">
    {#each modules as mod (mod.id)}
        {@const ModuleIcon = getModuleIcon(mod.code)}

        <Card class="rounded-2xl border-border/70 bg-card p-4 shadow-2xs">
            <CardContent class="p-0 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="size-11 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
                            <ModuleIcon class="size-5 stroke-[1.75]" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-foreground text-sm leading-snug truncate">{mod.name}</h3>
                            <Badge variant="outline" class="text-xs tracking-wide tabular-nums bg-muted/50 px-2 py-0.5 mt-0.5">
                                {mod.code}
                            </Badge>
                        </div>
                    </div>
                </div>

                {#if mod.description}
                    <p class="text-xs text-muted-foreground line-clamp-2 leading-relaxed">
                        {mod.description}
                    </p>
                {/if}

                <div class="flex items-center justify-between border-t border-border/40 pt-3 text-xs tabular-nums">
                    <div class="flex items-center gap-1.5 text-muted-foreground font-medium">
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
            </CardContent>
        </Card>
    {/each}
</div>

<ModuleDetailDialog bind:open={isDialogOpen} module={selectedModule} />
