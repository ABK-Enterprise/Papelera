<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { router } from '@inertiajs/svelte';
    import type { DeliveryOrder } from '../components/DeliverOrderModal.svelte';
    import type { ShiftSummary } from '../desktop/DeliveryDashboardDesktop.svelte';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Phone from 'lucide-svelte/icons/phone';
    import ExternalLink from 'lucide-svelte/icons/external-link';
    import Truck from 'lucide-svelte/icons/truck';
    import PackageCheck from 'lucide-svelte/icons/package-check';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Banknote from 'lucide-svelte/icons/banknote';
    import QrCode from 'lucide-svelte/icons/qr-code';
    import CheckSquare from 'lucide-svelte/icons/check-square';
    import Square from 'lucide-svelte/icons/square';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import Eye from 'lucide-svelte/icons/eye';
    import { hasPermission } from '@/lib/permissions';

    interface Props {
        availableOrders?: DeliveryOrder[];
        myRouteOrders?: DeliveryOrder[];
        failedOrders?: DeliveryOrder[];
        cancelledOrders?: DeliveryOrder[];
        shiftSummary?: ShiftSummary;
        onDeliverOrder: (order: DeliveryOrder) => void;
        onFailDelivery: (order: DeliveryOrder) => void;
        onRescheduleOrder?: (order: DeliveryOrder) => void;
        onViewOrderNotes?: (order: DeliveryOrder) => void;
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
        onDeliverOrder,
        onFailDelivery,
        onRescheduleOrder,
        onViewOrderNotes,
    }: Props = $props();

    let activeTab = $state<'my_route' | 'available' | 'failed'>('my_route');
    let selectedAvailableIds = $state<number[]>([]);
    let isAssigning = $state(false);

    function toggleSelectAvailable(id: number) {
        if (selectedAvailableIds.includes(id)) {
            selectedAvailableIds = selectedAvailableIds.filter((i) => i !== id);
        } else {
            selectedAvailableIds = [...selectedAvailableIds, id];
        }
    }

    function toggleSelectAllAvailable() {
        if (selectedAvailableIds.length === availableOrders.length) {
            selectedAvailableIds = [];
        } else {
            selectedAvailableIds = availableOrders.map((o) => o.id);
        }
    }

    function handleAssignSelected() {
        if (selectedAvailableIds.length === 0) return;
        isAssigning = true;
        router.post(
            '/tenant/repartos/asignar',
            { order_ids: selectedAvailableIds },
            {
                onFinish: () => {
                    isAssigning = false;
                    selectedAvailableIds = [];
                    activeTab = 'my_route';
                },
            }
        );
    }

    function handleAssignSingle(orderId: number) {
        isAssigning = true;
        router.post(
            '/tenant/repartos/asignar',
            { order_ids: [orderId] },
            {
                onFinish: () => {
                    isAssigning = false;
                    activeTab = 'my_route';
                },
            }
        );
    }

    function handleReschedule(orderId: number) {
        router.post(`/tenant/repartos/${orderId}/reprogramar`, {}, {
            preserveScroll: true,
        });
    }

    const failureReasonLabels: Record<string, string> = {
        local_cerrado: 'Local cerrado / Nadie atiende',
        rechazado: 'Rechazado por cliente',
        daniado: 'Mercadería dañada',
        sin_dinero: 'Cliente sin dinero',
        otro: 'Otro motivo',
    };
</script>

