<script lang="ts">
    import { untrack } from 'svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { router } from '@inertiajs/svelte';
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
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Phone from 'lucide-svelte/icons/phone';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Search from 'lucide-svelte/icons/search';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import Sliders from 'lucide-svelte/icons/sliders';
    import User from 'lucide-svelte/icons/user';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import X from 'lucide-svelte/icons/x';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import { ClearFilterButton } from '@/components/common';
    import { hasPermission } from '@/lib/permissions';

    export interface MovementData {
        id: number;
        type: 'charge' | 'payment' | 'adjustment' | 'credit_note' | 'debit_note';
        description: string;
        amount: string | number;
        balance_after: string | number;
        due_date?: string | null;
        payment_method?: string | null;
        reference_number?: string | null;
        notes?: string | null;
        created_at: string;
        order?: { id: number; order_number: string } | null;
        created_by?: { id: number; name: string } | null;
    }

    export interface CustomerAccountFull {
        id: number;
        name: string;
        tax_id: string | null;
        tax_condition: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        credit_limit: string | number;
        current_balance: string | number;
        credit_due_days?: number;
        is_defaulter: boolean;
        is_active?: boolean;
        available_credit?: number;
        movements?: MovementData[];
    }

    let {
        customers = [],
        selectedCustomer = null,
        filters = {},
        onOpenPayment,
        onOpenAdjustment,
        onOpenCreditSettings,
    }: {
        customers: CustomerAccountFull[];
        selectedCustomer: CustomerAccountFull | null;
        filters?: Record<string, any>;
        onOpenPayment: (cust: CustomerAccountFull) => void;
        onOpenAdjustment: (cust: CustomerAccountFull) => void;
        onOpenCreditSettings: (cust: CustomerAccountFull) => void;
    } = $props();

    // Filtros del Maestro de Cuentas Corrientes
    let searchQuery = $state(filters?.search ?? '');
    let defaulterFilter = $state(filters?.defaulters_only ? 'yes' : '');
    let taxConditionFilter = $state(filters?.tax_condition ?? '');
    let statusFilter = $state(filters?.status ?? '');
    let sortByFilter = $state(filters?.sort_by ?? 'balance_high');

    // Sincronizar filtros desde props
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

    // Filtros internos del Libro Mayor de Movimientos (Modo Seleccionado)
    let movementSort = $state<'latest' | 'oldest'>('latest');
    let movementTypeFilter = $state<string>('all');
    let movementSearchTerm = $state<string>('');

    // Combobox dropdown rápido de selección en detalle
    let customerSearchQuery = $state('');
    let isDropdownOpen = $state(false);
    let containerRef: HTMLDivElement | undefined = $state();

    let filteredQuickCustomers = $derived.by(() => {
        const q = customerSearchQuery.toLowerCase().trim();
        if (!q) return customers.slice(0, 8);
        return customers
            .filter((c) =>
                c.name.toLowerCase().includes(q) ||
                (c.tax_id && c.tax_id.toLowerCase().includes(q)) ||
                (c.phone && c.phone.toLowerCase().includes(q))
            )
            .slice(0, 8);
    });

    let processedMovements = $derived.by(() => {
        if (!selectedCustomer?.movements) return [];
        let list = [...selectedCustomer.movements];

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

    function applyMasterFilters() {
        router.get(
            '/tenant/clientes/cuentas-corrientes',
            {
                search: searchQuery || undefined,
                defaulters_only: defaulterFilter === 'yes' ? 1 : undefined,
                tax_condition: taxConditionFilter || undefined,
                status: statusFilter || undefined,
                sort_by: sortByFilter || undefined,
                customer_id: selectedCustomer?.id || undefined,
            },
            { preserveState: true, preserveScroll: true }
        );
    }

    function resetMasterFilters() {
        searchQuery = '';
        defaulterFilter = '';
        taxConditionFilter = '';
        statusFilter = '';
        sortByFilter = 'balance_high';
        router.get(
            '/tenant/clientes/cuentas-corrientes',
            selectedCustomer ? { customer_id: selectedCustomer.id } : {},
            { preserveState: true, preserveScroll: true }
        );
    }

    function selectCustomer(cust: CustomerAccountFull) {
        customerSearchQuery = '';
        isDropdownOpen = false;
        router.get(
            '/tenant/clientes/cuentas-corrientes',
            {
                search: searchQuery || undefined,
                defaulters_only: defaulterFilter === 'yes' ? 1 : undefined,
                tax_condition: taxConditionFilter || undefined,
                status: statusFilter || undefined,
                sort_by: sortByFilter || undefined,
                customer_id: cust.id,
            },
            { preserveState: true, preserveScroll: true }
        );
    }

    function deselectCustomer() {
        customerSearchQuery = '';
        isDropdownOpen = false;
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

    let hasActiveMasterFilters = $derived(
        Boolean(searchQuery || defaulterFilter || taxConditionFilter || statusFilter || (sortByFilter && sortByFilter !== 'balance_high'))
    );

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
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(d);
    }

    function formatTaxCondition(cond: string) {
        switch (cond) {
            case 'responsable_inscripto':
                return 'Resp. Inscripto';
            case 'monotributo':
                return 'Monotributo';
            case 'exento':
                return 'Exento IVA';
            default:
                return 'Consumidor Final';
        }
    }

    let creditLimitNum = $derived(Number(selectedCustomer?.credit_limit) || 0);
    let currentBalanceNum = $derived(Number(selectedCustomer?.current_balance) || 0);
    let creditUsagePercent = $derived.by(() => {
        if (creditLimitNum <= 0) return 0;
        return Math.min(100, Math.round((currentBalanceNum / creditLimitNum) * 100));
    });

    function handleWindowPointerDown(e: PointerEvent) {
        if (isDropdownOpen && containerRef && !containerRef.contains(e.target as Node)) {
            isDropdownOpen = false;
        }
    }

    function handleWindowKeyDown(e: KeyboardEvent) {
        if (e.key === 'Escape') {
            if (isDropdownOpen) {
                isDropdownOpen = false;
            } else if (selectedCustomer) {
                deselectCustomer();
            }
        }
    }
</script>

<svelte:window onpointerdown={handleWindowPointerDown} onkeydown={handleWindowKeyDown} />

<!-- ========================================================================= -->
<!-- ESTADO DESELECCIONADO (MASTER MODE A ANCHO COMPLETO - 100% SCREEN WIDTH) -->
<!-- ========================================================================= -->
{#if !selectedCustomer}
    <div class="w-full space-y-6 transition-all duration-300 ease-in-out">
        <!-- Bento Card: Controles de Búsqueda y Filtros Avanzados -->
        <Card class="rounded-2xl p-4 shadow-2xs border border-border/70 bg-card">
            <div class="flex flex-col lg:flex-row items-center justify-between gap-3">
                <!-- Buscador de Clientes Combobox (Filtra en tiempo real) -->
                <div class="w-full lg:w-96 min-w-0">
                    <SearchCombobox
                        bind:value={searchQuery}
                        items={customers}
                        searchKeys={['name', 'tax_id', 'phone']}
                        placeholder="Buscar por Nombre, CUIT/DNI, Teléfono..."
                        onsearch={(q) => {
                            searchQuery = q;
                            applyMasterFilters();
                        }}
                        onselect={(cust) => selectCustomer(cust)}
                        onclear={resetMasterFilters}
                    >
                        {#snippet itemSnippet(cust)}
                            {@const bal = Number(cust.current_balance) || 0}
                            <div class="flex items-center justify-between gap-3 w-full min-w-0">
                                <div class="flex flex-col min-w-0">
                                    <span class="truncate font-semibold text-foreground">{cust.name}</span>
                                    <span class="text-xs tabular-nums text-muted-foreground">
                                        {cust.tax_id ? `CUIT: ${cust.tax_id}` : 'Sin CUIT'}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0 text-right">
                                    {#if cust.is_defaulter}
                                        <span class="rounded-full bg-rose-500/15 px-2 py-0.5 text-xs font-bold text-rose-700 dark:text-rose-400 border border-rose-500/30">
                                            Moroso
                                        </span>
                                    {/if}
                                    <div class="text-right">
                                        <span class="text-xs font-bold tabular-nums block {bal > 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'}">
                                            {formatPrice(bal)}
                                        </span>
                                        <span class="text-xs text-muted-foreground">Saldo</span>
                                    </div>
                                </div>
                            </div>
                        {/snippet}
                    </SearchCombobox>
                </div>

                <!-- Selectores de Filtros Avanzados -->
                <div class="flex items-center gap-2.5 w-full lg:w-auto flex-wrap sm:flex-nowrap justify-start lg:justify-end">
                    <!-- Filtro Morosidad -->
                    <select
                        bind:value={defaulterFilter}
                        onchange={applyMasterFilters}
                        class="h-10 rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[140px]"
                    >
                        <option value="">Morosos: Todos</option>
                        <option value="yes">Solo Morosos</option>
                    </select>

                    <!-- Condición Fiscal -->
                    <select
                        bind:value={taxConditionFilter}
                        onchange={applyMasterFilters}
                        class="h-10 rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[145px]"
                    >
                        <option value="">Condición IVA: Todas</option>
                        <option value="consumidor_final">Consumidor Final</option>
                        <option value="responsable_inscripto">Resp. Inscripto</option>
                        <option value="monotributo">Monotributo</option>
                        <option value="exento">Exento</option>
                    </select>

                    <!-- Ordenamiento -->
                    <select
                        bind:value={sortByFilter}
                        onchange={applyMasterFilters}
                        class="h-10 rounded-xl border border-input bg-background px-3 py-2 text-sm font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[160px]"
                    >
                        <option value="balance_high">Mayor saldo deudor</option>
                        <option value="balance_low">Menor saldo deudor</option>
                        <option value="credit_high">Mayor límite de crédito</option>
                        <option value="name_asc">Nombre: A - Z</option>
                        <option value="name_desc">Nombre: Z - A</option>
                        <option value="latest">Más recientes</option>
                        <option value="oldest">Más antiguos</option>
                    </select>

                    <ClearFilterButton show={hasActiveMasterFilters} onclick={resetMasterFilters} />
                </div>
            </div>
        </Card>

        <!-- Listado Maestro de Cuentas Corrientes (Full Width Cards) -->
        {#if customers.length > 0}
            <div class="flex flex-col gap-3.5">
                {#each customers as cust (cust.id)}
                    {@const balanceNum = Number(cust.current_balance) || 0}
                    {@const limitNum = Number(cust.credit_limit) || 0}
                    {@const usagePercent = limitNum > 0 ? Math.min(100, Math.round((balanceNum / limitNum) * 100)) : 0}

                    <div
                        class="bento-card group flex items-center justify-between gap-6 rounded-2xl border border-border/80 bg-card p-5 shadow-2xs hover:scale-[1.01] hover:shadow-lg hover:border-primary/40 transition-all duration-200 cursor-pointer"
                        onclick={() => selectCustomer(cust)}
                        onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && selectCustomer(cust)}
                        role="button"
                        tabindex="0"
                        aria-label={`Inspeccionar cuenta corriente de ${cust.name}`}
                    >
                        <!-- Lado Izquierdo: Avatar + Identificación -->
                        <div class="flex items-center gap-4 min-w-0 flex-1">
                            <IconContainer
                                size="xl"
                                variant={cust.is_defaulter ? 'warning' : 'primary'}
                                class="transition-transform group-hover:scale-105 shrink-0"
                            >
                                {cust.name.slice(0, 2).toUpperCase()}
                            </IconContainer>

                            <div class="flex flex-col gap-1 min-w-0 flex-1">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <h3 class="font-bold text-base text-foreground group-hover:text-primary transition-colors truncate">
                                        {cust.name}
                                    </h3>

                                    {#if cust.is_defaulter}
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 border border-rose-200/60 shadow-2xs">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                            Moroso / Deuda Vencida
                                        </span>
                                    {:else if balanceNum === 0}
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/60">
                                            <CheckCircle2 class="size-3" />
                                            Al Día
                                        </span>
                                    {/if}

                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-muted text-muted-foreground border border-border/50">
                                        {formatTaxCondition(cust.tax_condition)}
                                    </span>
                                </div>

                                <div class="flex items-center gap-3 text-xs text-muted-foreground tabular-nums flex-wrap">
                                    {#if cust.tax_id}
                                        <span>CUIT: <strong class="text-foreground font-medium">{cust.tax_id}</strong></span>
                                        <span>•</span>
                                    {/if}
                                    {#if cust.phone}
                                        <span>Tel: {cust.phone}</span>
                                        <span>•</span>
                                    {/if}
                                    <span>Plazo: {cust.credit_due_days || 30} días</span>
                                </div>
                            </div>
                        </div>

                        <!-- Centro: Métricas de Límite y Crédito -->
                        <div class="hidden md:flex flex-col items-end gap-1.5 min-w-[190px] shrink-0 border-l border-border/60 pl-6">
                            <div class="flex items-center justify-between w-full text-xs text-muted-foreground">
                                <span>Límite de crédito:</span>
                                <span class="font-semibold text-foreground tabular-nums">{formatPrice(limitNum)}</span>
                            </div>
                            <div class="h-2 w-full bg-muted rounded-full overflow-hidden">
                                <div
                                    class="h-full transition-all duration-300 rounded-full {usagePercent > 90 ? 'bg-destructive' : usagePercent > 70 ? 'bg-amber-500' : 'bg-primary'}"
                                    style="width: {usagePercent}%;"
                                ></div>
                            </div>
                            <div class="flex items-center justify-between w-full text-xs text-muted-foreground">
                                <span>Uso: {usagePercent}%</span>
                                <span>Disp: <strong class="text-foreground tabular-nums">{formatPrice(cust.available_credit ?? Math.max(0, limitNum - balanceNum))}</strong></span>
                            </div>
                        </div>

                        <!-- Derecha: Saldo Actual y Botón de Apertura -->
                        <div class="flex items-center gap-4 shrink-0 pl-2">
                            <div class="text-right">
                                <span class="text-xs text-muted-foreground uppercase tracking-wider block font-semibold">
                                    Saldo Deudor
                                </span>
                                <span class="text-lg sm:text-xl font-bold tabular-nums block {balanceNum > 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'}">
                                    {formatPrice(balanceNum)}
                                </span>
                            </div>

                            <Button
                                variant="secondary"
                                class="min-h-[38px] px-3.5 gap-1.5 text-xs font-semibold rounded-xl border border-border/40 shadow-2xs"
                                onclick={(e) => {
                                    e.stopPropagation();
                                    selectCustomer(cust);
                                }}
                            >
                                <span>Inspeccionar</span>
                                <ChevronRight class="size-4" />
                            </Button>
                        </div>
                    </div>
                {/each}
            </div>
        {:else}
            <div class="bg-card text-card-foreground rounded-2xl border border-dashed border-border/80 p-12 text-center shadow-2xs space-y-3">
                <Search class="size-10 text-muted-foreground mx-auto opacity-60" />
                <h3 class="text-base font-semibold text-foreground">No se encontraron clientes</h3>
                <p class="text-xs text-muted-foreground max-w-md mx-auto">
                    No hay clientes con cuentas corrientes que coincidan con los filtros aplicados.
                </p>
                {#if hasActiveMasterFilters}
                    <Button variant="outline" size="sm" onclick={resetMasterFilters} class="min-h-[38px] rounded-xl gap-2 mt-2">
                        <RotateCcw class="size-3.5" />
                        <span>Restablecer filtros</span>
                    </Button>
                {/if}
            </div>
        {/if}
    </div>

<!-- ========================================================================= -->
<!-- ESTADO SELECCIONADO (SPLIT DETAIL MODE: LIBRO MAYOR 62% + CARD CLIENTE 38%) -->
<!-- ========================================================================= -->
{:else}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start transition-all duration-300 ease-in-out">
        <!-- Columna Izquierda: Libro Mayor de Movimientos (62%) -->
        <div class="lg:col-span-7 space-y-4">
            <!-- Selector Rápido de Cambio de Cliente (Combobox Flotante) -->
            <div class="relative z-20" bind:this={containerRef}>
                <div class="relative">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                    <input
                        type="text"
                        bind:value={customerSearchQuery}
                        onfocus={() => (isDropdownOpen = true)}
                        placeholder="Cambiar de cliente (Nombre, CUIT, Teléfono)..."
                        class="h-10 w-full rounded-2xl border border-input bg-card pl-10 pr-9 text-sm font-normal text-foreground placeholder:text-muted-foreground shadow-2xs focus:ring-1 focus:ring-primary outline-none transition-all"
                    />
                    {#if customerSearchQuery}
                        <button
                            type="button"
                            onclick={() => {
                                customerSearchQuery = '';
                                isDropdownOpen = false;
                            }}
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            aria-label="Limpiar campo"
                        >
                            <X class="size-3.5" />
                        </button>
                    {/if}
                </div>

                {#if isDropdownOpen}
                    <div class="absolute left-0 right-0 top-full mt-1.5 bg-popover text-popover-foreground rounded-2xl border border-border shadow-2xl z-50 overflow-hidden">
                        <div class="max-h-[260px] overflow-y-auto divide-y divide-border/40">
                            {#each filteredQuickCustomers as cust (cust.id)}
                                <button
                                    type="button"
                                    class="w-full px-4 py-2.5 text-left text-xs hover:bg-muted/60 transition-colors flex items-center justify-between gap-3 group"
                                    onclick={() => selectCustomer(cust)}
                                >
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-foreground group-hover:text-primary transition-colors truncate">
                                                {cust.name}
                                            </span>
                                            {#if cust.is_defaulter}
                                                <span class="text-xs font-semibold text-amber-700 bg-amber-500/15 dark:text-amber-400 px-1.5 py-0.5 rounded">
                                                    Moroso
                                                </span>
                                            {/if}
                                        </div>
                                        <p class="text-xs text-muted-foreground mt-0.5 tabular-nums">
                                            {cust.tax_id || 'Sin CUIT'} • {cust.phone || 'Sin tel.'}
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="font-semibold tabular-nums text-sm block {Number(cust.current_balance) > 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'}">
                                            {formatPrice(cust.current_balance)}
                                        </span>
                                        <span class="text-xs text-muted-foreground">Saldo</span>
                                    </div>
                                </button>
                            {/each}
                            {#if filteredQuickCustomers.length === 0}
                                <div class="p-4 text-center text-xs text-muted-foreground italic">
                                    No se encontraron clientes con ese criterio.
                                </div>
                            {/if}
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Card Principal del Libro Mayor -->
            <div class="bg-card text-card-foreground rounded-3xl border border-border/80 p-5 sm:p-6 shadow-2xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-border/60">
                    <div>
                        <h2 class="text-base font-semibold text-foreground flex items-center gap-2 leading-snug">
                            <History class="size-4 text-primary" />
                            Libro Mayor de Movimientos
                        </h2>
                    </div>
                    <span class="text-xs font-semibold tabular-nums text-muted-foreground bg-muted px-2.5 py-1 rounded-full self-start sm:self-auto">
                        {processedMovements.length} movimientos
                    </span>
                </div>

                <!-- Filtros del Libro Mayor: Tipo, Búsqueda y Orden Cronológico -->
                <div class="flex flex-col sm:flex-row items-center gap-2.5 pt-1">
                    <!-- Búsqueda en Movimientos -->
                    <div class="relative w-full sm:flex-1 min-w-0">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                        <input
                            type="text"
                            bind:value={movementSearchTerm}
                            placeholder="Buscar en comprobantes, notas..."
                            class="h-9 w-full rounded-xl border border-input bg-background pl-8 pr-7 text-xs font-normal text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary outline-none transition-all"
                        />
                        {#if movementSearchTerm}
                            <button
                                type="button"
                                onclick={() => (movementSearchTerm = '')}
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X class="size-3" />
                            </button>
                        {/if}
                    </div>

                    <!-- Tipo de Movimiento -->
                    <select
                        bind:value={movementTypeFilter}
                        class="h-9 rounded-xl border border-input bg-background px-2.5 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary w-full sm:w-auto min-w-[130px]"
                    >
                        <option value="all">Todos los tipos</option>
                        <option value="payment">Solo Cobros / Abonos</option>
                        <option value="charge">Solo Cargos / Compras</option>
                        <option value="adjustment">Solo Ajustes</option>
                    </select>

                    <!-- Orden Cronológico -->
                    <select
                        bind:value={movementSort}
                        class="h-9 rounded-xl border border-input bg-background px-2.5 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary w-full sm:w-auto min-w-[125px]"
                    >
                        <option value="latest">Más recientes</option>
                        <option value="oldest">Más antiguos</option>
                    </select>
                </div>

                <!-- Feed de Movimientos -->
                {#if processedMovements.length > 0}
                    <div class="space-y-3 pt-1">
                        {#each processedMovements as mov (mov.id)}
                            {@const isCharge = mov.type === 'charge'}
                            {@const isPayment = mov.type === 'payment'}
                            {@const isAdjustment = mov.type === 'adjustment'}

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl border bg-card shadow-2xs transition-all duration-200 hover:shadow-lg hover:shadow-accent/20">
                                <div class="flex items-start gap-3.5 min-w-0">
                                    <div class="size-9 rounded-xl flex items-center justify-center shrink-0 border {isPayment ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 dark:text-emerald-400' : isCharge ? 'bg-rose-500/10 text-rose-600 border-rose-500/20 dark:text-rose-400' : 'bg-sky-500/10 text-sky-600 border-sky-500/20 dark:text-sky-400'}">
                                        {#if isPayment}
                                            <ArrowDownLeft class="size-4" />
                                        {:else if isCharge}
                                            <ArrowUpRight class="size-4" />
                                        {:else}
                                            <Sliders class="size-4" />
                                        {/if}
                                    </div>

                                    <div class="min-w-0 space-y-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="font-semibold text-xs text-foreground">
                                                {mov.description}
                                            </h3>
                                            {#if mov.order}
                                                <span class="text-xs font-bold text-primary bg-primary/10 px-2 py-0.5 rounded tabular-nums">
                                                    {mov.order.order_number}
                                                </span>
                                            {/if}
                                            {#if mov.payment_method}
                                                <span class="text-xs font-medium tracking-wide uppercase bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                                                    {mov.payment_method}
                                                </span>
                                            {/if}
                                            {#if mov.reference_number}
                                                <span class="text-xs text-muted-foreground tabular-nums">
                                                    Ref: {mov.reference_number}
                                                </span>
                                            {/if}
                                        </div>

                                        <div class="flex items-center gap-2 text-xs tabular-nums text-muted-foreground flex-wrap">
                                            <span>{formatDate(mov.created_at)}</span>
                                            {#if mov.created_by}
                                                <span>•</span>
                                                <span>Por: {mov.created_by.name}</span>
                                            {/if}
                                            {#if mov.notes}
                                                <span>•</span>
                                                <span class="italic font-normal">"{mov.notes}"</span>
                                            {/if}
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right sm:shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 flex sm:flex-col justify-between sm:justify-center items-end">
                                    <span class="text-sm font-semibold tabular-nums {isPayment ? 'text-emerald-600 dark:text-emerald-400' : isCharge ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'}">
                                        {isPayment ? '-' : '+'}{formatPrice(mov.amount)}
                                    </span>
                                    <span class="text-xs text-muted-foreground tabular-nums mt-0.5">
                                        Saldo: <strong class="text-foreground font-semibold">{formatPrice(mov.balance_after)}</strong>
                                    </span>
                                </div>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <div class="py-12 text-center rounded-2xl bg-muted/15 border border-dashed border-border/80 p-6 space-y-2">
                        <History class="size-8 text-muted-foreground mx-auto opacity-50" />
                        <h4 class="text-xs font-semibold text-foreground">Sin movimientos coincidentes</h4>
                        <p class="text-xs text-muted-foreground max-w-sm mx-auto">
                            No se registran movimientos para los filtros seleccionados.
                        </p>
                    </div>
                {/if}
            </div>
        </div>

        <!-- Columna Derecha: Panel de Resumen del Cliente y Acciones (38%) -->
        <div class="lg:col-span-5 space-y-4">
            <div class="bg-card text-card-foreground rounded-3xl border border-border/80 p-6 shadow-2xs space-y-5 sticky top-20">
                <!-- Header Perfil Cliente -->
                <div class="flex items-start gap-3.5 pb-4 border-b border-border/70">
                    <IconContainer size="lg" variant="primary">
                        {selectedCustomer.name.slice(0, 2).toUpperCase()}
                    </IconContainer>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-base font-bold text-foreground truncate">{selectedCustomer.name}</h2>
                            {#if selectedCustomer.is_defaulter}
                                <span class="text-xs font-semibold text-amber-700 bg-amber-500/15 dark:text-amber-400 border border-amber-500/30 px-2 py-0.5 rounded-full flex items-center gap-1">
                                    <AlertTriangle class="size-3" />
                                    Moroso
                                </span>
                            {/if}
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5 tabular-nums">
                            {selectedCustomer.tax_id ? `CUIT ${selectedCustomer.tax_id}` : 'Sin CUIT'} • <span class="capitalize">{selectedCustomer.tax_condition.replace('_', ' ')}</span>
                        </p>
                    </div>
                </div>

                <!-- KPI Saldo Deudor -->
                <div class="p-4 rounded-2xl bg-muted/30 border border-border/70 space-y-3">
                    <div>
                        <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider block">
                            Saldo Deudor Actual
                        </span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-2xl sm:text-3xl font-bold tabular-nums {currentBalanceNum > 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'}">
                                {formatPrice(currentBalanceNum)}
                            </span>
                            {#if currentBalanceNum === 0}
                                <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                    <CheckCircle2 class="size-3.5" />
                                    Al día
                                </span>
                            {/if}
                        </div>
                    </div>

                    <!-- Barra de Línea de Crédito -->
                    <div class="space-y-1.5 pt-2 border-t border-border/60">
                        <div class="flex items-center justify-between text-xs tabular-nums">
                            <span class="text-muted-foreground">Límite de crédito:</span>
                            <span class="text-sm font-semibold text-foreground tabular-nums">{formatPrice(creditLimitNum)}</span>
                        </div>
                        
                        <div class="h-2 w-full bg-muted rounded-full overflow-hidden">
                            <div
                                class="h-full transition-all duration-300 rounded-full {creditUsagePercent > 90 ? 'bg-destructive' : creditUsagePercent > 70 ? 'bg-amber-500' : 'bg-primary'}"
                                style="width: {creditUsagePercent}%;"
                            ></div>
                        </div>

                        <div class="flex items-center justify-between text-xs text-muted-foreground tabular-nums">
                            <span>Uso: {creditUsagePercent}%</span>
                            <span>Disponible: <strong class="text-foreground text-sm font-semibold tabular-nums">{formatPrice(selectedCustomer.available_credit ?? Math.max(0, creditLimitNum - currentBalanceNum))}</strong></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-xs text-muted-foreground pt-1 border-t border-border/60">
                        <Clock class="size-3.5 text-primary" />
                        <span>Plazo de pago: <strong class="text-foreground tabular-nums">{selectedCustomer.credit_due_days || 30} días</strong></span>
                    </div>
                </div>

                <!-- Botones de Acción Primaria y Secundaria -->
                <div class="space-y-2.5 pt-2">
                    {#if hasPermission('cobranzas.crear')}
                        <Button
                            onclick={() => onOpenPayment(selectedCustomer!)}
                            class="w-full min-h-[38px] h-10 gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-xl shadow-sm"
                        >
                            <Banknote class="size-4" />
                            <span>Registrar Cobro / Abono</span>
                        </Button>
                    {/if}

                    {#if hasPermission('clientes.editar')}
                        <div class="grid grid-cols-2 gap-2.5">
                            <Button
                                variant="secondary"
                                onclick={() => onOpenAdjustment(selectedCustomer!)}
                                class="min-h-[38px] h-10 gap-1.5 text-sm font-medium rounded-xl"
                            >
                                <Sliders class="size-3.5" />
                                <span>Ajustar Saldo</span>
                            </Button>

                            <Button
                                variant="outline"
                                onclick={() => onOpenCreditSettings(selectedCustomer!)}
                                class="min-h-[38px] h-10 gap-1.5 text-sm font-medium rounded-xl"
                            >
                                <ShieldCheck class="size-3.5" />
                                <span>Editar Límites</span>
                            </Button>
                        </div>
                    {/if}
                </div>

                <!-- Datos de Contacto Directo -->
                <div class="p-3.5 rounded-2xl bg-muted/20 border border-border/60 space-y-2 text-xs">
                    <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Datos de Contacto</div>
                    <div class="text-foreground space-y-1.5">
                        <p class="flex items-center gap-2 truncate text-muted-foreground">
                            <MapPin class="size-3.5 text-primary shrink-0" />
                            <span class="truncate text-foreground">{selectedCustomer.address || 'Sin dirección registrada'}</span>
                        </p>
                        <p class="flex items-center gap-2 tabular-nums text-muted-foreground">
                            <Phone class="size-3.5 text-primary shrink-0" />
                            <span class="text-foreground">{selectedCustomer.phone || 'Sin teléfono'}</span>
                        </p>
                        <p class="flex items-center gap-2 truncate text-muted-foreground">
                            <Mail class="size-3.5 text-primary shrink-0" />
                            <span class="truncate text-foreground">{selectedCustomer.email || 'Sin correo electrónico'}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}
