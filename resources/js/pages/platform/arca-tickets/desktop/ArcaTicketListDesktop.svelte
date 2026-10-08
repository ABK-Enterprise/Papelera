<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import KeyRound from 'lucide-svelte/icons/key-round';
    import Calendar from 'lucide-svelte/icons/calendar';

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

    let {
        tickets = [],
    }: {
        tickets: TicketItem[];
    } = $props();
</script>

<div class="space-y-3">
    {#each tickets as ticket (ticket.id)}
        <div class="flex items-center justify-between gap-4 rounded-xl border border-border/70 bg-card p-4 sm:p-5 shadow-2xs hover:shadow-xs transition-shadow">
            <!-- Columna 1: Código del Ticket -->
            <div class="flex items-center gap-3 min-w-[240px]">
                <div class="size-9 rounded-lg bg-primary-500/10 border border-primary-500/20 flex items-center justify-center text-primary shrink-0">
                    <KeyRound class="size-4" />
                </div>
                <div class="space-y-0.5">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-foreground font-mono tabular-nums">
                            #{ticket.unique_id}
                        </span>
                        {#if ticket.status === 'active'}
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Activo
                            </span>
                        {:else}
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider bg-muted text-muted-foreground border border-border/60">
                                Vencido
                            </span>
                        {/if}
                    </div>
                    {#if ticket.token_preview}
                        <span class="text-xs font-mono tabular-nums text-muted-foreground block truncate max-w-[200px]">
                            Token: {ticket.token_preview}
                        </span>
                    {/if}
                </div>
            </div>

            <!-- Columna 2: Fecha de Inicio -->
            <div class="flex-1 min-w-[180px] space-y-0.5">
                <span class="text-xs text-muted-foreground block">Fecha de Inicio</span>
                <div class="flex items-center gap-1.5 text-xs font-medium text-foreground tabular-nums">
                    <Calendar class="size-3.5 text-muted-foreground shrink-0" />
                    <span>{ticket.generated_at ?? 'No registrada'}</span>
                </div>
            </div>

            <!-- Columna 3: Fecha de Fin -->
            <div class="flex-1 min-w-[180px] space-y-0.5">
                <span class="text-xs text-muted-foreground block">Fecha de Fin (Vencimiento)</span>
                <div class="flex items-center gap-1.5 text-xs font-medium text-foreground tabular-nums">
                    <Calendar class="size-3.5 text-muted-foreground shrink-0" />
                    <span>{ticket.expires_at ?? 'No registrada'}</span>
                </div>
            </div>

            <!-- Columna 4: CUIT y Servicio -->
            <div class="text-right min-w-[130px] space-y-0.5">
                <span class="text-xs font-mono tabular-nums text-muted-foreground block">
                    CUIT: {ticket.cuit}
                </span>
                <Badge variant="outline" class="text-xs uppercase tracking-wider font-semibold py-0.5 px-2">
                    {ticket.service} ({ticket.environment})
                </Badge>
            </div>
        </div>
    {/each}
</div>
