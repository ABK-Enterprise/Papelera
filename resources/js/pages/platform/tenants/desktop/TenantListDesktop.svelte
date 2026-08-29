<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
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

<div class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-xs">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-border/60 bg-muted/40 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            <tr>
                <th scope="col" class="py-3.5 pl-6 pr-4">Comercio</th>
                <th scope="col" class="px-4 py-3.5">Usuarios</th>
                <th scope="col" class="px-4 py-3.5">Estado</th>
                <th scope="col" class="px-4 py-3.5">Fecha de alta</th>
                <th scope="col" class="px-4 py-3.5 text-right">Módulos contratados</th>
                <th scope="col" class="py-3.5 pr-6 pl-4 text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border/40">
            {#each tenants as tenant (tenant.id)}
                <tr class="group transition-colors hover:bg-muted/30">
                    <td class="py-4 pl-6 pr-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary font-bold text-base">
                                {tenant.name ? tenant.name.charAt(0).toUpperCase() : 'C'}
                            </div>
                            <div class="flex flex-col">
                                <span class="font-semibold text-foreground">{tenant.name}</span>
                                <span class="text-xs text-muted-foreground tabular-nums">
                                    {tenant.cuit_tax_id ? `CUIT: ${tenant.cuit_tax_id}` : `ID: MER-${tenant.id.toString().padStart(5, '0')}`}
                                </span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-medium tabular-nums">
                            <Users class="h-3.5 w-3.5" />
                            <span>{tenant.users_count ?? 0} {tenant.users_count === 1 ? 'usuario' : 'usuarios'}</span>
                        </div>
                    </td>
                    <td class="px-4 py-4">
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
                    </td>
                    <td class="px-4 py-4 text-xs text-muted-foreground tabular-nums">
                        <div class="flex items-center gap-1.5">
                            <Calendar class="h-3.5 w-3.5 text-muted-foreground/70" />
                            <span>{formatDate(tenant.created_at)}</span>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-right">
                        <div class="flex flex-wrap justify-end gap-1.5 max-w-xs ml-auto">
                            {#each tenant.modules as mod}
                                <Badge variant="outline" class="text-xs font-medium tracking-wide bg-muted/40 px-2 py-0.5">
                                    {mod.name}
                                </Badge>
                            {/each}
                            {#if tenant.modules.length === 0}
                                <span class="text-xs italic text-muted-foreground">Sin módulos</span>
                            {/if}
                        </div>
                    </td>
                    <td class="py-4 pr-6 pl-4 text-right">
                        <Link href={`/platform/tenants/${tenant.id}/edit`}>
                            <Button
                                variant="outline"
                                size="sm"
                                class="min-h-[40px] gap-1.5 rounded-xl border-border/70 text-xs font-medium hover:bg-muted/60 transition-colors"
                            >
                                <Edit2 class="h-3.5 w-3.5" />
                                <span>Editar</span>
                            </Button>
                        </Link>
                    </td>
                </tr>
            {/each}
        </tbody>
    </table>
</div>
