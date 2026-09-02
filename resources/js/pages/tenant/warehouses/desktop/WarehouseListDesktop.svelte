<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Link } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import WarehouseIcon from 'lucide-svelte/icons/warehouse';

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

    let {
        warehouses = [],
        oncreate,
        onedit,
        ondelete,
    }: {
        warehouses: WarehouseItem[];
        oncreate?: () => void;
        onedit?: (wh: WarehouseItem) => void;
        ondelete?: (wh: WarehouseItem) => void;
    } = $props();
</script>

<div class="hidden md:flex flex-col gap-6 w-full font-sans">
    <!-- Header Desktop -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
            <WarehouseIcon class="size-7 text-primary" />
            Gestión de Depósitos
        </h1>

        <div class="flex items-center gap-2.5">
            <Link href="/tenant/productos/buscar-deposito">
                <Button variant="outline" class="gap-1.5 font-medium shadow-2xs rounded-xl">
                    <WarehouseIcon class="size-4 text-muted-foreground" />
                    <span>Buscar en depósitos</span>
                </Button>
            </Link>

            <Button onclick={oncreate} class="min-h-[40px] px-4 gap-2 rounded-xl text-sm font-semibold shadow-xs">
                <Plus class="size-4" />
                <span>Nuevo depósito</span>
            </Button>
        </div>
    </div>

    <!-- Lista de Depósitos (Grid Desktop) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {#each warehouses as wh (wh.id)}
            <Card class="p-5 rounded-2xl border border-border/70 hover:border-primary/40 transition-all shadow-2xs flex flex-col justify-between gap-4 bg-card">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary shrink-0">
                                <WarehouseIcon class="size-6" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-base text-foreground leading-snug truncate" title={wh.name}>
                                    {wh.name}
                                </h3>
                                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                    <span class="font-mono text-xs text-muted-foreground font-semibold">
                                        {wh.code}
                                    </span>
                                    {#if wh.is_default}
                                        <Badge variant="default" class="text-[10px] font-bold py-0 h-4 bg-primary text-primary-foreground">
                                            Principal
                                        </Badge>
                                    {/if}
                                    {#if !wh.is_active}
                                        <Badge variant="secondary" class="text-[10px] text-muted-foreground py-0 h-4">
                                            Inactivo
                                        </Badge>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5 text-xs text-muted-foreground pt-1 border-t border-border/40">
                        <div class="flex items-center gap-2">
                            <Building2 class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="truncate">{wh.branch?.name ? `Sucursal: ${wh.branch.name}` : 'Sin sucursal asignada'}</span>
                        </div>
                        {#if wh.address}
                            <div class="flex items-center gap-2">
                                <MapPin class="size-3.5 text-muted-foreground shrink-0" />
                                <span class="truncate">{wh.address}</span>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Footer de la Card con Métricas y Acciones -->
                <div class="pt-3 border-t border-border/50 flex items-center justify-between -mx-5 -mb-5 px-5 py-3 bg-muted/20 rounded-b-2xl">
                    <div class="text-xs text-muted-foreground">
                        <span class="font-semibold tabular-nums text-foreground">{wh.products_count ?? 0}</span> productos almacenados
                    </div>

                    <div class="flex items-center gap-1.5">
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => onedit?.(wh)}
                            class="h-8 px-2.5 text-xs text-muted-foreground hover:text-foreground gap-1 rounded-lg"
                        >
                            <Edit2 class="size-3.5" />
                            Editar
                        </Button>

                        {#if !wh.is_default}
                            <Button
                                variant="ghost"
                                size="sm"
                                onclick={() => ondelete?.(wh)}
                                class="h-8 px-2.5 text-xs text-destructive hover:bg-destructive/10 gap-1 rounded-lg"
                            >
                                <Trash2 class="size-3.5" />
                                Eliminar
                            </Button>
                        {/if}
                    </div>
                </div>
            </Card>
        {/each}
    </div>
</div>
