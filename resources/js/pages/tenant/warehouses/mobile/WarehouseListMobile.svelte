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
    import type { WarehouseItem } from '../desktop/WarehouseListDesktop.svelte';

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

<div class="flex md:hidden flex-col gap-5 w-full font-sans">
    <!-- Header Mobile: Título y Acciones (Buscar en depósitos + Nuevo depósito) -->
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-2">
            <h1 class="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                <WarehouseIcon class="size-6 text-primary" />
                <span>Gestión de Depósitos</span>
            </h1>
        </div>

        <div class="flex items-center justify-end gap-2">
            <Link href="/tenant/productos/buscar-deposito">
                <Button variant="outline" size="sm" class="min-h-[38px] px-3 gap-1.5 rounded-xl text-xs font-semibold shadow-2xs border-border/70">
                    <WarehouseIcon class="size-3.5 text-muted-foreground" />
                    <span>Buscar en depósito</span>
                </Button>
            </Link>

            <Button onclick={oncreate} size="sm" class="min-h-[38px] px-3.5 gap-1.5 rounded-xl text-xs font-semibold shadow-xs">
                <Plus class="size-3.5" />
                <span>Nuevo depósito</span>
            </Button>
        </div>
    </div>

    <!-- Lista de Cards en Mobile -->
    <div class="flex flex-col gap-3.5">
        {#each warehouses as wh (wh.id)}
            <Card class="p-4 sm:p-5 rounded-2xl border border-border/70 shadow-xs flex flex-col justify-between gap-3.5 bg-card">
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
                                        <Badge variant="default" class="text-[10px] font-bold py-0.5 px-2 bg-primary text-primary-foreground">
                                            Principal
                                        </Badge>
                                    {/if}
                                    {#if !wh.is_active}
                                        <Badge variant="secondary" class="text-[10px] text-muted-foreground py-0.5 px-2">
                                            Inactivo
                                        </Badge>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5 text-xs text-muted-foreground pt-2 border-t border-border/40">
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

                <!-- Footer de la Card Mobile -->
                <div class="pt-3 border-t border-border/50 flex items-center justify-between -mx-4 -mb-4 px-4 py-3 bg-muted/20 rounded-b-2xl">
                    <div class="text-xs text-muted-foreground">
                        <span class="font-semibold tabular-nums text-foreground">{wh.products_count ?? 0}</span> productos almacenados
                    </div>

                    <div class="flex items-center gap-1.5">
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => onedit?.(wh)}
                            class="min-h-[36px] px-3 text-xs text-muted-foreground hover:text-foreground gap-1.5 rounded-xl border border-border/40 bg-background/50"
                        >
                            <Edit2 class="size-3.5" />
                            <span>Editar</span>
                        </Button>

                        {#if !wh.is_default}
                            <Button
                                variant="ghost"
                                size="sm"
                                onclick={() => ondelete?.(wh)}
                                class="min-h-[36px] px-2.5 text-xs text-destructive hover:bg-destructive/10 gap-1 rounded-xl"
                            >
                                <Trash2 class="size-3.5" />
                                <span class="sr-only">Eliminar</span>
                            </Button>
                        {/if}
                    </div>
                </div>
            </Card>
        {/each}
    </div>
</div>

