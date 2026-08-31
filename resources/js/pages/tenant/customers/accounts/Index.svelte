<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import AccountLedgerDesktop, { type CustomerAccountFull } from './desktop/AccountLedgerDesktop.svelte';
    import EditCreditSettingsModal from './EditCreditSettingsModal.svelte';
    import AccountLedgerMobile from './mobile/AccountLedgerMobile.svelte';
    import RecordAdjustmentModal from './RecordAdjustmentModal.svelte';
    import RecordPaymentModal from './RecordPaymentModal.svelte';

    let {
        customers = [],
        selectedCustomer = null,
    }: {
        customers: CustomerAccountFull[];
        selectedCustomer: CustomerAccountFull | null;
    } = $props();

    // Modals state
    let isPaymentModalOpen = $state(false);
    let isAdjustmentModalOpen = $state(false);
    let isCreditSettingsModalOpen = $state(false);
    let activeCustomerForModal = $state<CustomerAccountFull | null>(null);

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
</script>

<AppHead title="Cuentas Corrientes - Clientes" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Header Operativo Limpio (Sin Bento KPI cards decorativas) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-border/50">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight leading-tight text-foreground">
                Cuentas Corrientes
            </h1>
        </div>

        <div class="flex items-center justify-end gap-2.5 w-full sm:w-auto">
            <a href="/tenant/customers" class="inline-flex">
                <Button variant="secondary" class="min-h-[40px] px-3.5 gap-2 text-xs font-semibold rounded-xl shadow-2xs hover:bg-secondary/90 border border-border/30 transition-all">
                    <ArrowLeft class="size-3.5" />
                    <span>Directorio de Clientes</span>
                </Button>
            </a>
        </div>
    </div>


    <!-- Vista Responsiva Operativa: Desktop Split / Mobile Feed -->
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
