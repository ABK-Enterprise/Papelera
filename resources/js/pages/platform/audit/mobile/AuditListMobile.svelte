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
            return { bg: 'bg-rose-500/10', text: 'text-rose-700 dark:text-rose-400', border: 'border-rose-500/20' };
        }
        if (lower.includes('emis') || lower.includes('creac') || lower.includes('entrega')) {
            return { bg: 'bg-emerald-500/10', text: 'text-emerald-700 dark:text-emerald-400', border: 'border-emerald-500/20' };
        }
        if (lower.includes('comercial') || lower.includes('actualiz')) {
            return { bg: 'bg-primary/10', text: 'text-primary', border: 'border-primary/25' };
        }
        return { bg: 'bg-muted/80', text: 'text-muted-foreground', border: 'border-border' };
    }
</script>

<div class="space-y-3">
    {#each logs as log (log.id)}
        {@const tone = getActionTone(log.action)}
        <div class="rounded-xl border border-border/70 bg-card p-4 space-y-3 shadow-2xs">
            <!-- Header con Acción y Fecha -->
            <div class="flex items-start justify-between gap-2">
                <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold uppercase tracking-wider border {tone.bg} {tone.text} {tone.border} line-clamp-1">
                    {log.action}
                </span>
                <div class="flex items-center gap-1 text-xs tabular-nums text-muted-foreground shrink-0 pt-0.5">
                    <Clock class="size-3 text-muted-foreground" />
                    <span>{log.created_at}</span>
                </div>
            </div>

            <!-- Entidad y Comercio -->
            <div class="space-y-1">
                <span class="text-sm font-semibold text-foreground block break-words">
                    {log.entity_label ?? 'Registro del Sistema'}
                </span>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <Building2 class="size-3.5 text-muted-foreground shrink-0" />
                    <span class="truncate">
                        {log.tenant ? `${log.tenant.name} (ID: ${log.tenant.id})` : 'Plataforma General'}
                    </span>
                </div>
            </div>

            <!-- Operador y Botón Ver en Mobile -->
            <div class="pt-1 flex items-center justify-between gap-3 border-t border-border/50 pt-2.5">
                <div class="flex items-center gap-2 min-w-0">
                    <User class="size-3.5 text-muted-foreground shrink-0" />
                    <span class="text-sm font-medium text-foreground truncate">
                        {log.user_name ?? 'Sistema'}
                    </span>
                    {#if log.user_role}
                        <Badge variant="outline" class="text-xs uppercase tracking-wider font-semibold py-0.5 px-1.5 shrink-0">
                            {log.user_role}
                        </Badge>
                    {/if}
                </div>

                <Button
                    type="button"
                    variant="outline"
                    class="min-h-[44px] px-4 gap-1.5 text-sm font-medium shrink-0"
                    onclick={() => onview(log)}
                >
                    <Eye class="size-4" />
                    <span>Ver</span>
                </Button>
            </div>
        </div>
    {/each}
</div>
