<script lang="ts">
    import { Label } from '@/components/ui/label';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import CalendarIcon from 'lucide-svelte/icons/calendar';

    let {
        startsAt = $bindable(''),
        endsAt = $bindable(''),
        daysOfWeek = $bindable<number[]>([]),
        disabled = false,
    }: {
        startsAt?: string;
        endsAt?: string;
        daysOfWeek?: number[];
        disabled?: boolean;
    } = $props();

    const WEEK_DAYS = [
        { id: 1, short: 'L', label: 'Lun', name: 'Lunes' },
        { id: 2, short: 'M', label: 'Mar', name: 'Martes' },
        { id: 3, short: 'M', label: 'Mié', name: 'Miércoles' },
        { id: 4, short: 'J', label: 'Jue', name: 'Jueves' },
        { id: 5, short: 'V', label: 'Vie', name: 'Viernes' },
        { id: 6, short: 'S', label: 'Sáb', name: 'Sábado' },
        { id: 7, short: 'D', label: 'Dom', name: 'Domingo' },
    ];

    // Current displayed month in preview
    let viewYear = $state(new Date().getFullYear());
    let viewMonth = $state(new Date().getMonth()); // 0-indexed

    const monthNames = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    function toggleDay(dayId: number) {
        if (disabled) return;
        if (daysOfWeek.includes(dayId)) {
            daysOfWeek = daysOfWeek.filter(d => d !== dayId);
        } else {
            daysOfWeek = [...daysOfWeek, dayId].sort((a, b) => a - b);
        }
    }

    function handleCellClick(cell: CalendarCell) {
        if (disabled || !cell.isCurrentMonth) return;
        toggleDay(cell.isoDay);
    }

    function prevMonth() {
        if (viewMonth === 0) {
            viewMonth = 11;
            viewYear -= 1;
        } else {
            viewMonth -= 1;
        }
    }

    function nextMonth() {
        if (viewMonth === 11) {
            viewMonth = 0;
            viewYear += 1;
        } else {
            viewMonth += 1;
        }
    }

    // Days grid for the preview month
    interface CalendarCell {
        dayNumber: number;
        dateStr: string;
        isoDay: number;
        isCurrentMonth: boolean;
        isEffective: boolean;
        isToday: boolean;
    }

    const calendarCells = $derived.by(() => {
        const firstDayOfMonth = new Date(viewYear, viewMonth, 1);
        const lastDayOfMonth = new Date(viewYear, viewMonth + 1, 0);

        // In JS: 0=Sun, 1=Mon, ..., 6=Sat. Convert to 1=Mon, ..., 7=Sun
        let startingDay = firstDayOfMonth.getDay();
        startingDay = startingDay === 0 ? 7 : startingDay;

        const cells: CalendarCell[] = [];
        const todayStr = new Date().toISOString().slice(0, 10);

        // Prev month padding
        const prevMonthLastDay = new Date(viewYear, viewMonth, 0).getDate();
        for (let i = startingDay - 1; i > 0; i--) {
            const d = prevMonthLastDay - i + 1;
            const m = viewMonth === 0 ? 12 : viewMonth;
            const y = viewMonth === 0 ? viewYear - 1 : viewYear;
            const dateStr = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const dateObj = new Date(y, m - 1, d);
            let isoDay = dateObj.getDay();
            isoDay = isoDay === 0 ? 7 : isoDay;
            cells.push({
                dayNumber: d,
                dateStr,
                isoDay,
                isCurrentMonth: false,
                isEffective: isDateEffective(dateStr, y, m - 1, d),
                isToday: dateStr === todayStr,
            });
        }

        // Current month days
        for (let d = 1; d <= lastDayOfMonth.getDate(); d++) {
            const dateStr = `${viewYear}-${String(viewMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const dateObj = new Date(viewYear, viewMonth, d);
            let isoDay = dateObj.getDay();
            isoDay = isoDay === 0 ? 7 : isoDay;
            cells.push({
                dayNumber: d,
                dateStr,
                isoDay,
                isCurrentMonth: true,
                isEffective: isDateEffective(dateStr, viewYear, viewMonth, d),
                isToday: dateStr === todayStr,
            });
        }

        // Next month padding to complete 35 or 42 cells
        const totalRows = cells.length > 35 ? 42 : 35;
        let nextDay = 1;
        while (cells.length < totalRows) {
            const m = viewMonth === 11 ? 1 : viewMonth + 2;
            const y = viewMonth === 11 ? viewYear + 1 : viewYear;
            const dateStr = `${y}-${String(m).padStart(2, '0')}-${String(nextDay).padStart(2, '0')}`;
            const dateObj = new Date(y, m - 1, nextDay);
            let isoDay = dateObj.getDay();
            isoDay = isoDay === 0 ? 7 : isoDay;
            cells.push({
                dayNumber: nextDay,
                dateStr,
                isoDay,
                isCurrentMonth: false,
                isEffective: isDateEffective(dateStr, y, m - 1, nextDay),
                isToday: dateStr === todayStr,
            });
            nextDay++;
        }

        return cells;
    });

    function isDateEffective(dateStr: string, year: number, month: number, day: number): boolean {
        // Date range check
        if (startsAt && dateStr < startsAt) return false;
        if (endsAt && dateStr > endsAt) return false;

        // Day of week check
        const dateObj = new Date(year, month, day);
        let isoDay = dateObj.getDay();
        isoDay = isoDay === 0 ? 7 : isoDay;

        if (daysOfWeek.length > 0) {
            return daysOfWeek.includes(isoDay);
        }

        // If no specific days selected, all days in range are effective
        return Boolean(startsAt || endsAt);
    }
</script>

<div class="w-[calc(100%-4px)] sm:w-full mx-auto space-y-3.5 sm:space-y-4 rounded-xl border border-border/70 bg-card p-3 sm:p-4 overflow-hidden box-border">
    <!-- Header: Días de la Semana -->
    <div>
        <div class="flex items-center justify-between gap-2 mb-2">
            <Label class="text-xs font-semibold text-foreground flex items-center gap-1.5 shrink-0">
                <CalendarIcon class="h-3.5 w-3.5 text-primary" />
                <span>Días recurrentes</span>
            </Label>
        </div>

        <!-- 7 Chips con touch target accesible min-h-[42px] y min-w-0 para evitar desbordes -->
        <div class="grid grid-cols-7 gap-1 sm:gap-1.5 w-full">
            {#each WEEK_DAYS as day}
                {@const isSelected = daysOfWeek.includes(day.id)}
                <button
                    type="button"
                    onclick={() => toggleDay(day.id)}
                    {disabled}
                    class={`min-h-[42px] sm:min-h-[44px] min-w-0 w-full flex flex-col items-center justify-center rounded-xl border text-[11px] sm:text-xs font-medium transition-colors cursor-pointer select-none touch-manipulation px-0.5 sm:px-1 ${
                        isSelected
                            ? 'bg-primary text-primary-foreground border-primary shadow-2xs font-semibold'
                            : 'bg-background hover:bg-muted/60 text-foreground border-border/80'
                    } disabled:opacity-50 disabled:cursor-not-allowed`}
                    aria-label={`Alternar ${day.name}`}
                >
                    <span class="sm:hidden text-xs font-semibold">{day.short}</span>
                    <span class="hidden sm:inline text-xs font-semibold">{day.label}</span>
                </button>
            {/each}
        </div>
    </div>

    <!-- Rango de Fechas (Desde / Hasta) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3 pt-2 border-t border-border/50">
        <div class="space-y-1 min-w-0">
            <Label for="adjustment-starts-at" class="text-xs font-medium">Vigencia desde (opcional)</Label>
            <input
                id="adjustment-starts-at"
                type="date"
                bind:value={startsAt}
                {disabled}
                class="min-h-[40px] w-full max-w-full rounded-xl border border-border/80 bg-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:opacity-50"
            />
        </div>

        <div class="space-y-1 min-w-0">
            <Label for="adjustment-ends-at" class="text-xs font-medium">Vigencia hasta (opcional)</Label>
            <input
                id="adjustment-ends-at"
                type="date"
                bind:value={endsAt}
                min={startsAt || undefined}
                {disabled}
                class="min-h-[40px] w-full max-w-full rounded-xl border border-border/80 bg-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:opacity-50"
            />
        </div>
    </div>

    <!-- Previsualizador Interactivo Mensual en CSS Grid -->
    <div class="pt-2 border-t border-border/50">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-foreground hidden sm:inline-flex items-center gap-1.5">
                Previsualización en tiempo real
            </span>
            <div class="flex items-center gap-1 justify-between sm:justify-end w-full sm:w-auto">
                <span class="text-xs font-medium text-muted-foreground mr-1">
                    {monthNames[viewMonth]} {viewYear}
                </span>
                <button
                    type="button"
                    onclick={prevMonth}
                    class="p-1 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer"
                    aria-label="Mes anterior"
                >
                    <ChevronLeft class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    onclick={nextMonth}
                    class="p-1 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer"
                    aria-label="Mes siguiente"
                >
                    <ChevronRight class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>

        <!-- Encabezados Días -->
        <div class="grid grid-cols-7 gap-1 text-center mb-1 w-full">
            {#each WEEK_DAYS as day}
                <button
                    type="button"
                    onclick={() => toggleDay(day.id)}
                    {disabled}
                    class="text-2xs font-semibold py-1 rounded-md transition-colors cursor-pointer select-none hover:bg-muted/60 {daysOfWeek.includes(day.id) ? 'text-primary font-bold' : 'text-muted-foreground'}"
                    aria-label={`Alternar columna ${day.name}`}
                >
                    {day.short}
                </button>
            {/each}
        </div>

        <!-- Grilla de Celdas Interactivas -->
        <div class="grid grid-cols-7 gap-1 w-full">
            {#each calendarCells as cell}
                {#if cell.isCurrentMonth}
                    <button
                        type="button"
                        onclick={() => handleCellClick(cell)}
                        {disabled}
                        class={`min-h-[28px] sm:min-h-[30px] min-w-0 w-full flex items-center justify-center rounded-md text-xs transition-all select-none cursor-pointer ${
                            cell.isEffective
                                ? 'bg-primary-500/20 text-primary-800 dark:text-primary-300 font-bold border border-primary-500/40 shadow-2xs hover:bg-primary-500/30'
                                : 'text-foreground/80 hover:bg-muted/60'
                        } ${cell.isToday ? 'ring-1 ring-primary-500/60' : ''} disabled:cursor-not-allowed disabled:opacity-50`}
                        title={cell.isEffective ? `${cell.dateStr}: Ajuste activo (Click para alternar)` : `${cell.dateStr} (Click para activar)`}
                    >
                        <span class="tabular-nums">{cell.dayNumber}</span>
                    </button>
                {:else}
                    <div
                        class="min-h-[28px] sm:min-h-[30px] min-w-0 w-full flex items-center justify-center rounded-md text-xs text-muted-foreground/30 opacity-40 select-none"
                    >
                        <span class="tabular-nums">{cell.dayNumber}</span>
                    </div>
                {/if}
            {/each}
        </div>
    </div>
</div>
