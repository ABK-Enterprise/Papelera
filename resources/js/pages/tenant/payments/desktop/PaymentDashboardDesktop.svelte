<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import User from 'lucide-svelte/icons/user';
    import CheckSquare from 'lucide-svelte/icons/check-square';
    import Square from 'lucide-svelte/icons/square';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import { Link } from '@inertiajs/svelte';

    export interface CustomerSummary {
        id: number;
        name: string;
        tax_id: string | null;
        phone: string | null;
        current_balance: string | number;
        unpaid_orders_count?: number;
        total_unpaid_debt?: string | number;
    }

    export interface OrderSummary {
        id: number;
        order_number: string;
        customer_name: string;
        total_amount: string | number;
        debt_amount: string | number;
        status: string;
        payment_status: string;
        created_at: string;
        shipping_zone?: { id: number; name: string; surcharge_percentage: string | number } | null;
    }

    export interface ReceiptSummary {
        id: number;
        receipt_number: string;
        amount: string | number;
        payment_method: string;
        reference_number: string | null;
        is_cancelled: boolean;
        created_at: string;
        customer?: { id: number; name: string } | null;
        created_by_user?: { id: number; name: string } | null;
    }

    let {
        customers = [],
        selectedCustomer = null,
        unpaidOrders = [],
        recentReceipts = [],
        searchTerm = '',
        selectedOrderIds = [],
        onSearch = undefined,
        onSelectCustomer = undefined,
        onToggleOrderSelection = undefined,
        onToggleSelectAllOrders = undefined,
        onOpenPaymentModal = undefined,
    }: {
        customers: CustomerSummary[];
        selectedCustomer: CustomerSummary | null;
        unpaidOrders: OrderSummary[];
        recentReceipts: ReceiptSummary[];
        searchTerm?: string;
        selectedOrderIds: number[];
        onSearch?: (term: string) => void;
        onSelectCustomer?: (customer: CustomerSummary) => void;
        onToggleOrderSelection?: (orderId: number) => void;
        onToggleSelectAllOrders?: () => void;
        onOpenPaymentModal?: () => void;
    } = $props();

    function formatPrice(val: number | string) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(val) || 0);
    }

    function formatDate(val: string) {
        if (!val) return '';
        return new Intl.DateTimeFormat('es-AR', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(val));
    }

    let selectedOrdersTotalDebt = $derived(
        unpaidOrders
            .filter((o) => selectedOrderIds.includes(o.id))
            .reduce((sum, o) => sum + (Number(o.debt_amount) || 0), 0)
    );

    let isAllSelected = $derived(
        unpaidOrders.length > 0 && selectedOrderIds.length === unpaidOrders.length
    );
</script>

