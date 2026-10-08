<script lang="ts">
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';

    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Search from 'lucide-svelte/icons/search';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import Banknote from 'lucide-svelte/icons/banknote';
    import { hasPermission } from '@/lib/permissions';

    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import CustomerFilterModalMobile from './mobile/CustomerFilterModalMobile.svelte';
    import CustomerListDesktop, { type CustomerItem } from './desktop/CustomerListDesktop.svelte';
    import CustomerListMobile from './mobile/CustomerListMobile.svelte';
    import CreateCustomerModal from './CreateCustomerModal.svelte';
    import ManageCustomerModal from './ManageCustomerModal.svelte';

    interface FilterProps {
        search?: string | null;
        status?: string | null;
        tax_condition?: string | null;
        is_defaulter?: string | null;
    }

    let {
        customers = [],
        totalCount = 0,
        filters = {},
    }: {
        customers: CustomerItem[];
        totalCount?: number;
        filters?: FilterProps;
    } = $props();

    let searchTerm = $state(filters?.search ?? '');
    let selectedStatus = $state(filters?.status ?? '');
    let selectedTaxCondition = $state(filters?.tax_condition ?? '');
    let selectedDefaulter = $state(filters?.is_defaulter ?? '');
    let showCreateModal = $state(false);
    let showManageModal = $state(false);
    let isFilterModalOpen = $state(false);
    let selectedCustomer = $state<CustomerItem | null>(null);

    $effect(() => {
        const s = filters?.search;
        const st = filters?.status;
        const tc = filters?.tax_condition;
        const def = filters?.is_defaulter;
        untrack(() => {
            if (s !== undefined && s !== null) searchTerm = s;
            if (st !== undefined && st !== null) selectedStatus = st;
            if (tc !== undefined && tc !== null) selectedTaxCondition = tc;
            if (def !== undefined && def !== null) selectedDefaulter = def;
        });
    });




    function applyFilters() {
        router.get(
            '/tenant/clientes',
            {
                search: searchTerm || undefined,
                status: selectedStatus || undefined,
                tax_condition: selectedTaxCondition || undefined,
                is_defaulter: selectedDefaulter || undefined,
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
        selectedTaxCondition = '';
        selectedDefaulter = '';
        router.get('/tenant/clientes', {}, { preserveState: true, preserveScroll: true });
    }

    function handleSelectCustomer(customer: CustomerItem) {
        selectedCustomer = customer;
        showManageModal = true;
    }

    let hasActiveFilters = $derived(
        Boolean(searchTerm || selectedStatus || selectedTaxCondition || selectedDefaulter)
    );
    let activeFiltersCount = $derived(
        (selectedStatus ? 1 : 0) + (selectedTaxCondition ? 1 : 0) + (selectedDefaulter ? 1 : 0)
    );
</script>

<AppHead title="Directorio de Clientes - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Header de la Página -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <UserCheck class="size-7 text-primary" />
                Directorio de Clientes
            </h1>
        </div>

        <div class="flex items-center justify-end gap-2.5 w-full sm:w-auto">
            <a href="/tenant/clientes/cuentas-corrientes" class="inline-flex">
                <Button variant="secondary" class="min-h-[38px] px-3.5 gap-2 rounded-xl text-sm font-medium shadow-2xs hover:bg-secondary/90 border border-border/30 transition-all">
                    <Banknote class="size-3.5" />
                    <span>Cuentas Corrientes</span>
                </Button>
            </a>
            {#if hasPermission('clientes.crear')}
                <Button class="min-h-[38px] px-4 gap-2 rounded-xl text-sm font-medium shadow-xs" onclick={() => (showCreateModal = true)}>
                    <Plus class="h-4 w-4" />
                    <span>Añadir Cliente</span>
                </Button>
            {/if}
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
                        items={customers}
                        searchKeys={['name', 'tax_id', 'phone', 'address']}
                        placeholder="Buscar por nombre, CUIT/DNI, teléfono..."
                        onsearch={(q) => {
                            searchTerm = q;
                            applyFilters();
                        }}
                        onselect={handleSelectCustomer}
                        onclear={resetFilters}
                    >

                        {#snippet itemSnippet(cust)}
                            <div class="flex items-center justify-between gap-3 w-full min-w-0">
                                <div class="flex flex-col min-w-0">
                                    <span class="truncate font-medium text-foreground">{cust.name}</span>
                                    {#if cust.tax_id}
                                        <span class="text-xs tabular-nums text-muted-foreground">CUIT: {cust.tax_id}</span>
                                    {/if}
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    {#if cust.is_defaulter}
                                        <span class="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive border border-destructive/20">
                                            Moroso
                                        </span>
                                    {:else if cust.is_active}
                                        <span class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                            Activo
                                        </span>
                                    {:else}
                                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground border border-border">
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
                            <span class="absolute -top-1 -right-1 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-primary px-1 text-xs font-bold text-primary-foreground tabular-nums shadow-xs">
                                {activeFiltersCount}
                            </span>
                        {/if}
                    </Button>
                </div>
            </div>

            <!-- Selectores de Filtro Desktop -->
            <div class="hidden md:flex items-center gap-2.5 w-auto flex-wrap sm:flex-nowrap">
                <!-- Condición Fiscal -->
                <select
                    bind:value={selectedTaxCondition}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-sm font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[140px]"
                >
                    <option value="">Condición IVA: Todas</option>
                    <option value="consumidor_final">Consumidor Final</option>
                    <option value="responsable_inscripto">Resp. Inscripto</option>
                    <option value="monotributo">Monotributo</option>
                    <option value="exento">Exento</option>
                </select>

                <!-- Estado -->
                <select
                    bind:value={selectedStatus}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-sm font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[130px]"
                >
                    <option value="">Estado: Todos</option>
                    <option value="active">Solo Activos</option>
                    <option value="inactive">Solo Inactivos</option>
                </select>

                <!-- Morosidad -->
                <select
                    bind:value={selectedDefaulter}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-sm font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[130px]"
                >
                    <option value="">Morosos: Todos</option>
                    <option value="yes">Solo Morosos</option>
                    <option value="no">Sin Deuda / Al Día</option>
                </select>

                {#if hasActiveFilters}
                    <Button variant="ghost" size="sm" class="min-h-[38px] h-10 px-2.5 text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-muted/50 gap-1.5 rounded-xl transition-all" onclick={resetFilters}>
                        <RotateCcw class="size-3.5" />
                        <span>Limpiar</span>
                    </Button>
                {/if}
            </div>
        </div>
    </Card>

    <!-- Lista en formato Wide Cards (Desglose Desktop) -->
    <div class="hidden md:block">
        <CustomerListDesktop
            {customers}
            onSelectCustomer={handleSelectCustomer}
            onOpenCreate={() => (showCreateModal = true)}
        />
    </div>

    <!-- Lista en formato Wide Cards (Desglose Mobile) -->
    <div class="block md:hidden">
        <CustomerListMobile
            {customers}
            onSelectCustomer={handleSelectCustomer}
            onOpenCreate={() => (showCreateModal = true)}
        />
    </div>
</div>

<!-- Modales de Operación -->
<CreateCustomerModal bind:open={showCreateModal} />

<ManageCustomerModal bind:open={showManageModal} customer={selectedCustomer} />

<!-- Modal de Filtros Mobile -->
<CustomerFilterModalMobile
    bind:open={isFilterModalOpen}
    {selectedTaxCondition}
    {selectedStatus}
    {selectedDefaulter}
    onapply={(f) => {
        selectedTaxCondition = f.tax_condition;
        selectedStatus = f.status;
        selectedDefaulter = f.is_defaulter;
        applyFilters();
    }}
    onreset={resetFilters}
/>
