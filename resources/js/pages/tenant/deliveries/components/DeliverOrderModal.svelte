<script lang="ts">
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogBody } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';
    import { Switch } from '@/components/ui/switch';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import MercadoPagoQrModal from '@/components/common/MercadoPagoQrModal.svelte';
    import { router } from '@inertiajs/svelte';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Banknote from 'lucide-svelte/icons/banknote';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import Coins from 'lucide-svelte/icons/coins';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import QrCode from 'lucide-svelte/icons/qr-code';
    import ArrowRightLeft from 'lucide-svelte/icons/arrow-right-left';

    export interface DeliveryOrder {
        id: number;
        order_number: string;
        customer_id: number | null;
        customer_name: string;
        customer_phone: string | null;
        customer_has_credit: boolean;
        customer_credit_limit: number;
        customer_current_balance: number;
        delivery_address: string | null;
        shipping_zone_name: string;
        shipping_cost: number;
        distance_km: number | null;
        status: string;
        payment_status: string;
        debt_amount: number;
        total_amount: number;
        notes?: string | null;
        delivery_failure_reason?: string | null;
        delivery_notes?: string | null;
        items_count?: number;
    }

    interface Props {
        open?: boolean;
        order?: DeliveryOrder | null;
        onSuccess?: () => void;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        order = null,
        onSuccess,
        onclose,
    }: Props = $props();

    let paymentMode = $state<'paid_now' | 'on_credit'>('paid_now');
    let paymentMethod = $state<string>('efectivo');
    let mpMode = $state<'qr' | 'transfer'>('qr');
    let amountToPay = $state<number>(0);
    let referenceNumber = $state<string>('');
    let deliveryNotes = $state<string>('');
    let collectExtraBalance = $state<boolean>(false);
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    // Modal de confirmación de saldo a favor para cuenta corriente
    let isOverpaymentConfirmOpen = $state(false);
    let isQrModalOpen = $state(false);

    let debt = $derived(Number(order?.debt_amount) || 0);
    let hasAccount = $derived(Boolean(order?.customer_id));
    let isOverpaid = $derived(amountToPay > debt);
    let overpaidAmount = $derived(Math.max(0, Math.round((amountToPay - debt) * 100) / 100));

    const MAX_FAVORABLE_BALANCE = 10_000_000;
    let customerCurrentBalance = $derived(Number(order?.customer_current_balance) || 0);
    let existingFavorableBalance = $derived(customerCurrentBalance < 0 ? Math.abs(customerCurrentBalance) : 0);
    let totalResultingFavorableBalance = $derived.by(() => {
        if (!hasAccount) return 0;
        if (debt > 0 && isOverpaid) {
            return existingFavorableBalance + overpaidAmount;
        }
        if (debt <= 0 && collectExtraBalance) {
            return existingFavorableBalance + amountToPay;
        }
        return existingFavorableBalance;
    });
    let isFavorableBalanceExceeded = $derived(totalResultingFavorableBalance > MAX_FAVORABLE_BALANCE);

    function handleAmountInput(e: Event) {
        const target = e.target as HTMLInputElement;
        if (target.value.length > 9) {
            target.value = target.value.slice(0, 9);
        }
        amountToPay = Number(target.value) || 0;
    }

    $effect(() => {
        if (open && order) {
            const currentDebt = Number(order.debt_amount) || 0;
            amountToPay = currentDebt > 0 ? currentDebt : 0;
            referenceNumber = '';
            deliveryNotes = '';
            errorMessage = null;
            isOverpaymentConfirmOpen = false;
            collectExtraBalance = false;
            paymentMode = (order.customer_has_credit || Boolean(order.customer_id)) ? 'on_credit' : 'paid_now';
            paymentMethod = 'efectivo';
            mpMode = 'qr';
        }
    });

    function formatCurrency(val: number): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(val);
    }

    function handleClose() {
        open = false;
        if (onclose) onclose();
    }

    function triggerSubmit() {
        if (!order) return;
        errorMessage = null;

        if (debt > 0) {
            if (paymentMode === 'paid_now') {
                if (!paymentMethod) {
                    errorMessage = 'Selecciona un método de cobro.';
                    return;
                }
                if (amountToPay <= 0) {
                    errorMessage = 'El monto cobrado debe ser mayor a cero.';
                    return;
                }

                // Bifurcación FSM para Mercado Pago
                if (paymentMethod === 'mercadopago') {
                    if (mpMode === 'qr') {
                        errorMessage = 'Para cobrar con QR en el momento, presiona "Generar y Mostrar QR". Si el cliente ya transfirió por alias, selecciona "Transfirió por Alias".';
                        return;
                    }
                }

                // Validaciones según si el cliente tiene o no cuenta corriente
                if (!hasAccount) {
                    if (paymentMethod !== 'efectivo' && isOverpaid) {
                        errorMessage = `En cobros electrónicos para clientes sin cuenta corriente, el importe no puede superar el total adeudado (${formatCurrency(debt)}).`;
                        return;
                    }
                } else {
                    if (isFavorableBalanceExceeded) {
                        errorMessage = `El excedente supera el límite máximo permitido de saldo a favor (${formatCurrency(MAX_FAVORABLE_BALANCE)}).`;
                        return;
                    }

                    // Si tiene cuenta corriente y hay sobrecobro, solicitar confirmación explícita
                    if (isOverpaid) {
                        isOverpaymentConfirmOpen = true;
                        return;
                    }
                }
            }
        } else {
            // Pedido ya pagado
            if (collectExtraBalance) {
                if (!paymentMethod) {
                    errorMessage = 'Selecciona un método de cobro para la cuenta corriente.';
                    return;
                }
                if (amountToPay <= 0) {
                    errorMessage = 'El monto a cobrar debe ser mayor a cero.';
                    return;
                }
                if (isFavorableBalanceExceeded) {
                    errorMessage = `El monto a cobrar supera el límite máximo permitido de saldo a favor (${formatCurrency(MAX_FAVORABLE_BALANCE)}).`;
                    return;
                }
                isOverpaymentConfirmOpen = true;
                return;
            }
        }

        executeDelivery();
    }

    function handleQrDelivered() {
        if (!order) return;
        isSubmitting = true;
        errorMessage = null;

        // Idempotencia FSM: El cobro ya fue acreditado por el Webhook/servicio de MP en backend.
        // Finalizamos la entrega sin re-cobrar importe para evitar duplicar el recibo.
        router.post(
            `/tenant/repartos/${order.id}/entregar`,
            {
                payment_mode: 'on_credit',
                payment_method: null,
                amount: null,
                reference_number: 'MP-QR-ACREDITADO',
                delivery_notes: deliveryNotes.trim()
                    ? `${deliveryNotes.trim()} (Cobrado en vivo con QR Mercado Pago)`
                    : 'Cobrado en vivo con QR Mercado Pago',
            },
            {
                onSuccess: () => {
                    isSubmitting = false;
                    isQrModalOpen = false;
                    handleClose();
                    if (onSuccess) onSuccess();
                },
                onError: (errors) => {
                    isSubmitting = false;
                    isQrModalOpen = false;
                    errorMessage = Object.values(errors).flat().join(' ') || 'Error al registrar la finalización de entrega.';
                },
            }
        );
    }

    function executeDelivery() {
        if (!order) return;
        isSubmitting = true;
        errorMessage = null;

        const willCollect = (debt > 0 && paymentMode === 'paid_now') || (debt <= 0 && collectExtraBalance);

        router.post(
            `/tenant/repartos/${order.id}/entregar`,
            {
                payment_mode: debt <= 0 && !collectExtraBalance ? 'on_credit' : paymentMode,
                payment_method: willCollect ? paymentMethod : null,
                amount: willCollect ? amountToPay : null,
                reference_number: referenceNumber.trim() || null,
                delivery_notes: deliveryNotes.trim() || null,
            },
            {
                onSuccess: () => {
                    isSubmitting = false;
                    isOverpaymentConfirmOpen = false;
                    handleClose();
                    if (onSuccess) onSuccess();
                },
                onError: (errors) => {
                    isSubmitting = false;
                    isOverpaymentConfirmOpen = false;
                    errorMessage = Object.values(errors).flat().join(' ') || 'Error al registrar la entrega.';
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-lg p-0 flex flex-col max-h-[85vh] sm:max-h-[88vh] bg-card text-card-foreground border border-border/80 overflow-hidden">
        <DialogHeader class="p-5 sm:p-6 pb-3 text-left border-b border-border/50 shrink-0">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <DialogTitle class="text-lg font-bold flex items-center gap-2 font-sans">
                        <CheckCircle2 class="size-5 text-emerald-500 shrink-0" />
                        <span>Confirmar entrega del pedido</span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground max-w-[260px] sm:max-w-md break-words font-sans">
                        Registra la finalización de entrega para este pedido
                    </DialogDescription>
                </div>
                {#if order}
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold font-sans tabular-nums bg-primary/15 text-primary border border-primary/20 shrink-0">
                        #{order.order_number}
                    </span>
                {/if}
            </div>
        </DialogHeader>

        <DialogBody class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-4">
            {#if order}
                <!-- Resumen de Pedido y Destinatario -->
                <div class="rounded-2xl border border-border/70 bg-background/50 p-4 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-foreground">{order.customer_name}</span>
                        <span class="text-xs font-sans font-bold text-primary tabular-nums">#{order.order_number}</span>
                    </div>
                    {#if order.delivery_address}
                        <p class="text-xs text-muted-foreground flex items-center gap-1.5">
                            <span class="inline-block size-1.5 rounded-full bg-primary shrink-0"></span>
                            <span>{order.delivery_address}</span>
                        </p>
                    {/if}
                    <div class="pt-2 border-t border-border/50 flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Zona: {order.shipping_zone_name}</span>
                        <div class="flex items-center gap-1.5">
                            <span class="text-muted-foreground">Saldo pendiente:</span>
                            <span class="font-bold tabular-nums text-sm {order.debt_amount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}">
                                {formatCurrency(order.debt_amount)}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Modalidad de Liquidación / Pago -->
                {#if debt > 0}
                    <div class="space-y-2">
                        <Label class="text-sm font-medium text-foreground">Modalidad de cobro</Label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="flex items-center gap-2.5 p-3 rounded-xl border text-left transition-all min-h-[44px] cursor-pointer {paymentMode === 'paid_now' ? 'border-primary bg-primary/10 text-foreground font-semibold shadow-2xs' : 'border-border/80 bg-background/40 hover:bg-muted/30 text-muted-foreground'}"
                                onclick={() => (paymentMode = 'paid_now')}
                            >
                                <Banknote class="size-4.5 text-primary shrink-0" />
                                <div>
                                    <p class="text-xs font-semibold">Cobrar en el acto</p>
                                    <p class="text-xs opacity-80">Efectivo o transferencia</p>
                                </div>
                            </button>

                            <button
                                type="button"
                                disabled={!order.customer_has_credit}
                                class="flex items-center gap-2.5 p-3 rounded-xl border text-left transition-all min-h-[44px] {paymentMode === 'on_credit' ? 'border-primary bg-primary/10 text-foreground font-semibold shadow-2xs' : 'border-border/80 bg-background/40 text-muted-foreground'} {!order.customer_has_credit ? 'opacity-40 cursor-not-allowed' : 'hover:bg-muted/30 cursor-pointer'}"
                                onclick={() => {
                                    if (order?.customer_has_credit) paymentMode = 'on_credit';
                                }}
                            >
                                <CreditCard class="size-4.5 text-amber-500 shrink-0" />
                                <div>
                                    <p class="text-xs font-semibold">Cuenta Corriente</p>
                                    <p class="text-xs opacity-80">{order.customer_has_credit ? 'Cargar a crédito' : 'Sin crédito habilitado'}</p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Si cobra en el acto, mostrar campos de pago -->
                    {#if paymentMode === 'paid_now'}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-2xl border border-border/80 bg-background/40">
                            <div class="space-y-1.5">
                                <Label for="delivery-payment-method" class="text-sm font-medium text-foreground">Método de cobro</Label>
                                <select
                                    id="delivery-payment-method"
                                    bind:value={paymentMethod}
                                    class="w-full h-11 px-3 text-sm rounded-xl border border-border/80 bg-background text-foreground focus:outline-none focus:border-primary-500 transition-all min-h-[44px]"
                                >
                                    <option value="efectivo">Efectivo</option>
                                    <option value="transferencia">Transferencia bancaria</option>
                                    <option value="mercadopago">MercadoPago / QR</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="delivery-amount" class="text-sm font-medium text-foreground">
                                    {paymentMethod === 'efectivo' && !hasAccount ? 'Monto recibido (Efectivo)' : 'Monto cobrado'}
                                </Label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm">$</span>
                                    <Input
                                        id="delivery-amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max="50000000"
                                        maxlength="9"
                                        oninput={handleAmountInput}
                                        bind:value={amountToPay}
                                        class="pl-7 h-11 tabular-nums font-semibold min-h-[44px] bg-background border-border/80 {isFavorableBalanceExceeded ? 'border-destructive focus-visible:ring-destructive' : ''}"
                                    />
                                </div>
                            </div>

                            {#if isFavorableBalanceExceeded}
                                <div class="col-span-1 sm:col-span-2 p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-center gap-2">
                                    <AlertCircle class="size-4 shrink-0" />
                                    <span>El saldo a favor acumulado no puede superar los {formatCurrency(MAX_FAVORABLE_BALANCE)}.</span>
                                </div>
                            {/if}

                            {#if paymentMethod === 'mercadopago'}
                                <div class="col-span-1 sm:col-span-2 space-y-3 pt-1">
                                    <div class="space-y-1.5">
                                        <Label class="text-sm font-medium text-foreground">¿Cómo abona con Mercado Pago?</Label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <button
                                                type="button"
                                                class="flex items-center gap-2.5 p-3 rounded-xl border text-left transition-all min-h-[44px] cursor-pointer {mpMode === 'qr' ? 'border-sky-500 bg-sky-500/10 text-foreground font-semibold shadow-2xs' : 'border-border/80 bg-background/40 hover:bg-muted/30 text-muted-foreground'}"
                                                onclick={() => (mpMode = 'qr')}
                                            >
                                                <QrCode class="size-4.5 text-sky-500 shrink-0" />
                                                <div>
                                                    <p class="text-xs font-semibold">QR en el acto</p>
                                                    <p class="text-xs opacity-80">Escanea desde la app</p>
                                                </div>
                                            </button>

                                            <button
                                                type="button"
                                                class="flex items-center gap-2.5 p-3 rounded-xl border text-left transition-all min-h-[44px] cursor-pointer {mpMode === 'transfer' ? 'border-sky-500 bg-sky-500/10 text-foreground font-semibold shadow-2xs' : 'border-border/80 bg-background/40 hover:bg-muted/30 text-muted-foreground'}"
                                                onclick={() => (mpMode = 'transfer')}
                                            >
                                                <ArrowRightLeft class="size-4.5 text-sky-500 shrink-0" />
                                                <div>
                                                    <p class="text-xs font-semibold">Transfirió por Alias</p>
                                                    <p class="text-xs opacity-80">Muestra comprobante</p>
                                                </div>
                                            </button>
                                        </div>
                                    </div>

                                    {#if mpMode === 'qr'}
                                        <div class="space-y-2 p-3 rounded-xl bg-sky-500/10 border border-sky-500/20">
                                            <Button
                                                type="button"
                                                variant="default"
                                                class="w-full h-11 min-h-[44px] rounded-xl font-semibold gap-2 shadow-xs bg-sky-600 hover:bg-sky-700 text-white dark:bg-sky-600 dark:hover:bg-sky-700 cursor-pointer"
                                                onclick={() => (isQrModalOpen = true)}
                                            >
                                                <QrCode class="size-4.5" />
                                                <span>Generar y Mostrar QR</span>
                                            </Button>
                                            <p class="text-xs text-muted-foreground text-center">
                                                Al escanear y abonar, el sistema detecta la acreditación en vivo y finaliza la entrega automáticamente.
                                            </p>
                                        </div>
                                    {:else}
                                        <div class="space-y-1.5">
                                            <Label for="delivery-ref-mp" class="text-sm font-medium text-foreground">
                                                Nº Comprobante / Transacción (opcional)
                                            </Label>
                                            <Input
                                                id="delivery-ref-mp"
                                                type="text"
                                                placeholder="Ej: 1049283741"
                                                bind:value={referenceNumber}
                                                class="h-11 min-h-[44px] bg-background border-border/80"
                                            />
                                            <p class="text-xs text-muted-foreground">
                                                Opcional. Puedes anotar el número de operación o comprobante provisto por el cliente.
                                            </p>
                                        </div>
                                    {/if}
                                </div>
                            {/if}

                            <!-- Vuelto POS para Efectivo en Clientes Ocasionales -->
                            {#if !hasAccount && paymentMethod === 'efectivo' && isOverpaid}
                                <div class="col-span-1 sm:col-span-2 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Coins class="size-4 text-emerald-600 dark:text-emerald-400" />
                                        <span class="text-xs font-medium text-emerald-800 dark:text-emerald-300">Vuelto a entregar al cliente:</span>
                                    </div>
                                    <span class="font-bold tabular-nums text-base text-emerald-700 dark:text-emerald-300 font-sans">
                                        {formatCurrency(overpaidAmount)}
                                    </span>
                                </div>
                            {/if}

                            <!-- Aviso de saldo a favor para clientes con cuenta corriente -->
                            {#if hasAccount && isOverpaid}
                                <div class="col-span-1 sm:col-span-2 p-3 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Coins class="size-4 text-primary" />
                                        <span class="text-xs font-medium text-foreground">Excedente a acreditar en cuenta:</span>
                                    </div>
                                    <span class="font-bold tabular-nums text-base text-primary font-sans">
                                        +{formatCurrency(overpaidAmount)}
                                    </span>
                                </div>
                            {/if}

                            {#if paymentMethod !== 'efectivo' && paymentMethod !== 'mercadopago'}
                                <div class="col-span-1 sm:col-span-2 space-y-1.5">
                                     <Label for="delivery-ref" class="text-sm font-medium text-foreground">Nº Comprobante / Transacción (opcional)</Label>
                                     <Input
                                         id="delivery-ref"
                                         type="text"
                                         placeholder="Ej: TRANSF-987213"
                                         bind:value={referenceNumber}
                                         class="h-11 min-h-[44px] bg-background border-border/80"
                                     />
                                 </div>
                            {/if}
                        </div>
                    {/if}
                {:else}
                    <div class="p-3.5 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-xs flex items-center gap-2.5">
                        <CheckCircle2 class="size-4 shrink-0" />
                        <span>Este pedido ya se encuentra completamente pago. No se requiere cobro en la entrega.</span>
                    </div>

                    <!-- Switch para saldar saldo a favor / cuenta corriente si el cliente tiene cuenta -->
                    {#if hasAccount}
                        <div class="p-4 rounded-2xl border border-border/80 bg-background/40 space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="space-y-0.5">
                                    <Label for="switch-extra-balance" class="text-sm font-medium text-foreground cursor-pointer">
                                        ¿Cobrar saldo a favor / saldar cuenta corriente?
                                    </Label>
                                    <p class="text-xs text-muted-foreground">
                                        Cobrar un importe adicional para acreditarlo en la cuenta corriente del cliente
                                    </p>
                                </div>
                                <Switch id="switch-extra-balance" bind:checked={collectExtraBalance} />
                            </div>

                            {#if collectExtraBalance}
                                <div class="pt-3 border-t border-border/50 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="space-y-1.5">
                                        <Label for="extra-payment-method" class="text-sm font-medium text-foreground">Método de cobro</Label>
                                        <select
                                            id="extra-payment-method"
                                            bind:value={paymentMethod}
                                            class="w-full h-11 px-3 text-sm rounded-xl border border-border/80 bg-background text-foreground focus:outline-none focus:border-primary-500 transition-all min-h-[44px]"
                                        >
                                            <option value="efectivo">Efectivo</option>
                                            <option value="transferencia">Transferencia bancaria</option>
                                            <option value="mercadopago">MercadoPago / QR</option>
                                            <option value="cheque">Cheque</option>
                                            <option value="otro">Otro</option>
                                        </select>
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label for="extra-amount" class="text-sm font-medium text-foreground">Monto a cobrar</Label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm">$</span>
                                            <Input
                                                id="extra-amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                max="50000000"
                                                maxlength="9"
                                                oninput={handleAmountInput}
                                                bind:value={amountToPay}
                                                class="pl-7 h-11 tabular-nums font-semibold min-h-[44px] bg-background border-border/80 {isFavorableBalanceExceeded ? 'border-destructive focus-visible:ring-destructive' : ''}"
                                            />
                                        </div>
                                    </div>

                                    {#if isFavorableBalanceExceeded}
                                        <div class="col-span-1 sm:col-span-2 p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-center gap-2">
                                            <AlertCircle class="size-4 shrink-0" />
                                            <span>El saldo a favor acumulado no puede superar los {formatCurrency(MAX_FAVORABLE_BALANCE)}.</span>
                                        </div>
                                    {/if}

                                    {#if amountToPay > 0}
                                        <div class="col-span-1 sm:col-span-2 p-3 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <Coins class="size-4 text-primary" />
                                                <span class="text-xs font-medium text-foreground">Importe a acreditar en cuenta:</span>
                                            </div>
                                            <span class="font-bold tabular-nums text-base text-primary font-sans">
                                                +{formatCurrency(amountToPay)}
                                            </span>
                                        </div>
                                    {/if}

                                    {#if paymentMethod === 'mercadopago'}
                                        <div class="col-span-1 sm:col-span-2 space-y-1.5">
                                            <Label for="extra-ref-mp" class="text-sm font-medium text-foreground">
                                                Nº Comprobante / Transacción (opcional)
                                            </Label>
                                            <Input
                                                id="extra-ref-mp"
                                                type="text"
                                                placeholder="Ej: 1049283741"
                                                bind:value={referenceNumber}
                                                class="h-11 min-h-[44px] bg-background border-border/80"
                                            />
                                            <p class="text-xs text-muted-foreground">
                                                Opcional. Puedes anotar el número de operación o comprobante si deseas dejar constancia.
                                            </p>
                                        </div>
                                    {:else if paymentMethod !== 'efectivo'}
                                        <div class="col-span-1 sm:col-span-2 space-y-1.5">
                                            <Label for="extra-ref" class="text-sm font-medium text-foreground">Nº Comprobante / Transacción (opcional)</Label>
                                            <Input
                                                id="extra-ref"
                                                type="text"
                                                placeholder="Ej: TRANSF-987213"
                                                bind:value={referenceNumber}
                                                class="h-11 min-h-[44px] bg-background border-border/80"
                                            />
                                        </div>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    {/if}
                {/if}

                <!-- Observaciones de la entrega -->
                <div class="space-y-1.5">
                    <Label for="delivery-notes" class="text-sm font-medium text-foreground">Observaciones de entrega</Label>
                    <Textarea
                        id="delivery-notes"
                        rows={2}
                        placeholder="Ej: Recibió guardia, portería, firma conforme..."
                        bind:value={deliveryNotes}
                        class="resize-none bg-background border-border/80 text-sm rounded-xl"
                    />
                </div>

                {#if errorMessage}
                    <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/30 text-destructive text-xs flex items-center gap-2">
                        <AlertCircle class="size-4 shrink-0" />
                        <span>{errorMessage}</span>
                    </div>
                {/if}
            {/if}
        </DialogBody>

        <DialogFooter class="p-5 sm:p-6 pt-3 border-t border-border/50 flex flex-col-reverse sm:flex-row items-center justify-end gap-3 shrink-0">
            <Button
                variant="secondary"
                class="w-full sm:w-auto h-11 min-h-[44px] px-4 text-sm font-semibold"
                onclick={handleClose}
                disabled={isSubmitting}
            >
                Cancelar
            </Button>
            <Button
                class="w-full sm:w-auto h-11 min-h-[44px] px-6 text-sm font-semibold gap-2 bg-emerald-600 hover:bg-emerald-700 text-white"
                onclick={triggerSubmit}
                disabled={isSubmitting}
            >
                {#if isSubmitting}
                    <Loader2 class="size-4 animate-spin" />
                    <span>Registrando...</span>
                {:else}
                    <CheckCircle2 class="size-4" />
                    <span>Confirmar Entrega</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal de Confirmación de Excedente a Cuenta Corriente -->
{#if order}
    <ConfirmActionModal
        bind:open={isOverpaymentConfirmOpen}
        title={debt <= 0 ? '¿Confirmar cobro para cuenta corriente?' : '¿Confirmar cobro con excedente a favor?'}
        description={debt <= 0
            ? `Se registrará un cobro de ${formatCurrency(amountToPay)} que se acreditará directamente en la cuenta corriente de ${order.customer_name}. ¿Deseas continuar?`
            : `El importe ingresado (${formatCurrency(amountToPay)}) supera el saldo adeudado del pedido (${formatCurrency(debt)}). El excedente de ${formatCurrency(overpaidAmount)} se acreditará como saldo a favor en la cuenta corriente de ${order.customer_name}. ¿Deseas continuar?`}
        confirmText="Confirmar e imputar"
        cancelText="Volver y corregir"
        variant="primary"
        loading={isSubmitting}
        onconfirm={executeDelivery}
    />

    <!-- Modal de Cobro QR Presencial Mercado Pago -->
    <MercadoPagoQrModal
        bind:open={isQrModalOpen}
        order={{
            id: order.id,
            order_number: order.order_number,
            customer_name: order.customer_name,
            total_amount: order.total_amount,
            debt_amount: amountToPay > 0 ? amountToPay : order.debt_amount,
            status: order.status,
            customer: order.customer_id ? {
                id: order.customer_id,
                name: order.customer_name,
                current_balance: order.customer_current_balance
            } : undefined
        }}
        onSuccess={handleQrDelivered}
        onMarkDelivered={handleQrDelivered}
    />
{/if}