<div class="grid grid-cols-12 gap-5 items-start">
    <!-- COLUMNA 1: Cuentas por Cobrar (30%) -->
    <div class="col-span-12 lg:col-span-4 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                <User class="size-3.5 text-primary" />
                Cuentas por Cobrar ({customers.length})
            </h2>
        </div>

        <Card class="p-3 border-border/80 bg-card shadow-xs rounded-xl">
            <SearchCombobox
                value={searchTerm}
                items={customers}
                searchKeys={['name', 'tax_id']}
                placeholder="Buscar por cliente o CUIT..."
                onsearch={(q) => onSearch?.(q)}
                onclear={() => onSearch?.('')}
            />
        </Card>

        <div class="space-y-2 max-h-[640px] overflow-y-auto pr-1">
            {#if customers.length === 0}
                <div class="p-8 text-center text-xs text-muted-foreground bg-card rounded-xl border border-border/60">
                    No se encontraron clientes con saldo pendiente.
                </div>
            {:else}
                {#each customers as cust}
                    <button
                        type="button"
                        onclick={() => onSelectCustomer?.(cust)}
                        class="w-full text-left p-3.5 rounded-xl border transition-all duration-150 cursor-pointer {selectedCustomer?.id === cust.id
                            ? 'bg-primary/10 border-primary/40 shadow-xs'
                            : 'bg-card border-border/70 hover:border-border hover:bg-muted/40'}"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="space-y-1 min-w-0">
                                <span class="font-semibold text-xs sm:text-sm text-foreground block truncate">
                                    {cust.name}
                                </span>
                                <div class="flex items-center gap-2 text-[11px] text-muted-foreground">
                                    {#if cust.tax_id}
                                        <span>CUIT: {cust.tax_id}</span>
                                    {/if}
                                    {#if cust.unpaid_orders_count && cust.unpaid_orders_count > 0}
                                        <span class="inline-flex items-center gap-1 font-semibold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-1.5 py-0.2 rounded">
                                            {cust.unpaid_orders_count} {cust.unpaid_orders_count === 1 ? 'pedido' : 'pedidos'}
                                        </span>
                                    {/if}
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="text-xs sm:text-sm font-bold font-sans tabular-nums {Number(cust.current_balance) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'}">
                                    {formatPrice(cust.current_balance)}
                                </span>
                            </div>
                        </div>
                    </button>
                {/each}
            {/if}
        </div>
    </div>

    <!-- COLUMNA 2: Pedidos Impagos del Cliente Seleccionado (45%) -->
    <div class="col-span-12 lg:col-span-5 space-y-3">
        {#if !selectedCustomer}
            <Card class="p-8 text-center rounded-2xl border border-dashed border-border bg-card/60 flex flex-col items-center justify-center min-h-[380px]">
                <div class="size-12 rounded-2xl bg-muted/60 text-muted-foreground flex items-center justify-center mb-3">
                    <User class="size-6" />
                </div>
                <h3 class="text-sm font-bold text-foreground mb-1">Selecciona un cliente</h3>
                <p class="text-xs text-muted-foreground max-w-xs">
                    Elige un cliente del listado de la izquierda para visualizar sus pedidos pendientes e imputar cobros mediante FIFO.
                </p>
            </Card>
        {:else}
            <!-- Cabecera de Cliente Seleccionado -->
            <div class="p-3.5 rounded-xl bg-card border border-border/80 shadow-2xs flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold text-sm text-foreground flex items-center gap-2">
                        {selectedCustomer.name}
                    </h3>
                    <span class="text-xs text-muted-foreground">
                        Saldo deudor en cuenta: <strong class="text-rose-600 dark:text-rose-400 font-sans tabular-nums">{formatPrice(selectedCustomer.current_balance)}</strong>
                    </span>
                </div>

                {#if unpaidOrders.length > 0}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 px-2.5 text-xs gap-1.5 rounded-lg"
                        onclick={() => onToggleSelectAllOrders?.()}
                    >
                        {#if isAllSelected}
                            <CheckSquare class="size-3.5 text-primary" />
                            <span>Deseleccionar</span>
                        {:else}
                            <Square class="size-3.5 text-muted-foreground" />
                            <span>Seleccionar Todos</span>
                        {/if}
                    </Button>
                {/if}
            </div>

            <!-- Listado de Wide-Cards de Pedidos Impagos -->
            <div class="space-y-2.5 max-h-[520px] overflow-y-auto pr-1">
                {#if unpaidOrders.length === 0}
                    <Card class="p-8 text-center rounded-xl border border-border/60 bg-card">
                        <ShoppingBag class="size-8 text-emerald-500 mx-auto mb-2 opacity-80" />
                        <h4 class="text-xs font-bold text-foreground">Sin pedidos impagos</h4>
                        <p class="text-[11px] text-muted-foreground mt-1">
                            Este cliente no registra pedidos pendientes de cancelación.
                        </p>
                    </Card>
                {:else}
                    {#each unpaidOrders as order}
                        {@const isSelected = selectedOrderIds.includes(order.id)}
                        <div
                            role="button"
                            tabindex="0"
                            onclick={() => onToggleOrderSelection?.(order.id)}
                            onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') onToggleOrderSelection?.(order.id); }}
                            class="p-3.5 rounded-xl border transition-all duration-150 cursor-pointer {isSelected
                                ? 'bg-primary/10 border-primary/40 shadow-xs'
                                : 'bg-card border-border/70 hover:border-border hover:bg-muted/30'}"
                        >
                            <div class="flex items-start justify-between gap-2.5">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 text-primary">
                                        {#if isSelected}
                                            <CheckSquare class="size-4 text-primary" />
                                        {:else}
                                            <Square class="size-4 text-muted-foreground" />
                                        {/if}
                                    </div>

                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-xs sm:text-sm text-foreground">
                                                {order.order_number}
                                            </span>
                                            {#if order.shipping_zone}
                                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-primary bg-primary/10 px-1.5 py-0.2 rounded">
                                                    <MapPin class="size-2.5" />
                                                    {order.shipping_zone.name}
                                                </span>
                                            {/if}
                                            <span class="text-[10px] px-1.5 py-0.2 rounded font-semibold {order.payment_status === 'parcial' ? 'bg-amber-500/10 text-amber-600 border border-amber-500/20' : 'bg-rose-500/10 text-rose-600 border border-rose-500/20'}">
                                                {order.payment_status === 'parcial' ? 'Pago Parcial' : 'Impago'}
                                            </span>
                                        </div>

                                        <p class="text-[11px] text-muted-foreground">
                                            Fecha: {formatDate(order.created_at)} • Total: {formatPrice(order.total_amount)}
                                        </p>
                                    </div>
                                </div>

                                <div class="text-right shrink-0">
                                    <span class="text-[10px] uppercase font-semibold text-muted-foreground block">Deuda Pendiente</span>
                                    <span class="text-xs sm:text-sm font-bold font-sans tabular-nums text-rose-600 dark:text-rose-400">
                                        {formatPrice(order.debt_amount)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    {/each}
                {/if}
            </div>

            <!-- Sticky Bottom Action Bar -->
            {#if unpaidOrders.length > 0}
                <div class="p-3.5 rounded-xl bg-card border border-border shadow-md flex items-center justify-between gap-3">
                    <div class="text-xs">
                        <span class="text-muted-foreground">Seleccionados: <strong>{selectedOrderIds.length}</strong></span>
                        <span class="block font-bold text-foreground text-sm font-sans tabular-nums">
                            Total a Cobrar: {formatPrice(selectedOrdersTotalDebt)}
                        </span>
                    </div>

                    <Button
                        type="button"
                        variant="default"
                        class="h-11 px-5 rounded-xl font-bold min-h-[44px] shadow-sm gap-2"
                        disabled={selectedOrderIds.length === 0}
                        onclick={() => onOpenPaymentModal?.()}
                    >
                        <ReceiptText class="size-4" />
                        <span>Cobrar Seleccionados ({formatPrice(selectedOrdersTotalDebt)})</span>
                    </Button>
                </div>
            {/if}
        {/if}
    </div>

    <!-- COLUMNA 3: Cobros Realizados Recientes (25%) -->
    <div class="col-span-12 lg:col-span-3 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                <ReceiptText class="size-3.5 text-primary" />
                Cobros Realizados
            </h2>

            <Link
                href="/tenant/cobranzas/recibos"
                class="text-xs font-semibold text-primary hover:underline flex items-center gap-1"
            >
                <span>Ver todos</span>
                <ArrowRight class="size-3" />
            </Link>
        </div>

        <div class="space-y-2 max-h-[640px] overflow-y-auto pr-1">
            {#if recentReceipts.length === 0}
                <div class="p-6 text-center text-xs text-muted-foreground bg-card rounded-xl border border-border/60">
                    No hay recibos registrados recientemente.
                </div>
            {:else}
                {#each recentReceipts as rc}
                    <div class="p-3 rounded-xl border bg-card border-border/70 space-y-1.5 shadow-2xs">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-xs text-foreground font-sans">
                                {rc.receipt_number}
                            </span>
                            {#if rc.is_cancelled}
                                <span class="text-[9px] font-bold text-destructive bg-destructive/10 px-1.5 py-0.2 rounded border border-destructive/20 line-through">
                                    ANULADO
                                </span>
                            {:else}
                                <span class="text-[9px] font-semibold text-emerald-600 bg-emerald-500/10 px-1.5 py-0.2 rounded border border-emerald-500/20 uppercase">
                                    {rc.payment_method}
                                </span>
                            {/if}
                        </div>

                        {#if rc.customer}
                            <span class="text-[11px] text-muted-foreground block truncate">
                                Cliente: <strong class="text-foreground">{rc.customer.name}</strong>
                            </span>
                        {/if}

                        <div class="flex items-center justify-between text-xs pt-1 border-t border-border/40">
                            <span class="text-[10px] text-muted-foreground">{formatDate(rc.created_at)}</span>
                            <span class="font-bold font-sans tabular-nums text-foreground">
                                {formatPrice(rc.amount)}
                            </span>
                        </div>
                    </div>
                {/each}
            {/if}
        </div>
    </div>
</div>
