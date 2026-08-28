<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import SortSelect from '@/components/common/SortSelect.svelte';
    import { Button } from '@/components/ui/button';
    import { router } from '@inertiajs/svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Banknote from 'lucide-svelte/icons/banknote';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';
    import Search from 'lucide-svelte/icons/search';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import Users from 'lucide-svelte/icons/users';
    import X from 'lucide-svelte/icons/x';
    import AccountLedgerDesktop, { type CustomerAccountFull } from './desktop/AccountLedgerDesktop.svelte';
    import EditCreditSettingsModal from './EditCreditSettingsModal.svelte';
    import AccountLedgerMobile from './mobile/AccountLedgerMobile.svelte';
    import RecordAdjustmentModal from './RecordAdjustmentModal.svelte';
    import RecordPaymentModal from './RecordPaymentModal.svelte';

    interface AccountStats {
        total_debt: number;
        total_customers: number;
        defaulters_count: number;
        total_credit_limit: number;
    }

    let {
        customers = [],
        selectedCustomer = null,
        stats = {
            total_debt: 0,
            total_customers: 0,
            defaulters_count: 0,
            total_credit_limit: 0,
        },
        filters = {
            search: '',
            defaulters_only: false,
            sort_by: 'balance_high',
            customer_id: null,
        },
    }: {
        customers: CustomerAccountFull[];
        selectedCustomer: CustomerAccountFull | null;
        stats: AccountStats;
        filters: {
            search?: string;
            defaulters_only?: boolean;
            sort_by?: string;
            customer_id?: number | null;
        };
    } = $props();

    let searchInput = $state(filters.search || '');
    let defaultersOnly = $state(!!filters.defaulters_only);
    let sortBy = $state(filters.sort_by || 'balance_high');

    // Modals state
    let isPaymentModalOpen = $state(false);
    let isAdjustmentModalOpen = $state(false);
    let isCreditSettingsModalOpen = $state(false);
    let activeCustomerForModal = $state<CustomerAccountFull | null>(null);

    const sortOptions = [
        { value: 'balance_high', label: 'Mayor saldo deudor' },
        { value: 'balance_low', label: 'Menor saldo deudor' },
        { value: 'credit_high', label: 'Mayor límite de crédito' },
        { value: 'credit_low', label: 'Menor límite de crédito' },
        { value: 'name_asc', label: 'Nombre (A - Z)' },
        { value: 'name_desc', label: 'Nombre (Z - A)' },
        { value: 'latest', label: 'Más recientes' },
        { value: 'oldest', label: 'Más antiguos' },
    ];

    function applyFilters() {
        router.get(
            '/tenant/customers/accounts',
            {
                search: searchInput.trim() || undefined,
                defaulters_only: defaultersOnly ? true : undefined,
                sort_by: sortBy,
                customer_id: filters.customer_id || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }

    function clearSearch() {
        searchInput = '';
        applyFilters();
    }

    function toggleDefaulters() {
        defaultersOnly = !defaultersOnly;
        applyFilters();
    }

    function handleSortChange(newSort: string) {
        sortBy = newSort;
        applyFilters();
    }

    function openPaymentModal(cust: CustomerAccountFull) {
        activeCustomerForModal = cust;
        isPaymentModalOpen = true;
    }

    function openAdjustmentModal(cust: CustomerAccountFull) {
        activeCustomerForModal = cust;
        isAdjustmentModalOpen = true;
    }

    function openCreditSettingsModal(cust: CustomerAccountFull) {
        activeCustomerForModal = cust;
        isCreditSettingsModalOpen = true;
    }

    function formatPrice(amount: number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(amount);
    }
</script>

<AppHead title="Cuentas Corrientes - Clientes" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight leading-tight text-foreground">
                Cuentas Corrientes
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5 max-w-prose">
                Libro mayor de clientes, seguimiento de deuda, cobros y límites de crédito comercial.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="/tenant/customers">
                <Button variant="outline" size="sm" class="gap-1.5 text-xs rounded-full font-medium">
                    <ArrowLeft class="size-3.5" />
                    Directorio de clientes
                </Button>
            </a>
        </div>
    </div>

    <!-- Banner Estadístico Bento Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Deuda Total en Calle -->
        <div class="bg-card text-card-foreground p-4 sm:p-5 rounded-2xl border border-border/80 shadow-2xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Saldo en Calle</span>
                <div class="p-1.5 rounded-lg bg-destructive/10 text-destructive">
                    <DollarSign class="size-4" />
                </div>
            </div>
            <p class="text-xl sm:text-2xl font-bold tabular-nums text-foreground">
                {formatPrice(stats.total_debt)}
            </p>
            <p class="text-[11px] text-muted-foreground">Deuda acumulada activa</p>
        </div>

        <!-- Clientes con Deuda -->
        <div class="bg-card text-card-foreground p-4 sm:p-5 rounded-2xl border border-border/80 shadow-2xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Cartera Activa</span>
                <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                    <Users class="size-4" />
                </div>
            </div>
            <p class="text-xl sm:text-2xl font-bold tabular-nums text-foreground">
                {stats.total_customers}
            </p>
            <p class="text-[11px] text-muted-foreground">Clientes registrados</p>
        </div>

        <!-- Clientes Morosos -->
        <div class="bg-card text-card-foreground p-4 sm:p-5 rounded-2xl border border-border/80 shadow-2xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Morosos</span>
                <div class="p-1.5 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <AlertTriangle class="size-4" />
                </div>
            </div>
            <p class="text-xl sm:text-2xl font-bold tabular-nums {stats.defaulters_count > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'}">
                {stats.defaulters_count}
            </p>
            <p class="text-[11px] text-muted-foreground">Con condición de mora</p>
        </div>

        <!-- Límite de Crédito Global -->
        <div class="bg-card text-card-foreground p-4 sm:p-5 rounded-2xl border border-border/80 shadow-2xs space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Crédito Otorgado</span>
                <div class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <CreditCard class="size-4" />
                </div>
            </div>
            <p class="text-xl sm:text-2xl font-bold tabular-nums text-foreground">
                {formatPrice(stats.total_credit_limit)}
            </p>
            <p class="text-[11px] text-muted-foreground">Límite total asignado</p>
        </div>
    </div>

    <!-- Barra de Búsqueda y Filtros -->
    <div class="bg-card text-card-foreground p-4 rounded-2xl border border-border/80 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative flex-1 w-full">
            <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
            <input
                type="text"
                bind:value={searchInput}
                onkeydown={(e) => e.key === 'Enter' && applyFilters()}
                placeholder="Buscar por cliente, CUIT o teléfono..."
                class="h-9 w-full rounded-xl border border-input bg-background pl-9 pr-8 text-xs font-normal text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary outline-none"
            />
            {#if searchInput}
                <button
                    type="button"
                    onclick={clearSearch}
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                    <X class="size-3.5" />
                </button>
            {/if}
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto shrink-0 justify-end flex-wrap">
            <!-- Toggle Morosos -->
            <button
                type="button"
                onclick={toggleDefaulters}
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium border transition-all {defaultersOnly ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30' : 'bg-background text-muted-foreground hover:text-foreground border-input'}"
            >
                <AlertTriangle class="size-3.5 {defaultersOnly ? 'text-amber-600' : 'text-muted-foreground'}" />
                <span>Solo morosos</span>
            </button>

            <!-- Selector de Ordenamiento -->
            <SortSelect
                value={sortBy}
                options={sortOptions}
                onchange={handleSortChange}
            />
        </div>
    </div>

    <!-- Vista Responsiva: Desktop Split / Mobile Feed -->
    <div class="hidden lg:block">
        <AccountLedgerDesktop
            {customers}
            {selectedCustomer}
            onOpenPayment={openPaymentModal}
            onOpenAdjustment={openAdjustmentModal}
            onOpenCreditSettings={openCreditSettingsModal}
        />
    </div>

    <div class="block lg:hidden">
        <AccountLedgerMobile
            {customers}
            onOpenPayment={openPaymentModal}
            onOpenAdjustment={openAdjustmentModal}
            onOpenCreditSettings={openCreditSettingsModal}
        />
    </div>
</div>

<!-- Modales de Operación de Cuenta Corriente -->
<RecordPaymentModal
    bind:open={isPaymentModalOpen}
    customer={activeCustomerForModal}
/>

<RecordAdjustmentModal
    bind:open={isAdjustmentModalOpen}
    customer={activeCustomerForModal}
/>

<EditCreditSettingsModal
    bind:open={isCreditSettingsModalOpen}
    customer={activeCustomerForModal}
/>
