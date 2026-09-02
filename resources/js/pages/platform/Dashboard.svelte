<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Link } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Users from 'lucide-svelte/icons/users';
    import Package from 'lucide-svelte/icons/package';
    import Boxes from 'lucide-svelte/icons/boxes';
    import Plus from 'lucide-svelte/icons/plus';
    import Eye from 'lucide-svelte/icons/eye';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Calendar from 'lucide-svelte/icons/calendar';
    import X from 'lucide-svelte/icons/x';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import Icon from '@iconify/svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import TenantDetailDialog from './tenants/TenantDetailDialog.svelte';
    import ModuleDetailDialog from './modules/ModuleDetailDialog.svelte';

    interface RecentTenant {
        id: number;
        name: string;
        cuit_tax_id: string | null;
        is_active: boolean;
        users_count: number;
        products_count: number;
        created_at?: string;
        modules?: Array<{ id: number; name: string; code: string }>;
    }

    interface PlatformModule {
        id: number;
        code: string;
        name: string;
        description?: string | null;
        is_active: boolean;
        tenants_count: number;
        permissions?: Array<{ id: number; name: string; module_id: number }>;
    }

    type DashboardSearchItem =
        | { type: 'tenant'; id: number; name: string; cuit_tax_id: string | null; is_active: boolean; raw: RecentTenant }
        | { type: 'module'; id: number; name: string; code: string; is_active: boolean; raw: PlatformModule };

    let {
        recentTenants = [],
        modules = [],
    }: {
        recentTenants: RecentTenant[];
        modules: PlatformModule[];
    } = $props();

    let selectedTenant = $state<RecentTenant | null>(null);
    let isMobileDialogOpen = $state(false);

    let selectedModule = $state<PlatformModule | null>(null);
    let isModuleDialogOpen = $state(false);

    let dashboardSearch = $state('');

    let searchItems = $derived.by<DashboardSearchItem[]>(() => {
        const q = dashboardSearch.toLowerCase().trim();
        if (!q) return [];

        const tenantMatches: DashboardSearchItem[] = recentTenants
            .filter((t) => t.name.toLowerCase().includes(q) || (t.cuit_tax_id && t.cuit_tax_id.toLowerCase().includes(q)))
            .map((t) => ({
                type: 'tenant',
                id: t.id,
                name: t.name,
                cuit_tax_id: t.cuit_tax_id,
                is_active: t.is_active,
                raw: t,
            }));

        const moduleMatches: DashboardSearchItem[] = modules
            .filter((m) => m.name.toLowerCase().includes(q) || m.code.toLowerCase().includes(q))
            .map((m) => ({
                type: 'module',
                id: m.id,
                name: m.name,
                code: m.code,
                is_active: m.is_active,
                raw: m,
            }));

        return [...tenantMatches, ...moduleMatches];
    });

    function handleSelectSearchItem(item: DashboardSearchItem) {
        if (item.type === 'tenant') {
            selectedTenant = item.raw;
            if (typeof window !== 'undefined' && window.innerWidth < 1024) {
                isMobileDialogOpen = true;
            }
        } else if (item.type === 'module') {
            selectedModule = {
                ...item.raw,
                description: item.raw.description ?? null,
                permissions: item.raw.permissions ?? [],
            };
            isModuleDialogOpen = true;
        }
        dashboardSearch = '';
    }

    function toggleDesktopDetail(tenant: RecentTenant) {
        if (selectedTenant?.id === tenant.id) {
            selectedTenant = null;
        } else {
            selectedTenant = tenant;
        }
    }

    function openMobileDetail(tenant: RecentTenant) {
        selectedTenant = tenant;
        isMobileDialogOpen = true;
    }

    function formatDate(dateStr?: string) {
        if (!dateStr) return '-';
        try {
            return new Intl.DateTimeFormat('es-AR', {
                dateStyle: 'medium',
            }).format(new Date(dateStr));
        } catch {
            return dateStr;
        }
    }
</script>

