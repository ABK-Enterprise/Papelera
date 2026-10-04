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

<div class="space-y-3.5">
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
            class={`rounded-2xl border p-4 transition-all text-left select-none cursor-pointer flex flex-col justify-between gap-4 ${
                adj.is_active
                    ? 'border-border/80 shadow-xs active:scale-[0.99] bg-card'
                    : 'border-border/40 opacity-80 bg-muted/15'
            }`}
        >
            <!-- Cabecera Superior: Badges, Estado y Menú de Opciones '...' -->
            <div class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <!-- Badge de Tipo limpio sin redundancia textual -->
                        <span
                            class={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider ${
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
                        <span class={`inline-flex items-center gap-1.5 text-xs font-semibold ${
                            adj.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'
                        }`}>
                            <span class={`h-2 w-2 rounded-full ${adj.is_active ? 'bg-emerald-500' : 'bg-muted-foreground'}`}></span>
                            <span>{adj.is_active ? 'Activo' : 'Pausado'}</span>
                        </span>
                    </div>

                    <!-- Menú de Opciones '...' con borde visible y shadow-2xs -->
                    <div class="shrink-0 -mr-1" onclick={(e) => e.stopPropagation()}>
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
                                        class="size-8 rounded-xl border border-border/80 bg-background/80 shadow-2xs hover:bg-muted hover:border-border text-muted-foreground hover:text-foreground transition-colors cursor-pointer flex items-center justify-center"
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
                    </div>
                </div>

                <!-- Título y Valor en Bloque Elástico -->
                <div class="flex items-start justify-between gap-3 pt-1">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-bold text-foreground tracking-tight line-clamp-2 break-words">
                            {adj.name || 'Sin Nombre'}
                        </h3>
                        {#if adj.reason}
                            <p class="text-xs text-muted-foreground line-clamp-2 mt-1">
                                {adj.reason}
                            </p>
                        {/if}
                    </div>

                    <div class="text-right shrink-0">
                        <span class="text-2xs font-semibold text-muted-foreground uppercase tracking-wider block">
                            Valor
                        </span>
                        <span class={`text-xl font-black tabular-nums tracking-tight ${
                            isDiscount ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'
                        }`}>
                            {formatValue(adj)}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Fila de Calendario y Días Recurrentes en Mobile -->
            <div class="rounded-xl bg-muted/40 p-2.5 space-y-2 border border-border/40">
                {#if adj.specific_dates?.length || adj.starts_at || adj.ends_at}
                    <div class="flex items-center justify-between gap-2 flex-wrap text-2xs">
                        <div class="flex items-center gap-1.5 text-muted-foreground">
                            <Calendar class="h-3.5 w-3.5 shrink-0" />
                            <span class="font-medium">{formatDateRange(adj)}</span>
                        </div>
                    </div>
                {/if}

                <!-- Chips de Días -->
                <div class="flex items-center gap-1 flex-wrap pt-0.5">
                    {#if !adj.days_of_week || adj.days_of_week.length === 0}
                        <span class="text-2xs font-medium px-2 py-0.5 rounded bg-background text-foreground border border-border/60">
                            Aplica todos los días
                        </span>
                    {:else}
                        <div class="flex items-center gap-1">
                            {#each [1, 2, 3, 4, 5, 6, 7] as dayId}
                                {@const hasDay = adj.days_of_week.includes(dayId)}
                                <span
                                    class={`h-6 w-6 rounded-lg flex items-center justify-center text-xs font-bold ${
                                        hasDay
                                            ? 'bg-primary text-primary-foreground shadow-2xs'
                                            : 'bg-background/80 text-muted-foreground/30 border border-border/40'
                                    }`}
                                >
                                    {DAY_NAMES[dayId]?.slice(0, 1)}
                                </span>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Botonera Operativa Móvil (Exclusivamente Edición con min-h-[44px] A11y Touch Target y text-sm) -->
            <div class="pt-1 border-t border-border/40">
                <Button
                    variant="outline"
                    onclick={(e) => {
                        e.stopPropagation();
                        onedit(adj);
                    }}
                    class="min-h-[44px] w-full rounded-xl text-sm font-semibold flex items-center justify-center gap-2 border-primary/30 bg-primary/10 text-primary shadow-2xs"
                >
                    <Pencil class="h-4 w-4" />
                    <span>Editar</span>
                </Button>
            </div>
        </Card>
    {/each}
</div>
