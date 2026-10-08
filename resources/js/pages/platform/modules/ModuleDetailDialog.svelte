<script lang="ts">
    import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogBody, DialogFooter, DialogClose } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import Icon from '@iconify/svelte';
    import X from 'lucide-svelte/icons/x';
    import IconContainer from '@/components/common/IconContainer.svelte';

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
        is_active?: boolean;
        tenants_count?: number;
        permissions: PermissionItem[];
    }

    let {
        open = $bindable(false),
        module = null,
    }: {
        open: boolean;
        module: ModuleItem | null;
    } = $props();

    function getModuleIcon(code?: string): string {
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

{#if module}
    {@const iconName = getModuleIcon(module.code)}

    <Dialog bind:open>
        <DialogContent class="max-w-md w-[calc(100%-2rem)] mx-4 p-5 rounded-2xl sm:rounded-3xl border border-border/80 shadow-lg">
            <DialogHeader class="flex flex-row items-center justify-between pb-3 border-b border-border/50">
                <div class="flex items-center gap-3 min-w-0">
                    <IconContainer size="md" variant="primary" icon={iconName} />
                    <div class="min-w-0">
                        <DialogTitle class="text-base font-bold text-foreground leading-snug truncate">{module.name}</DialogTitle>
                        <Badge variant="outline" class="text-xs tracking-wide tabular-nums bg-muted/50 px-2 py-0.5 mt-0.5">
                            {module.code}
                        </Badge>
                    </div>
                </div>
                <DialogClose>
                    <X class="size-4" />
                </DialogClose>
            </DialogHeader>

            <DialogBody class="space-y-4 py-3 overflow-y-auto max-h-[60vh]">
                <!-- Descripción -->
                {#if module.description}
                    <div class="p-3.5 rounded-xl border border-border/60 bg-muted/20">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground block mb-1">Descripción</span>
                        <p class="text-sm text-foreground leading-relaxed">{module.description}</p>
                    </div>
                {/if}

                <!-- Cobertura -->
                <div class="flex items-center gap-2 text-xs text-muted-foreground p-3 rounded-xl border border-border/50 bg-muted/20 tabular-nums">
                    <Icon icon="solar:shop-bold-duotone" class="size-4 text-muted-foreground/80" />
                    <span>Contratado por: <strong class="text-foreground font-semibold">{module.tenants_count ?? 0}</strong> {module.tenants_count === 1 ? 'comercio' : 'comercios'}</span>
                </div>

                <!-- Permisos Granulares Asociados -->
                <div class="space-y-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground block">
                        Permisos asignados ({module.permissions.length})
                    </span>

                    <div class="flex flex-wrap gap-1.5 p-3 rounded-xl border border-border/50 bg-card max-h-40 overflow-y-auto">
                        {#each module.permissions as perm (perm.id)}
                            <span class="inline-flex items-center text-xs tabular-nums bg-muted/50 border border-border/60 text-foreground px-2.5 py-1 rounded-lg">
                                {perm.name}
                            </span>
                        {/each}

                        {#if module.permissions.length === 0}
                            <span class="text-xs italic text-muted-foreground">Sin permisos específicos</span>
                        {/if}
                    </div>
                </div>
            </DialogBody>

            <DialogFooter class="pt-3 border-t border-border/50">
                <DialogClose class="w-full" asChild>
                    {#snippet children({ onClick })}
                        <Button variant="outline" class="w-full min-h-[44px] rounded-xl text-sm font-medium" onclick={onClick}>
                            Cerrar
                        </Button>
                    {/snippet}
                </DialogClose>
            </DialogFooter>

        </DialogContent>
    </Dialog>
{/if}
