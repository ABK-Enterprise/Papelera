<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Card, CardContent } from '@/components/ui/card';
    import { Link } from '@inertiajs/svelte';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Users from 'lucide-svelte/icons/users';
    import Calendar from 'lucide-svelte/icons/calendar';

    interface ModuleSummary {
        id: number;
        name: string;
        code: string;
        description?: string | null;
    }

    interface TenantItem {
        id: number;
        name: string;
        cuit_tax_id: string | null;
        is_active: boolean;
        users_count?: number;
        modules: ModuleSummary[];
        created_at: string;
    }

    let { tenants = [] }: { tenants: TenantItem[] } = $props();

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('es-AR', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        } catch {
            return dateStr;
        }
    }
</script>

<div class="flex flex-col gap-3">
    {#each tenants as tenant (tenant.id)}
        <Card class="rounded-2xl border-border/70 bg-card p-4 shadow-2xs transition-all active:scale-[0.99]">
            <CardContent class="p-0 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary font-bold text-base">
                            {tenant.name ? tenant.name.charAt(0).toUpperCase() : 'C'}
                        </div>
                        <div class="flex flex-col">
                            <span class="font-semibold text-foreground text-sm leading-snug">{tenant.name}</span>
                            <span class="text-xs text-muted-foreground font-mono tabular-nums">
                                {tenant.cuit_tax_id ? `CUIT: ${tenant.cuit_tax_id}` : `ID: MER-${tenant.id.toString().padStart(5, '0')}`}
                            </span>
                        </div>
                    </div>

                    {#if tenant.is_active}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Activo
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            Suspendido
                        </span>
                    {/if}
                </div>

                <div class="flex items-center justify-between text-xs text-muted-foreground tabular-nums pt-1">
                    <div class="flex items-center gap-1.5">
                        <Users class="h-3.5 w-3.5" />
                        <span>{tenant.users_count ?? 0} {tenant.users_count === 1 ? 'usuario' : 'usuarios'}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <Calendar class="h-3.5 w-3.5 text-muted-foreground/70" />
                        <span>{formatDate(tenant.created_at)}</span>
                    </div>
                </div>

                <div class="space-y-1.5 pt-1">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Módulos contratados</span>
                    <div class="flex flex-wrap gap-1.5">
                        {#each tenant.modules as mod}
                            <Badge variant="outline" class="text-xs font-medium tracking-wide bg-muted/40 px-2 py-0.5">
                                {mod.name}
                            </Badge>
                        {/each}
                        {#if tenant.modules.length === 0}
                            <span class="text-xs italic text-muted-foreground">Sin módulos</span>
                        {/if}
                    </div>
                </div>

                <div class="flex items-center justify-end border-t border-border/40 pt-3">
                    <Link href={`/platform/tenants/${tenant.id}/edit`} class="w-full">
                        <Button
                            variant="outline"
                            class="min-h-[44px] w-full gap-2 rounded-xl text-xs font-medium border-border/70 justify-center"
                        >
                            <Edit2 class="h-4 w-4" />
                            <span>Editar comercio</span>
                        </Button>
                    </Link>
                </div>
            </CardContent>
        </Card>
    {/each}
</div>
