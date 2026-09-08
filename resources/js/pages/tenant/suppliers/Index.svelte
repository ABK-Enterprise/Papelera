<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Link, router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Search from 'lucide-svelte/icons/search';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import Truck from 'lucide-svelte/icons/truck';
    import Users from 'lucide-svelte/icons/users';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import SupplierFilterModalMobile from './mobile/SupplierFilterModalMobile.svelte';
    import SupplierListDesktop from './desktop/SupplierListDesktop.svelte';
    import SupplierListMobile from './mobile/SupplierListMobile.svelte';
    import CreateSupplierModal from './CreateSupplierModal.svelte';
    import ManageSupplierModal, { type SupplierItem } from './ManageSupplierModal.svelte';

    interface FilterProps {
        search?: string | null;
        status?: string | null;
    }

    let {
        suppliers = [],
        totalCount = 0,
        filters = {},
    }: {
        suppliers: SupplierItem[];
        totalCount?: number;
        filters?: FilterProps;
    } = $props();

    let searchTerm = $state('');
    let selectedStatus = $state('');

    $effect(() => {
        searchTerm = filters?.search ?? '';
        selectedStatus = filters?.status ?? '';
    });

    let showCreateModal = $state(false);
    let showManageModal = $state(false);
    let isFilterModalOpen = $state(false);
    let selectedSupplier = $state<SupplierItem | null>(null);

    function applyFilters() {
        router.get(
            '/tenant/proveedores',
            {
                search: searchTerm || undefined,
                status: selectedStatus || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }

    function resetFilters() {
        searchTerm = '';
        selectedStatus = '';
        router.get('/tenant/proveedores');
    }

    function handleSelectSupplier(supplier: SupplierItem) {
        selectedSupplier = supplier;
        showManageModal = true;
    }

    let hasActiveFilters = $derived(Boolean(searchTerm || selectedStatus));
    let activeFiltersCount = $derived(selectedStatus ? 1 : 0);
</script>

<AppHead title="Gestión de Proveedores - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Pestañas de Navegación del Módulo Proveedores -->
    <div class="flex items-center gap-2 border-b pb-3">
        <Link href="/tenant/proveedores" class="inline-flex">
            <Button variant="secondary" size="sm" class="gap-2 text-xs font-bold text-primary bg-primary/10 shadow-2xs h-auto min-h-8 py-[2px] sm:h-8 sm:py-0">
                <Users class="size-4" />
                Directorio de Proveedores ({totalCount})
            </Button>
        </Link>
        <Link href="/tenant/proveedores/ordenes" class="inline-flex">
            <Button variant="ghost" size="sm" class="gap-2 text-xs font-semibold text-muted-foreground hover:text-foreground h-auto min-h-8 py-[2px] sm:h-8 sm:py-0">
                <Truck class="size-4" />
                Órdenes de Compra
            </Button>
        </Link>
    </div>

    <!-- Header de la Página -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <Truck class="size-7 text-primary" />
                Gestión de Proveedores
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Administra tu red de proveedores, contactos comerciales y compras integrales.
            </p>
        </div>

        <div class="flex items-center justify-end gap-2.5">
            <Button class="min-h-[40px] px-4 gap-2 rounded-xl text-sm font-semibold shadow-xs" onclick={() => (showCreateModal = true)}>
                <Plus class="h-4 w-4" />
                <span>Añadir Proveedor</span>
            </Button>
        </div>
    </div>

    <!-- Bento Card: Buscador y Filtros -->
    <Card class="rounded-xl p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.02)] border border-border/60">
        <div class="flex flex-col md:flex-row items-center justify-between gap-3">
            <!-- Buscador + Botón Filtros Mobile -->
            <div class="flex items-center gap-2 w-full md:w-[538px]">
                <div class="flex-1 min-w-0">
                    <SearchCombobox
                        bind:value={searchTerm}
                        items={suppliers}
                        searchKeys={['name', 'contact_number', 'email', 'address']}
                        placeholder="Buscar por nombre, teléfono, email..."
                        onsearch={(q) => {
                            searchTerm = q;
                            applyFilters();
                        }}
                        onselect={handleSelectSupplier}
                        onclear={resetFilters}
                    >

                        {#snippet itemSnippet(supp)}
                            <div class="flex items-center justify-between gap-3 w-full min-w-0">
                                <div class="flex flex-col min-w-0">
                                    <span class="truncate font-medium text-foreground">{supp.name}</span>
                                    {#if supp.contact_number}
                                        <span class="text-[11px] text-muted-foreground tabular-nums">{supp.contact_number}</span>
                                    {/if}
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    {#if supp.is_active}
                                        <span class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                            Activo
                                        </span>
                                    {:else}
                                        <span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground border border-border">
                                            Inactivo
                                        </span>
                                    {/if}
                                </div>
                            </div>
                        {/snippet}
                    </SearchCombobox>
                </div>

                <!-- Disparador de Filtros Mobile -->
                <div class="block md:hidden">
                    <Button
                        type="button"
                        variant={activeFiltersCount > 0 ? 'secondary' : 'outline'}
                        size="icon"
                        onclick={() => (isFilterModalOpen = true)}
                        class="relative h-10 w-10 shrink-0 rounded-xl border border-border/70 shadow-2xs"
                        aria-label="Abrir filtros"
                    >
                        <SlidersHorizontal class="h-4 w-4 text-foreground" />
                        {#if activeFiltersCount > 0}
                            <span class="absolute -top-1 -right-1 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-primary-foreground tabular-nums shadow-xs">
                                {activeFiltersCount}
                            </span>
                        {/if}
                    </Button>
                </div>
            </div>

            <!-- Selectores de Filtro Desktop -->
            <div class="hidden md:flex items-center gap-2.5 w-auto">
                <select
                    bind:value={selectedStatus}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[150px]"
                >
                    <option value="">Estado: Todos</option>
                    <option value="active">Solo Activos</option>
                    <option value="inactive">Solo Inactivos</option>
                </select>

                {#if hasActiveFilters}
                    <Button variant="ghost" size="sm" class="h-10 px-2.5 text-xs text-muted-foreground hover:text-foreground hover:bg-muted/50 gap-1.5 rounded-xl transition-all" onclick={resetFilters}>
                        <RotateCcw class="size-3.5" />
                        <span>Limpiar</span>
                    </Button>
                {/if}
            </div>
        </div>
    </Card>

    <!-- Lista en formato Wide Cards (Desglose Desktop) -->
    <div class="hidden md:block">
        <SupplierListDesktop
            {suppliers}
            onSelectSupplier={handleSelectSupplier}
            onOpenCreate={() => (showCreateModal = true)}
        />
    </div>

    <!-- Lista en formato Wide Cards (Desglose Mobile) -->
    <div class="block md:hidden">
        <SupplierListMobile
            {suppliers}
            onSelectSupplier={handleSelectSupplier}
            onOpenCreate={() => (showCreateModal = true)}
        />
    </div>
</div>

<!-- Modales de Operación -->
<CreateSupplierModal bind:open={showCreateModal} />

<ManageSupplierModal bind:open={showManageModal} supplier={selectedSupplier} />

<!-- Modal de Filtros Mobile -->
<SupplierFilterModalMobile
    bind:open={isFilterModalOpen}
    {selectedStatus}
    onapply={(f) => {
        selectedStatus = f.status;
        applyFilters();
    }}
    onreset={resetFilters}
/>
