<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import type { ShippingZoneItem } from '../ManageZoneModal.svelte';
    import Navigation from 'lucide-svelte/icons/navigation';
    import Percent from 'lucide-svelte/icons/percent';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Trash2 from 'lucide-svelte/icons/trash-2';

    interface Props {
        zones: ShippingZoneItem[];
        onedit: (zone: ShippingZoneItem) => void;
        ondelete: (zone: ShippingZoneItem) => void;
    }

    let { zones, onedit, ondelete }: Props = $props();
</script>

<div class="space-y-3">
    {#each zones as zone (zone.id)}
        <Card class="rounded-2xl border border-border/80 bg-card p-4 space-y-3.5 shadow-xs">
            <!-- Grid de 2x2 en Mobile -->
            <div class="grid grid-cols-2 gap-2.5">
                <!-- Fila 1: Nombre y Badge -->
                <div class="col-span-2 flex items-center justify-between gap-2 border-b border-border/60 pb-2.5">
                    <h3 class="text-base font-bold text-foreground truncate">
                        {zone.name}
                    </h3>
                    {#if zone.is_active}
                        <Badge variant="outline" class="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold text-[10px] px-2 py-0.5 rounded-full">
                            Activa
                        </Badge>
                    {:else}
                        <Badge variant="outline" class="border-muted-foreground/30 bg-muted text-muted-foreground font-semibold text-[10px] px-2 py-0.5 rounded-full">
                            Inactiva
                        </Badge>
                    {/if}
                </div>

                <!-- Fila 2, Col 1: Distancia -->
                <div class="rounded-xl border border-border/80 bg-background/80 dark:bg-background-900/60 p-2.5 flex flex-col justify-center">
                    <span class="text-[10px] font-semibold text-muted-foreground flex items-center gap-1 uppercase tracking-wider">
                        <Navigation class="size-3 text-primary" />
                        Distancia
                    </span>
                    <span class="text-sm font-bold tabular-nums text-foreground mt-0.5">
                        {Number(zone.distance_km).toFixed(1)} <span class="text-[11px] font-normal text-muted-foreground">km</span>
                    </span>
                </div>

                <!-- Fila 2, Col 2: Recargo -->
                <div class="rounded-xl border border-border/80 bg-background/80 dark:bg-background-900/60 p-2.5 flex flex-col justify-center">
                    <span class="text-[10px] font-semibold text-muted-foreground flex items-center gap-1 uppercase tracking-wider">
                        <Percent class="size-3 text-primary" />
                        Recargo
                    </span>
                    <span class="text-sm font-bold tabular-nums text-primary mt-0.5">
                        +{Number(zone.surcharge_percentage).toFixed(2)}%
                    </span>
                </div>
            </div>

            <!-- Acciones Mobile -->
            <div class="grid grid-cols-2 gap-2 pt-1 border-t border-border/40">
                <Button
                    variant="tertiary"
                    size="sm"
                    class="h-10 rounded-xl text-xs font-semibold gap-1.5 w-full min-h-[40px]"
                    onclick={() => onedit(zone)}
                >
                    <Pencil class="size-3.5" />
                    Editar
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-10 rounded-xl text-xs font-medium text-destructive hover:bg-destructive/10 hover:text-destructive gap-1.5 w-full min-h-[40px]"
                    onclick={() => ondelete(zone)}
                >
                    <Trash2 class="size-3.5" />
                    Eliminar
                </Button>
            </div>
        </Card>
    {/each}
</div>
