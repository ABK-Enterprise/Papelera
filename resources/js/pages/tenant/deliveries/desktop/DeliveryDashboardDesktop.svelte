<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { router } from '@inertiajs/svelte';
    import type { DeliveryOrder } from '../components/DeliverOrderModal.svelte';
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
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import Eye from 'lucide-svelte/icons/eye';
    import { hasPermission } from '@/lib/permissions';

    export interface ShiftSummary {
        cash_total: number;
        transfer_total: number;
        total_collected: number;
        receipts_count: number;
        delivered_count: number;
        pending_count: number;
    }

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
            {
                order_ids: selectedAvailableIds,
            },
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
            {
                order_ids: [orderId],
            },
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
        local_cerrado: 'Local o domicilio cerrado',
        rechazado: 'Rechazado por el cliente',
        daniado: 'Mercadería dañada',
        sin_dinero: 'Cliente sin fondos',
        otro: 'Otro motivo operacional',
    };
</script>

<div class="space-y-6">
    <!-- Widget Caja del Turno (Dashboard Superior del Chofer) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 rounded-2xl border border-border/80 bg-card shadow-xs">
        <div class="flex items-center gap-3.5 p-3 rounded-xl bg-background/60 border border-border/50">
            <div class="size-10 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <Banknote class="size-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Efectivo en Mano</p>
                <p class="text-lg font-bold tabular-nums text-foreground">
                    ${Number(shiftSummary.cash_total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3.5 p-3 rounded-xl bg-background/60 border border-border/50">
            <div class="size-10 rounded-xl bg-primary/15 text-primary flex items-center justify-center shrink-0">
                <QrCode class="size-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Transferencias / QR</p>
                <p class="text-lg font-bold tabular-nums text-foreground">
                    ${Number(shiftSummary.transfer_total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3.5 p-3 rounded-xl bg-background/60 border border-border/50">
            <div class="size-10 rounded-xl bg-accent/20 text-accent-foreground flex items-center justify-center shrink-0">
                <PackageCheck class="size-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Entregados Hoy</p>
                <p class="text-lg font-bold tabular-nums text-foreground">
                    {shiftSummary.delivered_count} <span class="text-xs font-normal text-muted-foreground">pedidos</span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3.5 p-3 rounded-xl bg-background/60 border border-border/50">
            <div class="size-10 rounded-xl bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                <PackageCheck class="size-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Pedidos Disponibles</p>
                <p class="text-lg font-bold tabular-nums text-foreground">
                    {availableOrders.length} <span class="text-xs font-normal text-muted-foreground">en bolsa</span>
                </p>
            </div>
        </div>
    </div>

    <!-- Pestañas de Navegación Operativa -->
    <div class="flex items-center gap-2 border-b border-border/80 pb-3">
        <button
            type="button"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-sm transition-all min-h-[44px] {activeTab === 'my_route' ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-background hover:bg-muted text-muted-foreground'}"
            onclick={() => (activeTab = 'my_route')}
        >
            <Truck class="size-4" />
            <span>Mis pedidos</span>
            <Badge variant="secondary" class="ml-1 px-1.5 py-0.5 text-xs font-sans tabular-nums">
                {myRouteOrders.length}
            </Badge>
        </button>

        <button
            type="button"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-sm transition-all min-h-[44px] {activeTab === 'available' ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-background hover:bg-muted text-muted-foreground'}"
            onclick={() => (activeTab = 'available')}
        >
            <PackageCheck class="size-4" />
            <span>Pedidos Disponibles</span>
            <Badge variant="secondary" class="ml-1 px-1.5 py-0.5 text-xs font-sans tabular-nums">
                {availableOrders.length}
            </Badge>
        </button>

        <button
            type="button"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-sm transition-all min-h-[44px] {activeTab === 'failed' ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-background hover:bg-muted text-muted-foreground'}"
            onclick={() => (activeTab = 'failed')}
        >
            <AlertTriangle class="size-4" />
            <span>Pedidos no entregados</span>
            <Badge variant="secondary" class="ml-1 px-1.5 py-0.5 text-xs font-sans tabular-nums">
                {failedOrders.length}
            </Badge>
        </button>
    </div>

    <!-- Contenido de las Pestañas -->
    {#if activeTab === 'my_route'}
        {#if myRouteOrders.length === 0}
            <div class="flex flex-col items-center justify-center p-12 text-center rounded-2xl border border-dashed border-border/80 bg-card/40">
                <Truck class="size-12 text-muted-foreground/40 mb-3" />
                <h3 class="text-base font-semibold text-foreground">No tienes pedidos en tu ruta actual</h3>
                <p class="text-xs text-muted-foreground max-w-md mt-1 mb-4">
                    Dirígete a la pestaña "Bolsa de Pedidos Disponibles" para asignarte los envíos que ya han sido preparados en depósito.
                </p>
                <Button
                    class="h-11 min-h-[44px] px-5 font-semibold gap-2"
                    onclick={() => (activeTab = 'available')}
                >
                    <PackageCheck class="size-4" />
                    <span>Ver Pedidos Disponibles</span>
                </Button>
            </div>
        {:else}
            <div class="grid grid-cols-1 gap-4">
                {#each myRouteOrders as order (order.id)}
                    <div class="p-5 rounded-2xl border border-border/80 bg-card shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-5 transition-all hover:border-primary/40">
                        <div class="space-y-2.5 flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="font-sans font-bold text-sm text-primary tabular-nums">#{order.order_number}</span>
                                <span class="text-base font-bold text-foreground">{order.customer_name}</span>

                                <!-- Badge Financiero de Alto Contraste -->
                                {#if Number(order.debt_amount) <= 0}
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                        PAGADO
                                    </span>
                                {:else}
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30 tabular-nums">
                                        A COBRAR: ${Number(order.debt_amount).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                                    </span>
                                {/if}

                                <Badge variant="outline" class="text-xs">
                                    Zona: {order.shipping_zone_name} {order.distance_km ? `(${order.distance_km} km)` : ''}
                                </Badge>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-muted-foreground">
                                {#if order.delivery_address}
                                    <div class="flex items-center gap-1.5">
                                        <MapPin class="size-3.5 text-primary shrink-0" />
                                        <span class="font-medium text-foreground">{order.delivery_address}</span>
                                        <a
                                            href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(order.delivery_address)}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 text-primary hover:underline ml-1 font-semibold"
                                        >
                                            <ExternalLink class="size-3" />
                                            <span>Abrir Maps</span>
                                        </a>
                                    </div>
                                {/if}

                                {#if order.customer_phone}
                                    <a
                                        href={`tel:${order.customer_phone}`}
                                        class="flex items-center gap-1.5 hover:text-foreground font-medium text-muted-foreground"
                                    >
                                        <Phone class="size-3.5 text-emerald-500 shrink-0" />
                                        <span>{order.customer_phone}</span>
                                    </a>
                                {/if}

                                <span class="tabular-nums">Total: ${Number(order.total_amount).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</span>
                                <span>{order.items_count} producto(s)</span>
                            </div>

                            {#if order.notes}
                                <div class="flex items-center gap-2 bg-background/50 p-2 rounded-xl border border-border/50 text-xs w-full md:max-w-[25%]">
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
                        </div>

                        <!-- Botones de Acción en Stack Vertical con Altura Simétrica min-h-[44px] -->
                        {#if hasPermission('repartos.gestionar')}
                            <div class="flex flex-col gap-2 w-full sm:w-48 shrink-0">
                                <Button
                                    class="w-full h-11 min-h-[44px] px-4 font-semibold bg-emerald-600 hover:bg-emerald-700 text-white gap-2 shadow-xs"
                                    onclick={() => onDeliverOrder(order)}
                                >
                                    <PackageCheck class="size-4.5" />
                                    <span>Entregado</span>
                                </Button>

                                <Button
                                    variant="outline"
                                    class="w-full h-11 min-h-[44px] px-4 font-semibold text-rose-600 dark:text-rose-400 border-rose-500/30 hover:bg-rose-500/10 gap-2"
                                    onclick={() => onFailDelivery(order)}
                                >
                                    <AlertTriangle class="size-4" />
                                    <span>No Entregado</span>
                                </Button>
                            </div>
                        {/if}
                    </div>
                {/each}
            </div>
        {/if}
    {:else if activeTab === 'available'}
        <!-- Barra de Asignación Múltiple -->
        {#if availableOrders.length > 0}
            <div class="flex items-center justify-between p-3.5 rounded-xl border border-border/80 bg-background/50">
                <button
                    type="button"
                    class="flex items-center gap-2 text-xs font-semibold text-foreground hover:text-primary transition-colors cursor-pointer"
                    onclick={toggleSelectAllAvailable}
                >
                    {#if selectedAvailableIds.length === availableOrders.length}
                        <CheckSquare class="size-4 text-primary" />
                        <span>Deseleccionar todos ({availableOrders.length})</span>
                    {:else}
                        <Square class="size-4 text-muted-foreground" />
                        <span>Seleccionar todos ({availableOrders.length})</span>
                    {/if}
                </button>

                {#if hasPermission('repartos.gestionar')}
                    <Button
                        class="h-11 min-h-[44px] px-5 font-semibold gap-2"
                        disabled={selectedAvailableIds.length === 0 || isAssigning}
                        onclick={handleAssignSelected}
                    >
                        <Truck class="size-4" />
                        <span>Tomar Seleccionados ({selectedAvailableIds.length})</span>
                    </Button>
                {/if}
            </div>
        {/if}

        {#if availableOrders.length === 0}
            <div class="flex flex-col items-center justify-center p-12 text-center rounded-2xl border border-dashed border-border/80 bg-card/40">
                <PackageCheck class="size-12 text-muted-foreground/40 mb-3" />
                <h3 class="text-base font-semibold text-foreground">No hay pedidos disponibles en depósito</h3>
                <p class="text-xs text-muted-foreground max-w-md mt-1">
                    Cuando el depósito finalice el armado y empaque de los pedidos, aparecerán automáticamente en esta bolsa de trabajo.
                </p>
            </div>
        {:else}
            <div class="grid grid-cols-1 gap-3.5">
                {#each availableOrders as order (order.id)}
                    <div class="p-4 rounded-2xl border border-border/80 bg-card shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <button
                                type="button"
                                class="mt-1 text-muted-foreground hover:text-primary cursor-pointer transition-colors"
                                onclick={() => toggleSelectAvailable(order.id)}
                            >
                                {#if selectedAvailableIds.includes(order.id)}
                                    <CheckSquare class="size-5 text-primary" />
                                {:else}
                                    <Square class="size-5 text-muted-foreground" />
                                {/if}
                            </button>

                            <div class="space-y-1.5 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-sans font-bold text-sm text-primary tabular-nums">#{order.order_number}</span>
                                    <span class="text-sm font-semibold text-foreground">{order.customer_name}</span>

                                    {#if Number(order.debt_amount) <= 0}
                                        <Badge variant="outline" class="text-xs font-bold text-emerald-600 border-emerald-500/30">
                                            PAGADO
                                        </Badge>
                                    {:else}
                                        <Badge variant="outline" class="text-xs font-bold text-rose-600 border-rose-500/30 tabular-nums">
                                            A COBRAR: ${Number(order.debt_amount).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                                        </Badge>
                                    {/if}

                                    <Badge variant="secondary" class="text-xs">
                                        {order.shipping_zone_name} {order.distance_km ? `(${order.distance_km} km)` : ''}
                                    </Badge>
                                </div>

                                {#if order.delivery_address}
                                    <p class="text-xs text-muted-foreground flex items-center gap-1.5">
                                        <MapPin class="size-3 text-muted-foreground shrink-0" />
                                        {order.delivery_address}
                                    </p>
                                {/if}

                                <div class="flex items-center gap-4 text-xs text-muted-foreground">
                                    <span class="tabular-nums">Total: ${Number(order.total_amount).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</span>
                                    <span>{order.items_count} producto(s)</span>
                                </div>
                            </div>
                        </div>

                        {#if hasPermission('repartos.gestionar')}
                            <Button
                                variant="secondary"
                                class="h-11 min-h-[44px] px-5 font-semibold gap-2 shrink-0"
                                disabled={isAssigning}
                                onclick={() => handleAssignSingle(order.id)}
                            >
                                <Truck class="size-4" />
                                <span>Tomar para Mi Ruta</span>
                            </Button>
                        {/if}
                    </div>
                {/each}
            </div>
        {/if}
    {:else if activeTab === 'failed'}
        {#if failedOrders.length === 0}
            <div class="flex flex-col items-center justify-center p-12 text-center rounded-2xl border border-dashed border-border/80 bg-card/40">
                <ShieldAlert class="size-12 text-muted-foreground/40 mb-3" />
                <h3 class="text-base font-semibold text-foreground">No hay pedidos no entregados o con incidentes</h3>
                <p class="text-xs text-muted-foreground mt-1 max-w-sm">
                    Todos los pedidos están en curso o se han entregado normalmente.
                </p>
            </div>
        {:else}
            <div class="grid grid-cols-1 gap-4">
                {#each failedOrders as order (order.id)}
                    <div class="p-5 rounded-2xl border border-rose-500/30 bg-card shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="space-y-2 flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-sans font-bold text-sm text-rose-600 dark:text-rose-400 tabular-nums">#{order.order_number}</span>
                                <span class="text-base font-bold text-foreground">{order.customer_name}</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-300">
                                    {order.delivery_failure_reason ? (failureReasonLabels[order.delivery_failure_reason] || order.delivery_failure_reason) : 'Visita fallida'}
                                </span>
                            </div>

                            {#if order.delivery_notes}
                                <div class="flex items-center gap-2 bg-background/50 p-2.5 rounded-xl border border-border/50 text-xs w-full md:max-w-[25%]">
                                    <p class="text-muted-foreground italic truncate flex-1 min-w-0">
                                        Observaciones: "{order.delivery_notes}"
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

                            <p class="text-xs text-muted-foreground flex items-center gap-2">
                                <span>Dirección: {order.delivery_address || 'Sin dirección registrada'}</span>
                                <span>•</span>
                                <span class="tabular-nums">Importe: ${Number(order.total_amount).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</span>
                            </p>
                        </div>

                        {#if hasPermission('repartos.gestionar')}
                            <Button
                                class="h-11 min-h-[44px] px-5 font-semibold gap-2 shrink-0 bg-primary"
                                onclick={() => onRescheduleOrder ? onRescheduleOrder(order) : handleReschedule(order.id)}
                            >
                                <RefreshCw class="size-4" />
                                <span>Reprogramar Pedido</span>
                            </Button>
                        {/if}
                    </div>
                {/each}
            </div>
        {/if}
    {/if}
</div>
