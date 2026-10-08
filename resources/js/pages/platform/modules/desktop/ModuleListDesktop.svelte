<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import Icon from '@iconify/svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
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

    function getModuleIcon(code: string): string {
        switch (code) {
            case 'productos':
                return 'solar:box-minimalistic-bold-duotone';
            case 'stock':
                return 'solar:archive-minimalistic-bold-duotone';
            case 'pedidos':
                return 'solar:bill-list-bold-duotone';
            case 'proveedores':
                return 'solar:delivery-bold-duotone';
            case 'clientes':
                return 'solar:shop-bold-duotone';
            case 'facturacion_arca':
                return 'solar:document-text-bold-duotone';
            default:
                return 'solar:layers-minimalistic-bold-duotone';
        }
    }
</script>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    {#each modules as mod (mod.id)}
        {@const iconName = getModuleIcon(mod.code)}

        <div class="bg-card rounded-2xl p-5 shadow-2xs border border-border/70 flex flex-col justify-between transition-all duration-200 hover:shadow-lg hover:shadow-accent/20 hover:border-border">

            <div>
                <div class="flex justify-between items-start mb-3.5">
                    <IconContainer size="md" variant="primary" icon={iconName} />

                    <Badge variant="outline" class="text-xs font-mono tracking-wide tabular-nums bg-muted/50 px-2 py-0.5">
                        {mod.code}
                    </Badge>
                </div>

                <div class="space-y-1.5">
                    <h3 class="text-base font-semibold leading-snug text-foreground">
                        {mod.name}
                    </h3>

                    {#if mod.description}
                        <p class="text-sm text-foreground line-clamp-2 leading-relaxed">
                            {mod.description}
                        </p>
                    {/if}
                </div>
            </div>

            <!-- Footer con métrica y botón Ver -->
            <div class="mt-4 pt-3.5 border-t border-border/50 flex items-center justify-between">
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-medium tabular-nums">
                    <Icon icon="solar:shop-bold-duotone" class="size-4 text-muted-foreground/80" />
                    <span>{mod.tenants_count ?? 0} {mod.tenants_count === 1 ? 'comercio' : 'comercios'}</span>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="min-h-[38px] gap-1.5 rounded-xl text-sm font-medium border-border/70"
                    onclick={() => openDetail(mod)}
                >
                    <Icon icon="solar:eye-bold-duotone" class="size-4" />
                    <span>Ver detalle</span>
                </Button>
            </div>
        </div>
    {/each}
</div>

<ModuleDetailDialog bind:open={isDialogOpen} module={selectedModule} />