<div class="space-y-4">
    <!-- Widget Caja del Turno Mobile (Resumen Horizontal Escaneable) -->
    <div class="p-3.5 rounded-2xl border border-border/80 bg-card shadow-xs space-y-2.5">
        <div class="flex items-center justify-between text-xs font-semibold text-muted-foreground uppercase tracking-wider">
            <span>Caja del Turno</span>
            <span class="text-foreground font-bold tabular-nums">
                Total: ${Number(shiftSummary.total_collected).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div class="p-2.5 rounded-xl bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 flex items-center gap-2">
                <Banknote class="size-4 text-emerald-500 shrink-0" />
                <div class="min-w-0">
                    <p class="text-xs text-muted-foreground">Efectivo</p>
                    <p class="text-xs font-bold tabular-nums text-foreground truncate">
                        ${Number(shiftSummary.cash_total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                    </p>
                </div>
            </div>

            <div class="p-2.5 rounded-xl bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 flex items-center gap-2">
                <QrCode class="size-4 text-primary shrink-0" />
                <div class="min-w-0">
                    <p class="text-xs text-muted-foreground">Transfer / QR</p>
                    <p class="text-xs font-bold tabular-nums text-foreground truncate">
                        ${Number(shiftSummary.transfer_total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between pt-1 border-t border-border/40 text-xs text-muted-foreground">
            <span>Entregados: <strong class="text-foreground font-bold">{shiftSummary.delivered_count}</strong></span>
            <span>Disponibles: <strong class="text-foreground font-bold">{availableOrders.length}</strong></span>
        </div>
    </div>

    <!-- Segmented Tabs Mobile -->
    <div class="grid grid-cols-3 gap-1.5 p-1 bg-muted/80 dark:bg-card border border-border/80 rounded-xl">
        <button
            type="button"
            class="py-2.5 px-2 rounded-lg text-xs font-bold transition-all min-h-[44px] flex flex-col items-center justify-center gap-0.5 {activeTab === 'my_route' ? 'bg-card dark:bg-secondary/70 border border-border/80 text-foreground shadow-xs' : 'border border-transparent text-muted-foreground hover:text-foreground'}"
            onclick={() => (activeTab = 'my_route')}
        >
            <span>Mis pedidos</span>
            <span class="text-xs font-sans tabular-nums opacity-80">({myRouteOrders.length})</span>
        </button>

        <button
            type="button"
            class="py-2.5 px-2 rounded-lg text-xs font-bold transition-all min-h-[44px] flex flex-col items-center justify-center gap-0.5 {activeTab === 'available' ? 'bg-card dark:bg-secondary/70 border border-border/80 text-foreground shadow-xs' : 'border border-transparent text-muted-foreground hover:text-foreground'}"
            onclick={() => (activeTab = 'available')}
        >
            <span>Disponibles</span>
            <span class="text-xs font-sans tabular-nums opacity-80">({availableOrders.length})</span>
        </button>

        <button
            type="button"
            class="py-2.5 px-2 rounded-lg text-xs font-bold transition-all min-h-[44px] flex flex-col items-center justify-center gap-0.5 {activeTab === 'failed' ? 'bg-card dark:bg-secondary/70 border border-rose-500/35 text-rose-700 dark:text-rose-400 shadow-xs' : 'border border-transparent text-muted-foreground hover:text-foreground'}"
            onclick={() => (activeTab = 'failed')}
        >
            <span>No entregados</span>
            <span class="text-xs font-sans tabular-nums opacity-80">({failedOrders.length})</span>
        </button>
    </div>

    <!-- Pestaña Mis Pedidos Mobile -->
    {#if activeTab === 'my_route'}
        {#if myRouteOrders.length === 0}
            <div class="flex flex-col items-center justify-center p-8 text-center rounded-2xl border border-dashed border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35">
                <Truck class="size-10 text-muted-foreground/40 mb-2" />
                <h3 class="text-sm font-semibold text-foreground">Sin pedidos en tu ruta actual</h3>
                <p class="text-xs text-muted-foreground mt-1 mb-3">
                    Toca la pestaña "Disponibles" para tomar pedidos en depósito.
                </p>
                <Button
                    class="h-11 min-h-[44px] px-4 font-semibold text-xs gap-1.5"
                    onclick={() => (activeTab = 'available')}
                >
                    <PackageCheck class="size-4" />
                    <span>Ver Pedidos Disponibles</span>
                </Button>
            </div>
        {:else}
            <div class="space-y-3">
                {#each myRouteOrders as order (order.id)}
                    <div class="p-4 rounded-2xl border border-border/80 bg-card shadow-xs space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs font-sans font-bold text-primary tabular-nums">#{order.order_number}</p>
                                <h4 class="text-sm font-bold text-foreground truncate">{order.customer_name}</h4>
                            </div>

                            {#if Number(order.debt_amount) <= 0}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 shrink-0">
                                    PAGADO
                                </span>
                            {:else}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-bold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30 tabular-nums shrink-0">
                                    A COBRAR: ${Number(order.debt_amount).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                                </span>
                            {/if}
                        </div>

                        <!-- Dirección y Contacto Clickeables -->
                        {#if order.delivery_address}
                            <div class="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90">
                                <div class="flex items-start gap-1.5 min-w-0">
                                    <MapPin class="size-3.5 text-primary shrink-0 mt-0.5" />
                                    <p class="text-xs text-foreground line-clamp-2">{order.delivery_address}</p>
                                </div>
                                <a
                                    href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(order.delivery_address)}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="h-9 px-2.5 rounded-lg bg-primary/10 hover:bg-primary/20 text-primary text-xs font-bold inline-flex items-center gap-1 shrink-0 min-h-[36px]"
                                >
                                    <ExternalLink class="size-3" />
                                    <span>Maps</span>
                                </a>
                            </div>
                        {/if}

                        <div class="flex items-center justify-between text-xs text-muted-foreground pt-1">
                            <span>Zona: {order.shipping_zone_name}</span>
                            {#if order.customer_phone}
                                <a
                                    href={`tel:${order.customer_phone}`}
                                    class="h-8 px-2.5 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold inline-flex items-center gap-1"
                                >
                                    <Phone class="size-3" />
                                    <span>Llamar</span>
                                </a>
                            {/if}
                        </div>
                        {#if order.notes}
                            <div class="flex items-center gap-2 bg-muted/40 dark:bg-secondary/35 p-2 rounded-xl border border-border/80 dark:border-border/90 text-xs">
                                <p class="text-muted-foreground italic truncate flex-1 min-w-0">
                                    Nota: {order.notes}
                                </p>
                                {#if onViewOrderNotes}
                                    <button
                                        type="button"
                                        class="size-7 rounded-lg flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer shrink-0"
                                        title="Ver notas completas"
                                        onclick={() => onViewOrderNotes(order)}
                                    >
                                        <Eye class="size-3.5" />
                                    </button>
                                {/if}
                            </div>
                        {/if}

                        <!-- Botones de Acción Mobile con min-h-[44px] -->
                        {#if hasPermission('repartos.gestionar')}
                            <div class="grid grid-cols-2 gap-2 pt-1 border-t border-border/50">
                                <Button
                                    variant="destructive"
                                    class="w-full h-11 min-h-[44px] text-xs font-semibold gap-1.5"
                                    onclick={() => onFailDelivery(order)}
                                >
                                    <AlertTriangle class="size-3.5" />
                                    <span>No Entregado</span>
                                </Button>

                                <Button
                                    variant="success"
                                    class="w-full h-11 min-h-[44px] text-xs font-semibold gap-1.5 shadow-xs"
                                    onclick={() => onDeliverOrder(order)}
                                >
                                    <PackageCheck class="size-4" />
                                    <span>Entregado</span>
                                </Button>
                            </div>
                        {/if}
                    </div>
                {/each}
            </div>
        {/if}
    {:else if activeTab === 'available'}
        {#if availableOrders.length > 0 && hasPermission('repartos.gestionar')}
            <div class="flex items-center justify-between p-3 rounded-xl border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35">
                <button
                    type="button"
                    class="flex items-center gap-2 text-xs font-semibold text-foreground cursor-pointer min-h-[44px]"
                    onclick={toggleSelectAllAvailable}
                >
                    {#if selectedAvailableIds.length === availableOrders.length}
                        <CheckSquare class="size-4 text-primary" />
                        <span>Deseleccionar</span>
                    {:else}
                        <Square class="size-4 text-muted-foreground" />
                        <span>Todos ({availableOrders.length})</span>
                    {/if}
                </button>

                <Button
                    class="h-11 min-h-[44px] px-3 font-semibold text-xs gap-1.5"
                    disabled={selectedAvailableIds.length === 0 || isAssigning}
                    onclick={handleAssignSelected}
                >
                    <Truck class="size-3.5" />
                    <span>Tomar ({selectedAvailableIds.length})</span>
                </Button>
            </div>
        {/if}

        {#if availableOrders.length === 0}
            <div class="flex flex-col items-center justify-center p-8 text-center rounded-2xl border border-dashed border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35">
                <PackageCheck class="size-10 text-muted-foreground/40 mb-2" />
                <h3 class="text-sm font-semibold text-foreground">No hay pedidos disponibles</h3>
                <p class="text-xs text-muted-foreground mt-1">
                    Los pedidos preparados por el depósito aparecerán aquí automáticamente.
                </p>
            </div>
        {:else}
            <div class="space-y-3">
                {#each availableOrders as order (order.id)}
                    <div class="p-3.5 rounded-2xl border border-border/80 bg-card shadow-xs space-y-2.5">
                        <div class="flex items-start gap-2.5">
                            <button
                                type="button"
                                class="mt-0.5 text-muted-foreground hover:text-primary cursor-pointer p-1 min-h-[44px] min-w-[44px] flex items-center justify-center shrink-0 -ml-1"
                                onclick={() => toggleSelectAvailable(order.id)}
                            >
                                {#if selectedAvailableIds.includes(order.id)}
                                    <CheckSquare class="size-5 text-primary" />
                                {:else}
                                    <Square class="size-5 text-muted-foreground" />
                                {/if}
                            </button>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-xs font-sans font-bold text-primary tabular-nums">#{order.order_number}</span>
                                    {#if Number(order.debt_amount) <= 0}
                                        <Badge variant="outline" class="text-xs font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30">PAGADO</Badge>
                                    {:else}
                                        <Badge variant="outline" class="text-xs font-bold bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/30 tabular-nums">
                                            ${Number(order.debt_amount).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                                        </Badge>
                                    {/if}
                                </div>
                                <h4 class="text-sm font-bold text-foreground truncate">{order.customer_name}</h4>
                                {#if order.delivery_address}
                                    <p class="text-xs text-muted-foreground line-clamp-1">{order.delivery_address}</p>
                                {/if}
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-xs text-muted-foreground pt-1 border-t border-border/40">
                            <span>Zona: {order.shipping_zone_name}</span>
                            {#if hasPermission('repartos.gestionar')}
                                <Button
                                    variant="secondary"
                                    class="h-9 min-h-[44px] px-3 text-xs font-semibold gap-1.5"
                                    disabled={isAssigning}
                                    onclick={() => handleAssignSingle(order.id)}
                                >
                                    <Truck class="size-3.5" />
                                    <span>Tomar</span>
                                </Button>
                            {/if}
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    {:else if activeTab === 'failed'}
        {#if failedOrders.length === 0}
            <div class="flex flex-col items-center justify-center p-8 text-center rounded-2xl border border-dashed border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35">
                <AlertTriangle class="size-10 text-muted-foreground/40 mb-2" />
                <h3 class="text-sm font-semibold text-foreground">No hay pedidos no entregados</h3>
                <p class="text-xs text-muted-foreground mt-1">
                    Todos los repartos están en curso o se han entregado normalmente.
                </p>
            </div>
        {:else}
            <div class="space-y-3">
                {#each failedOrders as order (order.id)}
                    <div class="p-4 rounded-2xl border border-rose-500/30 bg-card shadow-xs space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs font-sans font-bold text-rose-600 dark:text-rose-400 tabular-nums">#{order.order_number}</p>
                                <h4 class="text-sm font-bold text-foreground truncate">{order.customer_name}</h4>
                            </div>

                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-bold bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30 shrink-0">
                                {order.delivery_failure_reason ? (failureReasonLabels[order.delivery_failure_reason] || order.delivery_failure_reason) : 'No entregado'}
                            </span>
                        </div>

                        <!-- Dirección y Contacto Clickeables -->
                        {#if order.delivery_address}
                            <div class="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90">
                                <div class="flex items-start gap-1.5 min-w-0">
                                    <MapPin class="size-3.5 text-primary shrink-0 mt-0.5" />
                                    <p class="text-xs text-foreground line-clamp-2">{order.delivery_address}</p>
                                </div>
                                <a
                                    href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(order.delivery_address)}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="h-9 px-2.5 rounded-lg bg-primary/10 hover:bg-primary/20 text-primary text-xs font-bold inline-flex items-center gap-1 shrink-0 min-h-[36px]"
                                >
                                    <ExternalLink class="size-3" />
                                    <span>Maps</span>
                                </a>
                            </div>
                        {/if}

                        <div class="flex items-center justify-between text-xs text-muted-foreground pt-1">
                            <span>Zona: {order.shipping_zone_name}</span>
                            {#if order.customer_phone}
                                <a
                                    href={`tel:${order.customer_phone}`}
                                    class="h-8 px-2.5 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold inline-flex items-center gap-1"
                                >
                                    <Phone class="size-3" />
                                    <span>Llamar</span>
                                </a>
                            {/if}
                        </div>

                        <div class="flex items-center justify-between text-xs pt-1 border-t border-border/40">
                            <span class="text-muted-foreground tabular-nums">Total: ${Number(order.total_amount).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</span>
                            {#if Number(order.debt_amount) <= 0}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                    PAGADO
                                </span>
                            {:else}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-bold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30 tabular-nums">
                                    Deuda: ${Number(order.debt_amount).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                                </span>
                            {/if}
                        </div>

                        {#if order.delivery_notes}
                            <div class="flex items-center gap-2 bg-muted/40 dark:bg-secondary/35 p-2 rounded-xl border border-border/80 dark:border-border/90 text-xs">
                                <p class="text-muted-foreground italic truncate flex-1 min-w-0">
                                    Nota: "{order.delivery_notes}"
                                </p>
                                {#if onViewOrderNotes}
                                    <button
                                        type="button"
                                        class="size-7 rounded-lg flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer shrink-0"
                                        title="Ver observaciones completas"
                                        onclick={() => onViewOrderNotes(order)}
                                    >
                                        <Eye class="size-3.5" />
                                    </button>
                                {/if}
                            </div>
                        {/if}

                        {#if hasPermission('repartos.gestionar')}
                            <div class="pt-1">
                                <Button
                                    variant="default"
                                    class="w-full h-11 min-h-[44px] text-xs font-semibold gap-2"
                                    onclick={() => onRescheduleOrder ? onRescheduleOrder(order) : handleReschedule(order.id)}
                                >
                                    <RefreshCw class="size-4" />
                                    <span>Reprogramar Pedido</span>
                                </Button>
                            </div>
                        {/if}
                    </div>
                {/each}
            </div>
        {/if}
    {/if}
</div>
