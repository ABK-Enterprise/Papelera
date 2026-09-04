<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import DeliveryDashboardDesktop, { type ShiftSummary } from './desktop/DeliveryDashboardDesktop.svelte';
    import DeliveryDashboardMobile from './mobile/DeliveryDashboardMobile.svelte';
    import DeliverOrderModal, { type DeliveryOrder } from './components/DeliverOrderModal.svelte';
    import FailDeliveryModal from './components/FailDeliveryModal.svelte';
    import Truck from 'lucide-svelte/icons/truck';

    interface Props {
        availableOrders?: DeliveryOrder[];
        myRouteOrders?: DeliveryOrder[];
        failedOrders?: DeliveryOrder[];
        shiftSummary?: ShiftSummary;
    }

    let {
        availableOrders = [],
        myRouteOrders = [],
        failedOrders = [],
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

    function handleOpenDeliver(order: DeliveryOrder) {
        selectedOrderForDeliver = order;
        isDeliverModalOpen = true;
    }

    function handleOpenFail(order: DeliveryOrder) {
        selectedOrderForFail = order;
        isFailModalOpen = true;
    }
</script>

<AppHead title="Repartos y Última Milla" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1440px] mx-auto w-full">
    <!-- Header del Módulo con separación estándar mb-6 / gap-6 -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <Truck class="size-7 text-primary" />
                Repartos y Última Milla
            </h1>
            <p class="text-xs sm:text-sm text-muted-foreground mt-1">
                Bolsa de trabajo para choferes, gestión de ruta en tiempo real, cobros contra entrega y rendición de caja.
            </p>
        </div>
    </div>

    <!-- Vista Desktop -->
    <div class="hidden md:block">
        <DeliveryDashboardDesktop
            {availableOrders}
            {myRouteOrders}
            {failedOrders}
            {shiftSummary}
            onDeliverOrder={handleOpenDeliver}
            onFailDelivery={handleOpenFail}
        />
    </div>

    <!-- Vista Mobile -->
    <div class="block md:hidden">
        <DeliveryDashboardMobile
            {availableOrders}
            {myRouteOrders}
            {failedOrders}
            {shiftSummary}
            onDeliverOrder={handleOpenDeliver}
            onFailDelivery={handleOpenFail}
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
