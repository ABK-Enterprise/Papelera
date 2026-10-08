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
        <div class="rounded-xl border border-border/70 bg-card p-4 space-y-3 shadow-2xs">
            <!-- Header con Código y Estado -->
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    <KeyRound class="size-4 text-primary shrink-0" />
                    <span class="font-bold text-sm text-foreground tabular-nums truncate">
                        #{ticket.unique_id}
                    </span>
                </div>

                {#if ticket.status === 'active'}
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 shrink-0">
                        <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Activo
                    </span>
                {:else}
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider bg-muted text-muted-foreground border border-border/60 shrink-0">
                        Vencido
                    </span>
                {/if}
            </div>

            {#if ticket.token_preview}
                <div class="text-xs tabular-nums text-muted-foreground truncate">
                    Token: {ticket.token_preview}
                </div>
            {/if}

            <!-- Fechas Inicio y Fin -->
            <div class="grid grid-cols-1 gap-2 pt-2 border-t border-border/40 text-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-muted-foreground flex items-center gap-1">
                        <Calendar class="size-3.5" />
                        Inicio:
                    </span>
                    <span class="font-medium text-foreground tabular-nums">
                        {ticket.generated_at ?? 'No registrada'}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <span class="text-muted-foreground flex items-center gap-1">
                        <Calendar class="size-3.5" />
                        Fin:
                    </span>
                    <span class="font-medium text-foreground tabular-nums">
                        {ticket.expires_at ?? 'No registrada'}
                    </span>
                </div>
            </div>

            <!-- CUIT y Servicio -->
            <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/30 text-xs">
                <span class="text-muted-foreground tabular-nums">CUIT: {ticket.cuit}</span>
                <Badge variant="outline" class="text-xs uppercase tracking-wider font-semibold py-0.5 px-2">
                    {ticket.service}
                </Badge>
            </div>
        </div>
    {/each}
</div>
