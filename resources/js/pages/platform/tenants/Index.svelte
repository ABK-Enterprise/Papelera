<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import CreateTenantDialog from '@/pages/platform/tenants/CreateTenantDialog.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Edit2 from 'lucide-svelte/icons/edit-2';

    import Filter from 'lucide-svelte/icons/filter';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Store from 'lucide-svelte/icons/store';
    import Users from 'lucide-svelte/icons/users';
    import X from 'lucide-svelte/icons/x';

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

    interface AvailableModule {
        id: number;
        name: string;
        code: string;
        description: string | null;
    }

    interface Filters {
        search: string;
        status: string;
        sort: string;
    }

    let {
        tenants = [],
        availableModules = [],
        filters = { search: '', status: 'all', sort: 'latest' },
    }: {
        tenants: TenantItem[];
        availableModules?: AvailableModule[];
        filters?: Filters;
    } = $props();

    let isCreateModalOpen = $state(false);
    let searchInput = $state(filters.search ?? '');
    let statusFilter = $state(filters.status ?? 'all');
    let sortFilter = $state(filters.sort ?? 'latest');

    $effect(() => {
        searchInput = filters.search ?? '';
        statusFilter = filters.status ?? 'all';
        sortFilter = filters.sort ?? 'latest';
    });

    let debounceTimer: ReturnType<typeof setTimeout> | undefined;

    function applyFilters() {
        router.get(
            '/platform/tenants',
            {
                search: searchInput || undefined,
                status: statusFilter !== 'all' ? statusFilter : undefined,
                sort: sortFilter !== 'latest' ? sortFilter : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function handleSearchInput(e: Event) {
        const target = e.target as HTMLInputElement;
        searchInput = target.value;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            applyFilters();
        }, 300);
    }

    function handleStatusChange(e: Event) {
        const target = e.target as HTMLSelectElement;
        statusFilter = target.value;
        applyFilters();
    }

    function handleSortChange(e: Event) {
        const target = e.target as HTMLSelectElement;
        sortFilter = target.value;
        applyFilters();
    }

    function clearSearch() {
        searchInput = '';
        applyFilters();
    }

    function resetAllFilters() {
        searchInput = '';
        statusFilter = 'all';
        sortFilter = 'latest';
        router.get('/platform/tenants', {}, { preserveState: true, replace: true });
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

<AppHead title="Gestión de Comercios - Luminous" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
        <!-- Page Header & Actions -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">Comercios</h1>
                <p class="mt-1.5 text-sm text-muted-foreground sm:text-base max-w-2xl">
                    Gestiona y monitorea todas las cuentas de comercios registradas en el ecosistema.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <Button
                    onclick={() => (isCreateModalOpen = true)}
                    class="flex items-center gap-2 font-semibold shadow-sm"
                >
                    <Plus class="size-4" />
                    <span>Añadir Comercio</span>
                </Button>
            </div>
        </div>

        <!-- Filter / Action Toolbar -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3.5 bg-card/60 backdrop-blur-sm p-3.5 rounded-xl border border-border/60 shadow-xs">
            <div class="flex flex-wrap items-center gap-3 flex-1">
                <!-- Status Filter Select -->
                <div class="min-w-[160px]">
                    <select
                        value={statusFilter}
                        onchange={handleStatusChange}
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2 text-xs sm:text-sm font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                    >
                        <option value="all">Todos los estados</option>
                        <option value="activo">Activo</option>
                        <option value="suspendido">Suspendido</option>
                    </select>
                </div>

                <!-- Sort Order Select -->
                <div class="min-w-[210px]">
                    <select
                        value={sortFilter}
                        onchange={handleSortChange}
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2 text-xs sm:text-sm font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                    >
                        <option value="latest">Fecha de alta (Más recientes)</option>
                        <option value="oldest">Fecha de alta (Más antiguos)</option>
                        <option value="name_asc">Nombre (A-Z)</option>
                    </select>
                </div>


                <!-- Advanced Search Input (Laravel Scout powered) -->
                <div class="relative flex-1 min-w-[240px]">
                    <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                    <input
                        type="text"
                        value={searchInput}
                        oninput={handleSearchInput}
                        placeholder="Buscar comercios por nombre o CUIT..."
                        class="w-full rounded-lg border border-border bg-background pl-9 pr-8 py-2 text-xs sm:text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                    />
                    {#if searchInput}
                        <button
                            type="button"
                            onclick={clearSearch}
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            aria-label="Limpiar búsqueda"
                        >
                            <X class="size-3.5" />
                        </button>
                    {/if}
                </div>
            </div>

            <div class="flex items-center justify-between lg:justify-end gap-2 text-xs text-muted-foreground font-medium pt-1 lg:pt-0">
                <span>Mostrando {tenants.length} {tenants.length === 1 ? 'comercio' : 'comercios'}</span>
            </div>
        </div>

        <!-- Data Table (Glass Panel) -->
        {#if tenants.length > 0}
            <div class="rounded-xl overflow-hidden border border-border/60 bg-card shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border/80 bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <th class="py-3.5 px-6">Nombre del comercio</th>
                                <th class="py-3.5 px-6">Contacto / Usuarios</th>
                                <th class="py-3.5 px-6">Estado</th>
                                <th class="py-3.5 px-6">Fecha de alta</th>
                                <th class="py-3.5 px-6 text-right">Módulos</th>
                                <th class="py-3.5 px-6 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40 text-sm">
                            {#each tenants as tenant (tenant.id)}
                                <tr class="hover:bg-muted/30 transition-colors group">
                                    <!-- Nombre del Comercio + Initial Avatar -->
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-bold text-base shrink-0">
                                                {tenant.name ? tenant.name.charAt(0).toUpperCase() : 'C'}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-foreground">{tenant.name}</div>
                                                <div class="text-xs text-muted-foreground font-mono">
                                                    {tenant.cuit_tax_id ? `CUIT: ${tenant.cuit_tax_id}` : `ID: MER-${tenant.id.toString().padStart(5, '0')}`}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Usuarios / Contacto -->
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-1.5 text-xs text-foreground font-medium">
                                            <Users class="size-3.5 text-muted-foreground" />
                                            <span>{tenant.users_count ?? 0} {tenant.users_count === 1 ? 'usuario registrado' : 'usuarios registrados'}</span>
                                        </div>
                                    </td>

                                    <!-- Estado (Activo / Suspendido) -->
                                    <td class="py-4 px-6">
                                        {#if tenant.is_active}
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                Activo
                                            </span>
                                        {:else}
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                                <span class="size-1.5 rounded-full bg-rose-500"></span>
                                                Suspendido
                                            </span>
                                        {/if}
                                    </td>

                                    <!-- Fecha de Alta -->
                                    <td class="py-4 px-6 text-xs text-muted-foreground">
                                        <div class="flex items-center gap-1.5">
                                            <Calendar class="size-3.5 text-muted-foreground/70" />
                                            <span>{formatDate(tenant.created_at)}</span>
                                        </div>
                                    </td>

                                    <!-- Módulos Contratados -->
                                    <td class="py-4 px-6 text-right">
                                        <div class="flex flex-wrap justify-end gap-1.5 max-w-xs ml-auto">
                                            {#each tenant.modules as mod}
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-muted text-muted-foreground border border-border/60">
                                                    {mod.name}
                                                </span>
                                            {/each}
                                            {#if tenant.modules.length === 0}
                                                <span class="text-xs italic text-muted-foreground">Sin módulos</span>
                                            {/if}
                                        </div>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="py-4 px-6 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link href={`/platform/tenants/${tenant.id}/edit`}>
                                                <Button size="sm" variant="outline" class="h-8 text-xs font-semibold gap-1.5 hover:bg-muted">
                                                    <Edit2 class="size-3" />
                                                    <span>Editar</span>
                                                </Button>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </div>
        {:else}
            <!-- Empty State -->
            <div class="rounded-xl border border-dashed border-border/80 p-12 text-center bg-card/40 flex flex-col items-center justify-center shadow-xs">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary mb-4">
                    <Store class="size-7" />
                </div>
                <h3 class="text-lg font-bold text-foreground">
                    {searchInput || statusFilter !== 'all' ? 'No se encontraron comercios con esos criterios' : 'No hay comercios registrados aún'}
                </h3>
                <p class="mt-1.5 text-sm text-muted-foreground max-w-md">
                    {searchInput || statusFilter !== 'all'
                        ? 'Prueba modificando los términos de búsqueda o limpiando los filtros seleccionados.'
                        : 'Crea el primer comercio para asignarle su administrador principal y módulos de trabajo.'}
                </p>
                {#if !searchInput && statusFilter === 'all'}
                    <Button
                        onclick={() => (isCreateModalOpen = true)}
                        class="mt-5 flex items-center gap-2 font-semibold shadow-sm"
                    >
                        <Plus class="size-4" />
                        <span>Crear Primer Comercio</span>
                    </Button>
                {:else}
                    <Button
                        variant="outline"
                        onclick={resetAllFilters}
                        class="mt-4 text-xs font-medium"
                    >
                        Limpiar todos los filtros
                    </Button>
                {/if}
            </div>
        {/if}
    </div>

    <!-- Modal para Añadir Comercio -->
    <CreateTenantDialog
        bind:open={isCreateModalOpen}
        modules={availableModules}
    />