<AppHead title="Panel de control - Plataforma" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Header Minimalista -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between border-b border-border/50 pb-5">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">
                Panel de control
            </h1>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <!-- SearchCombobox Rápido de Plataforma -->
            <div class="w-full sm:w-72">
                <SearchCombobox
                    bind:value={dashboardSearch}
                    items={searchItems}
                    placeholder="Buscar comercio o módulo..."
                    showDropdown={true}
                    onselect={handleSelectSearchItem}
                >
                    {#snippet itemSnippet(item)}
                        <div class="flex items-center justify-between gap-2 w-full">
                            <div class="flex items-center gap-2 min-w-0">
                                {#if item.type === 'tenant'}
                                    <Building2 class="size-3.5 text-primary shrink-0" />
                                    <span class="font-semibold truncate">{item.name}</span>
                                    {#if item.cuit_tax_id}
                                        <span class="text-[10px] text-muted-foreground tabular-nums">({item.cuit_tax_id})</span>
                                    {/if}
                                {:else}
                                    <Boxes class="size-3.5 text-primary shrink-0" />
                                    <span class="font-semibold truncate">{item.name}</span>
                                    <span class="text-[10px] bg-muted px-1.5 py-0.5 rounded text-muted-foreground tabular-nums">{item.code}</span>
                                {/if}
                            </div>
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground shrink-0 font-medium">
                                {item.type === 'tenant' ? 'Comercio' : 'Módulo'}
                            </span>
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>

            <div class="flex items-center gap-2.5">
                <Link href="/platform/tenants/create">
                    <Button class="min-h-[40px] gap-2 shadow-sm font-medium rounded-xl">
                        <Plus class="size-4" />
                        <span>Crear comercio</span>
                    </Button>
                </Link>
                <Link href="/platform/modules">
                    <Button variant="outline" class="min-h-[40px] gap-2 font-medium rounded-xl border-border/70">
                        <Boxes class="size-4" />
                        <span>Gestionar módulos</span>
                    </Button>
                </Link>
            </div>
        </div>
    </div>

    <!-- Sección de Comercios con Split View en Desktop -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base sm:text-lg font-semibold tracking-tight text-foreground">Comercios registrados</h2>
            <Link href="/platform/tenants" class="text-xs font-medium text-primary hover:underline flex items-center gap-1">
                <span>Ver todos los comercios</span>
                <ArrowUpRight class="size-3.5" />
            </Link>
        </div>

        <div class="flex flex-col lg:flex-row gap-6 items-start">
            <!-- Lista de Wide-Cards -->
            <div class={`w-full transition-all duration-300 ${selectedTenant ? 'lg:w-[65%]' : 'lg:w-full'}`}>
                <div class="flex flex-col gap-3">
                    {#each recentTenants as tenant (tenant.id)}
                        <div
                            class={`p-4 sm:p-5 rounded-2xl border bg-card shadow-2xs transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-accent/25 ${
                                selectedTenant?.id === tenant.id
                                    ? 'border-primary ring-1 ring-primary/30 bg-primary/[0.02]'
                                    : 'border-border/70 hover:border-border'
                            }`}
                        >

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <!-- Info Principal -->
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <IconContainer size="lg" variant="primary">
                                        {tenant.name ? tenant.name.charAt(0).toUpperCase() : 'C'}
                                    </IconContainer>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2.5">
                                            <h3 class="font-semibold text-base text-foreground truncate">{tenant.name}</h3>
                                            {#if tenant.is_active}
                                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    Activo
                                                </span>
                                            {:else}
                                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                                    <span class="size-1.5 rounded-full bg-rose-500"></span>
                                                    Suspendido
                                                </span>
                                            {/if}
                                        </div>
                                        <p class="text-xs text-muted-foreground tabular-nums mt-0.5">
                                            {tenant.cuit_tax_id ? `CUIT: ${tenant.cuit_tax_id}` : `ID: MER-${tenant.id.toString().padStart(5, '0')}`}
                                            • Alta: {formatDate(tenant.created_at)}
                                        </p>
                                    </div>
                                </div>

                                <!-- Métricas y Acción -->
                                <div class="flex items-center justify-between sm:justify-end gap-5 pt-2 sm:pt-0 border-t sm:border-t-0 border-border/40 shrink-0">
                                    <!-- Desktop: Fila única con separador -->
                                    <div class="hidden sm:flex items-center gap-2.5 text-xs text-muted-foreground tabular-nums font-medium">
                                        <div class="flex items-center gap-1.5">
                                            <Icon icon="solar:users-group-rounded-bold-duotone" class="size-3.5 text-muted-foreground/80" />
                                            <span>Usuarios <strong class="text-foreground font-semibold">{tenant.users_count ?? 0}</strong></span>
                                        </div>
                                        {#if tenant.products_count !== undefined}
                                            <span class="inline-block h-3 w-px bg-border/80 shrink-0" aria-hidden="true"></span>
                                            <div class="flex items-center gap-1.5">
                                                <Icon icon="solar:box-minimalistic-bold-duotone" class="size-3.5 text-muted-foreground/80" />
                                                <span>Productos <strong class="text-foreground font-semibold">{tenant.products_count}</strong></span>
                                            </div>
                                        {/if}
                                    </div>

                                    <!-- Mobile: Stack vertical de 2 filas -->
                                    <div class="flex sm:hidden flex-col gap-1 text-xs text-muted-foreground tabular-nums font-medium">
                                        <div class="flex items-center gap-1.5">
                                            <Icon icon="solar:users-group-rounded-bold-duotone" class="size-3.5 text-muted-foreground/80 shrink-0" />
                                            <span>Usuarios <strong class="text-foreground font-semibold">{tenant.users_count ?? 0}</strong></span>
                                        </div>
                                        {#if tenant.products_count !== undefined}
                                            <div class="flex items-center gap-1.5">
                                                <Icon icon="solar:box-minimalistic-bold-duotone" class="size-3.5 text-muted-foreground/80 shrink-0" />
                                                <span>Productos <strong class="text-foreground font-semibold">{tenant.products_count}</strong></span>
                                            </div>
                                        {/if}
                                    </div>

                                    <!-- Botón Ver (Desktop activa Split View / Mobile abre Dialog) -->
                                    <div class="hidden lg:block">
                                        <Button
                                            variant={selectedTenant?.id === tenant.id ? 'default' : 'outline'}
                                            size="sm"
                                            class="min-h-[38px] gap-1.5 rounded-xl text-xs font-medium border-border/70"
                                            onclick={() => toggleDesktopDetail(tenant)}
                                        >
                                            <Icon icon="solar:eye-bold-duotone" class="size-4" />
                                            <span>{selectedTenant?.id === tenant.id ? 'Ocultar' : 'Ver'}</span>
                                        </Button>
                                    </div>
                                    <div class="block lg:hidden">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="min-h-[40px] gap-1.5 rounded-xl text-xs font-medium border-border/70"
                                            onclick={() => openMobileDetail(tenant)}
                                        >
                                            <Icon icon="solar:eye-bold-duotone" class="size-4" />
                                            <span>Ver detalle</span>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    {/each}

                    {#if recentTenants.length === 0}
                        <div class="rounded-2xl border border-dashed border-border/80 p-10 text-center bg-card/40 text-muted-foreground text-sm">
                            No hay comercios registrados en el sistema.
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Panel Lateral Desktop (Split View con Fade) -->
            {#if selectedTenant}
                <div class="hidden lg:block lg:w-[35%] lg:sticky lg:top-6 bg-card border border-border/80 rounded-2xl p-5 shadow-sm space-y-4 animate-in fade-in duration-200">
                    <div class="flex items-center justify-between pb-3 border-b border-border/50">
                        <div class="flex items-center gap-3">
                            <IconContainer size="md" variant="primary">
                                {selectedTenant.name ? selectedTenant.name.charAt(0).toUpperCase() : 'C'}
                            </IconContainer>
                            <div>
                                <h3 class="font-bold text-base text-foreground leading-snug">{selectedTenant.name}</h3>
                                <span class="text-xs text-muted-foreground tabular-nums">
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
                    <div class="flex items-center justify-between p-3 rounded-xl bg-muted/40 border border-border/50">
                        <span class="text-xs font-medium text-muted-foreground">Estado del comercio</span>
                        {#if selectedTenant.is_active}
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                Activo
                            </span>
                        {:else}
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                <span class="size-1.5 rounded-full bg-rose-500"></span>
                                Suspendido
                            </span>
                        {/if}
                    </div>

                    <!-- Métricas -->
                    <div class="grid grid-cols-2 gap-2.5">
                        <div class="p-3 rounded-xl border border-border/60 bg-card">
                            <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium">
                                <Users class="size-3.5" />
                                <span>Usuarios</span>
                            </div>
                            <div class="text-xl font-bold tabular-nums text-foreground mt-1">
                                {selectedTenant.users_count}
                            </div>
                        </div>
                        <div class="p-3 rounded-xl border border-border/60 bg-card">
                            <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium">
                                <Package class="size-3.5" />
                                <span>Productos</span>
                            </div>
                            <div class="text-xl font-bold tabular-nums text-foreground mt-1">
                                {selectedTenant.products_count}
                            </div>
                        </div>
                    </div>

                    <!-- Fecha de alta -->
                    <div class="flex items-center gap-2 text-xs text-muted-foreground p-3 rounded-xl border border-border/50 bg-muted/20 tabular-nums">
                        <Calendar class="size-4 text-muted-foreground/70" />
                        <span>Fecha de alta: {formatDate(selectedTenant.created_at)}</span>
                    </div>

                    <!-- Acción Principal -->
                    <div class="pt-2 border-t border-border/50">
                        <Link href={`/platform/tenants/${selectedTenant.id}/edit`} class="w-full">
                            <Button class="w-full min-h-[40px] gap-2 rounded-xl font-medium shadow-sm">
                                <Edit2 class="size-4" />
                                <span>Editar comercio</span>
                            </Button>
                        </Link>
                    </div>
                </div>
            {/if}
        </div>
    </div>
</div>

<!-- Modal para Mobile -->
<TenantDetailDialog bind:open={isMobileDialogOpen} tenant={selectedTenant} />

<!-- Modal Detalle de Módulo -->
<ModuleDetailDialog bind:open={isModuleDialogOpen} module={selectedModule} />
