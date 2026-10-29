<script lang="ts">
    import AppDrawer from '@/components/common/AppDrawer.svelte';
    import { Button } from '@/components/ui/button';
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import ArrowUpDown from 'lucide-svelte/icons/arrow-up-down';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Banknote from 'lucide-svelte/icons/banknote';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Clock from 'lucide-svelte/icons/clock';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import History from 'lucide-svelte/icons/history';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import Search from 'lucide-svelte/icons/search';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import Sliders from 'lucide-svelte/icons/sliders';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import { hasPermission } from '@/lib/permissions';
    import AppPagination, { type PaginationMeta } from '@/components/common/AppPagination.svelte';
    import type { CustomerAccountFull, MovementData } from '../desktop/AccountLedgerDesktop.svelte';
    import CustomerAccountFilterModalMobile from './CustomerAccountFilterModalMobile.svelte';

    let {
        customers = [],
        pagination = null,
        filters = {},
        onOpenPayment,
        onOpenAdjustment,
        onOpenCreditSettings,
    }: {
        customers: CustomerAccountFull[];
        pagination?: PaginationMeta | null;
        filters?: Record<string, any>;
        onOpenPayment: (cust: CustomerAccountFull) => void;
        onOpenAdjustment: (cust: CustomerAccountFull) => void;
        onOpenCreditSettings: (cust: CustomerAccountFull) => void;
    } = $props();

    // Filtros del listado de clientes
    let searchQuery = $state(filters?.search ?? '');
    let defaulterFilter = $state(filters?.defaulters_only ? 'yes' : '');
    let taxConditionFilter = $state(filters?.tax_condition ?? '');
    let statusFilter = $state(filters?.status ?? '');
    let sortByFilter = $state(filters?.sort_by ?? 'balance_high');
    let isFilterModalOpen = $state(false);

    $effect(() => {
        const s = filters?.search;
        const d = filters?.defaulters_only;
        const tc = filters?.tax_condition;
        const st = filters?.status;
        const sb = filters?.sort_by;

        untrack(() => {
            if (s !== undefined && s !== null) searchQuery = s;
            if (d !== undefined && d !== null) defaulterFilter = d ? 'yes' : '';
            if (tc !== undefined && tc !== null) taxConditionFilter = tc;
            if (st !== undefined && st !== null) statusFilter = st;
            if (sb !== undefined && sb !== null) sortByFilter = sb;
        });
    });

    let activeFiltersCount = $derived(
        (defaulterFilter ? 1 : 0) +
        (taxConditionFilter ? 1 : 0) +
        (statusFilter ? 1 : 0) +
        (sortByFilter && sortByFilter !== 'balance_high' ? 1 : 0)
    );

    function applyMobileFilters() {
        router.get(
            '/tenant/clientes/cuentas-corrientes',
            {
                search: searchQuery || undefined,
                defaulters_only: defaulterFilter === 'yes' ? 1 : undefined,
                tax_condition: taxConditionFilter || undefined,
                status: statusFilter || undefined,
                sort_by: sortByFilter || undefined,
            },
            { preserveState: true, preserveScroll: true }
        );
    }

    function handleApplyFilterModal(applied: {
        tax_condition: string;
        status: string;
        defaulters_only: string;
        sort_by: string;
    }) {
        taxConditionFilter = applied.tax_condition;
        statusFilter = applied.status;
        defaulterFilter = applied.defaulters_only;
        sortByFilter = applied.sort_by;
        applyMobileFilters();
    }

    function handleResetFilterModal() {
        searchQuery = '';
        taxConditionFilter = '';
        statusFilter = '';
        defaulterFilter = '';
        sortByFilter = 'balance_high';
        router.get('/tenant/clientes/cuentas-corrientes', {}, { preserveState: true, preserveScroll: true });
    }

    // Estado del drawer de cliente seleccionado
    let selectedCustomerForDrawer = $state<CustomerAccountFull | null>(null);
    let isDrawerOpen = $state(false);
    let isLoadingMovements = $state(false);

    // Filtros internos del drawer (movimientos)
    let movementSort = $state<'latest' | 'oldest'>('latest');
    let movementTypeFilter = $state<string>('all');
    let movementSearchTerm = $state<string>('');

    async function openCustomerDrawer(cust: CustomerAccountFull) {
        selectedCustomerForDrawer = cust;
        movementSort = 'latest';
        movementTypeFilter = 'all';
        movementSearchTerm = '';
        isDrawerOpen = true;
        isLoadingMovements = true;
        try {
            const res = await fetch(`/tenant/clientes/${cust.id}/cuenta-corriente`, {
                headers: { Accept: 'application/json' },
            });

            if (res.ok) {
                const fullData = await res.json();
                if (selectedCustomerForDrawer && selectedCustomerForDrawer.id === cust.id) {
                    selectedCustomerForDrawer = fullData;
                }
            }
        } catch (err) {
            console.error('Error loading customer account details:', err);
        } finally {
            isLoadingMovements = false;
        }
    }

    let processedDrawerMovements = $derived.by(() => {
        if (!selectedCustomerForDrawer?.movements) return [];
        let list = [...selectedCustomerForDrawer.movements];

        if (movementTypeFilter !== 'all') {
            list = list.filter((m) => m.type === movementTypeFilter);
        }

        if (movementSearchTerm.trim()) {
            const q = movementSearchTerm.toLowerCase().trim();
            list = list.filter(
                (m) =>
                    m.description.toLowerCase().includes(q) ||
                    (m.order?.order_number && m.order.order_number.toLowerCase().includes(q)) ||
                    (m.reference_number && m.reference_number.toLowerCase().includes(q)) ||
                    (m.notes && m.notes.toLowerCase().includes(q)) ||
                    (m.payment_method && m.payment_method.toLowerCase().includes(q))
            );
        }

        list.sort((a, b) => {
            const dateA = new Date(a.created_at).getTime();
            const dateB = new Date(b.created_at).getTime();
            return movementSort === 'latest' ? dateB - dateA : dateA - dateB;
        });

        return list;
    });

    function formatPrice(val: number | string | null | undefined): string {
        const num = Number(val) || 0;
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(num);
    }

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return new Intl.DateTimeFormat('es-AR', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).format(d);
    }
