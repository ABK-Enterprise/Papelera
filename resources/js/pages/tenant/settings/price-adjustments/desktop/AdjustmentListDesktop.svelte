<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import MinusCircle from 'lucide-svelte/icons/minus-circle';
    import PlusCircle from 'lucide-svelte/icons/plus-circle';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Pencil from 'lucide-svelte/icons/pencil';
    import MoreHorizontal from 'lucide-svelte/icons/more-horizontal';
    import Power from 'lucide-svelte/icons/power';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import type { PriceAdjustmentItem } from '@/components/adjustments/ManageAdjustmentModal.svelte';

    let {
        adjustments = [],
        onedit,
        ontoggle,
        ondelete,
    }: {
        adjustments: PriceAdjustmentItem[];
        onedit: (adj: PriceAdjustmentItem) => void;
        ontoggle: (adj: PriceAdjustmentItem) => void;
        ondelete: (adj: PriceAdjustmentItem) => void;
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

    function formatDateParts(dateStr: string): string {
        const parts = dateStr.slice(0, 10).split('-');
        if (parts.length !== 3) return dateStr;
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function formatDateRange(adj: PriceAdjustmentItem): string {
        if (adj.specific_dates && adj.specific_dates.length > 0) {
            const count = adj.specific_dates.length;
            const start = adj.specific_dates[0].slice(0, 10);
            const end = adj.specific_dates[count - 1].slice(0, 10);
            if (start === end) {
                return `${formatDateParts(start)} (1 día)`;
            }
            return `${formatDateParts(start)} al ${formatDateParts(end)} (${count} días)`;
        }
        if (adj.starts_at && !adj.ends_at) {
            return `Desde ${formatDateParts(adj.starts_at)}`;
        }
        if (!adj.starts_at && adj.ends_at) {
            return `Hasta ${formatDateParts(adj.ends_at)}`;
        }
        if (adj.starts_at && adj.ends_at) {
            const start = adj.starts_at.slice(0, 10);
            const end = adj.ends_at.slice(0, 10);
            if (start === end) {
                return `${formatDateParts(start)} (1 día)`;
            }
            return `${formatDateParts(start)} al ${formatDateParts(end)}`;
        }
        return '';
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
            class={`relative group rounded-2xl border p-5 transition-all text-left select-none cursor-pointer ${
                adj.is_active
                    ? 'border-border/80 shadow-xs hover:border-primary/40 hover:shadow-md'
                    : 'border-border/80 dark:border-border/90 opacity-75 bg-muted/40 dark:bg-secondary/35 hover:opacity-90'
            }`}
        >
            <div class="flex items-center justify-between gap-5">
                <!-- Columna Izquierda: Badges, Nombre y Motivo -->
                <div class="space-y-2 flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Badge de Tipo: Descuento o Recargo -->
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

                        <!-- Indicador de Estado Activo / Pausado -->
                        <span class={`inline-flex items-center gap-1.5 text-2xs font-semibold ${
                            adj.is_active ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'
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

                <!-- Columna Central: Valor y Calendario -->
                <div class="flex items-center gap-6 lg:gap-8 shrink-0">
                    <!-- Valor Destacado -->
                    <div class="text-right shrink-0">
                        <span class="text-2xs font-semibold text-muted-foreground uppercase tracking-wider block">
                            Valor
                        </span>
                        <span class={`text-2xl font-black tabular-nums tracking-tight ${
                            isDiscount ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'
                        }`}>
                            {formatValue(adj)}
                        </span>
                    </div>

                    <!-- Resumen de Vigencia y Días -->
                    <div class="space-y-1.5 text-xs text-muted-foreground min-w-[190px]">
                        <!-- Días recurrentes -->
                        <div class="flex items-center gap-1 flex-wrap">
                            {#if !adj.days_of_week || adj.days_of_week.length === 0}
                                <span class="text-2xs font-medium px-2 py-0.5 rounded-md bg-muted/40 dark:bg-secondary/35 border border-border/60 text-foreground">
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
                                                    : 'bg-muted/40 dark:bg-secondary/35 text-muted-foreground'
                                            }`}
                                            title={DAY_NAMES[dayId]}
                                        >
                                            {DAY_NAMES[dayId]?.slice(0, 1)}
                                        </span>
                                    {/each}
                                </div>
                            {/if}
                        </div>

                        <!-- Rango de fechas (únicamente si existen fechas definidas) -->
                        {#if adj.specific_dates?.length || adj.starts_at || adj.ends_at}
                            <div class="flex items-center gap-1.5 text-2xs text-muted-foreground">
                                <Calendar class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                                <span>{formatDateRange(adj)}</span>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Columna Derecha de Acciones: Alineación vertical de '...' arriba y 'Editar' abajo -->
                <div class="flex flex-col items-end justify-between self-stretch shrink-0 gap-3" onclick={(e) => e.stopPropagation()}>
                    <!-- Menú de Opciones '...' con borde visible y affordance táctil -->
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            {#snippet children(props)}
                                <button
                                    {...props}
                                    type="button"
                                    onclick={(e) => {
                                        e.stopPropagation();
                                        props.onclick?.(e);
                                    }}
                                    class="size-8 rounded-xl border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 shadow-2xs hover:bg-muted/70 dark:hover:bg-secondary/55 hover:border-border text-muted-foreground hover:text-foreground transition-colors cursor-pointer flex items-center justify-center"
                                    aria-label="Más opciones"
                                >
                                    <MoreHorizontal class="size-4" />
                                </button>
                            {/snippet}
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48 shadow-lg">
                            <DropdownMenuItem
                                onclick={() => ontoggle(adj)}
                                class="flex items-center gap-2 cursor-pointer text-xs font-medium"
                            >
                                <Power class="size-4" />
                                <span>{adj.is_active ? 'Suspender ajuste' : 'Reactivar ajuste'}</span>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onclick={() => ondelete(adj)}
                                class="flex items-center gap-2 text-destructive focus:text-destructive cursor-pointer text-xs font-medium"
                            >
                                <Trash2 class="size-4" />
                                <span>Eliminar ajuste</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Acciones Operativas (Exclusivamente Edición) -->
                    <Button
                        variant="tertiary"
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
        </Card>
    {/each}
</div>
