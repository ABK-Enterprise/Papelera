<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import CreateTenantDialog from '@/pages/platform/tenants/CreateTenantDialog.svelte';
    import TenantListDesktop from './desktop/TenantListDesktop.svelte';
    import TenantListMobile from './mobile/TenantListMobile.svelte';
    import { router } from '@inertiajs/svelte';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Store from 'lucide-svelte/icons/store';
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
</script>

<AppHead title="Gestión de Comercios - Plataforma" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Encabezado y Acción Principal -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">Comercios</h1>
                <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                    {tenants.length} {tenants.length === 1 ? 'comercio' : 'comercios'}
                </span>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Gestiona y monitorea todas las cuentas de comercios registradas en el ecosistema.
            </p>
        </div>
        <Button
            onclick={() => (isCreateModalOpen = true)}
            class="min-h-[44px] gap-2 rounded-xl text-sm font-medium shadow-xs"
        >
            <Plus class="h-4 w-4" />
            <span>Añadir Comercio</span>
        </Button>
    </div>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3.5 bg-card/60 backdrop-blur-xs p-3.5 rounded-2xl border border-border/70 shadow-2xs">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <!-- Selector de Estado -->
            <div class="relative min-w-[150px]">
                <select
                    value={statusFilter}
                    onchange={handleStatusChange}
                    class="w-full appearance-none rounded-xl border border-border/70 bg-background px-3.5 py-2.5 pr-9 text-xs sm:text-sm font-medium text-foreground focus:border-primary focus:outline-hidden focus:ring-2 focus:ring-ring shadow-2xs"
                >
                    <option value="all">Todos los estados</option>
                    <option value="activo">Activo</option>
                    <option value="suspendido">Suspendido</option>
                </select>
                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            </div>

            <!-- Selector de Orden -->
            <div class="relative min-w-[200px]">
                <select
                    value={sortFilter}
                    onchange={handleSortChange}
                    class="w-full appearance-none rounded-xl border border-border/70 bg-background px-3.5 py-2.5 pr-9 text-xs sm:text-sm font-medium text-foreground focus:border-primary focus:outline-hidden focus:ring-2 focus:ring-ring shadow-2xs"
                >
                    <option value="latest">Fecha de alta (Más recientes)</option>
                    <option value="oldest">Fecha de alta (Más antiguos)</option>
                    <option value="name_asc">Nombre (A-Z)</option>
                </select>
                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            </div>

            <!-- Búsqueda -->
            <div class="relative flex-1 min-w-[240px]">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <input
                    type="text"
                    value={searchInput}
                    oninput={handleSearchInput}
                    placeholder="Buscar comercios por nombre o CUIT..."
                    class="w-full rounded-xl border border-border/70 bg-background pl-10 pr-9 py-2 text-xs sm:text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-hidden focus:ring-2 focus:ring-ring shadow-2xs"
                />
                {#if searchInput}
                    <button
                        type="button"
                        onclick={clearSearch}
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        aria-label="Limpiar búsqueda"
                    >
                        <X class="h-4 w-4" />
                    </button>
                {/if}
            </div>
        </div>
    </div>

    <!-- Contenido Modular: Desktop vs Mobile -->
    {#if tenants.length > 0}
        <div class="hidden md:block">
            <TenantListDesktop {tenants} />
        </div>
        <div class="block md:hidden">
            <TenantListMobile {tenants} />
        </div>
    {:else}
        <EmptyState
            icon={Store}
            title={searchInput || statusFilter !== 'all' ? 'No se encontraron comercios' : 'No hay comercios registrados'}
            description={searchInput || statusFilter !== 'all'
                ? 'Prueba modificando los términos de búsqueda o limpiando los filtros seleccionados.'
                : 'Crea el primer comercio para asignarle su administrador principal y módulos de trabajo.'}
            actionLabel={!searchInput && statusFilter === 'all' ? 'Crear Primer Comercio' : 'Limpiar Filtros'}
            onaction={!searchInput && statusFilter === 'all' ? () => (isCreateModalOpen = true) : resetAllFilters}
        />
    {/if}
</div>

<!-- Modal para Crear Comercio -->
<CreateTenantDialog
    bind:open={isCreateModalOpen}
    modules={availableModules}
/>
