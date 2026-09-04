<script lang="ts">
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';
    import { router } from '@inertiajs/svelte';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Banknote from 'lucide-svelte/icons/banknote';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import Loader2 from 'lucide-svelte/icons/loader-2';

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
    let amountToPay = $state<number>(0);
    let referenceNumber = $state<string>('');
    let deliveryNotes = $state<string>('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    $effect(() => {
        if (open && order) {
            amountToPay = Number(order.debt_amount) || 0;
            referenceNumber = '';
            deliveryNotes = '';
            errorMessage = null;

            if (Number(order.debt_amount) <= 0) {
                paymentMode = 'paid_now';
            } else if (order.customer_has_credit) {
                paymentMode = 'paid_now';
            } else {
                paymentMode = 'paid_now';
            }
        }
    });

    function handleClose() {
        open = false;
        if (onclose) onclose();
    }

    function handleSubmit() {
        if (!order) return;
        errorMessage = null;

        if (paymentMode === 'paid_now' && Number(order.debt_amount) > 0) {
            if (!paymentMethod) {
                errorMessage = 'Selecciona un método de pago.';
                return;
            }
            if (amountToPay <= 0) {
                errorMessage = 'El monto cobrado debe ser mayor a 0.';
                return;
            }
            if (amountToPay > Number(order.debt_amount)) {
                errorMessage = `El monto no puede superar el saldo adeudado ($${Number(order.debt_amount).toFixed(2)}).`;
                return;
            }
        }

        isSubmitting = true;

        router.post(
            `/tenant/repartos/${order.id}/entregar`,
            {
                payment_mode: paymentMode,
                payment_method: paymentMode === 'paid_now' && Number(order.debt_amount) > 0 ? paymentMethod : null,
                amount: paymentMode === 'paid_now' && Number(order.debt_amount) > 0 ? amountToPay : null,
                reference_number: referenceNumber.trim() || null,
                delivery_notes: deliveryNotes.trim() || null,
            },
            {
                onSuccess: () => {
                    isSubmitting = false;
                    handleClose();
                    if (onSuccess) onSuccess();
                },
                onError: (errors) => {
                    isSubmitting = false;
                    errorMessage = Object.values(errors).flat().join(' ') || 'Error al registrar la entrega.';
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-lg p-5 sm:p-6 space-y-4 bg-card text-card-foreground border border-border/80">
        <DialogHeader class="space-y-1 text-left">
            <DialogTitle class="text-lg font-bold flex items-center gap-2">
                <CheckCircle2 class="size-5 text-emerald-500" />
                Confirmar Entrega de Pedido
            </DialogTitle>
            <DialogDescription class="text-xs text-muted-foreground">
                Registra la finalización de entrega para el pedido #{order?.order_number}.
            </DialogDescription>
        </DialogHeader>

        {#if order}
            <div class="rounded-xl border border-border/70 bg-background/50 p-4 space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-foreground">{order.customer_name}</span>
                    <span class="text-xs font-mono text-muted-foreground">#{order.order_number}</span>
                </div>
                {#if order.delivery_address}
                    <p class="text-xs text-muted-foreground flex items-center gap-1.5">
                        <span class="inline-block size-1.5 rounded-full bg-primary"></span>
                        {order.delivery_address}
                    </p>
                {/if}
                <div class="pt-2 border-t border-border/50 flex items-center justify-between text-xs">
                    <span class="text-muted-foreground">Zona: {order.shipping_zone_name}</span>
                    <div class="flex items-center gap-1.5">
                        <span class="text-muted-foreground">Saldo pendiente:</span>
                        <span class="font-bold tabular-nums text-sm {order.debt_amount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}">
                            ${Number(order.debt_amount).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Modalidad de Liquidación / Pago -->
            {#if Number(order.debt_amount) > 0}
                <div class="space-y-2">
                    <Label class="text-xs font-medium text-foreground">Modalidad de cobro</Label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="flex items-center gap-2.5 p-3 rounded-xl border text-left transition-all min-h-[44px] {paymentMode === 'paid_now' ? 'border-primary bg-primary/10 text-foreground font-semibold' : 'border-border/80 bg-background/40 hover:bg-muted/30 text-muted-foreground'}"
                            onclick={() => (paymentMode = 'paid_now')}
                        >
                            <Banknote class="size-4.5 text-primary shrink-0" />
                            <div>
                                <p class="text-xs font-semibold">Cobrar en el acto</p>
                                <p class="text-[11px] opacity-80">Efectivo o transferencia</p>
                            </div>
                        </button>

                        <button
                            type="button"
                            disabled={!order.customer_has_credit}
                            class="flex items-center gap-2.5 p-3 rounded-xl border text-left transition-all min-h-[44px] {paymentMode === 'on_credit' ? 'border-primary bg-primary/10 text-foreground font-semibold' : 'border-border/80 bg-background/40 text-muted-foreground'} {!order.customer_has_credit ? 'opacity-40 cursor-not-allowed' : 'hover:bg-muted/30'}"
                            onclick={() => {
                                if (order?.customer_has_credit) paymentMode = 'on_credit';
                            }}
                        >
                            <CreditCard class="size-4.5 text-amber-500 shrink-0" />
                            <div>
                                <p class="text-xs font-semibold">Cuenta Corriente</p>
                                <p class="text-[11px] opacity-80">{order.customer_has_credit ? 'Cargar a crédito' : 'Sin crédito habilitado'}</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Si cobra en el acto, mostrar campos de pago -->
                {#if paymentMode === 'paid_now'}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-xl border border-border/80 bg-background/30">
                        <div class="space-y-1.5">
                            <Label for="delivery-payment-method" class="text-xs font-medium text-foreground">Método de cobro</Label>
                            <select
                                id="delivery-payment-method"
                                bind:value={paymentMethod}
                                class="w-full h-11 px-3 text-sm rounded-xl border border-border/80 bg-background text-foreground focus:outline-none focus:border-primary transition-all min-h-[44px]"
                            >
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia bancaria</option>
                                <option value="mercadopago">MercadoPago / QR</option>
                                <option value="cheque">Cheque</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="delivery-amount" class="text-xs font-medium text-foreground">Monto cobrado</Label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm">$</span>
                                <Input
                                    id="delivery-amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max={order.debt_amount}
                                    bind:value={amountToPay}
                                    class="pl-7 h-11 tabular-nums font-semibold min-h-[44px] bg-background border-border/80"
                                />
                            </div>
                        </div>

                        {#if paymentMethod !== 'efectivo'}
                            <div class="col-span-1 sm:col-span-2 space-y-1.5">
                                <Label for="delivery-ref" class="text-xs font-medium text-foreground">Nº Comprobante / Transacción (opcional)</Label>
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
                <div class="p-3.5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-xs flex items-center gap-2">
                    <CheckCircle2 class="size-4 shrink-0" />
                    <span>Este pedido ya se encuentra completamente pago. No se requiere cobro en la entrega.</span>
                </div>
            {/if}

            <!-- Observaciones de la entrega -->
            <div class="space-y-1.5">
                <Label for="delivery-notes" class="text-xs font-medium text-foreground">Observaciones de entrega</Label>
                <Textarea
                    id="delivery-notes"
                    rows={2}
                    placeholder="Ej: Recibió guardia, portería, firma conforme..."
                    bind:value={deliveryNotes}
                    class="resize-none bg-background border-border/80 text-sm"
                />
            </div>

            {#if errorMessage}
                <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/30 text-destructive text-xs">
                    {errorMessage}
                </div>
            {/if}
        {/if}

        <DialogFooter class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-2">
            <Button
                variant="secondary"
                class="w-full sm:w-auto h-11 min-h-[44px] px-4 font-semibold"
                onclick={handleClose}
                disabled={isSubmitting}
            >
                Cancelar
            </Button>
            <Button
                class="w-full sm:w-auto h-11 min-h-[44px] px-6 font-semibold gap-2 bg-emerald-600 hover:bg-emerald-700 text-white"
                onclick={handleSubmit}
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
