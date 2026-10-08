<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Card, CardContent } from '@/components/ui/card';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Building2 from 'lucide-svelte/icons/building-2';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import type { WarehouseItem } from '../desktop/WarehouseListDesktop.svelte';

    let {
        warehouses = [],
        onedit,
        ondelete,
    }: {
        warehouses: WarehouseItem[];
        onedit?: (wh: WarehouseItem) => void;
        ondelete?: (wh: WarehouseItem) => void;
    } = $props();
</script>

<div class="flex flex-col gap-3">
    {#each warehouses as wh (wh.id)}
        <Card class="rounded-2xl border-border/70 bg-card p-4 shadow-2xs transition-all duration-200 hover:shadow-lg hover:shadow-accent/20 active:scale-[0.99]">

            <CardContent class="p-0 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <IconContainer size="md" variant="primary" icon="solar:box-minimalistic-bold-duotone" />
                        <div>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-sm font-semibold text-foreground">{wh.name}</span>
                                {#if wh.is_default}
                                    <Badge variant="default" class="h-5 text-xs font-medium px-2">
                                        Principal
                                    </Badge>
                                {/if}
                                {#if !wh.is_active}
                                    <Badge variant="secondary" class="h-5 text-xs font-medium px-2">
                                        Inactivo
                                    </Badge>
                                {/if}
                            </div>
                            <span class="text-xs font-sans tabular-nums text-muted-foreground mt-0.5 block">
                                {wh.code}
                            </span>
                        </div>
                    </div>
                    <Badge variant="secondary" class="h-5 text-xs font-medium tracking-wide px-2 tabular-nums shrink-0">
                        {wh.products_count ?? 0} {wh.products_count === 1 ? 'prod.' : 'prods.'}
                    </Badge>
                </div>

                <div class="space-y-1 rounded-xl bg-muted/40 p-2.5 text-xs text-muted-foreground">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5 font-medium">
                            <Building2 class="h-3 w-3 text-muted-foreground" />
                            <span>Sucursal:</span>
                        </span>
                        <span class="font-medium text-foreground">{wh.branch?.name ?? 'Independiente'}</span>
                    </div>
                    {#if wh.address}
                        <div class="flex items-center justify-between pt-1 border-t border-border/30">
                            <span class="flex items-center gap-1.5 font-medium">
                                <MapPin class="h-3 w-3 text-muted-foreground" />
                                <span>Dirección:</span>
                            </span>
                            <span class="truncate max-w-[180px]">{wh.address}</span>
                        </div>
                    {/if}
                </div>

                <div class="flex items-center justify-between border-t border-border/40 pt-3 gap-2">
                    <Button
                        variant="tertiary"
                        onclick={() => onedit?.(wh)}
                        class="flex-1 inline-flex items-center justify-center min-h-[44px] w-full gap-2 rounded-xl text-sm font-medium"
                    >
                        <Edit2 class="h-4 w-4" />
                        <span>Editar depósito</span>
                    </Button>

                    {#if !wh.is_default}
                        <Button
                            variant="destructive"
                            class="min-h-[44px] min-w-[44px] px-3.5 gap-2 rounded-xl text-sm font-medium justify-center"
                            onclick={() => ondelete?.(wh)}
                        >
                            <Trash2 class="h-4 w-4" />
                            <span class="sr-only">Eliminar depósito</span>
                        </Button>
                    {/if}
                </div>
            </CardContent>
        </Card>
    {/each}
</div>


