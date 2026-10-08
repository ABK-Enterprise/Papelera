<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Card, CardContent } from '@/components/ui/card';
    import { Link } from '@inertiajs/svelte';
    import Icon from '@iconify/svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import TenantDetailDialog from '../TenantDetailDialog.svelte';

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
        products_count?: number;
        modules: ModuleSummary[];
        created_at: string;
    }

    let { tenants = [] }: { tenants: TenantItem[] } = $props();

    let selectedTenant = $state<TenantItem | null>(null);
    let isDialogOpen = $state(false);

    function openDetail(tenant: TenantItem) {
        selectedTenant = tenant;
        isDialogOpen = true;
    }

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
        <Card class="rounded-2xl border-border/70 bg-card p-4 shadow-2xs transition-all duration-200 hover:shadow-lg hover:shadow-accent/20 active:scale-[0.99]">

            <CardContent class="p-0 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <IconContainer size="lg" variant="primary">
                            {tenant.name ? tenant.name.charAt(0).toUpperCase() : 'C'}
                        </IconContainer>
                        <div class="flex flex-col min-w-0">
                            <span class="font-semibold text-foreground text-sm leading-snug truncate">{tenant.name}</span>
                            <span class="text-xs font-mono tabular-nums text-muted-foreground">
                                {tenant.cuit_tax_id ? `CUIT: ${tenant.cuit_tax_id}` : `ID: MER-${tenant.id.toString().padStart(5, '0')}`}
                            </span>
                        </div>
                    </div>

                    {#if tenant.is_active}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 shrink-0">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            Activo
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 shrink-0">
                            <span class="size-1.5 rounded-full bg-rose-500"></span>
                            Suspendido
                        </span>
                    {/if}
                </div>

                <div class="flex flex-col gap-1.5 text-xs text-muted-foreground tabular-nums pt-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5 font-medium">
                            <Icon icon="solar:users-group-rounded-bold-duotone" class="size-3.5 text-muted-foreground/80 shrink-0" />
                            <span>Usuarios <strong class="text-foreground font-semibold">{tenant.users_count ?? 0}</strong></span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs font-mono tabular-nums text-muted-foreground/80">
                            <Icon icon="solar:calendar-bold-duotone" class="size-3.5 text-muted-foreground/70" />
                            <span>{formatDate(tenant.created_at)}</span>
                        </div>
                    </div>

                    {#if tenant.products_count !== undefined}
                        <div class="flex items-center gap-1.5 font-medium">
                            <Icon icon="solar:box-minimalistic-bold-duotone" class="size-3.5 text-muted-foreground/80 shrink-0" />
                            <span>Productos <strong class="text-foreground font-semibold">{tenant.products_count}</strong></span>
                        </div>
                    {:else if tenant.modules}
                        <div class="flex items-center gap-1.5 font-medium">
                            <Icon icon="solar:archive-minimalistic-bold-duotone" class="size-3.5 text-muted-foreground/80 shrink-0" />
                            <span>Módulos activos <strong class="text-foreground font-semibold">{tenant.modules.length}</strong></span>
                        </div>
                    {/if}
                </div>

                <div class="space-y-1.5 pt-1">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Módulos contratados</span>
                    <div class="flex flex-wrap gap-1.5 pt-0.5">
                        {#each tenant.modules as mod}
                            <Badge variant="outline" class="text-xs font-medium tracking-wide bg-muted/40 px-2 py-0.5">
                                {mod.name}
                            </Badge>
                        {:else}
                            <span class="text-xs italic text-muted-foreground">Sin módulos</span>
                        {/each}
                    </div>
                </div>

                <div class="flex items-center gap-2 border-t border-border/40 pt-3">
                    <Button
                        variant="outline"
                        class="min-h-[44px] flex-1 gap-1.5 rounded-xl text-sm font-medium border-border/70 justify-center"
                        onclick={() => openDetail(tenant)}
                    >
                        <Icon icon="solar:eye-bold-duotone" class="size-4" />
                        <span>Ver detalle</span>
                    </Button>
                    <Link href={`/platform/tenants/${tenant.id}/edit`} class="flex-1">
                        <Button
                            variant="default"
                            class="min-h-[44px] w-full gap-1.5 rounded-xl text-sm font-medium justify-center"
                        >
                            <Icon icon="solar:pen-new-square-bold-duotone" class="size-3.5" />
                            <span>Editar</span>
                        </Button>
                    </Link>
                </div>
            </CardContent>
        </Card>
    {/each}
</div>

<TenantDetailDialog bind:open={isDialogOpen} tenant={selectedTenant} />
