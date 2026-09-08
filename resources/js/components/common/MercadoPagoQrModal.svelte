<script lang="ts">
    import { onDestroy, untrack } from 'svelte';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogBody } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import { echo } from '@/lib/echo';
    import { page } from '@inertiajs/svelte';
    import QRCode from 'qrcode';
    import QrCodeIcon from 'lucide-svelte/icons/qr-code';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Clock from 'lucide-svelte/icons/clock';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import X from 'lucide-svelte/icons/x';
    import User from 'lucide-svelte/icons/user';

    export interface OrderData {
        id: number;
        order_number: string;
        customer_name?: string;
        total_amount: number | string;
        debt_amount?: number | string;
        paid_amount?: number | string;
        payment_status?: string;
        customer?: {
            id: number;
            name: string;
            current_balance?: number | string;
        };
    }

    interface Props {
        open?: boolean;
        order: OrderData | null;
        onSuccess?: (order?: any) => void;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        order = null,
        onSuccess = undefined,
        onclose = undefined,
    }: Props = $props();

    type ModalStep = 'amount_setup' | 'generating' | 'waiting_payment' | 'paid_success' | 'expired' | 'error';

    let currentStep = $state<ModalStep>('amount_setup');
    let amountToCharge = $state<number>(0);
    let qrDataUrl = $state<string | null>(null);
    let rawQrData = $state<string | null>(null);
    let mpOrderId = $state<string | null>(null);
    let errorMessage = $state<string | null>(null);
    let timeRemainingSeconds = $state<number>(900); // 15 minutos por defecto
    let paidReceipt = $state<{ receipt_number?: string; amount?: number; payment_method?: string } | null>(null);
    let lastOpenedOrderId = $state<number | null>(null);
    let isRequestInProgress = false;

    // Timers y suscripciones
    let countdownInterval: any = null;
    let pollInterval: any = null;
    let activeChannelName: string | null = null;

    let tenantId = $derived(
        Number(page.props.auth?.tenant?.id ?? page.props.auth?.user?.tenant_id ?? 1)
    );

    let orderDebt = $derived(
        order ? Number(order.debt_amount ?? order.total_amount) : 0
    );

    let formattedRemainingTime = $derived.by(() => {
        const mins = Math.floor(timeRemainingSeconds / 60);
        const secs = timeRemainingSeconds % 60;
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    });

    function formatCurrency(val: number | string): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(val) || 0);
    }

    // Inicialización idempotente y limpieza al cambiar 'open'
    $effect(() => {
        const isOpen = open;
        const currentOrderId = order?.id;

        if (isOpen && currentOrderId) {
            untrack(() => {
                if (lastOpenedOrderId !== currentOrderId) {
                    lastOpenedOrderId = currentOrderId;
                    resetModalState();
                    const initialDebt = Number(order?.debt_amount ?? 0);
                    amountToCharge = initialDebt > 0 ? initialDebt : Number(order?.total_amount || 0);
                    // Generar inmediatamente el QR si el monto es válido
                    if (amountToCharge > 0) {
                        generateQrOrder();
                    }
                }
            });
        } else if (!isOpen) {
            untrack(() => {
                lastOpenedOrderId = null;
                cleanupSubscriptions();
            });
        }
    });

    function resetModalState() {
        cleanupSubscriptions();
        currentStep = 'amount_setup';
        qrDataUrl = null;
        rawQrData = null;
        mpOrderId = null;
        errorMessage = null;
        paidReceipt = null;
        timeRemainingSeconds = 900;
    }

    function cleanupSubscriptions() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        if (activeChannelName) {
            try {
                echo.leave(activeChannelName);
            } catch (e) {
                // ignore
            }
            activeChannelName = null;
        }
    }

    onDestroy(() => {
        cleanupSubscriptions();
    });

    function handleClose() {
        cleanupSubscriptions();
        open = false;
        if (onclose) onclose();
    }

    async function generateQrOrder() {
        if (isRequestInProgress || !order || amountToCharge <= 0) {
            if (!order || amountToCharge <= 0) {
                errorMessage = 'El monto a cobrar debe ser mayor a cero.';
            }
            return;
        }

        isRequestInProgress = true;
        currentStep = 'generating';
        errorMessage = null;
        cleanupSubscriptions();

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch(`/tenant/pedidos/${order.id}/generar-qr-cobro`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    amount: amountToCharge,
                }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                currentStep = 'error';
                errorMessage = data.error || data.message || 'No fue posible generar el código QR de cobro.';
                return;
            }

            rawQrData = data.qr_data;
            mpOrderId = data.order_id;
            timeRemainingSeconds = 900;

            // Renderizar SVG/DataURL del QR con margen blanco y alta legibilidad
            qrDataUrl = await QRCode.toDataURL(data.qr_data, {
                width: 280,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#ffffff',
                },
                errorCorrectionLevel: 'M',
            });

            currentStep = 'waiting_payment';

            // Iniciar temporizador de expiración (15 minutos)
            countdownInterval = setInterval(() => {
                if (timeRemainingSeconds > 0) {
                    timeRemainingSeconds -= 1;
                } else {
                    currentStep = 'expired';
                    cleanupSubscriptions();
                }
            }, 1000);

            // Iniciar suscripción en tiempo real con Laravel Reverb
            startEchoRealtimeListener();

            // Iniciar polling de respaldo cada 3.5 segundos
            startBackupPolling();

        } catch (err: any) {
            currentStep = 'error';
            errorMessage = err.message || 'Error de comunicación al conectar con Mercado Pago.';
        } finally {
            isRequestInProgress = false;
        }
    }

    function startEchoRealtimeListener() {
        if (!order) return;
        const channelName = `tenant.${tenantId}.orders.${order.id}`;
        activeChannelName = channelName;

        echo.private(channelName)
            .listen('.CustomerOrderPaymentReceived', (event: any) => {
                handlePaymentSuccess({
                    receipt_number: event.receipt_number ?? 'REC-MP',
                    amount: event.amount ?? amountToCharge,
                    payment_method: 'Mercado Pago QR',
                });
            });
    }

    function startBackupPolling() {
        if (!order) return;
        pollInterval = setInterval(async () => {
            try {
                const res = await fetch(`/tenant/pedidos/${order.id}/estado-pago`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const statusData = await res.json();

                if (statusData.paid || (statusData.order && Number(statusData.order.debt_amount) <= 0)) {
                    handlePaymentSuccess({
                        receipt_number: 'REC-AUTO',
                        amount: amountToCharge,
                        payment_method: 'Mercado Pago QR',
                    });
                }
            } catch {
                // Silencioso en caso de error de red puntual
            }
        }, 3500);
    }

    function handlePaymentSuccess(receiptData: any) {
        cleanupSubscriptions();
        paidReceipt = receiptData;
        currentStep = 'paid_success';

        if (order) {
            order.debt_amount = Math.max(0, Number(order.debt_amount ?? 0) - amountToCharge);
            order.paid_amount = Number(order.paid_amount ?? 0) + amountToCharge;
            if (order.debt_amount <= 0) {
                order.payment_status = 'pagado';
            } else {
                order.payment_status = 'parcial';
            }
        }
    }

    function handleCompleteSuccess() {
        open = false;
        if (onSuccess) {
            onSuccess(order);
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="w-[95vw] max-w-md p-5 sm:p-6 rounded-2xl sm:rounded-3xl bg-card border-border shadow-2xl flex flex-col relative">
        <!-- Botón de Cerrar con token semántico acorde a dark y light mode -->
        <button
            type="button"
            onclick={handleClose}
            class="absolute top-4 right-4 sm:top-5 sm:right-6 size-9 sm:size-10 rounded-xl flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer z-20"
            aria-label="Cerrar modal"
        >
            <X class="size-4.5" />
        </button>

        <DialogHeader class="border-b pb-3 shrink-0 pr-12">
            <div class="flex items-center gap-2.5 min-w-0">
                <IconContainer variant="primary" size="md">
                    <QrCodeIcon class="size-5" />
                </IconContainer>
                <div class="min-w-0">
                    <DialogTitle class="text-base sm:text-lg font-bold text-foreground truncate">
                        Cobro QR Mercado Pago
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground truncate">
                        {order?.order_number ? `Pedido ${order.order_number}` : 'Cobro presencial en mostrador o entrega'}
                    </DialogDescription>
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="space-y-4 py-3 flex-1 overflow-y-auto">

            <!-- Paso: Configuración de Monto a Cobrar -->
            {#if currentStep === 'amount_setup' || currentStep === 'generating'}
                <div class="space-y-3">
                    {#if order?.customer_name}
                        <div class="p-3 rounded-xl bg-muted/40 border border-border/70 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <User class="size-4 text-muted-foreground" />
                                <span class="font-semibold text-foreground">{order.customer_name}</span>
                            </div>
                            <span class="text-[11px] text-muted-foreground">
                                Total Pedido: <strong class="text-foreground">{formatCurrency(order.total_amount)}</strong>
                            </span>
                        </div>
                    {/if}

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="qr-amount" class="text-xs font-semibold text-foreground">
                                Monto a Cobrar con QR
                            </Label>
                            {#if orderDebt > 0}
                                <button
                                    type="button"
                                    onclick={() => (amountToCharge = orderDebt)}
                                    class="text-[11px] font-semibold text-primary hover:underline cursor-pointer"
                                >
                                    Deuda ({formatCurrency(orderDebt)})
                                </button>
                            {/if}
                        </div>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-muted-foreground">$</span>
                            <Input
                                id="qr-amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                bind:value={amountToCharge}
                                placeholder="0.00"
                                class="h-11 rounded-xl bg-background border-border/80 pl-8 text-base font-bold font-sans tabular-nums text-foreground"
                                disabled={currentStep === 'generating'}
                            />
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Puedes modificar el importe si el cliente realiza un pago parcial o adelanto a cuenta corriente.
                        </p>
                    </div>

                    {#if currentStep === 'generating'}
                        <div class="py-6 flex flex-col items-center justify-center gap-3 text-center">
                            <div class="size-8 border-3 border-primary border-t-transparent rounded-full animate-spin"></div>
                            <span class="text-xs font-medium text-muted-foreground">
                                Conectando con Mercado Pago y generando QR...
                            </span>
                        </div>
                    {/if}
                </div>
            {/if}

            <!-- Paso: Código QR Activo y Esperando Pago -->
            {#if currentStep === 'waiting_payment'}
                <div class="flex flex-col items-center text-center space-y-3.5">
                    <div class="bg-white p-3 rounded-2xl shadow-md border border-border/60 inline-block">
                        {#if qrDataUrl}
                            <img
                                src={qrDataUrl}
                                alt="Código QR Mercado Pago"
                                class="size-60 sm:size-64 object-contain rounded-lg"
                            />
                        {/if}
                    </div>

                    <!-- Monto y Estado de Escaneo -->
                    <div class="space-y-1">
                        <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground block">
                            Importe a Escanear
                        </span>
                        <div class="text-2xl sm:text-3xl font-extrabold font-sans tabular-nums text-foreground">
                            {formatCurrency(amountToCharge)}
                        </div>
                    </div>

                    <!-- Indicador en vivo de WebSockets / Polling -->
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-medium">
                        <span class="relative flex size-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full size-2 bg-emerald-500"></span>
                        </span>
                        <span>Esperando escaneo y acreditación en tiempo real...</span>
                    </div>

                    <!-- Temporizador de Expiración -->
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-sans tabular-nums">
                        <Clock class="size-3.5" />
                        <span>Expira en <strong>{formattedRemainingTime}</strong></span>
                    </div>
                </div>
            {/if}

            <!-- Paso: Cobro Acreditado Exitosamente -->
            {#if currentStep === 'paid_success'}
                <div class="py-4 flex flex-col items-center text-center space-y-4">
                    <div class="size-16 rounded-2xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 flex items-center justify-center shadow-sm">
                        <CheckCircle2 class="size-10" />
                    </div>

                    <div class="space-y-1">
                        <h3 class="text-lg sm:text-xl font-extrabold text-foreground">
                            ¡Cobro Acreditado Exitosamente!
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            El pago fue procesado y asentado en el sistema automáticamente.
                        </p>
                    </div>

                    <div class="w-full p-3.5 rounded-2xl bg-muted/30 border border-border/80 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Monto recibido:</span>
                            <strong class="text-emerald-600 dark:text-emerald-400 font-sans tabular-nums text-sm">
                                {formatCurrency(paidReceipt?.amount ?? amountToCharge)}
                            </strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Medio de pago:</span>
                            <span class="font-medium text-foreground">Mercado Pago (QR Presencial)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Comprobante:</span>
                            <span class="font-mono text-foreground font-bold">{paidReceipt?.receipt_number ?? 'REC-MP'}</span>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Paso: QR Expirado -->
            {#if currentStep === 'expired'}
                <div class="py-6 flex flex-col items-center text-center space-y-3">
                    <div class="size-14 rounded-2xl bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 flex items-center justify-center">
                        <Clock class="size-8" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-base font-bold text-foreground">Código QR Expirado</h3>
                        <p class="text-xs text-muted-foreground max-w-xs">
                            El tiempo límite de 15 minutos para escanear el QR ha finalizado. Genera un nuevo código para continuar.
                        </p>
                    </div>
                </div>
            {/if}

            <!-- Paso: Error -->
            {#if currentStep === 'error'}
                <div class="py-6 flex flex-col items-center text-center space-y-3">
                    <div class="size-14 rounded-2xl bg-destructive/15 text-destructive border border-destructive/30 flex items-center justify-center">
                        <AlertCircle class="size-8" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-base font-bold text-foreground">Error al Generar Cobro</h3>
                        <p class="text-xs text-destructive max-w-xs">{errorMessage}</p>
                    </div>
                </div>
            {/if}
        </DialogBody>

        <DialogFooter class="border-t pt-3.5 flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5 shrink-0">
            {#if currentStep === 'amount_setup'}
                <Button
                    type="button"
                    variant="outline"
                    class="h-11 px-5 rounded-xl font-semibold w-full sm:w-auto min-h-[44px]"
                    onclick={handleClose}
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    variant="default"
                    class="h-11 px-6 rounded-xl font-bold w-full sm:w-auto min-h-[44px] gap-2 shadow-sm"
                    onclick={generateQrOrder}
                    disabled={amountToCharge <= 0}
                >
                    <QrCodeIcon class="size-4" />
                    <span>Generar QR ({formatCurrency(amountToCharge)})</span>
                </Button>
            {:else if currentStep === 'waiting_payment'}
                <Button
                    type="button"
                    variant="outline"
                    class="h-11 px-5 rounded-xl font-semibold w-full sm:w-auto min-h-[44px]"
                    onclick={handleClose}
                >
                    Cancelar Cobro
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    class="h-11 px-5 rounded-xl font-semibold w-full sm:w-auto min-h-[44px] gap-2"
                    onclick={generateQrOrder}
                >
                    <RefreshCw class="size-4" />
                    <span>Regenerar QR</span>
                </Button>
            {:else if currentStep === 'expired' || currentStep === 'error'}
                <Button
                    type="button"
                    variant="outline"
                    class="h-11 px-5 rounded-xl font-semibold w-full sm:w-auto min-h-[44px]"
                    onclick={handleClose}
                >
                    Cerrar
                </Button>
                <Button
                    type="button"
                    variant="default"
                    class="h-11 px-6 rounded-xl font-bold w-full sm:w-auto min-h-[44px] gap-2"
                    onclick={generateQrOrder}
                >
                    <RefreshCw class="size-4" />
                    <span>Reintentar</span>
                </Button>
            {:else if currentStep === 'paid_success'}
                <Button
                    type="button"
                    variant="default"
                    class="h-11 px-8 rounded-xl font-bold w-full sm:w-auto min-h-[44px] gap-2 shadow-sm bg-emerald-600 hover:bg-emerald-700 text-white"
                    onclick={handleCompleteSuccess}
                >
                    <CheckCircle2 class="size-4" />
                    <span>Aceptar y Finalizar</span>
                </Button>
            {/if}
        </DialogFooter>
    </DialogContent>
</Dialog>
