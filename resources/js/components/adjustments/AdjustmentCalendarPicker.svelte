<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import CalendarIcon from 'lucide-svelte/icons/calendar';
    import Check from 'lucide-svelte/icons/check';

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

    function selectAllDays() {
        if (disabled) return;
        daysOfWeek = [1, 2, 3, 4, 5, 6, 7];
    }

    function selectWeekdays() {
        if (disabled) return;
        daysOfWeek = [1, 2, 3, 4, 5];
    }

    function selectWeekends() {
        if (disabled) return;
        daysOfWeek = [6, 7];
    }

    function clearDays() {
        if (disabled) return;
        daysOfWeek = [];
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
            cells.push({
                dayNumber: d,
                dateStr,
                isCurrentMonth: false,
                isEffective: isDateEffective(dateStr, y, m - 1, d),
                isToday: dateStr === todayStr,
            });
        }

        // Current month days
        for (let d = 1; d <= lastDayOfMonth.getDate(); d++) {
            const dateStr = `${viewYear}-${String(viewMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            cells.push({
                dayNumber: d,
                dateStr,
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
            cells.push({
                dayNumber: nextDay,
                dateStr,
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

<div class="space-y-4 rounded-xl border border-border/70 bg-card p-4">
    <!-- Header: Días de la Semana -->
    <div>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <Label class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                <CalendarIcon class="h-3.5 w-3.5 text-primary" />
                Días recurrentes
            </Label>
            <div class="flex items-center gap-1.5 text-2xs flex-wrap">
                <button
                    type="button"
                    onclick={selectAllDays}
                    {disabled}
                    class="text-xs text-primary hover:underline font-medium cursor-pointer disabled:opacity-50"
                >
                    Todos
                </button>
                <span class="text-muted-foreground">•</span>
                <button
                    type="button"
                    onclick={selectWeekdays}
                    {disabled}
                    class="text-xs text-primary hover:underline font-medium cursor-pointer disabled:opacity-50"
                >
                    Lun-Vie
                </button>
                <span class="text-muted-foreground">•</span>
                <button
                    type="button"
                    onclick={selectWeekends}
                    {disabled}
                    class="text-xs text-primary hover:underline font-medium cursor-pointer disabled:opacity-50"
                >
                    Fines de semana
                </button>
                {#if daysOfWeek.length > 0}
                    <span class="text-muted-foreground">•</span>
                    <button
                        type="button"
                        onclick={clearDays}
                        {disabled}
                        class="text-xs text-destructive hover:underline font-medium cursor-pointer"
                    >
                        Limpiar
                    </button>
                {/if}
            </div>
        </div>

        <!-- 7 Chips con touch target accesible min-h-[44px] -->
        <div class="grid grid-cols-7 gap-1 sm:gap-1.5">
            {#each WEEK_DAYS as day}
                {@const isSelected = daysOfWeek.includes(day.id)}
                <button
                    type="button"
                    onclick={() => toggleDay(day.id)}
                    {disabled}
                    class={`min-h-[44px] flex flex-col items-center justify-center rounded-xl border text-xs font-medium transition-colors cursor-pointer select-none touch-manipulation ${
                        isSelected
                            ? 'bg-primary text-primary-foreground border-primary shadow-2xs font-semibold'
                            : 'bg-background hover:bg-muted/60 text-foreground border-border/80'
                    } disabled:opacity-50 disabled:cursor-not-allowed`}
                >
                    <span class="text-xs font-semibold">{day.label}</span>
                    <span class="text-2xs opacity-75 hidden sm:inline">{day.short}</span>
                </button>
            {/each}
        </div>
        {#if daysOfWeek.length > 0}
            <p class="text-2xs text-muted-foreground mt-1.5">
                Aplica recurrentemente los días: {daysOfWeek.map(d => WEEK_DAYS.find(w => w.id === d)?.name).join(', ')}.
            </p>
        {/if}
    </div>

    <!-- Rango de Fechas (Desde / Hasta) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-border/50">
        <div class="space-y-1">
            <Label for="adjustment-starts-at" class="text-xs font-medium">Vigencia desde (opcional)</Label>
            <input
                id="adjustment-starts-at"
                type="date"
                bind:value={startsAt}
                {disabled}
                class="min-h-[40px] w-full rounded-xl border border-border/80 bg-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:opacity-50"
            />
        </div>

        <div class="space-y-1">
            <Label for="adjustment-ends-at" class="text-xs font-medium">Vigencia hasta (opcional)</Label>
            <input
                id="adjustment-ends-at"
                type="date"
                bind:value={endsAt}
                min={startsAt || undefined}
                {disabled}
                class="min-h-[40px] w-full rounded-xl border border-border/80 bg-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:opacity-50"
            />
        </div>
    </div>

    <!-- Previsualizador Interactivo Mensual en CSS Grid -->
    <div class="pt-2 border-t border-border/50">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                Previsualización en tiempo real
            </span>
            <div class="flex items-center gap-1">
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
        <div class="grid grid-cols-7 gap-1 text-center mb-1">
            {#each ['L', 'M', 'M', 'J', 'V', 'S', 'D'] as h}
                <div class="text-2xs font-semibold text-muted-foreground py-0.5">{h}</div>
            {/each}
        </div>

        <!-- Grilla de Celdas -->
        <div class="grid grid-cols-7 gap-1">
            {#each calendarCells as cell}
                <div
                    class={`min-h-[28px] sm:min-h-[30px] flex items-center justify-center rounded-md text-xs transition-all select-none ${
                        !cell.isCurrentMonth
                            ? 'text-muted-foreground/30 opacity-40'
                            : cell.isEffective
                                ? 'bg-primary-500/20 text-primary-800 dark:text-primary-300 font-bold border border-primary-500/40 shadow-2xs'
                                : 'text-foreground/80 hover:bg-muted/40'
                    } ${cell.isToday ? 'ring-1 ring-primary-500/60' : ''}`}
                    title={cell.isEffective ? `${cell.dateStr}: Aplicable` : cell.dateStr}
                >
                    <span class="tabular-nums">{cell.dayNumber}</span>
                </div>
            {/each}
        </div>

        <div class="flex flex-wrap items-center gap-3 mt-2 text-2xs text-muted-foreground">
            <div class="flex items-center gap-1.5">
                <div class="h-2.5 w-2.5 rounded-sm bg-primary-500/25 border border-primary-500/50"></div>
                <span>Días con ajuste aplicado</span>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="h-2.5 w-2.5 rounded-sm ring-1 ring-primary-500"></div>
                <span>Hoy</span>
            </div>
        </div>
    </div>
</div>
