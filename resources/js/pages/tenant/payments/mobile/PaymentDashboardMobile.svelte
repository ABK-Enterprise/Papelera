<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import User from 'lucide-svelte/icons/user';
    import CheckSquare from 'lucide-svelte/icons/check-square';
    import Square from 'lucide-svelte/icons/square';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import { Link } from '@inertiajs/svelte';
    import type { CustomerSummary, OrderSummary, ReceiptSummary } from '../desktop/PaymentDashboardDesktop.svelte';

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

    let activeTab = $state<'customers' | 'orders' | 'receipts'>('customers');

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

    function selectCustomerAndSwitchTab(cust: CustomerSummary) {
        onSelectCustomer?.(cust);
        activeTab = 'orders';
    }
</script>

<div class="space-y-4">
    <!-- Pestañas Superiores Mobile -->
    <div class="grid grid-cols-3 gap-1.5 p-1 bg-muted/40 rounded-xl border border-border/60">
        <button
            type="button"
            onclick={() => (activeTab = 'customers')}
            class="py-2.5 px-2 rounded-lg text-xs font-semibold transition-all duration-150 {activeTab === 'customers'
                ? 'bg-card text-foreground shadow-2xs'
                : 'text-muted-foreground hover:text-foreground'}"
        >
            Clientes ({customers.length})
        </button>

        <button
            type="button"
            onclick={() => (activeTab = 'orders')}
            class="py-2.5 px-2 rounded-lg text-xs font-semibold transition-all duration-150 {activeTab === 'orders'
                ? 'bg-card text-foreground shadow-2xs'
                : 'text-muted-foreground hover:text-foreground'}"
        >
            Pedidos ({unpaidOrders.length})
        </button>

        <button
            type="button"
            onclick={() => (activeTab = 'receipts')}
            class="py-2.5 px-2 rounded-lg text-xs font-semibold transition-all duration-150 {activeTab === 'receipts'
                ? 'bg-card text-foreground shadow-2xs'
                : 'text-muted-foreground hover:text-foreground'}"
        >
            Recibos ({recentReceipts.length})
        </button>
    </div>

    <!-- Pestaña 1: Clientes con Deuda -->
    {#if activeTab === 'customers'}
        <div class="space-y-3">
            <SearchCombobox
                value={searchTerm}
                items={customers}
                searchKeys={['name', 'tax_id']}
                placeholder="Buscar cliente..."
                onsearch={(q) => onSearch?.(q)}
                onclear={() => onSearch?.('')}
            />

            <div class="space-y-2">
                {#if customers.length === 0}
                    <Card class="p-6 text-center text-xs text-muted-foreground">
                        No hay clientes con saldo pendiente.
                    </Card>
                {:else}
                    {#each customers as cust}
                        <button
                            type="button"
                            onclick={() => selectCustomerAndSwitchTab(cust)}
                            class="w-full text-left p-3.5 rounded-xl border bg-card border-border/80 shadow-2xs transition-all active:scale-[0.98]"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="space-y-1 min-w-0">
                                    <span class="font-bold text-xs text-foreground block truncate">
                                        {cust.name}
                                    </span>
                                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                        {#if cust.tax_id}
                                            <span>CUIT: {cust.tax_id}</span>
                                        {/if}
                                        {#if cust.unpaid_orders_count && cust.unpaid_orders_count > 0}
                                            <span class="font-semibold text-amber-600 bg-amber-500/10 px-1.5 py-0.2 rounded">
                                                {cust.unpaid_orders_count} ped.
                                            </span>
                                        {/if}
                                    </div>
                                </div>

                                <div class="text-right shrink-0">
                                    <span class="text-xs font-bold font-sans tabular-nums text-rose-600 dark:text-rose-400">
                                        {formatPrice(cust.current_balance)}
                                    </span>
                                    <span class="text-[10px] text-primary font-semibold flex items-center justify-end gap-0.5 mt-0.5">
                                        Ver pedidos <ArrowRight class="size-2.5" />
                                    </span>
                                </div>
                            </div>
                        </button>
                    {/each}
                {/if}
            </div>
        </div>
    {/if}

    <!-- Pestaña 2: Pedidos Impagos -->
    {#if activeTab === 'orders'}
        <div class="space-y-3">
            {#if !selectedCustomer}
                <Card class="p-8 text-center rounded-2xl border border-dashed border-border bg-card">
                    <User class="size-8 text-muted-foreground mx-auto mb-2 opacity-60" />
                    <h3 class="text-xs font-bold text-foreground">Ningún cliente seleccionado</h3>
                    <p class="text-[11px] text-muted-foreground mt-1 mb-3">
                        Selecciona un cliente para gestionar sus pedidos impagos.
                    </p>
                    <Button
                        variant="secondary"
                        size="sm"
                        class="min-h-[40px]"
                        onclick={() => (activeTab = 'customers')}
                    >
                        Ir a lista de clientes
                    </Button>
                </Card>
            {:else}
                <!-- Cabecera del Cliente -->
                <div class="p-3 rounded-xl bg-card border border-border/80 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-foreground block">{selectedCustomer.name}</span>
                        <span class="text-[11px] text-muted-foreground">
                            Deuda: <strong class="text-rose-600 font-sans tabular-nums">{formatPrice(selectedCustomer.current_balance)}</strong>
                        </span>
                    </div>

                    {#if unpaidOrders.length > 0}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 px-2 text-[11px] gap-1"
                            onclick={() => onToggleSelectAllOrders?.()}
                        >
                            {#if isAllSelected}
                                <CheckSquare class="size-3 text-primary" />
                                <span>Quitar</span>
                            {:else}
                                <Square class="size-3 text-muted-foreground" />
                                <span>Todos</span>
                            {/if}
                        </Button>
                    {/if}
                </div>

                <!-- Lista de Pedidos -->
                <div class="space-y-2 pb-20">
                    {#if unpaidOrders.length === 0}
                        <Card class="p-6 text-center text-xs text-muted-foreground">
                            No hay pedidos impagos para este cliente.
                        </Card>
                    {:else}
                        {#each unpaidOrders as order}
                            {@const isSelected = selectedOrderIds.includes(order.id)}
                            <div
                                role="button"
                                tabindex="0"
                                onclick={() => onToggleOrderSelection?.(order.id)}
                                onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') onToggleOrderSelection?.(order.id); }}
                                class="p-3 rounded-xl border transition-all active:scale-[0.98] {isSelected
                                    ? 'bg-primary/10 border-primary/40'
                                    : 'bg-card border-border/80'}"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-start gap-2.5">
                                        <div class="mt-0.5 text-primary">
                                            {#if isSelected}
                                                <CheckSquare class="size-4 text-primary" />
                                            {:else}
                                                <Square class="size-4 text-muted-foreground" />
                                            {/if}
                                        </div>

                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold text-xs text-foreground">{order.order_number}</span>
                                                {#if order.shipping_zone}
                                                    <span class="text-[9px] font-semibold text-primary bg-primary/10 px-1 py-0.2 rounded">
                                                        {order.shipping_zone.name}
                                                    </span>
                                                {/if}
                                            </div>
                                            <span class="text-[10px] text-muted-foreground block">
                                                Total: {formatPrice(order.total_amount)}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="text-right shrink-0">
                                        <span class="text-[9px] uppercase font-semibold text-muted-foreground block">Deuda</span>
                                        <span class="text-xs font-bold font-sans tabular-nums text-rose-600 dark:text-rose-400">
                                            {formatPrice(order.debt_amount)}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    {/if}
                </div>

                <!-- Barra Flotante Inferior de Cobro Mobile -->
                {#if unpaidOrders.length > 0}
                    <div class="fixed bottom-0 inset-x-0 p-3 bg-card/95 backdrop-blur-md border-t border-border shadow-lg z-40 flex items-center justify-between gap-3">
                        <div>
                            <span class="text-[11px] text-muted-foreground block">
                                Seleccionados: <strong>{selectedOrderIds.length}</strong>
                            </span>
                            <span class="text-xs font-bold text-foreground font-sans tabular-nums">
                                {formatPrice(selectedOrdersTotalDebt)}
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
                            <span>Cobrar ({formatPrice(selectedOrdersTotalDebt)})</span>
                        </Button>
                    </div>
                {/if}
            {/if}
        </div>
    {/if}

    <!-- Pestaña 3: Recibos Recientes -->
    {#if activeTab === 'receipts'}
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                    Últimos Recibos
                </span>
                <Link
                    href="/tenant/cobranzas/recibos"
                    class="text-xs font-semibold text-primary hover:underline flex items-center gap-1"
                >
                    <span>Historial completo</span>
                    <ArrowRight class="size-3" />
                </Link>
            </div>

            <div class="space-y-2">
                {#if recentReceipts.length === 0}
                    <Card class="p-6 text-center text-xs text-muted-foreground">
                        No hay recibos registrados recientemente.
                    </Card>
                {:else}
                    {#each recentReceipts as rc}
                        <div class="p-3 rounded-xl border bg-card border-border/80 space-y-1 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-foreground font-sans">{rc.receipt_number}</span>
                                {#if rc.is_cancelled}
                                    <span class="text-[9px] font-bold text-destructive bg-destructive/10 px-1.5 py-0.2 rounded line-through">
                                        ANULADO
                                    </span>
                                {:else}
                                    <span class="text-[9px] font-semibold text-emerald-600 bg-emerald-500/10 px-1.5 py-0.2 rounded uppercase">
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
    {/if}
</div>
