<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Filter from 'lucide-svelte/icons/filter';
    import X from 'lucide-svelte/icons/x';
    import UserCheck from 'lucide-svelte/icons/user-check';
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

    let searchTerm = $state(filters.search ?? '');
    let selectedStatus = $state(filters.status ?? '');
    let selectedTaxCondition = $state(filters.tax_condition ?? '');
    let selectedDefaulter = $state(filters.is_defaulter ?? '');

    let showCreateModal = $state(false);
    let showManageModal = $state(false);
    let selectedCustomer = $state<CustomerItem | null>(null);

    function applyFilters() {
        router.get(
            '/tenant/customers',
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
        router.get('/tenant/customers');
    }

    function handleSelectCustomer(customer: CustomerItem) {
        selectedCustomer = customer;
        showManageModal = true;
    }

    let hasActiveFilters = $derived(
        Boolean(searchTerm || selectedStatus || selectedTaxCondition || selectedDefaulter)
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
            <p class="text-sm text-muted-foreground mt-0.5">
                Gestión de cuentas de clientes, condiciones fiscales, control de morosidad y ventas.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <Button class="gap-2 shadow-sm font-medium" onclick={() => (showCreateModal = true)}>
                <Plus class="h-4 w-4" />
                <span>Añadir cliente</span>
            </Button>
        </div>
    </div>

    <!-- Bento Card: Buscador y Filtros -->
    <Card class="rounded-xl p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.02)] border border-border/60">
        <div class="flex flex-col md:flex-row items-center justify-between gap-3">
            <!-- Buscador por Nombre, CUIT, Teléfono, Email o Dirección -->
            <div class="relative w-full md:w-96">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <input
                    type="text"
                    bind:value={searchTerm}
                    onkeydown={(e) => e.key === 'Enter' && applyFilters()}
                    placeholder="Buscar por nombre, CUIT/DNI, teléfono..."
                    class="h-10 w-full rounded-lg border border-input bg-muted/20 pl-9 pr-4 text-sm placeholder:text-muted-foreground focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary transition-all"
                />
            </div>

            <!-- Selectores de Filtro -->
            <div class="flex items-center gap-2.5 w-full md:w-auto flex-wrap sm:flex-nowrap">
                <!-- Condición Fiscal -->
                <select
                    bind:value={selectedTaxCondition}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[140px]"
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
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[130px]"
                >
                    <option value="">Estado: Todos</option>
                    <option value="active">Solo Activos</option>
                    <option value="inactive">Solo Inactivos</option>
                </select>

                <!-- Morosidad -->
                <select
                    bind:value={selectedDefaulter}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[130px]"
                >
                    <option value="">Morosos: Todos</option>
                    <option value="yes">Solo Morosos</option>
                    <option value="no">Sin Deuda / Al Día</option>
                </select>

                <Button variant="secondary" size="sm" class="h-10 px-3.5 text-xs gap-1.5" onclick={applyFilters}>
                    <Filter class="h-3.5 w-3.5" />
                    Filtrar
                </Button>

                {#if hasActiveFilters}
                    <Button variant="ghost" size="sm" class="h-10 px-2 text-xs text-muted-foreground hover:text-foreground gap-1" onclick={resetFilters}>
                        <X class="h-3.5 w-3.5" />
                        Limpiar
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
