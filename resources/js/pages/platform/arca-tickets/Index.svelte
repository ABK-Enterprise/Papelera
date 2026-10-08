<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import ArcaTicketListDesktop from './desktop/ArcaTicketListDesktop.svelte';
    import ArcaTicketListMobile from './mobile/ArcaTicketListMobile.svelte';
    import KeyRound from 'lucide-svelte/icons/key-round';
    import Clock from 'lucide-svelte/icons/clock';
    import Calendar from 'lucide-svelte/icons/calendar';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Copy from 'lucide-svelte/icons/copy';
    import Check from 'lucide-svelte/icons/check';
    import { onDestroy } from 'svelte';

    interface ActiveTicket {
        id: number;
        unique_id: string;
        token_preview?: string | null;
        cuit: string;
        service: string;
        environment: string;
        generated_at?: string | null;
        expires_at?: string | null;
        remaining_seconds: number;
        is_valid: boolean;
    }

    interface TicketItem {
        id: number;
        unique_id: string;
        token_preview?: string | null;
        cuit: string;
        service: string;
        environment: string;
        generated_at?: string | null;
        expires_at?: string | null;
        status: string;
    }

    interface PaginatedResponse {
        data: TicketItem[];
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from?: number | null;
        to?: number | null;
    }

    let {
        activeTicket = null,
        historicalTickets,
    }: {
        activeTicket?: ActiveTicket | null;
        historicalTickets: PaginatedResponse;
    } = $props();

    let secondsLeft = $state(activeTicket?.remaining_seconds ?? 0);
    let copiedCode = $state(false);

    let intervalId: any = null;

    $effect(() => {
        secondsLeft = activeTicket?.remaining_seconds ?? 0;
        if (activeTicket && secondsLeft > 0) {
            if (intervalId) clearInterval(intervalId);
            intervalId = setInterval(() => {
                if (secondsLeft > 0) {
                    secondsLeft -= 1;
                } else {
                    clearInterval(intervalId);
                }
            }, 1000);
        }

        return () => {
            if (intervalId) clearInterval(intervalId);
        };
    });

    onDestroy(() => {
        if (intervalId) clearInterval(intervalId);
    });

    let formattedRemainingTime = $derived.by(() => {
        if (secondsLeft <= 0) return 'Expirado';
        const hours = Math.floor(secondsLeft / 3600);
        const minutes = Math.floor((secondsLeft % 3600) / 60);
        const seconds = secondsLeft % 60;
        return `${hours.toString().padStart(2, '0')}h ${minutes.toString().padStart(2, '0')}m ${seconds.toString().padStart(2, '0')}s restantes`;
    });

    // 41400s is default 11h 30m WSAA TTL
    let progressPercent = $derived.by(() => {
        if (secondsLeft <= 0) return 0;
        return Math.min(100, Math.round((secondsLeft / 41400) * 100));
    });

    function copyTicketCode() {
        if (!activeTicket) return;
        navigator.clipboard.writeText(`#${activeTicket.unique_id}`);
        copiedCode = true;
        setTimeout(() => {
            copiedCode = false;
        }, 2000);
    }
</script>

<AppHead title="Seguimiento de Tickets ARCA" />

