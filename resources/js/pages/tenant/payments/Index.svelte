<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import PaymentDashboardDesktop, { type CustomerSummary, type OrderSummary, type ReceiptSummary } from './desktop/PaymentDashboardDesktop.svelte';
    import PaymentDashboardMobile from './mobile/PaymentDashboardMobile.svelte';
    import UniversalPaymentModal from '@/components/common/UniversalPaymentModal.svelte';
    import { router } from '@inertiajs/svelte';
    import History from 'lucide-svelte/icons/history';
    import FileText from 'lucide-svelte/icons/file-text';

    interface Props {
        customers?: CustomerSummary[];
        selectedCustomer?: CustomerSummary | null;
        unpaidOrders?: OrderSummary[];
        recentReceipts?: ReceiptSummary[];
        filters?: {
            search?: string | null;
            customer_id?: number | null;
        };
    }

    let {
        customers = [],
        selectedCustomer = null,
        unpaidOrders = [],
        recentReceipts = [],
        filters = {},
    }: Props = $props();

    let searchTerm = $state(filters.search || '');
    let selectedOrderIds = $state<number[]>([]);
    let isPaymentModalOpen = $state(false);

    // Seleccionar todos los pedidos por defecto cuando se carga un nuevo cliente
    $effect(() => {
        if (unpaidOrders.length > 0) {
            selectedOrderIds = unpaidOrders.map((o) => o.id);
        } else {
            selectedOrderIds = [];
        }
    });

    function handleSearch(term: string) {
        searchTerm = term;
        router.get(
            '/tenant/cobranzas',
            {
                search: term || undefined,
                customer_id: selectedCustomer?.id || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }

    function handleSelectCustomer(customer: CustomerSummary | null) {
        if (!customer || customer.id === selectedCustomer?.id) {
            handleDeselectCustomer();
            return;
        }

        router.get(
            '/tenant/cobranzas',
            {
                customer_id: customer.id,
                search: searchTerm || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }

    function handleDeselectCustomer() {
        router.get(
            '/tenant/cobranzas',
            {
                search: searchTerm || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }

    function toggleOrderSelection(orderId: number) {
        if (selectedOrderIds.includes(orderId)) {
            selectedOrderIds = selectedOrderIds.filter((id) => id !== orderId);
        } else {
            selectedOrderIds = [...selectedOrderIds, orderId];
        }
    }

    function toggleSelectAllOrders() {
        if (selectedOrderIds.length === unpaidOrders.length) {
            selectedOrderIds = [];
        } else {
            selectedOrderIds = unpaidOrders.map((o) => o.id);
        }
    }

    let selectedOrdersForModal = $derived(
        unpaidOrders.filter((o) => selectedOrderIds.includes(o.id))
    );

    let suggestedAmountForModal = $derived(
        selectedOrdersForModal.reduce((sum, o) => sum + (Number(o.debt_amount) || 0), 0)
    );
</script>

<AppHead title="Panel de Cobranzas y Recibos" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1680px] mx-auto w-full font-sans">
    <!-- Header del Módulo con separación estándar mb-6 / gap-6 -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                Panel de Cobranzas
            </h1>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3 w-full sm:w-auto">
            <Button
                variant="secondary"
                class="h-11 px-4 rounded-xl text-sm font-medium gap-2 shadow-xs min-h-[44px] w-full sm:w-auto justify-center"
                onclick={() => router.get('/tenant/cobranzas/facturas')}
            >
                <FileText class="size-4" />
                <span>Historial de Facturas</span>
            </Button>

            <Button
                variant="secondary"
                class="h-11 px-4 rounded-xl text-sm font-medium gap-2 shadow-xs min-h-[44px] w-full sm:w-auto justify-center"
                onclick={() => router.get('/tenant/cobranzas/recibos')}
            >
                <History class="size-4" />
                <span>Historial de Recibos</span>
            </Button>
        </div>
    </div>

    <!-- Vista Desktop -->
    <div class="hidden md:block">
        <PaymentDashboardDesktop
            {customers}
            {selectedCustomer}
            {unpaidOrders}
            {recentReceipts}
            {searchTerm}
            {selectedOrderIds}
            onSearch={handleSearch}
            onSelectCustomer={handleSelectCustomer}
            onDeselectCustomer={handleDeselectCustomer}
            onToggleOrderSelection={toggleOrderSelection}
            onToggleSelectAllOrders={toggleSelectAllOrders}
            onOpenPaymentModal={() => (isPaymentModalOpen = true)}
        />
    </div>

    <!-- Vista Mobile -->
    <div class="block md:hidden">
        <PaymentDashboardMobile
            {customers}
            {selectedCustomer}
            {unpaidOrders}
            {recentReceipts}
            {searchTerm}
            {selectedOrderIds}
            onSearch={handleSearch}
            onSelectCustomer={handleSelectCustomer}
            onToggleOrderSelection={toggleOrderSelection}
            onToggleSelectAllOrders={toggleSelectAllOrders}
            onOpenPaymentModal={() => (isPaymentModalOpen = true)}
        />
    </div>
</div>

<!-- Modal Universal de Cobro -->
<UniversalPaymentModal
    bind:open={isPaymentModalOpen}
    customer={selectedCustomer}
    orders={selectedOrdersForModal}
    suggestedAmount={suggestedAmountForModal}
    onSuccess={() => {
        isPaymentModalOpen = false;
        if (selectedCustomer) {
            handleSelectCustomer(selectedCustomer);
        }
    }}
/>
