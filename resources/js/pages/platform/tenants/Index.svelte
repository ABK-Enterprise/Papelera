<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
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

<AppHead title="Comercios - Plataforma" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Header Minimalista -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-border/50 pb-5">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">Comercios</h1>
        </div>
        <div class="flex items-center gap-3">
            <Button
                onclick={() => (isCreateModalOpen = true)}
                class="flex items-center gap-2 font-medium shadow-sm min-h-[40px] rounded-xl"
            >
                <Plus class="size-4" />
                <span>Crear comercio</span>
            </Button>
        </div>
    </div>

    <!-- Toolbar de B�squeda y Filtros -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3.5 bg-card p-3.5 rounded-2xl border border-border/70 shadow-xs">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <!-- Selector de Estado -->
            <div class="relative min-w-[150px]">
                <select
                    value={statusFilter}
                    onchange={handleStatusChange}
                    class="w-full appearance-none rounded-xl border border-border bg-background px-3.5 py-2 pr-9 text-xs sm:text-sm font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                >
                    <option value="all">Todos los estados</option>
                    <option value="activo">Activo</option>
                    <option value="suspendido">Suspendido</option>
                </select>
                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
            </div>

            <!-- Selector de Orden -->
            <div class="relative min-w-[190px]">
                <select
                    value={sortFilter}
                    onchange={handleSortChange}
                    class="w-full appearance-none rounded-xl border border-border bg-background px-3.5 py-2 pr-9 text-xs sm:text-sm font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                >
                    <option value="latest">M�s recientes</option>
                    <option value="oldest">M�s antiguos</option>
                    <option value="name_asc">Nombre (A-Z)</option>
                </select>
                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
            </div>

            <!-- Input de B�squeda -->
            <div class="relative flex-1 min-w-[240px]">
                <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <input
                    type="text"
                    value={searchInput}
                    oninput={handleSearchInput}
                    placeholder="Buscar comercios por nombre o CUIT..."
                    class="w-full rounded-xl border border-border bg-background pl-9 pr-8 py-2 text-xs sm:text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                />
                {#if searchInput}
                    <button
                        type="button"
                        onclick={clearSearch}
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        aria-label="Limpiar b�squeda"
                    >
                        <X class="size-3.5" />
                    </button>
                {/if}
            </div>
        </div>

        <div class="flex items-center justify-between lg:justify-end gap-2 text-xs text-muted-foreground font-medium pt-1 lg:pt-0 tabular-nums">
            <span>Mostrando {tenants.length} {tenants.length === 1 ? 'comercio' : 'comercios'}</span>
        </div>
    </div>

    <!-- Lista Desglosada Desktop & Mobile -->
    {#if tenants.length > 0}
        <div class="hidden md:block">
            <TenantListDesktop {tenants} />
        </div>
        <div class="block md:hidden">
            <TenantListMobile {tenants} />
        </div>
    {:else}
        <!-- Empty State -->
        <div class="rounded-2xl border border-dashed border-border/80 p-12 text-center bg-card/40 flex flex-col items-center justify-center shadow-xs">
            <div class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary mb-4 border border-primary/20">
                <Store class="size-7" />
            </div>
            <h3 class="text-lg font-bold text-foreground">
                {searchInput || statusFilter !== 'all' ? 'No se encontraron comercios' : 'No hay comercios registrados a�n'}
            </h3>
            <p class="mt-1.5 text-sm text-muted-foreground max-w-md">
                {searchInput || statusFilter !== 'all'
                    ? 'Prueba modificando los t�rminos de b�squeda o limpiando los filtros seleccionados.'
                    : 'Crea el primer comercio para asignarle su administrador principal y m�dulos de trabajo.'}
            </p>
            {#if !searchInput && statusFilter === 'all'}
                <Button
                    onclick={() => (isCreateModalOpen = true)}
                    class="mt-5 flex items-center gap-2 font-medium shadow-sm rounded-xl min-h-[40px]"
                >
                    <Plus class="size-4" />
                    <span>Crear primer comercio</span>
                </Button>
            {:else}
                <Button
                    variant="outline"
                    onclick={resetAllFilters}
                    class="mt-4 text-xs font-medium rounded-xl"
                >
                    Limpiar todos los filtros
                </Button>
            {/if}
        </div>
    {/if}
</div>

<!-- Modal para Crear Comercio -->
<CreateTenantDialog
    bind:open={isCreateModalOpen}
    modules={availableModules}
/>
