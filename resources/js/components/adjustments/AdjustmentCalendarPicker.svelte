<script lang="ts">
    import { Label } from '@/components/ui/label';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import CalendarIcon from 'lucide-svelte/icons/calendar';

    let {
        specificDates = $bindable<string[]>([]),
        startsAt = $bindable(''),
        endsAt = $bindable(''),
        daysOfWeek = $bindable<number[]>([]),
        disabled = false,
    }: {
        specificDates?: string[];
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
    let lastClickedDate = $state<string | null>(null);

    const monthNames = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    const derivedStartsAt = $derived(specificDates.length > 0 ? specificDates[0] : startsAt);
    const derivedEndsAt = $derived(specificDates.length > 0 ? specificDates[specificDates.length - 1] : endsAt);

    let initialMonthSynced = $state(false);
    $effect(() => {
        if (!initialMonthSynced && specificDates && specificDates.length > 0) {
            const first = specificDates[0];
            const parts = first.split('-');
            if (parts.length === 3) {
                const y = parseInt(parts[0], 10);
                const m = parseInt(parts[1], 10) - 1;
                if (!isNaN(y) && !isNaN(m) && m >= 0 && m <= 11) {
                    viewYear = y;
                    viewMonth = m;
                    initialMonthSynced = true;
                }
            }
        }
    });

    function syncBounds() {
        if (specificDates.length > 0) {
            startsAt = specificDates[0];
            endsAt = specificDates[specificDates.length - 1];
        } else {
            startsAt = '';
            endsAt = '';
        }
    }

    function toggleDate(dateStr: string, e?: MouseEvent) {
        if (disabled) return;

        // Shift-click range support
        if (e?.shiftKey && lastClickedDate) {
            const start = lastClickedDate < dateStr ? lastClickedDate : dateStr;
            const end = lastClickedDate < dateStr ? dateStr : lastClickedDate;
            const rangeDates: string[] = [];

            const curr = new Date(start + 'T00:00:00');
            const endD = new Date(end + 'T00:00:00');
            while (curr <= endD) {
                const y = curr.getFullYear();
                const m = String(curr.getMonth() + 1).padStart(2, '0');
                const d = String(curr.getDate()).padStart(2, '0');
                rangeDates.push(`${y}-${m}-${d}`);
                curr.setDate(curr.getDate() + 1);
            }

            const set = new Set([...specificDates, ...rangeDates]);
            specificDates = Array.from(set).sort();
            lastClickedDate = dateStr;
            syncBounds();
            return;
        }

        if (specificDates.includes(dateStr)) {
            specificDates = specificDates.filter(d => d !== dateStr);
        } else {
            specificDates = [...specificDates, dateStr].sort();
        }
        lastClickedDate = dateStr;
        syncBounds();
    }

    function toggleWeekdayInActiveMonth(dayId: number) {
        if (disabled) return;

        const monthDates = calendarCells
            .filter(c => c.isCurrentMonth && c.isoDay === dayId)
            .map(c => c.dateStr);

        if (monthDates.length === 0) return;

        const allAlreadyActive = monthDates.every(d => specificDates.includes(d));

        if (allAlreadyActive) {
            // Deselect all dates of this weekday in this month
            specificDates = specificDates.filter(d => !monthDates.includes(d));
        } else {
            // Idempotent union: turn all dates of this weekday in this month to 1 (active)
            const set = new Set([...specificDates, ...monthDates]);
            specificDates = Array.from(set).sort();
        }

        syncBounds();
    }

    function getYearDatesForWeekday(year: number, dayId: number): string[] {
        const dates: string[] = [];
        const d = new Date(year, 0, 1);
        while (d.getFullYear() === year) {
            let isoDay = d.getDay();
            isoDay = isoDay === 0 ? 7 : isoDay;
            if (isoDay === dayId) {
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                dates.push(`${year}-${m}-${day}`);
            }
            d.setDate(d.getDate() + 1);
        }
        return dates;
    }

    function toggleWeekdayInActiveYear(dayId: number) {
        if (disabled) return;

        const yearDates = getYearDatesForWeekday(viewYear, dayId);
        if (yearDates.length === 0) return;

        const allAlreadyActive = yearDates.every(d => specificDates.includes(d));

        if (allAlreadyActive) {
            // Deselect all dates of this weekday in this year
            specificDates = specificDates.filter(d => !yearDates.includes(d));
        } else {
            // Idempotent union: turn all dates of this weekday in this year to active (1)
            const set = new Set([...specificDates, ...yearDates]);
            specificDates = Array.from(set).sort();
        }

        syncBounds();
    }

    function getWeekdayStatusInActiveYear(dayId: number): 'none' | 'some' | 'all' {
        const yearDates = getYearDatesForWeekday(viewYear, dayId);
        if (yearDates.length === 0) return 'none';

        const activeCount = yearDates.filter(d => specificDates.includes(d)).length;
        if (activeCount === 0) return 'none';
        if (activeCount === yearDates.length) return 'all';
        return 'some';
    }

    function clearAllDates() {
        if (disabled) return;
        specificDates = [];
        syncBounds();
    }

    function getWeekdayStatusInActiveMonth(dayId: number): 'none' | 'some' | 'all' {
        const monthDates = calendarCells
            .filter(c => c.isCurrentMonth && c.isoDay === dayId)
            .map(c => c.dateStr);

        if (monthDates.length === 0) return 'none';

        const activeCount = monthDates.filter(d => specificDates.includes(d)).length;
        if (activeCount === 0) return 'none';
        if (activeCount === monthDates.length) return 'all';
        return 'some';
    }

    function formatDateReadable(dateStr: string): string {
        if (!dateStr) return '';
        const parts = dateStr.slice(0, 10).split('-');
        if (parts.length !== 3) return dateStr;
        const [y, m, d] = parts;
        return `${d}/${m}/${y}`;
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
                isToday: dateStr === todayStr,
            });
            nextDay++;
        }

        return cells;
    });
