<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { router } from '@inertiajs/svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Banknote from 'lucide-svelte/icons/banknote';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Clock from 'lucide-svelte/icons/clock';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';
    import FileText from 'lucide-svelte/icons/file-text';
    import History from 'lucide-svelte/icons/history';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Phone from 'lucide-svelte/icons/phone';
    import Search from 'lucide-svelte/icons/search';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Sliders from 'lucide-svelte/icons/sliders';
    import User from 'lucide-svelte/icons/user';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import X from 'lucide-svelte/icons/x';
    import IconContainer from '@/components/common/IconContainer.svelte';

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
        available_credit?: number;
        movements?: MovementData[];
    }

    let {
        customers = [],
        selectedCustomer = null,
        onOpenPayment,
        onOpenAdjustment,
        onOpenCreditSettings,
    }: {
        customers: CustomerAccountFull[];
        selectedCustomer: CustomerAccountFull | null;
        onOpenPayment: (cust: CustomerAccountFull) => void;
        onOpenAdjustment: (cust: CustomerAccountFull) => void;
        onOpenCreditSettings: (cust: CustomerAccountFull) => void;
    } = $props();

    let customerSearchQuery = $state('');
    let isDropdownOpen = $state(false);

    let filteredCustomers = $derived.by(() => {
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

    function selectCustomer(cust: CustomerAccountFull) {
        customerSearchQuery = '';
        isDropdownOpen = false;
        router.get(
            '/tenant/customers/accounts',
            { customer_id: cust.id },
            { preserveState: true, preserveScroll: true }
        );
    }

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

    let creditLimitNum = $derived(Number(selectedCustomer?.credit_limit) || 0);
    let currentBalanceNum = $derived(Number(selectedCustomer?.current_balance) || 0);
    let creditUsagePercent = $derived.by(() => {
        if (creditLimitNum <= 0) return 0;
        return Math.min(100, Math.round((currentBalanceNum / creditLimitNum) * 100));
    });
    let containerRef: HTMLDivElement | undefined = $state();

    function handleWindowPointerDown(e: PointerEvent) {
        if (isDropdownOpen && containerRef && !containerRef.contains(e.target as Node)) {
            isDropdownOpen = false;
        }
    }

    function handleWindowKeyDown(e: KeyboardEvent) {
        if (e.key === 'Escape' && isDropdownOpen) {
            isDropdownOpen = false;
        }
    }
</script>

<svelte:window onpointerdown={handleWindowPointerDown} onkeydown={handleWindowKeyDown} />

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    <!-- Columna Izquierda: Selector & Feed de Movimientos (62%) -->
    <div class="lg:col-span-7 space-y-4">
        <!-- Selector Rápido de Cliente (Combobox Flotante) -->
        <div class="relative z-20" bind:this={containerRef}>
            <div class="relative">
                <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <input
                    type="text"
                    bind:value={customerSearchQuery}
                    onfocus={() => (isDropdownOpen = true)}
                    placeholder="Buscar cliente para ver cuenta corriente (Nombre, CUIT, Teléfono)..."
                    class="h-10 w-full rounded-2xl border border-input bg-card pl-10 pr-9 text-xs font-normal text-foreground placeholder:text-muted-foreground shadow-2xs focus:ring-1 focus:ring-primary outline-none transition-all"
                />
                {#if customerSearchQuery}
                    <button
                        type="button"
                        onclick={() => {
                            customerSearchQuery = '';
                            isDropdownOpen = false;
                        }}
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                        <X class="size-3.5" />
                    </button>
                {/if}
            </div>

            {#if isDropdownOpen}
                <div class="absolute left-0 right-0 top-full mt-1.5 bg-popover text-popover-foreground rounded-2xl border border-border shadow-2xl z-50 overflow-hidden">
                    <div class="max-h-[260px] overflow-y-auto divide-y divide-border/40">
                        {#each filteredCustomers as cust (cust.id)}
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
                                            <span class="text-[10px] font-bold text-amber-700 bg-amber-500/15 dark:text-amber-400 px-1.5 py-0.5 rounded">
                                                Moroso
                                            </span>
                                        {/if}
                                    </div>
                                    <p class="text-[11px] text-muted-foreground mt-0.5 tabular-nums">
                                        {cust.tax_id || 'Sin CUIT'} • {cust.phone || 'Sin tel.'}
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="font-bold tabular-nums text-xs block {Number(cust.current_balance) > 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'}">
                                        {formatPrice(cust.current_balance)}
                                    </span>
                                    <span class="text-[10px] text-muted-foreground">Saldo</span>
                                </div>
                            </button>
                        {/each}
                        {#if filteredCustomers.length === 0}
                            <div class="p-4 text-center text-xs text-muted-foreground italic">
                                No se encontraron clientes con ese criterio.
                            </div>
                        {/if}
                    </div>
                </div>
            {/if}
        </div>

        {#if selectedCustomer}
            <!-- Historial de Movimientos -->
            <div class="bg-card text-card-foreground rounded-3xl border border-border/80 p-5 sm:p-6 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border/60">
                    <div>
                        <h2 class="text-base font-semibold text-foreground flex items-center gap-2 leading-snug">
                            <History class="size-4 text-primary" />
                            Libro Mayor de Movimientos
                        </h2>
                    </div>
                    <span class="text-xs font-semibold tabular-nums text-muted-foreground bg-muted px-2.5 py-1 rounded-full">
                        {selectedCustomer.movements?.length || 0} movimientos
                    </span>
                </div>

                {#if selectedCustomer.movements && selectedCustomer.movements.length > 0}
                    <div class="space-y-3">
                        {#each selectedCustomer.movements as mov (mov.id)}
                            {@const isCharge = mov.type === 'charge'}
                            {@const isPayment = mov.type === 'payment'}
                            {@const isAdjustment = mov.type === 'adjustment'}

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl border bg-card hover:bg-muted/20 transition-colors shadow-2xs">
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
                                                <span class="text-[11px] font-bold text-primary bg-primary/10 px-2 py-0.5 rounded tabular-nums">
                                                    {mov.order.order_number}
                                                </span>
                                            {/if}
                                            {#if mov.payment_method}
                                                <span class="text-[10px] font-medium tracking-wide uppercase bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                                                    {mov.payment_method}
                                                </span>
                                            {/if}
                                            {#if mov.reference_number}
                                                <span class="text-[11px] text-muted-foreground tabular-nums">
                                                    Ref: {mov.reference_number}
                                                </span>
                                            {/if}
                                        </div>

                                        <div class="flex items-center gap-2 text-[11px] text-muted-foreground tabular-nums flex-wrap">
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
                                    <span class="text-xs font-bold tabular-nums {isPayment ? 'text-emerald-600 dark:text-emerald-400' : isCharge ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'}">
                                        {isPayment ? '-' : '+'}{formatPrice(mov.amount)}
                                    </span>
                                    <span class="text-[10px] text-muted-foreground tabular-nums mt-0.5">
                                        Saldo: <strong class="text-foreground">{formatPrice(mov.balance_after)}</strong>
                                    </span>
                                </div>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <div class="py-12 text-center rounded-2xl bg-muted/15 border border-dashed border-border/80 p-6 space-y-2">
                        <History class="size-8 text-muted-foreground mx-auto opacity-50" />
                        <h4 class="text-xs font-semibold text-foreground">Sin movimientos registrados</h4>
                        <p class="text-[11px] text-muted-foreground max-w-sm mx-auto">
                            Este cliente aún no registra compras a crédito ni abonos en su cuenta corriente.
                        </p>
                    </div>
                {/if}
            </div>
        {:else}
            <div class="bg-card text-card-foreground rounded-3xl border border-border/80 p-12 text-center shadow-2xs space-y-3">
                <UserCheck class="size-10 text-muted-foreground mx-auto opacity-60" />
                <h3 class="text-sm font-semibold text-foreground">Selecciona un cliente</h3>
                <p class="text-xs text-muted-foreground max-w-md mx-auto">
                    Utiliza el buscador superior para inspeccionar el estado de cuenta corriente, registrar pagos o ajustar condiciones.
                </p>
            </div>
        {/if}
    </div>

    <!-- Columna Derecha: Panel de Resumen y Acciones (38%) -->
    <div class="lg:col-span-5 space-y-4">
        {#if selectedCustomer}
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
                                <span class="text-[10px] font-bold text-amber-700 bg-amber-500/15 dark:text-amber-400 border border-amber-500/30 px-2 py-0.5 rounded-full flex items-center gap-1">
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
                            <span class="font-semibold text-foreground">{formatPrice(creditLimitNum)}</span>
                        </div>
                        
                        <div class="h-2 w-full bg-muted rounded-full overflow-hidden">
                            <div
                                class="h-full transition-all duration-300 rounded-full {creditUsagePercent > 90 ? 'bg-destructive' : creditUsagePercent > 70 ? 'bg-amber-500' : 'bg-primary'}"
                                style="width: {creditUsagePercent}%;"
                            ></div>
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-muted-foreground tabular-nums">
                            <span>Uso: {creditUsagePercent}%</span>
                            <span>Disponible: <strong class="text-foreground">{formatPrice(selectedCustomer.available_credit ?? Math.max(0, creditLimitNum - currentBalanceNum))}</strong></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-xs text-muted-foreground pt-1 border-t border-border/60">
                        <Clock class="size-3.5 text-primary" />
                        <span>Plazo de pago: <strong class="text-foreground tabular-nums">{selectedCustomer.credit_due_days || 30} días</strong></span>
                    </div>
                </div>

                <!-- Botones de Acción Primaria y Secundaria -->
                <div class="space-y-2.5 pt-2">
                    <Button
                        onclick={() => onOpenPayment(selectedCustomer!)}
                        class="w-full h-10 gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl shadow-sm"
                    >
                        <Banknote class="size-4" />
                        <span>Registrar Cobro / Abono</span>
                    </Button>

                    <div class="grid grid-cols-2 gap-2.5">
                        <Button
                            variant="secondary"
                            onclick={() => onOpenAdjustment(selectedCustomer!)}
                            class="min-h-[40px] h-10 gap-1.5 text-xs font-semibold rounded-xl"
                        >
                            <Sliders class="size-3.5" />
                            <span>Ajustar Saldo</span>
                        </Button>

                        <Button
                            variant="outline"
                            onclick={() => onOpenCreditSettings(selectedCustomer!)}
                            class="min-h-[40px] h-10 gap-1.5 text-xs font-semibold rounded-xl"
                        >
                            <ShieldCheck class="size-3.5" />
                            <span>Editar Límites</span>
                        </Button>
                    </div>

                </div>

                <!-- Datos de Contacto Directo -->
                <div class="p-3.5 rounded-2xl bg-muted/20 border border-border/60 space-y-2 text-xs">
                    <div class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Datos de Contacto</div>
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
        {/if}
    </div>
</div>
