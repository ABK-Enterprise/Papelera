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
    import X from 'lucide-svelte/icons/x';
    import Check from 'lucide-svelte/icons/check';
    import { Link } from '@inertiajs/svelte';
    import { hasPermission } from '@/lib/permissions';

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
        order_allocations?: { order: { id: number; order_number: string } }[] | null;
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
        onDeselectCustomer = undefined,
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
        onDeselectCustomer?: () => void;
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

<div class="grid grid-cols-12 gap-5 lg:gap-6 items-start font-sans">
    <!-- COLUMNA 1: Cuentas por Cobrar (33.3% / 4 cols) -->
    <div class="col-span-12 lg:col-span-4 xl:col-span-4 space-y-3 font-sans">
        <div class="flex items-center justify-between min-h-[30px]">
            <h2 class="text-sm font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2 font-sans">
                <User class="size-4 text-primary shrink-0" />
                <span>Cuentas por Cobrar</span>
                {#if customers.length > 0}
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-muted text-muted-foreground font-sans tabular-nums">
                        {customers.length}
                    </span>
                {/if}
            </h2>
        </div>

        <Card class="p-3 border-border/80 bg-card shadow-xs rounded-2xl font-sans">
            <SearchCombobox
                value={searchTerm}
                items={customers}
                searchKeys={['name', 'tax_id']}
                placeholder="Buscar por cliente o CUIT..."
                onsearch={(q) => onSearch?.(q)}
                onclear={() => onSearch?.('')}
            />
        </Card>

        <div class="space-y-2.5 max-h-[660px] overflow-y-auto pr-1 font-sans">
            {#if customers.length === 0}
                <div class="p-8 text-center text-sm text-muted-foreground bg-card rounded-2xl border border-border/70 font-sans shadow-2xs">
                    No se encontraron clientes con saldo pendiente.
                </div>
            {:else}
                {#each customers as cust}
                    <button
                        type="button"
                        onclick={() => onSelectCustomer?.(cust)}
                        class="w-full text-left p-4 rounded-2xl border transition-all duration-150 cursor-pointer font-sans {selectedCustomer?.id === cust.id
                            ? 'bg-primary/10 border-primary/40 shadow-xs'
                            : 'bg-card border-border/70 hover:border-border hover:bg-muted/40'}"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="space-y-1 min-w-0">
                                <span class="font-semibold text-sm sm:text-base text-foreground block truncate font-sans">
                                    {cust.name}
                                </span>
                                <div class="flex items-center gap-2 text-xs text-muted-foreground font-sans flex-wrap">
                                    {#if cust.tax_id}
                                        <span>CUIT: {cust.tax_id}</span>
                                    {/if}
                                    {#if cust.unpaid_orders_count && cust.unpaid_orders_count > 0}
                                        <span class="inline-flex items-center gap-1 font-semibold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-md font-sans">
                                            {cust.unpaid_orders_count} {cust.unpaid_orders_count === 1 ? 'pedido' : 'pedidos'}
                                        </span>
                                    {/if}
                                    {#if selectedCustomer?.id === cust.id}
                                        <span class="inline-flex items-center gap-1 font-semibold text-primary bg-primary/15 px-1.5 py-0.5 rounded-md font-sans text-[11px]">
                                            <Check class="size-3" /> Seleccionado
                                        </span>
                                    {/if}
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="text-sm sm:text-base font-bold font-sans tabular-nums {Number(cust.current_balance) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'}">
                                    {formatPrice(cust.current_balance)}
                                </span>
                            </div>
                        </div>
                    </button>
                {/each}
            {/if}
        </div>
    </div>

    <!-- COLUMNA 2: Pedidos Impagos del Cliente Seleccionado (33.3% / 4 cols) -->
    <div class="col-span-12 lg:col-span-4 xl:col-span-4 space-y-3 font-sans">
        <!-- Header de Columna 2: Altura y alineación nivelada con Columna 1 y Columna 3 -->
        <div class="flex items-center justify-between min-h-[30px]">
            <h2 class="text-sm font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2 font-sans">
                <ShoppingBag class="size-4 text-primary shrink-0" />
                <span>Pedidos a Cobrar</span>
                {#if selectedCustomer && unpaidOrders.length > 0}
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-sans tabular-nums">
                        {unpaidOrders.length} {unpaidOrders.length === 1 ? 'pedido' : 'pedidos'}
                    </span>
                {/if}
            </h2>

            {#if selectedCustomer}
                <button
                    type="button"
                    onclick={() => onDeselectCustomer?.()}
                    class="text-xs sm:text-sm font-bold uppercase tracking-wider text-muted-foreground hover:text-foreground flex items-center gap-1.5 font-sans transition-colors cursor-pointer py-1 px-2.5 rounded-xl hover:bg-muted border border-border/70 shadow-2xs bg-card"
                    title="Quitar cliente y ver histórico de cobros"
                >
                    <X class="size-3.5 text-muted-foreground shrink-0" />
                    <span>Quitar cliente</span>
                </button>
            {/if}
        </div>

        {#if !selectedCustomer}
            <Card class="p-8 text-center rounded-2xl border border-dashed border-border bg-card/60 flex flex-col items-center justify-center min-h-[380px] font-sans">
                <div class="size-12 rounded-2xl bg-muted/60 text-muted-foreground flex items-center justify-center mb-3">
                    <User class="size-6" />
                </div>
                <h3 class="text-base font-bold text-foreground mb-1 font-sans">Selecciona un cliente</h3>
                <p class="text-xs sm:text-sm text-muted-foreground max-w-xs font-sans">
                    Elige un cliente del listado de la izquierda para visualizar sus pedidos pendientes e imputar cobros mediante FIFO.
                </p>
            </Card>
        {:else}
            <!-- Cabecera de Cliente Seleccionado -->
            <div class="p-4 rounded-2xl bg-card border border-border/80 shadow-2xs flex items-center justify-between gap-3 font-sans">
                <div>
                    <h3 class="font-bold text-base text-foreground flex items-center gap-2 font-sans">
                        {selectedCustomer.name}
                    </h3>
                    <span class="text-xs sm:text-sm text-muted-foreground font-sans">
                        Saldo deudor en cuenta: <strong class="text-rose-600 dark:text-rose-400 font-sans tabular-nums">{formatPrice(selectedCustomer.current_balance)}</strong>
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    {#if unpaidOrders.length > 0}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8.5 px-3 text-xs gap-1.5 rounded-xl font-semibold font-sans"
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
            </div>

            <!-- Listado de Cards de Pedidos Impagos -->
            <div class="space-y-3 max-h-[540px] overflow-y-auto pr-1 font-sans">
                {#if unpaidOrders.length === 0}
                    <Card class="p-8 text-center rounded-2xl border border-border/70 bg-card font-sans">
                        <ShoppingBag class="size-8 text-emerald-500 mx-auto mb-2 opacity-80" />
                        <h4 class="text-sm font-bold text-foreground font-sans">Sin pedidos impagos</h4>
                        <p class="text-xs text-muted-foreground mt-1 font-sans">
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
                            class="p-4 rounded-2xl border transition-all duration-150 cursor-pointer font-sans {isSelected
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

                                    <div class="space-y-1 font-sans">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-sm sm:text-base text-foreground font-sans">
                                                {order.order_number}
                                            </span>
                                            {#if order.shipping_zone}
                                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-primary bg-primary/10 px-2 py-0.5 rounded-md font-sans">
                                                    <MapPin class="size-3" />
                                                    {order.shipping_zone.name}
                                                </span>
                                            {/if}
                                            <span class="text-xs px-2 py-0.5 rounded-md font-semibold font-sans {order.payment_status === 'parcial' ? 'bg-amber-500/10 text-amber-600 border border-amber-500/20' : 'bg-rose-500/10 text-rose-600 border border-rose-500/20'}">
                                                {order.payment_status === 'parcial' ? 'Pago Parcial' : 'Impago'}
                                            </span>
                                        </div>

                                        <p class="text-xs text-muted-foreground font-sans">
                                            Fecha: {formatDate(order.created_at)} • Total: <strong class="font-sans tabular-nums text-foreground">{formatPrice(order.total_amount)}</strong>
                                        </p>
                                    </div>
                                </div>

                                <div class="text-right shrink-0 font-sans">
                                    <span class="text-xs uppercase font-semibold text-muted-foreground block font-sans">Deuda Pendiente</span>
                                    <span class="text-sm sm:text-base font-bold font-sans tabular-nums text-rose-600 dark:text-rose-400">
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
                <div class="p-4 rounded-2xl bg-card border border-border shadow-md flex items-center justify-between gap-3 font-sans">
                    <div class="text-xs sm:text-sm font-sans">
                        <span class="text-muted-foreground font-sans">Seleccionados: <strong class="font-bold text-foreground font-sans">{selectedOrderIds.length}</strong></span>
                        <span class="block font-bold text-foreground text-sm sm:text-base font-sans tabular-nums">
                            Total a Cobrar: {formatPrice(selectedOrdersTotalDebt)}
                        </span>
                    </div>

                    {#if hasPermission('cobranzas.crear')}
                        <Button
                            type="button"
                            variant="default"
                            class="min-h-[46px] h-auto py-3 px-5 rounded-xl font-bold text-xs sm:text-sm font-sans shadow-sm gap-2 leading-snug text-center flex items-center justify-center"
                            disabled={selectedOrderIds.length === 0}
                            onclick={() => onOpenPaymentModal?.()}
                        >
                            <ReceiptText class="size-4 shrink-0" />
                            <span>Cobrar Seleccionados ({formatPrice(selectedOrdersTotalDebt)})</span>
                        </Button>
                    {/if}
                </div>
            {/if}
        {/if}
    </div>

    <!-- COLUMNA 3: Cobros Realizados Recientes (33.3% / 4 cols con Google Sans y cards accesibles) -->
    <div class="col-span-12 lg:col-span-4 xl:col-span-4 space-y-3 font-sans">
        <div class="flex items-center justify-between min-h-[30px]">
            <h2 class="text-sm font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2 font-sans">
                <ReceiptText class="size-4 text-primary shrink-0" />
                <span>Cobros Realizados</span>
                {#if recentReceipts.length > 0}
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-muted text-muted-foreground font-sans tabular-nums">
                        {recentReceipts.length}
                    </span>
                {/if}
            </h2>

            <Link
                href="/tenant/cobranzas/recibos"
                class="text-xs sm:text-sm font-semibold text-primary hover:underline flex items-center gap-1.5 font-sans transition-colors"
            >
                <span>Ver todos</span>
                <ArrowRight class="size-3.5 shrink-0" />
            </Link>
        </div>

        <div class="space-y-3 max-h-[660px] overflow-y-auto pr-1 font-sans">
            {#if recentReceipts.length === 0}
                <div class="p-8 text-center text-sm text-muted-foreground bg-card rounded-2xl border border-border/70 font-sans shadow-2xs">
                    No hay recibos registrados recientemente.
                </div>
            {:else}
                {#each recentReceipts as rc}
                    <div class="p-4 sm:p-4.5 rounded-2xl border bg-card border-border/80 hover:border-border transition-all duration-150 space-y-3 shadow-2xs font-sans">
                        <!-- Fila 1: Número de Recibo y Badge de Método / Cancelación -->
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-sm sm:text-base text-foreground font-sans tracking-tight">
                                {rc.receipt_number}
                            </span>
                            {#if rc.is_cancelled}
                                <span class="text-xs font-bold text-destructive bg-destructive/10 px-2.5 py-1 rounded-lg border border-destructive/20 line-through font-sans">
                                    ANULADO
                                </span>
                            {:else}
                                <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-lg border border-emerald-500/20 uppercase font-sans tracking-wide">
                                    {rc.payment_method}
                                </span>
                            {/if}
                        </div>

                        <!-- Fila 2: Cliente -->
                        {#if rc.customer}
                            <div class="text-xs sm:text-sm text-muted-foreground font-sans truncate">
                                Cliente: <strong class="font-semibold text-foreground font-sans">{rc.customer.name}</strong>
                            </div>
                        {/if}

                        <!-- Fila 3: Imputaciones a pedidos -->
                        {#if rc.order_allocations && rc.order_allocations.length > 0}
                            <div class="flex flex-wrap items-center gap-1.5 pt-0.5 font-sans">
                                <span class="text-xs text-muted-foreground font-sans mr-0.5">Imputado:</span>
                                {#each rc.order_allocations as alloc}
                                    <span class="text-xs font-sans font-semibold tabular-nums bg-muted/80 px-2.5 py-1 rounded-lg text-foreground border border-border/70">
                                        {alloc.order.order_number}
                                    </span>
                                {/each}
                            </div>
                        {/if}

                        <!-- Fila 4: Fecha y Monto -->
                        <div class="flex items-center justify-between text-xs sm:text-sm pt-2.5 border-t border-border/60 font-sans">
                            <span class="text-xs text-muted-foreground font-sans">{formatDate(rc.created_at)}</span>
                            <span class="text-sm sm:text-base font-bold font-sans tabular-nums text-foreground">
                                {formatPrice(rc.amount)}
                            </span>
                        </div>
                    </div>
                {/each}
            {/if}
        </div>
    </div>
</div>
