<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import MinusCircle from 'lucide-svelte/icons/minus-circle';
    import PlusCircle from 'lucide-svelte/icons/plus-circle';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Pencil from 'lucide-svelte/icons/pencil';
    import type { PriceAdjustmentItem } from '@/components/adjustments/ManageAdjustmentModal.svelte';

    let {
        adjustments = [],
        onedit,
        ontoggle,
    }: {
        adjustments: PriceAdjustmentItem[];
        onedit: (adj: PriceAdjustmentItem) => void;
        ontoggle: (adj: PriceAdjustmentItem) => void;
    } = $props();

    const DAY_NAMES: Record<number, string> = {
        1: 'Lun',
        2: 'Mar',
        3: 'Mié',
        4: 'Jue',
        5: 'Vie',
        6: 'Sáb',
        7: 'Dom',
    };

    function formatValue(adj: PriceAdjustmentItem): string {
        const val = Number(adj.value) || 0;
        if (adj.calculation_type === 'percentage') {
            return `${val.toFixed(2)}%`;
        }
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(val);
    }

    function formatDateRange(adj: PriceAdjustmentItem): string {
        if (!adj.starts_at && !adj.ends_at) {
            return 'Vigencia permanente';
        }
        if (adj.starts_at && !adj.ends_at) {
            return `Desde ${adj.starts_at.slice(0, 10)}`;
        }
        if (!adj.starts_at && adj.ends_at) {
            return `Hasta ${adj.ends_at.slice(0, 10)}`;
        }
        return `${adj.starts_at?.slice(0, 10)} al ${adj.ends_at?.slice(0, 10)}`;
    }
</script>

<div class="space-y-3">
    {#each adjustments as adj (adj.id)}
        {@const isDiscount = adj.type === 'discount'}
        <Card
            role="button"
            tabindex="0"
            onclick={() => onedit(adj)}
            onkeydown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onedit(adj);
                }
            }}
            class={`group rounded-2xl border p-5 transition-all text-left select-none cursor-pointer ${
                adj.is_active
                    ? 'border-border/80 shadow-xs hover:border-primary/40 hover:shadow-md'
                    : 'border-border/40 opacity-75 bg-muted/15 hover:opacity-90'
            }`}
        >
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                <!-- Columna Izquierda: Badges, Nombre y Motivo -->
                <div class="space-y-2 flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Badge de Tipo: Descuento (-) o Recargo (+) expresado limpiamente sin redundancias -->
                        <span
                            class={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase ${
                                isDiscount
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20'
                                    : 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20'
                            }`}
                        >
                            {#if isDiscount}
                                <MinusCircle class="h-3.5 w-3.5" />
                                <span>Descuento</span>
                            {:else}
                                <PlusCircle class="h-3.5 w-3.5" />
                                <span>Recargo</span>
                            {/if}
                        </span>

                        <!-- Modalidad de Cálculo -->
                        <Badge variant="secondary" class="text-2xs font-medium tracking-wide">
                            {adj.calculation_type === 'percentage' ? 'Porcentaje' : 'Monto fijo'}
                        </Badge>

                        <!-- Indicador de Estado Activo / Pausado -->
                        <span class={`inline-flex items-center gap-1.5 text-2xs font-semibold ${
                            adj.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'
                        }`}>
                            <span class={`h-2 w-2 rounded-full ${adj.is_active ? 'bg-emerald-500' : 'bg-muted-foreground'}`}></span>
                            <span>{adj.is_active ? 'Activo' : 'Pausado'}</span>
                        </span>
                    </div>

                    <!-- Nombre del Ajuste -->
                    <h3 class="text-base font-bold text-foreground tracking-tight break-words">
                        {adj.name || 'Sin Nombre'}
                    </h3>

                    <!-- Motivo / Justificación -->
                    {#if adj.reason}
                        <p class="text-xs text-muted-foreground line-clamp-2 max-w-2xl">
                            {adj.reason}
                        </p>
                    {/if}
                </div>

                <!-- Columna Central / Derecha: Valor, Calendario y Acciones de Creación/Edición -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-6 lg:gap-8 pt-3 lg:pt-0 border-t lg:border-t-0 border-border/60">
                    <!-- Valor Destacado -->
                    <div class="text-left sm:text-right shrink-0">
                        <span class="text-2xs font-semibold text-muted-foreground uppercase tracking-wider block">
                            Valor
                        </span>
                        <span class={`text-2xl font-black tabular-nums tracking-tight ${
                            isDiscount ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'
                        }`}>
                            {formatValue(adj)}
                        </span>
                    </div>

                    <!-- Resumen de Vigencia y Días -->
                    <div class="space-y-1.5 text-xs text-muted-foreground min-w-[200px]">
                        <!-- Días recurrentes -->
                        <div class="flex items-center gap-1 flex-wrap">
                            {#if !adj.days_of_week || adj.days_of_week.length === 0}
                                <span class="text-2xs font-medium px-2 py-0.5 rounded-md bg-muted/60 text-foreground">
                                    Todos los días
                                </span>
                            {:else}
                                <div class="flex items-center gap-1">
                                    {#each [1, 2, 3, 4, 5, 6, 7] as dayId}
                                        {@const hasDay = adj.days_of_week.includes(dayId)}
                                        <span
                                            class={`h-5 w-5 rounded flex items-center justify-center text-2xs font-bold transition-all ${
                                                hasDay
                                                    ? 'bg-primary text-primary-foreground shadow-2xs'
                                                    : 'bg-muted/40 text-muted-foreground/30'
                                            }`}
                                            title={DAY_NAMES[dayId]}
                                        >
                                            {DAY_NAMES[dayId]?.slice(0, 1)}
                                        </span>
                                    {/each}
                                </div>
                            {/if}
                        </div>

                        <!-- Rango de fechas -->
                        <div class="flex items-center gap-1.5 text-2xs text-muted-foreground">
                            <Calendar class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span>{formatDateRange(adj)}</span>
                        </div>
                    </div>

                    <!-- Acciones Operativas (Exclusivamente Edición y Alternar Estado) -->
                    <div class="flex items-center gap-2 shrink-0 pt-2 sm:pt-0">
                        <Button
                            variant="secondary"
                            size="sm"
                            onclick={(e) => {
                                e.stopPropagation();
                                ontoggle(adj);
                            }}
                            class={`min-h-[38px] px-3 rounded-xl border text-xs font-semibold transition-all flex items-center gap-1.5 shadow-2xs cursor-pointer ${
                                adj.is_active
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/20'
                                    : 'bg-muted/40 text-muted-foreground border-border/80 hover:bg-muted'
                            }`}
                        >
                            <span>{adj.is_active ? 'Pausar' : 'Activar'}</span>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            onclick={(e) => {
                                e.stopPropagation();
                                onedit(adj);
                            }}
                            class="min-h-[38px] px-3.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 shadow-2xs cursor-pointer"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                            <span>Editar</span>
                        </Button>
                    </div>
                </div>
            </div>
        </Card>
    {/each}
</div>
