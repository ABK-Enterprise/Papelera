<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import type { ShippingZoneItem } from '../ManageZoneModal.svelte';
    import Navigation from 'lucide-svelte/icons/navigation';
    import Percent from 'lucide-svelte/icons/percent';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import PackageCheck from 'lucide-svelte/icons/package-check';

    interface Props {
        zones: ShippingZoneItem[];
        onedit: (zone: ShippingZoneItem) => void;
        ondelete: (zone: ShippingZoneItem) => void;
    }

    let { zones, onedit, ondelete }: Props = $props();
</script>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    {#each zones as zone (zone.id)}
        <Card class="rounded-2xl border border-border/80 bg-card p-5 space-y-4 hover:shadow-lg hover:shadow-accent/20 transition-all duration-200">
            <!-- Grid de 2x2 según especificación de requerimientos -->
            <div class="grid grid-cols-2 gap-3">
                <!-- Fila 1 (Span 2): Nombre de la Zona + Badge de Estado -->
                <div class="col-span-2 flex items-start justify-between gap-3 border-b border-border/60 pb-3">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base sm:text-lg font-bold text-foreground truncate" title={zone.name}>
                            {zone.name}
                        </h3>
                        {#if zone.orders_count !== undefined && zone.orders_count > 0}
                            <p class="text-xs text-muted-foreground mt-0.5 flex items-center gap-1">
                                <PackageCheck class="size-3.5 text-primary" />
                                <span class="tabular-nums font-semibold">{zone.orders_count}</span> {zone.orders_count === 1 ? 'pedido entregado' : 'pedidos entregados'}
                            </p>
                        {/if}
                    </div>
                    <div>
                        {#if zone.is_active}
                            <Badge variant="outline" class="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold text-[11px] px-2.5 py-0.5 rounded-full">
                                Activa
                            </Badge>
                        {:else}
                            <Badge variant="outline" class="border-muted-foreground/30 bg-muted text-muted-foreground font-semibold text-[11px] px-2.5 py-0.5 rounded-full">
                                Inactiva
                            </Badge>
                        {/if}
                    </div>
                </div>

                <!-- Fila 2, Columna 1: Distancia (ej. 20 km) -->
                <div class="rounded-xl border border-border/80 bg-background/80 dark:bg-card/50 p-3 flex flex-col justify-center">
                    <span class="text-[11px] font-semibold text-muted-foreground flex items-center gap-1 uppercase tracking-wider">
                        <Navigation class="size-3 text-primary" />
                        Distancia
                    </span>
                    <span class="text-base font-bold tabular-nums text-foreground mt-1">
                        {Number(zone.distance_km).toFixed(1)} <span class="text-xs font-medium text-muted-foreground">km</span>
                    </span>
                </div>

                <!-- Fila 2, Columna 2: Porcentaje de Recargo (ej. 5%) -->
                <div class="rounded-xl border border-border/80 bg-background/80 dark:bg-card/50 p-3 flex flex-col justify-center">
                    <span class="text-[11px] font-semibold text-muted-foreground flex items-center gap-1 uppercase tracking-wider">
                        <Percent class="size-3 text-primary" />
                        Recargo
                    </span>
                    <span class="text-base font-bold tabular-nums text-primary mt-1">
                        +{Number(zone.surcharge_percentage).toFixed(2)}%
                    </span>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex items-center justify-end gap-2 pt-1 border-t border-border/40">
                <Button
                    variant="tertiary"
                    size="sm"
                    class="h-9 px-3.5 rounded-xl text-xs font-semibold gap-1.5"
                    onclick={() => onedit(zone)}
                >
                    <Pencil class="size-3.5" />
                    Editar zona
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-9 px-3 rounded-xl text-xs font-medium text-destructive hover:bg-destructive/10 hover:text-destructive gap-1.5"
                    onclick={() => ondelete(zone)}
                >
                    <Trash2 class="size-3.5" />
                    Eliminar
                </Button>
            </div>
        </Card>
    {/each}
</div>
