<script lang="ts">
    import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogBody, DialogFooter } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Switch } from '@/components/ui/switch';
    import { Textarea } from '@/components/ui/textarea';
    import MercadoPagoQrModal from '@/components/common/MercadoPagoQrModal.svelte';
    import Banknote from 'lucide-svelte/icons/banknote';
    import Building2 from 'lucide-svelte/icons/building-2';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import QrCode from 'lucide-svelte/icons/qr-code';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import { toast } from 'svelte-sonner';
    import { router } from '@inertiajs/svelte';
    import { preventInvalidNumericKeys } from '@/lib/numericSanitizer';

    interface OrderData {
        id: number;
        order_number: string;
        customer_id: number | null;
        customer_name: string;
        customer?: {
            id: number;
            name: string;
            has_account?: boolean;
            current_balance?: number;
        } | null;
        status: string;
        payment_status: string;
        debt_amount: number | string;
        previous_debt_collected?: number | string | null;
        total_amount: number | string;
        delivered_at?: string | null;
        notes?: string | null;
    }

    interface Props {
        open?: boolean;
        order?: OrderData | null;
        onSuccess?: (updatedOrder?: any) => void;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        order = null,
        onSuccess,
        onclose,
    }: Props = $props();

    let paymentMethod = $state<'efectivo' | 'transferencia' | 'debito' | 'credito' | 'cheque'>('efectivo');
    let amountToPay = $state<number>(0);
    let referenceNumber = $state<string>('');
    let notes = $state<string>('');
    let markAsDelivered = $state<boolean>(false);
    let isSubmitting = $state<boolean>(false);
    let errorMessage = $state<string | null>(null);

    // Modal secundario de QR
    let isQrModalOpen = $state<boolean>(false);

    let previousDebtToCollect = $derived(
        order?.payment_status === 'pendiente' ? (Number(order?.previous_debt_collected) || 0) : 0
    );
    let debt = $derived(Math.round(((Number(order?.debt_amount) || 0) + previousDebtToCollect) * 100) / 100);
    let total = $derived(Number(order?.total_amount) || 0);
    let hasAccount = $derived(Boolean(order?.customer_id));
    let isAlreadyDelivered = $derived(order?.status === 'entregado');
    let isOverpaid = $derived(amountToPay > debt);
    let overpaidAmount = $derived(Math.max(0, Math.round((amountToPay - debt) * 100) / 100));

    $effect(() => {
        if (open && order) {
            const extraDebt = order.payment_status === 'pendiente' ? (Number(order.previous_debt_collected) || 0) : 0;
            const currentDebt = Math.round(((Number(order.debt_amount) || 0) + extraDebt) * 100) / 100;
            amountToPay = currentDebt > 0 ? currentDebt : 0;
            paymentMethod = 'efectivo';
            referenceNumber = '';
            notes = '';
            markAsDelivered = false;
            errorMessage = null;
            isSubmitting = false;
        }
    });

    function formatCurrency(val: number): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(val);
    }

    function handleAmountInput(e: Event) {
        const target = e.target as HTMLInputElement;
        const val = parseFloat(target.value);
        if (isNaN(val)) {
            amountToPay = 0;
        } else if (val > 100_000_000) {
            target.value = '100000000';
            amountToPay = 100_000_000;
        } else {
            amountToPay = Math.max(0, val);
        }
    }

    function openQrModal() {
        open = false;
        isQrModalOpen = true;
    }

    async function handleCollect() {
        if (!order) return;
        errorMessage = null;

        if (amountToPay <= 0) {
            errorMessage = 'El importe a cobrar debe ser mayor a cero.';
            return;
        }

        if (amountToPay > 100_000_000) {
            errorMessage = 'El importe a cobrar no puede superar los $100.000.000.';
            return;
        }

        if (!hasAccount && paymentMethod !== 'efectivo' && amountToPay > debt) {
            errorMessage = `Para clientes sin cuenta corriente, el cobro digital no puede superar el saldo pendiente (${formatCurrency(debt)}).`;
            return;
        }

        isSubmitting = true;
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        try {
            const response = await fetch(`/tenant/pedidos/${order.id}/cobrar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    payment_method: paymentMethod,
                    amount: amountToPay,
                    reference_number: referenceNumber.trim() || null,
                    notes: notes.trim() || null,
                    mark_as_delivered: !isAlreadyDelivered && markAsDelivered,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    const firstError = Object.values(data.errors)[0];
                    throw new Error(Array.isArray(firstError) ? firstError[0] : String(firstError));
                }
                throw new Error(data.message || 'Error al registrar el cobro del pedido.');
            }

            toast.success(data.message || 'Cobro registrado correctamente.');
            open = false;
            onSuccess?.(data.order);
            router.reload({ only: ['orders'], preserveScroll: true, preserveState: true });
        } catch (err: any) {
            errorMessage = err.message || 'Ocurrió un error al procesar el cobro.';
        } finally {
            isSubmitting = false;
        }
    }
</script>

<Dialog bind:open onOpenChange={(val) => { if (!val) onclose?.(); }}>
    <DialogContent class="max-w-lg p-5 sm:p-6 space-y-4">
        <DialogHeader class="pb-3 border-b border-border/60 shrink-0">
            <div class="flex items-center gap-2.5 sm:gap-3.5">
                <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                    <ReceiptText class="size-5 sm:size-6 text-primary" />
                </div>
                <div class="min-w-0 flex-1 flex items-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                        Cobrar Pedido {order?.order_number || ''}
                    </DialogTitle>
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="space-y-4">
            {#if errorMessage}
                <div class="p-3 text-xs rounded-xl bg-destructive/10 border border-destructive/20 text-destructive flex items-start gap-2">
                    <AlertCircle class="size-4 shrink-0 mt-0.5" />
                    <span>{errorMessage}</span>
                </div>
            {/if}

            <!-- Resumen de Saldos del Pedido -->
            <div class="grid grid-cols-2 gap-3 p-3.5 rounded-xl border border-border/70 bg-card dark:bg-slate-900/60">
                <div class="space-y-0.5">
                    <span class="text-xs text-muted-foreground block">Total del Pedido</span>
                    <span class="text-sm font-semibold text-foreground tabular-nums">
                        {formatCurrency(total)}
                    </span>
                </div>
                <div class="space-y-0.5 text-right">
                    <span class="text-xs text-muted-foreground block">Saldo Pendiente</span>
                    <span class="text-base font-bold text-primary tabular-nums">
                        {formatCurrency(debt)}
                    </span>
                </div>
            </div>

            <!-- Método de Pago -->
            <div class="space-y-2">
                <Label class="text-xs font-semibold text-foreground">Método de Cobro</Label>
                <div class="grid grid-cols-3 gap-2">
                    <button
                        type="button"
                        onclick={() => (paymentMethod = 'efectivo')}
                        class="min-h-[44px] px-3 py-2 rounded-xl text-xs font-semibold border transition-all flex flex-col items-center justify-center gap-1 cursor-pointer {paymentMethod === 'efectivo' ? 'border-primary bg-primary/10 text-primary shadow-xs' : 'border-border/80 bg-background/80 hover:bg-muted text-muted-foreground'}"
                    >
                        <Banknote class="size-4 shrink-0" />
                        <span>Efectivo</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => (paymentMethod = 'transferencia')}
                        class="min-h-[44px] px-3 py-2 rounded-xl text-xs font-semibold border transition-all flex flex-col items-center justify-center gap-1 cursor-pointer {paymentMethod === 'transferencia' ? 'border-primary bg-primary/10 text-primary shadow-xs' : 'border-border/80 bg-background/80 hover:bg-muted text-muted-foreground'}"
                    >
                        <Building2 class="size-4 shrink-0" />
                        <span>Transferencia</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => (paymentMethod = 'debito')}
                        class="min-h-[44px] px-3 py-2 rounded-xl text-xs font-semibold border transition-all flex flex-col items-center justify-center gap-1 cursor-pointer {paymentMethod === 'debito' ? 'border-primary bg-primary/10 text-primary shadow-xs' : 'border-border/80 bg-background/80 hover:bg-muted text-muted-foreground'}"
                    >
                        <CreditCard class="size-4 shrink-0" />
                        <span>Débito</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => (paymentMethod = 'credito')}
                        class="min-h-[44px] px-3 py-2 rounded-xl text-xs font-semibold border transition-all flex flex-col items-center justify-center gap-1 cursor-pointer {paymentMethod === 'credito' ? 'border-primary bg-primary/10 text-primary shadow-xs' : 'border-border/80 bg-background/80 hover:bg-muted text-muted-foreground'}"
                    >
                        <CreditCard class="size-4 shrink-0" />
                        <span>Crédito</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => (paymentMethod = 'cheque')}
                        class="min-h-[44px] px-3 py-2 rounded-xl text-xs font-semibold border transition-all flex flex-col items-center justify-center gap-1 cursor-pointer {paymentMethod === 'cheque' ? 'border-primary bg-primary/10 text-primary shadow-xs' : 'border-border/80 bg-background/80 hover:bg-muted text-muted-foreground'}"
                    >
                        <ReceiptText class="size-4 shrink-0" />
                        <span>Cheque</span>
                    </button>

                    <!-- Acceso rápido a Cobro QR Mercado Pago -->
                    <button
                        type="button"
                        onclick={openQrModal}
                        class="min-h-[44px] px-3 py-2 rounded-xl text-xs font-semibold border border-sky-500/40 bg-sky-500/10 text-sky-600 dark:text-sky-400 hover:bg-sky-500/20 transition-all flex flex-col items-center justify-center gap-1 cursor-pointer shadow-xs"
                        title="Cobrar presencialmente con QR de Mercado Pago"
                    >
                        <QrCode class="size-4 shrink-0" />
                        <span>QR MP</span>
                    </button>
                </div>
            </div>

            <!-- Importe a Cobrar -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <Label for="amountToPay" class="text-xs font-semibold text-foreground">Importe Recibido</Label>
                    {#if debt > 0}
                        <button
                            type="button"
                            onclick={() => (amountToPay = debt)}
                            class="text-xs text-primary hover:underline font-medium cursor-pointer"
                        >
                            Saldar exacto ({formatCurrency(debt)})
                        </button>
                    {/if}
                </div>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-muted-foreground select-none">
                        $
                    </span>
                    <Input
                        id="amountToPay"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100000000"
                        value={amountToPay}
                        onkeydown={(e) => preventInvalidNumericKeys(e)}
                        oninput={handleAmountInput}
                        placeholder="0.00"
                        class="pl-7 text-sm font-semibold tabular-nums"
                    />
                </div>

                {#if isOverpaid && hasAccount}
                    <div class="p-2.5 rounded-xl bg-primary/10 border border-primary/20 text-xs text-primary flex items-start gap-2">
                        <CheckCircle2 class="size-4 shrink-0 mt-0.5 text-primary" />
                        <span>
                            El importe supera la deuda por <strong>{formatCurrency(overpaidAmount)}</strong>. Este excedente se acreditará como saldo a favor en la cuenta corriente del cliente.
                        </span>
                    </div>
                {:else if isOverpaid && !hasAccount && paymentMethod === 'efectivo'}
                    <div class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-700 dark:text-amber-400 flex items-start gap-2">
                        <AlertCircle class="size-4 shrink-0 mt-0.5" />
                        <span>
                            Vuelto en mano: <strong>{formatCurrency(overpaidAmount)}</strong>. El recibo se emitirá por el saldo adeudado del pedido.
                        </span>
                    </div>
                {/if}
            </div>

            <!-- Referencia de Pago -->
            {#if paymentMethod !== 'efectivo'}
                <div class="space-y-1.5">
                    <Label for="referenceNumber" class="text-xs font-semibold text-foreground">
                        Número de Comprobante / Transacción
                    </Label>
                    <Input
                        id="referenceNumber"
                        type="text"
                        bind:value={referenceNumber}
                        maxlength="100"
                        placeholder="Ej: TRANSF-98124, N° cupón o cheque"
                        class="text-xs tabular-nums"
                    />
                </div>
            {/if}

            <!-- Switch de Entrega en Mostrador -->
            <div class="pt-1">
                <div class="flex items-center justify-between p-3 rounded-xl border border-border/70 bg-muted/40">
                    <div class="space-y-0.5 pr-2">
                        <Label for="markAsDelivered" class="text-xs font-semibold text-foreground block cursor-pointer">
                            Marcar como entregado (Retiro en mostrador)
                        </Label>
                        <span class="text-[11px] text-muted-foreground block">
                            {#if isAlreadyDelivered}
                                El pedido ya fue entregado con anterioridad.
                            {:else}
                                Cambia el estado del pedido a entregado inmediatamente.
                            {/if}
                        </span>
                    </div>
                    <Switch
                        id="markAsDelivered"
                        bind:checked={markAsDelivered}
                        disabled={isAlreadyDelivered}
                    />
                </div>
            </div>

            <!-- Observaciones -->
            <div class="space-y-1.5">
                <Label for="notes" class="text-xs font-semibold text-foreground">Observaciones (opcional)</Label>
                <Textarea
                    id="notes"
                    bind:value={notes}
                    rows={2}
                    maxlength="255"
                    placeholder="Notas o aclaraciones sobre el cobro..."
                    class="text-xs resize-none"
                />
            </div>
        </DialogBody>

        <DialogFooter class="grid grid-cols-2 gap-2.5 pt-2">
            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] w-full text-xs font-semibold rounded-xl cursor-pointer"
                disabled={isSubmitting}
                onclick={() => (open = false)}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                variant="default"
                class="min-h-[44px] w-full text-xs font-semibold rounded-xl cursor-pointer flex items-center justify-center gap-1.5"
                disabled={isSubmitting || amountToPay <= 0}
                onclick={handleCollect}
            >
                {#if isSubmitting}
                    <Loader2 class="size-4 animate-spin shrink-0" />
                    <span>Registrando...</span>
                {:else}
                    <CheckCircle2 class="size-4 shrink-0" />
                    <span>Registrar Cobro</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal secundario de QR presencial Mercado Pago -->
{#if order}
    <MercadoPagoQrModal
        bind:open={isQrModalOpen}
        {order}
        onSuccess={(updatedOrder) => {
            onSuccess?.(updatedOrder);
            router.reload({ only: ['orders'] });
        }}
    />
{/if}