</script>

<div class="space-y-4">
    <!-- Barra Superior Mobile: Buscador + Botón Filtros Avanzados -->
    <div class="flex items-center gap-2">
        <div class="relative flex-1 min-w-0">
            <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
            <input
                type="text"
                bind:value={searchQuery}
                onkeydown={(e) => e.key === 'Enter' && applyMobileFilters()}
                placeholder="Buscar cliente, CUIT, teléfono..."
                class="h-11 w-full rounded-xl border border-input bg-card pl-10 pr-9 text-sm font-normal text-foreground placeholder:text-muted-foreground shadow-2xs focus:ring-1 focus:ring-primary outline-none transition-all"
            />
            {#if searchQuery}
                <button
                    type="button"
                    onclick={() => {
                        searchQuery = '';
                        applyMobileFilters();
                    }}
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    aria-label="Limpiar búsqueda"
                >
                    <X class="size-4" />
                </button>
            {/if}
        </div>

        <Button
            type="button"
            variant={activeFiltersCount > 0 ? 'secondary' : 'outline'}
            size="icon"
            onclick={() => (isFilterModalOpen = true)}
            class="relative h-11 w-11 shrink-0 rounded-xl border border-border/70 shadow-2xs"
            aria-label="Abrir filtros avanzados"
        >
            <SlidersHorizontal class="size-4 text-foreground" />
            {#if activeFiltersCount > 0}
                <span class="absolute -top-1 -right-1 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-primary px-1 text-xs font-bold text-primary-foreground tabular-nums shadow-xs">
                    {activeFiltersCount}
                </span>
            {/if}
        </Button>
    </div>

    <!-- Indicador de Filtros Activos con Botón Limpiar -->
    {#if activeFiltersCount > 0 || searchQuery}
        <div class="flex items-center justify-between text-xs text-muted-foreground px-1">
            <span class="tabular-nums">
                {customers.length} {customers.length === 1 ? 'cliente encontrado' : 'clientes encontrados'}
            </span>
            <button
                type="button"
                onclick={handleResetFilterModal}
                class="font-medium text-primary hover:underline"
            >
                Limpiar filtros
            </button>
        </div>
    {/if}

    <!-- Feed de Tarjetas de Clientes -->
    <div class="space-y-3">
        {#each customers as cust (cust.id)}
            {@const balanceNum = Number(cust.current_balance) || 0}
            {@const limitNum = Number(cust.credit_limit) || 0}
            {@const usagePercent = limitNum > 0 ? Math.min(100, Math.round((balanceNum / limitNum) * 100)) : 0}

            <button
                type="button"
                class="w-full text-left p-4 rounded-2xl border border-border/80 bg-card shadow-2xs space-y-3 group transition-all duration-200 hover:scale-[1.01] hover:shadow-md hover:border-primary/40"
                onclick={() => openCustomerDrawer(cust)}
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <IconContainer size="md" variant={cust.is_defaulter ? 'warning' : 'primary'}>
                            {cust.name.slice(0, 2).toUpperCase()}
                        </IconContainer>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <h3 class="font-bold text-sm text-foreground truncate group-hover:text-primary transition-colors">
                                    {cust.name}
                                </h3>
                                {#if cust.is_defaulter}
                                    <span class="text-xs font-semibold text-rose-700 bg-rose-500/15 dark:text-rose-400 px-1.5 py-0.5 rounded">
                                        Moroso
                                    </span>
                                {/if}
                            </div>
                            <p class="text-xs text-muted-foreground tabular-nums mt-0.5">
                                {cust.tax_id ? `CUIT ${cust.tax_id}` : 'Sin CUIT'}
                            </p>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <span class="text-sm font-bold tabular-nums block {balanceNum > 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'}">
                            {formatPrice(balanceNum)}
                        </span>
                        <span class="text-xs text-muted-foreground">Saldo Deudor</span>
                    </div>
                </div>

                <!-- Barra de Límite y Crédito -->
                <div class="space-y-1 pt-1 border-t border-border/50">
                    <div class="flex items-center justify-between text-xs text-muted-foreground tabular-nums">
                        <span>Límite: {formatPrice(limitNum)}</span>
                        <span class="font-medium text-foreground">Disp: {formatPrice(cust.available_credit ?? Math.max(0, limitNum - balanceNum))}</span>
                    </div>
                    <div class="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all {usagePercent > 90 ? 'bg-destructive' : usagePercent > 70 ? 'bg-amber-500' : 'bg-primary'}"
                            style="width: {usagePercent}%;"
                        ></div>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-primary pt-0.5 font-medium">
                    <span>Gestionar movimientos y cobros</span>
                    <ChevronRight class="size-3.5" />
                </div>
            </button>
        {/each}

        {#if pagination}
            <div class="pt-2">
                <AppPagination {pagination} />
            </div>
        {/if}

        {#if customers.length === 0}
            <div class="py-12 text-center rounded-2xl bg-card border border-border p-6 space-y-2">
                <CreditCard class="size-8 text-muted-foreground mx-auto opacity-50" />
                <h4 class="text-xs font-semibold text-foreground">No se encontraron clientes</h4>
                <p class="text-xs text-muted-foreground">
                    Intenta modificar los términos de búsqueda o filtros.
                </p>
            </div>
        {/if}
    </div>
</div>

<!-- Modal de Filtros Avanzados Mobile -->
<CustomerAccountFilterModalMobile
    bind:open={isFilterModalOpen}
    selectedTaxCondition={taxConditionFilter}
    selectedStatus={statusFilter}
    selectedDefaulter={defaulterFilter}
    selectedSort={sortByFilter}
    onapply={handleApplyFilterModal}
    onreset={handleResetFilterModal}
/>

<!-- Drawer de Detalle y Operaciones Mobile -->
<AppDrawer
    bind:open={isDrawerOpen}
    title={selectedCustomerForDrawer ? selectedCustomerForDrawer.name : 'Cuenta Corriente'}
    description={selectedCustomerForDrawer?.tax_id ? `CUIT ${selectedCustomerForDrawer.tax_id}` : 'Detalle de saldos y movimientos'}
>
    {#if selectedCustomerForDrawer}
        {@const balanceNum = Number(selectedCustomerForDrawer.current_balance) || 0}
        {@const limitNum = Number(selectedCustomerForDrawer.credit_limit) || 0}
        {@const usagePercent = limitNum > 0 ? Math.min(100, Math.round((balanceNum / limitNum) * 100)) : 0}

        <div class="space-y-4">
            <!-- Saldo y Límites -->
            <div class="p-4 rounded-2xl bg-muted/40 border border-border/70 space-y-3">
                <div class="flex items-baseline justify-between">
                    <div>
                        <span class="text-xs text-muted-foreground block">Saldo deudor actual:</span>
                        <span class="text-2xl font-bold tabular-nums {balanceNum > 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'}">
                            {formatPrice(balanceNum)}
                        </span>
                    </div>
                    {#if selectedCustomerForDrawer.is_defaulter}
                        <span class="text-xs font-bold text-rose-700 bg-rose-500/15 dark:text-rose-400 px-2 py-0.5 rounded-full flex items-center gap-1">
                            <AlertTriangle class="size-3.5" />
                            Moroso
                        </span>
                    {/if}
                </div>

                <div class="space-y-1 pt-2 border-t border-border/60">
                    <div class="flex items-center justify-between text-xs text-muted-foreground tabular-nums">
                        <span>Límite: {formatPrice(limitNum)}</span>
                        <span>Disponible: <strong class="text-foreground">{formatPrice(selectedCustomerForDrawer.available_credit ?? Math.max(0, limitNum - balanceNum))}</strong></span>
                    </div>
                    <div class="h-2 w-full bg-muted rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full {usagePercent > 90 ? 'bg-destructive' : usagePercent > 70 ? 'bg-amber-500' : 'bg-primary'}"
                            style="width: {usagePercent}%;"
                        ></div>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 text-xs text-muted-foreground pt-1 border-t border-border/60">
                    <Clock class="size-3.5 text-primary" />
                    <span>Plazo de pago: <strong class="text-foreground tabular-nums">{selectedCustomerForDrawer.credit_due_days || 30} días</strong></span>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="space-y-2">
                {#if hasPermission('cobranzas.crear')}
                    <Button
                        onclick={() => {
                            const cust = selectedCustomerForDrawer;
                            isDrawerOpen = false;
                            if (cust) onOpenPayment(cust);
                        }}
                        class="w-full min-h-[44px] h-11 gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm"
                    >
                        <Banknote class="size-4" />
                        <span>Registrar Cobro / Abono</span>
                    </Button>
                {/if}

                {#if hasPermission('clientes.editar')}
                    <div class="grid grid-cols-2 gap-2.5">
                        <Button
                            variant="secondary"
                            onclick={() => {
                                const cust = selectedCustomerForDrawer;
                                isDrawerOpen = false;
                                if (cust) onOpenAdjustment(cust);
                            }}
                            class="min-h-[44px] h-11 text-sm font-semibold rounded-xl gap-1.5"
                        >
                            <Sliders class="size-3.5" />
                            <span>Ajustar Saldo</span>
                        </Button>

                        <Button
                            variant="outline"
                            onclick={() => {
                                const cust = selectedCustomerForDrawer;
                                isDrawerOpen = false;
                                if (cust) onOpenCreditSettings(cust);
                            }}
                            class="min-h-[44px] h-11 text-sm font-semibold rounded-xl gap-1.5"
                        >
                            <ShieldCheck class="size-3.5" />
                            <span>Límites</span>
                        </Button>
                    </div>
                {/if}
            </div>

            <!-- Movimientos y Filtros de Libro Mayor en Mobile -->
            <div class="space-y-3 pt-2">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                        <History class="size-3.5 text-primary" />
                        Libro Mayor de Movimientos
                    </h4>
                    {#if isLoadingMovements}
                        <Loader2 class="size-3.5 animate-spin text-muted-foreground" />
                    {/if}
                </div>

                <!-- Buscador de Movimientos Mobile -->
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <input
                        type="text"
                        bind:value={movementSearchTerm}
                        placeholder="Buscar en descripción, orden, método..."
                        class="h-9 w-full rounded-lg border border-input bg-card pl-8.5 pr-8 text-xs font-normal text-foreground placeholder:text-muted-foreground shadow-2xs focus:ring-1 focus:ring-primary outline-none"
                    />
                    {#if movementSearchTerm}
                        <button
                            type="button"
                            onclick={() => (movementSearchTerm = '')}
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            aria-label="Limpiar búsqueda de movimientos"
                        >
                            <X class="size-3" />
                        </button>
                    {/if}
                </div>

                <!-- Pills de Filtro de Movimientos + Selector Orden -->
                <div class="flex items-center justify-between gap-1.5 flex-wrap">
                    <div class="flex items-center gap-1 overflow-x-auto pb-0.5 custom-scrollbar">
                        <button
                            type="button"
                            onclick={() => (movementTypeFilter = 'all')}
                            class="px-2.5 py-1 rounded-lg text-xs font-medium transition-all {movementTypeFilter === 'all'
                                ? 'bg-primary text-primary-foreground font-semibold shadow-2xs'
                                : 'bg-muted/60 text-muted-foreground hover:bg-muted'}"
                        >
                            Todos
                        </button>
                        <button
                            type="button"
                            onclick={() => (movementTypeFilter = 'charge')}
                            class="px-2.5 py-1 rounded-lg text-xs font-medium transition-all {movementTypeFilter === 'charge'
                                ? 'bg-rose-600 text-white font-semibold shadow-2xs'
                                : 'bg-muted/60 text-muted-foreground hover:bg-muted'}"
                        >
                            Cargos
                        </button>
                        <button
                            type="button"
                            onclick={() => (movementTypeFilter = 'payment')}
                            class="px-2.5 py-1 rounded-lg text-xs font-medium transition-all {movementTypeFilter === 'payment'
                                ? 'bg-emerald-600 text-white font-semibold shadow-2xs'
                                : 'bg-muted/60 text-muted-foreground hover:bg-muted'}"
                        >
                            Cobros
                        </button>
                        <button
                            type="button"
                            onclick={() => (movementTypeFilter = 'adjustment')}
                            class="px-2.5 py-1 rounded-lg text-xs font-medium transition-all {movementTypeFilter === 'adjustment'
                                ? 'bg-amber-600 text-white font-semibold shadow-2xs'
                                : 'bg-muted/60 text-muted-foreground hover:bg-muted'}"
                        >
                            Ajustes
                        </button>
                    </div>

                    <button
                        type="button"
                        onclick={() => (movementSort = movementSort === 'latest' ? 'oldest' : 'latest')}
                        class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium bg-muted/60 text-foreground hover:bg-muted border border-border/50 shrink-0"
                    >
                        <ArrowUpDown class="size-3 text-muted-foreground" />
                        <span>{movementSort === 'latest' ? 'Recientes' : 'Antiguos'}</span>
                    </button>
                </div>

                <!-- Lista de Movimientos Filtrados -->
                {#if isLoadingMovements && (!selectedCustomerForDrawer.movements || selectedCustomerForDrawer.movements.length === 0)}
                    <div class="py-6 text-center text-xs text-muted-foreground flex items-center justify-center gap-2">
                        <Loader2 class="size-4 animate-spin text-primary" />
                        <span>Cargando movimientos...</span>
                    </div>
                {:else if processedDrawerMovements.length > 0}
                    <div class="space-y-2">
                        {#each processedDrawerMovements as mov (mov.id)}
                            {@const isPayment = mov.type === 'payment'}
                            {@const isCharge = mov.type === 'charge'}

                            <div class="p-3 rounded-xl border bg-card text-xs space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-foreground truncate pr-2">
                                        {mov.description}
                                    </span>
                                    <span class="font-bold tabular-nums {isPayment ? 'text-emerald-600 dark:text-emerald-400' : isCharge ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'}">
                                        {isPayment ? '-' : '+'}{formatPrice(mov.amount)}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-xs tabular-nums text-muted-foreground">
                                    <span>{formatDate(mov.created_at)}</span>
                                    <span>Saldo: <strong class="text-foreground">{formatPrice(mov.balance_after)}</strong></span>
                                </div>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <p class="text-xs text-muted-foreground italic py-4 text-center border border-dashed rounded-xl">
                        {movementSearchTerm || movementTypeFilter !== 'all' ? 'No se encontraron movimientos con los filtros aplicados.' : 'Sin movimientos registrados.'}
                    </p>
                {/if}
            </div>
        </div>
    {/if}
</AppDrawer>
