<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import Eye from 'lucide-svelte/icons/eye';
    import Building2 from 'lucide-svelte/icons/building-2';
    import User from 'lucide-svelte/icons/user';
    import Clock from 'lucide-svelte/icons/clock';

    interface AuditLogItem {
        id: number;
        action: string;
        user_name?: string;
        user_role?: string;
        entity_label?: string;
        auditable_type?: string;
        auditable_id?: number;
        old_values?: Record<string, any> | null;
        new_values?: Record<string, any> | null;
        ip_address?: string;
        user_agent?: string;
        created_at: string;
        tenant?: { id: number; name: string } | null;
    }

    let {
        logs = [],
        onview = () => {},
    }: {
        logs: AuditLogItem[];
        onview: (log: AuditLogItem) => void;
    } = $props();

    function getActionTone(action: string): { bg: string; text: string; border: string } {
        const lower = action.toLowerCase();
        if (lower.includes('cancelac') || lower.includes('elimin')) {
            return { bg: 'bg-destructive/10', text: 'text-destructive', border: 'border-destructive/20' };
        }
        if (lower.includes('emis') || lower.includes('creac') || lower.includes('entrega')) {
            return { bg: 'bg-emerald-500/10', text: 'text-emerald-700 dark:text-emerald-400', border: 'border-emerald-500/20' };
        }
        if (lower.includes('comercial') || lower.includes('actualiz')) {
            return { bg: 'bg-primary-500/10', text: 'text-primary-700 dark:text-primary-400', border: 'border-primary-500/20' };
        }
        return { bg: 'bg-muted/80', text: 'text-muted-foreground', border: 'border-border' };
    }
</script>

<div class="space-y-3">
    {#each logs as log (log.id)}
        {@const tone = getActionTone(log.action)}
        <div class="flex items-center justify-between gap-4 rounded-xl border border-border/70 bg-card p-4 sm:p-5 shadow-2xs hover:shadow-xs transition-shadow">
            <!-- Columna 1: Acción y Fecha -->
            <div class="flex items-center gap-3 min-w-[220px]">
                <div class="space-y-1">
                    <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-semibold border {tone.bg} {tone.text} {tone.border}">
                        {log.action}
                    </span>
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground tabular-nums">
                        <Clock class="size-3.5 text-muted-foreground shrink-0" />
                        <span>{log.created_at}</span>
                    </div>
                </div>
            </div>

            <!-- Columna 2: Entidad Afectada y Comercio -->
            <div class="flex-1 min-w-[200px] space-y-1">
                <span class="text-sm font-semibold text-foreground block truncate">
                    {log.entity_label ?? 'Registro del Sistema'}
                </span>
                <div class="flex items-center gap-2">
                    {#if log.tenant}
                        <span class="inline-flex items-center gap-1 text-xs text-muted-foreground truncate">
                            <Building2 class="size-3 text-muted-foreground shrink-0" />
                            {log.tenant.name}
                        </span>
                        <span class="text-muted-foreground/40">•</span>
                        <span class="text-[11px] text-muted-foreground tabular-nums">
                            ID: {log.tenant.id}
                        </span>
                    {:else}
                        <span class="text-xs text-muted-foreground italic">Plataforma General</span>
                    {/if}
                </div>
            </div>

            <!-- Columna 3: Usuario Ejecutor y Rol -->
            <div class="min-w-[170px] space-y-1 text-right sm:text-left">
                <div class="flex items-center gap-1.5">
                    <User class="size-3.5 text-muted-foreground shrink-0" />
                    <span class="text-xs font-medium text-foreground truncate max-w-[140px]">
                        {log.user_name ?? 'Sistema'}
                    </span>
                </div>
                {#if log.user_role}
                    <Badge variant="outline" class="text-[10px] uppercase tracking-wider font-semibold py-0 px-1.5">
                        {log.user_role}
                    </Badge>
                {/if}
            </div>

            <!-- Columna 4: Botón de Detalle -->
            <div class="shrink-0 pl-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="min-h-[36px] px-3.5 gap-1.5 font-medium shadow-2xs hover:bg-accent"
                    onclick={() => onview(log)}
                >
                    <Eye class="size-3.5" />
                    <span>Ver</span>
                </Button>
            </div>
        </div>
    {/each}
</div>