</script>

<div class="w-full space-y-3.5 sm:space-y-4 rounded-xl border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 p-3 sm:p-4 overflow-hidden box-border">
    <!-- Header: Días de la Semana Recurrentes en el Año Activo -->
    <div>
        <div class="flex items-center justify-between gap-2 mb-2">
            <Label class="text-xs font-semibold text-foreground flex items-center gap-1.5 shrink-0">
                <CalendarIcon class="h-3.5 w-3.5 text-primary" />
                <span>Días recurrentes del año ({viewYear})</span>
            </Label>
        </div>

        <!-- 7 Chips con touch target accesible min-h-[42px] y min-w-0 para evitar desbordes -->
        <div class="grid grid-cols-7 gap-1 sm:gap-1.5 w-full">
            {#each WEEK_DAYS as day}
                {@const status = getWeekdayStatusInActiveYear(day.id)}
                <button
                    type="button"
                    onclick={() => toggleWeekdayInActiveYear(day.id)}
                    {disabled}
                    class={`min-h-[42px] sm:min-h-[44px] min-w-0 w-full flex flex-col items-center justify-center rounded-xl border text-xs sm:text-sm font-medium tabular-nums transition-colors cursor-pointer select-none touch-manipulation px-0.5 sm:px-1 ${
                        status === 'all'
                            ? 'bg-primary text-primary-foreground border-primary shadow-2xs font-semibold'
                            : status === 'some'
                                ? 'bg-primary/10 dark:bg-primary/15 text-primary border-primary font-semibold'
                                : 'bg-card hover:bg-muted/70 dark:hover:bg-secondary/55 text-foreground border-border/80 dark:border-border/90'
                    } disabled:opacity-50 disabled:cursor-not-allowed`}
                    aria-label={`Alternar ${day.name} en todo ${viewYear}`}
                    title={`Alternar ${day.name} en todo ${viewYear}`}
                >
                    <span class="text-xs font-semibold">{day.short}</span>
                </button>
            {/each}
        </div>
    </div>

    <!-- Previsualizador Interactivo Mensual en CSS Grid -->
    <div class="pt-2 border-t border-border/50">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-foreground hidden sm:inline-flex items-center gap-1.5">
                Calendario de aplicación
            </span>
            <div class="flex items-center gap-1 justify-between sm:justify-end w-full sm:w-auto">
                <span class="text-xs font-medium text-muted-foreground mr-1">
                    {monthNames[viewMonth]} {viewYear}
                </span>
                <button
                    type="button"
                    onclick={prevMonth}
                    class="p-1 rounded-md hover:bg-muted dark:hover:bg-secondary/60 text-muted-foreground hover:text-foreground cursor-pointer"
                    aria-label="Mes anterior"
                >
                    <ChevronLeft class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    onclick={nextMonth}
                    class="p-1 rounded-md hover:bg-muted dark:hover:bg-secondary/60 text-muted-foreground hover:text-foreground cursor-pointer"
                    aria-label="Mes siguiente"
                >
                    <ChevronRight class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>

        <!-- Encabezados Días -->
        <div class="grid grid-cols-7 gap-1 text-center mb-1 w-full">
            {#each WEEK_DAYS as day}
                {@const status = getWeekdayStatusInActiveMonth(day.id)}
                <button
                    type="button"
                    onclick={() => toggleWeekdayInActiveMonth(day.id)}
                    {disabled}
                    class="text-2xs font-semibold py-1 rounded-md transition-colors cursor-pointer select-none hover:bg-muted/70 dark:hover:bg-secondary/55 {status === 'all' ? 'text-primary font-bold' : status === 'some' ? 'text-primary font-semibold' : 'text-muted-foreground'}"
                    aria-label={`Alternar columna ${day.name} en ${monthNames[viewMonth]}`}
                    title={`Alternar columna ${day.name} en ${monthNames[viewMonth]}`}
                >
                    {day.short}
                </button>
            {/each}
        </div>

        <!-- Grilla de Celdas Interactivas -->
        <div class="grid grid-cols-7 gap-1 w-full">
            {#each calendarCells as cell}
                {#if cell.isCurrentMonth}
                    {@const isSelected = specificDates.includes(cell.dateStr)}
                    <button
                        type="button"
                        onclick={(e) => toggleDate(cell.dateStr, e)}
                        {disabled}
                        class={`min-h-[28px] sm:min-h-[30px] min-w-0 w-full flex items-center justify-center rounded-md text-xs transition-all select-none cursor-pointer ${
                            isSelected
                                ? 'bg-primary text-primary-foreground font-bold border border-primary shadow-2xs hover:bg-primary/90'
                                : 'text-foreground hover:bg-muted/70 dark:hover:bg-secondary/55'
                        } ${cell.isToday ? 'ring-1 ring-primary' : ''} disabled:cursor-not-allowed disabled:opacity-50`}
                        title={isSelected ? `${cell.dateStr}: Activo (Click para desmarcar)` : `${cell.dateStr} (Click para marcar)`}
                    >
                        <span class="tabular-nums">{cell.dayNumber}</span>
                    </button>
                {:else}
                    <div
                        class="min-h-[28px] sm:min-h-[30px] min-w-0 w-full flex items-center justify-center rounded-md text-xs text-muted-foreground/60 select-none"
                    >
                        <span class="tabular-nums">{cell.dayNumber}</span>
                    </div>
                {/if}
            {/each}
        </div>
    </div>

    <!-- Display Informativo de Vigencia Bounding Box (Solo Lectura) -->
    <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/50 text-xs">
        <div class="flex items-center gap-1.5 text-muted-foreground min-w-0">
            <CalendarIcon class="size-3.5 text-primary shrink-0" />
            {#if specificDates && specificDates.length > 0}
                <span class="truncate">
                    Vigencia:
                    {#if derivedStartsAt === derivedEndsAt}
                        <strong class="text-foreground font-semibold">{formatDateReadable(derivedStartsAt)}</strong> (1 día)
                    {:else}
                        <strong class="text-foreground font-semibold">{formatDateReadable(derivedStartsAt)}</strong> al <strong class="text-foreground font-semibold">{formatDateReadable(derivedEndsAt)}</strong> ({specificDates.length} días)
                    {/if}
                </span>
            {:else}
                <span class="text-muted-foreground italic">
                    Sin fechas seleccionadas (Vigencia permanente en todos los pedidos)
                </span>
            {/if}
        </div>
        {#if specificDates && specificDates.length > 0}
            <button
                type="button"
                onclick={clearAllDates}
                {disabled}
                class="text-2xs text-destructive hover:underline font-medium cursor-pointer shrink-0"
            >
                Limpiar fechas
            </button>
        {/if}
    </div>
</div>
