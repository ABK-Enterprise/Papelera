<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent } from '@/components/ui/card';
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

<div class="flex flex-col gap-3">
    {#each modules as mod (mod.id)}
        {@const iconName = getModuleIcon(mod.code)}

        <Card class="rounded-2xl border-border/70 bg-card p-4 shadow-2xs transition-all duration-200 hover:shadow-lg hover:shadow-accent/20">

            <CardContent class="p-0 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <IconContainer size="md" variant="primary" icon={iconName} />
                        <div class="min-w-0">
                            <h3 class="font-semibold text-foreground text-sm leading-snug truncate">{mod.name}</h3>
                            <Badge variant="outline" class="text-xs tracking-wide tabular-nums bg-muted/50 px-2 py-0.5 mt-0.5">
                                {mod.code}
                            </Badge>
                        </div>
                    </div>
                </div>

                {#if mod.description}
                    <p class="text-sm text-foreground line-clamp-2 leading-relaxed">
                        {mod.description}
                    </p>
                {/if}

                <div class="flex items-center justify-between border-t border-border/40 pt-3 text-xs tabular-nums">
                    <div class="flex items-center gap-1.5 text-muted-foreground font-medium">
                        <Icon icon="solar:shop-bold-duotone" class="size-4 text-muted-foreground/80" />
                        <span>{mod.tenants_count ?? 0} {mod.tenants_count === 1 ? 'comercio' : 'comercios'}</span>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="min-h-[44px] gap-1.5 rounded-xl text-sm font-medium border-border/70"
                        onclick={() => openDetail(mod)}
                    >
                        <Icon icon="solar:eye-bold-duotone" class="size-4" />
                        <span>Ver detalle</span>
                    </Button>
                </div>
            </CardContent>
        </Card>
    {/each}
</div>

<ModuleDetailDialog bind:open={isDialogOpen} module={selectedModule} />
