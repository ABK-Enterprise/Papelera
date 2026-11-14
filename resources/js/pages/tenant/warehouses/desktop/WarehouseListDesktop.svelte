<script lang="ts" module>
    export interface WarehouseItem {
        id: number;
        branch_id?: number | null;
        branch?: { id: number; name: string } | null;
        name: string;
        code: string;
        address?: string | null;
        is_active: boolean;
        is_default: boolean;
        products_count?: number;
    }

    export interface BranchOption {
        id: number;
        name: string;
        code?: string;
    }
</script>

<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Building2 from 'lucide-svelte/icons/building-2';
    import IconContainer from '@/components/common/IconContainer.svelte';

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

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
    {#each warehouses as wh (wh.id)}
        <Card class="flex flex-col justify-between rounded-2xl border border-border/70 bg-card shadow-xs transition-all duration-200 hover:shadow-lg hover:shadow-accent/20 hover:border-border">

            <CardHeader class="pb-3.5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <IconContainer size="md" variant="primary" icon="solar:box-minimalistic-bold-duotone" />
                        <div>
                            <div class="flex items-center gap-2">
                                <CardTitle class="text-sm font-semibold text-foreground">{wh.name}</CardTitle>
                                {#if wh.is_default}
                                    <Badge variant="default" class="h-5 text-xs font-medium tracking-wide px-2">
                                        Principal
                                    </Badge>
                                {/if}
                                {#if !wh.is_active}
                                    <Badge variant="secondary" class="h-5 text-xs font-medium tracking-wide px-2">
                                        Inactivo
                                    </Badge>
                                {/if}
                            </div>
                            <CardDescription class="text-xs font-sans tabular-nums text-muted-foreground mt-0.5">
                                {wh.code}
                            </CardDescription>
                        </div>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="space-y-4 pt-0">
                <div class="space-y-1.5 rounded-xl border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 p-2.5 text-xs text-muted-foreground">
                    <div class="flex items-center justify-between">
                        <span class="font-medium flex items-center gap-1.5">
                            <Building2 class="h-3.5 w-3.5 text-muted-foreground" />
                            <span>Sucursal:</span>
                        </span>
                        <span class="font-semibold text-foreground truncate max-w-[180px] text-right text-sm">
                            {wh.branch?.name ? wh.branch.name : 'Independiente'}
                        </span>
                    </div>

                    {#if wh.address}
                        <div class="flex items-center justify-between pt-1 border-t border-border/30">
                            <span class="font-medium flex items-center gap-1.5">
                                <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                                <span>Dirección:</span>
                            </span>
                            <span class="truncate max-w-[180px] text-right text-sm text-muted-foreground">{wh.address}</span>
                        </div>
                    {/if}

                    <div class="flex items-center justify-between pt-1 border-t border-border/30">
                        <span class="font-medium">Existencias:</span>
                        <Badge variant="secondary" class="font-medium text-xs tracking-wide text-foreground px-2 py-0.5 tabular-nums">
                            {wh.products_count ?? 0} {wh.products_count === 1 ? 'producto' : 'productos'}
                        </Badge>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-border/40 pt-3 gap-2">
                    <Button
                        variant="tertiary"
                        onclick={() => onedit?.(wh)}
                        class="flex-1 inline-flex items-center justify-center min-h-[38px] w-full gap-1.5 rounded-xl text-sm font-medium"
                    >
                        <Edit2 class="h-3.5 w-3.5" />
                        <span>Editar depósito</span>
                    </Button>

                    {#if !wh.is_default}
                        <Button
                            variant="destructive"
                            size="sm"
                            onclick={() => ondelete?.(wh)}
                            class="min-h-[38px] min-w-[38px] px-3 gap-1.5 rounded-xl text-sm font-medium justify-center"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span class="sr-only">Eliminar depósito</span>
                        </Button>
                    {/if}
                </div>
            </CardContent>
        </Card>
    {/each}
</div>