<div class="max-w-6xl w-full lg:w-[80%] mx-auto px-4 sm:px-6 py-6 space-y-6">
    <!-- Header del Módulo -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-border/60 pb-5">
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
            <KeyRound class="size-7 text-primary shrink-0" />
            Seguimiento de Tickets ARCA
        </h1>

        <div class="flex items-center gap-2 self-start sm:self-auto text-xs text-muted-foreground font-medium tabular-nums">
            <span>{historicalTickets.total} {historicalTickets.total === 1 ? 'ticket emitido' : 'tickets emitidos'}</span>
        </div>
    </div>

    <!-- Recuadro Central: Estado del Ticket Activo -->
    {#if activeTicket && secondsLeft > 0}
        <div class="rounded-xl border border-border/80 bg-card p-6 sm:p-7 space-y-5 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="size-11 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                        <ShieldCheck class="size-6" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-bold text-foreground">Ticket de Acceso Activo</h2>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Operativo
                        </span>
                    </div>
                </div>

                <Badge variant="outline" class="text-xs uppercase tracking-wider font-semibold py-1 px-2.5 self-start sm:self-auto">
                    {activeTicket.service} ({activeTicket.environment})
                </Badge>
            </div>

            <!-- Código y Cuenta Regresiva -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                <!-- Código del Ticket -->
                <div class="rounded-lg border border-border/70 bg-muted/30 p-4 space-y-1.5">
                    <span class="text-xs text-muted-foreground block font-medium">Código del Ticket (WSAA Unique ID)</span>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xl sm:text-2xl font-bold font-mono text-foreground tabular-nums">
                            #{activeTicket.unique_id}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="min-h-[36px] gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground"
                            onclick={copyTicketCode}
                        >
                            {#if copiedCode}
                                <Check class="size-3.5 text-emerald-600" />
                                <span>Copiado</span>
                            {:else}
                                <Copy class="size-3.5" />
                                <span>Copiar</span>
                            {/if}
                        </Button>
                    </div>
                    {#if activeTicket.token_preview}
                        <span class="text-xs font-mono tabular-nums text-muted-foreground/80 block truncate">
                            Preview Token: {activeTicket.token_preview}
                        </span>
                    {/if}
                </div>

                <!-- Cronómetro Regresivo -->
                <div class="rounded-lg border border-border/70 bg-muted/30 p-4 space-y-2">
                    <span class="text-xs text-muted-foreground block font-medium">Vigencia y Tiempo Restante</span>
                    <div class="flex items-center gap-2">
                        <Clock class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                        <span class="text-xl sm:text-2xl font-bold font-mono text-foreground tabular-nums">
                            {formattedRemainingTime}
                        </span>
                    </div>
                    <!-- Barra de progreso -->
                    <div class="w-full bg-border/60 rounded-full h-2 overflow-hidden">
                        <div
                            class="bg-emerald-500 h-2 rounded-full transition-all duration-1000"
                            style="width: {progressPercent}%"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Metadatos de Emisión -->
            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground border-t border-border/50 pt-3">
                <div class="flex items-center gap-1.5 tabular-nums">
                    <Calendar class="size-3.5" />
                    <span>Emisión: {activeTicket.generated_at ?? 'No registrada'}</span>
                </div>
                <div class="flex items-center gap-1.5 tabular-nums">
                    <Calendar class="size-3.5" />
                    <span>Vencimiento: {activeTicket.expires_at ?? 'No registrada'}</span>
                </div>
                <div class="font-mono tabular-nums">
                    CUIT Emisor: {activeTicket.cuit}
                </div>
            </div>
        </div>
    {:else}
        <!-- Estado Sin Ticket Activo -->
        <div class="rounded-xl border border-dashed border-border bg-card p-6 sm:p-7 space-y-3 shadow-xs">
            <div class="flex items-start gap-3">
                <div class="size-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
                    <AlertCircle class="size-5" />
                </div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-foreground">Sin Ticket de Acceso Activo</h2>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider bg-muted text-muted-foreground border border-border">
                            Inactivo
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-muted-foreground">
                        No hay un ticket válido en memoria o el anterior ya expiró. El sistema generará, firmará y renovará automáticamente un nuevo Ticket de Acceso (TRA) en la próxima solicitud de facturación electrónica.
                    </p>
                </div>
            </div>
        </div>
    {/if}

    <!-- Tabla Histórica de Tickets Emitidos -->
    <div class="space-y-4 pt-2">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-foreground tracking-tight">
                Historial de Tickets Emitidos
            </h3>
            <span class="text-xs text-muted-foreground tabular-nums">
                Página {historicalTickets.current_page} de {historicalTickets.last_page}
            </span>
        </div>

        {#if historicalTickets.data.length > 0}
            <div class="hidden md:block">
                <ArcaTicketListDesktop tickets={historicalTickets.data} />
            </div>
            <div class="block md:hidden">
                <ArcaTicketListMobile tickets={historicalTickets.data} />
            </div>

            <!-- Paginación -->
            <div class="pt-2">
                <AppPagination pagination={historicalTickets} />
            </div>
        {:else}
            <div class="rounded-xl border border-dashed border-border bg-card/60 p-8 text-center text-xs sm:text-sm text-muted-foreground">
                No hay registros históricos de tickets en la base de datos.
            </div>
        {/if}
    </div>
</div>
