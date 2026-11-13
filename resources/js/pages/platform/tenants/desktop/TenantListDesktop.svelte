<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Link } from '@inertiajs/svelte';
    import Icon from '@iconify/svelte';
    import X from 'lucide-svelte/icons/x';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import IconContainer from '@/components/common/IconContainer.svelte';

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

    function toggleDetail(tenant: TenantItem) {
        if (selectedTenant?.id === tenant.id) {
            selectedTenant = null;
        } else {
            selectedTenant = tenant;
        }
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

<div class="flex flex-col lg:flex-row gap-6 items-start">
    <!-- Lista de Wide-Cards -->
    <div class={`w-full transition-all duration-300 ${selectedTenant ? 'lg:w-[65%]' : 'lg:w-full'}`}>
        <div class="flex flex-col gap-3">
            {#each tenants as tenant (tenant.id)}
                <div
                    class={`p-5 rounded-2xl border bg-card shadow-2xs transition-all duration-200 hover:shadow-lg hover:shadow-accent/20 ${
                        selectedTenant?.id === tenant.id
                            ? 'border-primary ring-1 ring-primary/30 bg-primary/[0.02]'
                            : 'border-border/70 hover:border-border'
                    }`}
                >

                    <div class="flex items-center justify-between gap-4">
                        <!-- Info Principal -->
                        <div class="flex items-center gap-3.5 min-w-0">
                            <IconContainer size="lg" variant="primary">
                                {tenant.name ? tenant.name.charAt(0).toUpperCase() : 'C'}
                            </IconContainer>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2.5">
                                    <h3 class="font-semibold text-base text-foreground truncate">{tenant.name}</h3>
                                    {#if tenant.is_active}
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium tracking-wide bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            Activo
                                        </span>
                                    {:else}
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium tracking-wide bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20">
                                            <span class="size-1.5 rounded-full bg-rose-500"></span>
                                            Suspendido
                                        </span>
                                    {/if}
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    <span class="tabular-nums">{tenant.cuit_tax_id ? `CUIT: ${tenant.cuit_tax_id}` : `ID: MER-${tenant.id.toString().padStart(5, '0')}`}</span>
                                    • Alta: <span class="tabular-nums">{formatDate(tenant.created_at)}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Métricas y Botones -->
                        <div class="flex items-center gap-5 shrink-0">
                            <div class="flex items-center gap-2.5 text-xs text-muted-foreground tabular-nums font-medium">
                                <span>Usuarios <strong class="text-foreground font-semibold">{tenant.users_count ?? 0}</strong></span>
                                {#if tenant.products_count !== undefined}
                                    <span class="inline-block h-3 w-px bg-border/80 shrink-0" aria-hidden="true"></span>
                                    <span>Productos <strong class="text-foreground font-semibold">{tenant.products_count}</strong></span>
                                {:else if tenant.modules}
                                    <span class="inline-block h-3 w-px bg-border/80 shrink-0" aria-hidden="true"></span>
                                    <span>Módulos <strong class="text-foreground font-semibold">{tenant.modules.length}</strong></span>
                                {/if}
                            </div>

                            <div class="flex items-center gap-2">
                                <Button
                                    variant={selectedTenant?.id === tenant.id ? 'default' : 'outline'}
                                    size="sm"
                                    class="min-h-[38px] gap-1.5 rounded-xl text-sm font-medium border-border/70"
                                    onclick={() => toggleDetail(tenant)}
                                >
                                    <Icon icon="solar:eye-bold-duotone" class="size-4" />
                                    <span>{selectedTenant?.id === tenant.id ? 'Ocultar' : 'Ver'}</span>
                                </Button>

                                <Link href={`/platform/tenants/${tenant.id}/edit`}>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="min-h-[38px] gap-1.5 rounded-xl text-sm font-medium border-border/70"
                                    >
                                        <Icon icon="solar:pen-new-square-bold-duotone" class="size-3.5" />
                                        <span>Editar</span>
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            {/each}
        </div>
    </div>

    <!-- Panel Lateral Desktop (Split View con Fade) -->
    {#if selectedTenant}
        <div class="lg:w-[35%] lg:sticky lg:top-6 bg-card border border-border/80 rounded-2xl p-5 shadow-sm space-y-4 animate-in fade-in duration-200">
            <div class="flex items-center justify-between pb-3 border-b border-border/50">
                <div class="flex items-center gap-3">
                    <IconContainer size="md" variant="primary">
                        {selectedTenant.name ? selectedTenant.name.charAt(0).toUpperCase() : 'C'}
                    </IconContainer>
                    <div>
                        <h3 class="font-bold text-base text-foreground leading-snug">{selectedTenant.name}</h3>
                        <span class="text-xs tabular-nums text-muted-foreground">
                            {selectedTenant.cuit_tax_id ? `CUIT: ${selectedTenant.cuit_tax_id}` : `ID: MER-${selectedTenant.id.toString().padStart(5, '0')}`}
                        </span>
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    class="size-8 p-0 rounded-lg text-muted-foreground hover:text-foreground"
                    onclick={() => selectedTenant = null}
                >
                    <X class="size-4" />
                </Button>
            </div>

            <!-- Estado -->
            <div class="flex items-center justify-between p-3 rounded-xl bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90">
                <span class="text-xs font-medium text-muted-foreground">Estado del comercio</span>
                {#if selectedTenant.is_active}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        Activo
                    </span>
                {:else}
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20">
                        <span class="size-1.5 rounded-full bg-rose-500"></span>
                        Suspendido
                    </span>
                {/if}
            </div>

            <!-- Métricas -->
            <div class="grid grid-cols-2 gap-2.5">
                <div class="p-3 rounded-xl border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35">
                    <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium">
                        <Icon icon="solar:users-group-rounded-bold-duotone" class="size-3.5" />
                        <span>Usuarios</span>
                    </div>
                    <div class="text-xl font-bold tabular-nums text-foreground mt-1">
                        {selectedTenant.users_count ?? 0}
                    </div>
                </div>
                <div class="p-3 rounded-xl border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35">
                    <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium">
                        <Icon icon="solar:box-minimalistic-bold-duotone" class="size-3.5" />
                        <span>Módulos</span>
                    </div>
                    <div class="text-xl font-bold tabular-nums text-foreground mt-1">
                        {selectedTenant.modules?.length ?? 0}
                    </div>
                </div>
            </div>

            <!-- Fecha de alta -->
            <div class="flex items-center gap-2 text-xs text-muted-foreground p-3 rounded-xl border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35">
                <Icon icon="solar:calendar-bold-duotone" class="size-4 text-muted-foreground/70" />
                <span>Fecha de alta: <span class="tabular-nums">{formatDate(selectedTenant.created_at)}</span></span>
            </div>

            <!-- Módulos Contratados -->
            {#if selectedTenant.modules && selectedTenant.modules.length > 0}
                <div class="space-y-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Módulos contratados</span>
                    <div class="flex flex-wrap gap-1.5">
                        {#each selectedTenant.modules as mod}
                            <Badge variant="outline" class="text-xs font-medium tracking-wide bg-muted/60 dark:bg-secondary/45 border-border/80 px-2.5 py-0.5">
                                {mod.name}
                            </Badge>
                        {/each}
                    </div>
                </div>
            {/if}

            <!-- Acción Principal -->
            <div class="pt-2 border-t border-border/50">
                <Link href={`/platform/tenants/${selectedTenant.id}/edit`} class="w-full">
                    <Button class="w-full min-h-[40px] gap-2 rounded-xl text-sm font-medium shadow-sm">
                        <Edit2 class="size-4" />
                        <span>Editar datos del comercio</span>
                    </Button>
                </Link>
            </div>
        </div>
    {/if}
</div>
