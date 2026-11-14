<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { router, page } from '@inertiajs/svelte';
    import { echo } from '@/lib/echo';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import X from 'lucide-svelte/icons/x';
    import AccountLedgerDesktop, { type CustomerAccountFull } from './desktop/AccountLedgerDesktop.svelte';
    import EditCreditSettingsModal from './EditCreditSettingsModal.svelte';
    import AccountLedgerMobile from './mobile/AccountLedgerMobile.svelte';
    import RecordAdjustmentModal from './RecordAdjustmentModal.svelte';
    import RecordPaymentModal from './RecordPaymentModal.svelte';
    import { ModuleHeader } from '@/components/common';
    import type { PaginationMeta } from '@/components/common/AppPagination.svelte';

    let {
        customers = [],
        selectedCustomer = null,
        movementsPagination = null,
        filters = {},
    }: {
        customers: (PaginationMeta & { data: CustomerAccountFull[] }) | CustomerAccountFull[];
        selectedCustomer: CustomerAccountFull | null;
        movementsPagination?: PaginationMeta | null;
        filters?: Record<string, any>;
    } = $props();

    let customerItems = $derived(Array.isArray(customers) ? customers : (customers?.data ?? []));
    let customerPagination = $derived(Array.isArray(customers) ? null : customers);

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

    let tenantId = $derived(
        Number(page.props.auth?.tenant?.id ?? page.props.auth?.user?.tenant_id ?? 1)
    );

    // Sincronización en tiempo real vía Laravel Reverb
    $effect(() => {
        const channelName = `tenant.${tenantId}.customer-accounts`;
        echo.private(channelName)
            .listen('.CustomerAccountMovementRecorded', () => {
                router.reload({ preserveScroll: true, preserveState: true });
            });

        return () => {
            echo.leave(channelName);
        };
    });

    function handleDeselectCustomer() {
        router.get(
            '/tenant/clientes/cuentas-corrientes',
            {
                search: filters?.search || undefined,
                defaulters_only: filters?.defaulters_only || undefined,
                tax_condition: filters?.tax_condition || undefined,
                status: filters?.status || undefined,
                sort_by: filters?.sort_by || undefined,
            },
            { preserveState: true, preserveScroll: true }
        );
    }
</script>

<AppHead title="Cuentas Corrientes - Clientes" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Header Operativo Limpio Escalonado -->
    <ModuleHeader title="Cuentas Corrientes">
        {#snippet actions()}
            {#if selectedCustomer}
                <Button
                    variant="secondary"
                    onclick={handleDeselectCustomer}
                    class="min-h-[38px] px-3.5 gap-2 text-sm font-medium rounded-xl shadow-2xs transition-all"
                >
                    <X class="size-3.5" />
                    <span>Quitar selección</span>
                </Button>
            {/if}

            <a href="/tenant/clientes" class="inline-flex">
                <Button variant="secondary" class="min-h-[38px] px-3.5 gap-2 text-sm font-medium rounded-xl shadow-2xs transition-all">
                    <ArrowLeft class="size-3.5" />
                    <span>Clientes</span>
                </Button>
            </a>
        {/snippet}
    </ModuleHeader>


    <!-- Vista Responsiva Operativa: Desktop Split / Mobile Feed -->
    <div class="hidden lg:block">
        <AccountLedgerDesktop
            customers={customerItems}
            pagination={customerPagination}
            {selectedCustomer}
            {movementsPagination}
            {filters}
            onOpenPayment={openPaymentModal}
            onOpenAdjustment={openAdjustmentModal}
            onOpenCreditSettings={openCreditSettingsModal}
        />
    </div>

    <div class="block lg:hidden">
        <AccountLedgerMobile
            customers={customerItems}
            pagination={customerPagination}
            {filters}
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
