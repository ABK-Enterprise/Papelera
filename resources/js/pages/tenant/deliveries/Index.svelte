<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { echo } from '@/lib/echo';
    import DeliveryDashboardDesktop, { type ShiftSummary } from './desktop/DeliveryDashboardDesktop.svelte';
    import DeliveryDashboardMobile from './mobile/DeliveryDashboardMobile.svelte';
    import DeliverOrderModal, { type DeliveryOrder } from './components/DeliverOrderModal.svelte';
    import FailDeliveryModal from './components/FailDeliveryModal.svelte';
    import RescheduleOrderModal from './components/RescheduleOrderModal.svelte';
    import ViewOrderNotesModal from './components/ViewOrderNotesModal.svelte';
    import Truck from 'lucide-svelte/icons/truck';

    interface Props {
        availableOrders?: DeliveryOrder[];
        myRouteOrders?: DeliveryOrder[];
        failedOrders?: DeliveryOrder[];
        cancelledOrders?: DeliveryOrder[];
        shiftSummary?: ShiftSummary;
    }

    let {
        availableOrders = [],
        myRouteOrders = [],
        failedOrders = [],
        cancelledOrders = [],
        shiftSummary = {
            cash_total: 0,
            transfer_total: 0,
            total_collected: 0,
            receipts_count: 0,
            delivered_count: 0,
            pending_count: 0,
        },
    }: Props = $props();

    let selectedOrderForDeliver = $state<DeliveryOrder | null>(null);
    let isDeliverModalOpen = $state(false);

    let selectedOrderForFail = $state<DeliveryOrder | null>(null);
    let isFailModalOpen = $state(false);

    let selectedOrderForReschedule = $state<DeliveryOrder | null>(null);
    let isRescheduleModalOpen = $state(false);

    let selectedOrderForNotes = $state<DeliveryOrder | null>(null);
    let isNotesModalOpen = $state(false);

    let effectiveCancelledOrders = $derived(cancelledOrders.length > 0 ? cancelledOrders : failedOrders);

    function handleOpenDeliver(order: DeliveryOrder) {
        selectedOrderForDeliver = order;
        isDeliverModalOpen = true;
    }

    function handleOpenFail(order: DeliveryOrder) {
        selectedOrderForFail = order;
        isFailModalOpen = true;
    }

    function handleOpenReschedule(order: DeliveryOrder) {
        selectedOrderForReschedule = order;
        isRescheduleModalOpen = true;
    }

    function handleOpenNotes(order: DeliveryOrder) {
        selectedOrderForNotes = order;
        isNotesModalOpen = true;
    }

    let tenantId = $derived(
        Number(page.props.auth?.tenant?.id ?? page.props.auth?.user?.tenant_id ?? 1)
    );

    // Sincronización en tiempo real vía Laravel Reverb
    $effect(() => {
        const channel = `tenant.${tenantId}.deliveries`;
        echo.private(channel)
            .listen('.OrderTakenForDelivery', () => {
                router.reload({ only: ['availableOrders', 'myRouteOrders', 'shiftSummary'], preserveScroll: true, preserveState: true });
            })
            .listen('.OrderDelivered', () => {
                router.reload({ only: ['myRouteOrders', 'shiftSummary'], preserveScroll: true, preserveState: true });
            })
            .listen('.OrderDeliveryFailed', () => {
                router.reload({ only: ['myRouteOrders', 'failedOrders', 'cancelledOrders', 'shiftSummary'], preserveScroll: true, preserveState: true });
            })
            .listen('.OrderDeliveryRescheduled', () => {
                router.reload({ only: ['availableOrders', 'myRouteOrders', 'failedOrders', 'cancelledOrders', 'shiftSummary'], preserveScroll: true, preserveState: true });
            })
            .listen('.CustomerOrderPaymentReceived', () => {
                router.reload({ only: ['myRouteOrders', 'availableOrders', 'shiftSummary'], preserveScroll: true, preserveState: true });
            });

        return () => {
            echo.leave(channel);
        };
    });
</script>

<AppHead title="Repartos" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1440px] mx-auto w-full">
    <!-- Header del Módulo con separación estándar mb-6 / gap-6 -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <Truck class="size-7 text-primary" />
                Repartos
            </h1>
        </div>
    </div>

    <!-- Vista Desktop -->
    <div class="hidden md:block">
        <DeliveryDashboardDesktop
            {availableOrders}
            {myRouteOrders}
            failedOrders={effectiveCancelledOrders}
            cancelledOrders={effectiveCancelledOrders}
            {shiftSummary}
            onDeliverOrder={handleOpenDeliver}
            onFailDelivery={handleOpenFail}
            onRescheduleOrder={handleOpenReschedule}
            onViewOrderNotes={handleOpenNotes}
        />
    </div>

    <!-- Vista Mobile -->
    <div class="block md:hidden">
        <DeliveryDashboardMobile
            {availableOrders}
            {myRouteOrders}
            failedOrders={effectiveCancelledOrders}
            cancelledOrders={effectiveCancelledOrders}
            {shiftSummary}
            onDeliverOrder={handleOpenDeliver}
            onFailDelivery={handleOpenFail}
            onRescheduleOrder={handleOpenReschedule}
            onViewOrderNotes={handleOpenNotes}
        />
    </div>
</div>

<!-- Modales de Operatoria de Entrega -->
<DeliverOrderModal
    bind:open={isDeliverModalOpen}
    order={selectedOrderForDeliver}
    onSuccess={() => {
        isDeliverModalOpen = false;
        selectedOrderForDeliver = null;
    }}
/>

<FailDeliveryModal
    bind:open={isFailModalOpen}
    order={selectedOrderForFail}
    onSuccess={() => {
        isFailModalOpen = false;
        selectedOrderForFail = null;
    }}
/>

<RescheduleOrderModal
    bind:open={isRescheduleModalOpen}
    order={selectedOrderForReschedule}
    onSuccess={() => {
        isRescheduleModalOpen = false;
        selectedOrderForReschedule = null;
    }}
/>

<ViewOrderNotesModal
    bind:open={isNotesModalOpen}
    order={selectedOrderForNotes}
    onclose={() => {
        isNotesModalOpen = false;
        selectedOrderForNotes = null;
    }}
/>
