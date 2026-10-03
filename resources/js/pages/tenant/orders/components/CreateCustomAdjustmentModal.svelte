<script lang="ts">
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogDescription,
        DialogBody,
        DialogFooter,
    } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import Percent from 'lucide-svelte/icons/percent';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';
    import PlusCircle from 'lucide-svelte/icons/plus-circle';
    import MinusCircle from 'lucide-svelte/icons/minus-circle';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import Tag from 'lucide-svelte/icons/tag';
    import FileText from 'lucide-svelte/icons/file-text';

    let {
        open = $bindable(false),
        orderId = null,
        orderNumber = '',
        netProductsAmount = 0,
        onSuccess = undefined,
        onAddLocal = undefined,
    }: {
        open: boolean;
        orderId?: number | null;
        orderNumber?: string;
        netProductsAmount?: number;
        onSuccess?: ((updatedOrder: any) => void) | undefined;
        onAddLocal?: ((adjustment: any) => void) | undefined;
    } = $props();

    let isSubmitting = $state(false);
    let type = $state<'discount' | 'surcharge'>('discount');
    let name = $state('');
    let calculationType = $state<'percentage' | 'fixed'>('percentage');
    let value = $state<number | string>('');
    let reason = $state('');
    let showInReceipt = $state(true);

    let formErrors = $state<Record<string, string>>({});

    // Resetear formulario al abrir
    $effect(() => {
        if (open) {
            type = 'discount';
            name = '';
            calculationType = 'percentage';
            value = '';
            reason = '';
            showInReceipt = true;
            formErrors = {};
        }
    });

    const numericValue = $derived(Number(value) || 0);

    const calculatedAmount = $derived.by(() => {
        if (numericValue <= 0) return 0;
        if (calculationType === 'percentage') {
            return Math.round(netProductsAmount * (numericValue / 100) * 100) / 100;
        }
        return Math.round(numericValue * 100) / 100;
    });

    function formatCurrency(amount: number | string): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    }

    function validate(): boolean {
        const errors: Record<string, string> = {};

        if (!numericValue || numericValue <= 0) {
            errors.value = 'El valor debe ser un número positivo mayor a 0.';
        } else if (calculationType === 'percentage' && numericValue > 100) {
            errors.value = 'El porcentaje no puede ser superior al 100%.';
        }

        formErrors = errors;
        return Object.keys(errors).length === 0;
    }

    function handleSubmit() {
        if (isSubmitting) return;

        if (!validate()) return;

        const defaultName = type === 'discount' ? 'Descuento' : 'Recargo';
        const finalName = name.trim() ? name.trim() : defaultName;

        if (onAddLocal) {
            onAddLocal({
                id: crypto.randomUUID(),
                source: 'ad_hoc',
                source_id: null,
                type,
                name: finalName,
                calculation_type: calculationType,
                value: numericValue,
                applied_amount: calculatedAmount,
                reason: reason.trim() ? reason.trim() : null,
                show_in_receipt: showInReceipt,
            });
            toast.success(
                type === 'discount'
                    ? 'Descuento agregado al pedido.'
                    : 'Recargo agregado al pedido.'
            );
            open = false;
            return;
        }

        if (!orderId) return;

        isSubmitting = true;

        router.post(
            `/tenant/pedidos/${orderId}/ajustes`,
            {
                source: 'ad_hoc',
                type,
                name: finalName,
                calculation_type: calculationType,
                value: numericValue,
                reason: reason.trim() ? reason.trim() : null,
                show_in_receipt: showInReceipt,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (pageProps: any) => {
                    isSubmitting = false;
                    toast.success(
                        type === 'discount'
                            ? 'Descuento aplicado correctamente al pedido.'
                            : 'Recargo aplicado correctamente al pedido.'
                    );
                    if (onSuccess) {
                        onSuccess(pageProps?.props?.order);
                    }
                    open = false;
                },
                onError: (errors) => {
                    isSubmitting = false;
                    formErrors = errors;
                    const msg = errors.order || Object.values(errors)[0] || 'Error al aplicar el ajuste.';
                    toast.error(msg);
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="sm:max-w-md max-h-[90vh] flex flex-col p-5 sm:p-6 space-y-4">
        <DialogHeader class="space-y-1.5 shrink-0 text-left">
            <div class="flex items-center gap-2">
                <div class="size-8 rounded-lg {type === 'discount' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-amber-500/10 text-amber-600'} flex items-center justify-center shrink-0">
                    <Tag class="size-4" />
                </div>
                <DialogTitle class="text-base sm:text-lg font-bold text-foreground">
                    Nuevo Ajuste en Pedido
                </DialogTitle>
            </div>
            <DialogDescription class="text-xs text-muted-foreground">
                Aplica un recargo o descuento ad-hoc para el pedido #{orderNumber}.
            </DialogDescription>
        </DialogHeader>

        <DialogBody class="flex-1 overflow-y-auto space-y-4 pr-1">
            <!-- Tipo de Ajuste: Descuento o Recargo -->
            <div class="space-y-1.5">
                <Label class="text-xs font-semibold text-foreground">Tipo de Ajuste</Label>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        class="min-h-[44px] flex items-center justify-center gap-2 rounded-xl border text-xs font-semibold transition-all cursor-pointer {type === 'discount' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500 shadow-xs' : 'bg-card text-muted-foreground border-border hover:bg-muted/50'}"
                        onclick={() => (type = 'discount')}
                    >
                        <MinusCircle class="size-4" />
                        <span>Descuento (-)</span>
                    </button>
                    <button
                        type="button"
                        class="min-h-[44px] flex items-center justify-center gap-2 rounded-xl border text-xs font-semibold transition-all cursor-pointer {type === 'surcharge' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500 shadow-xs' : 'bg-card text-muted-foreground border-border hover:bg-muted/50'}"
                        onclick={() => (type = 'surcharge')}
                    >
                        <PlusCircle class="size-4" />
                        <span>Recargo (+)</span>
                    </button>
                </div>
            </div>

            <!-- Nombre Identificativo -->
            <div class="space-y-1.5">
                <Label for="adj-name" class="text-xs font-semibold text-foreground">
                    Nombre o Concepto <span class="text-muted-foreground font-normal">(Opcional)</span>
                </Label>
                <Input
                    id="adj-name"
                    type="text"
                    bind:value={name}
                    placeholder={type === 'discount' ? 'Ej. Descuento comercial por volumen' : 'Ej. Flete express / Seguro de carga'}
                    class="min-h-[44px] rounded-xl text-xs sm:text-sm"
                />
            </div>

            <!-- Modo de Cálculo: Porcentaje o Fijo -->
            <div class="space-y-1.5">
                <Label class="text-xs font-semibold text-foreground">Método de Cálculo</Label>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        class="min-h-[44px] flex items-center justify-center gap-2 rounded-xl border text-xs font-semibold transition-all cursor-pointer {calculationType === 'percentage' ? 'bg-primary/10 text-primary border-primary shadow-xs' : 'bg-card text-muted-foreground border-border hover:bg-muted/50'}"
                        onclick={() => (calculationType = 'percentage')}
                    >
                        <Percent class="size-4" />
                        <span>Porcentual (%)</span>
                    </button>
                    <button
                        type="button"
                        class="min-h-[44px] flex items-center justify-center gap-2 rounded-xl border text-xs font-semibold transition-all cursor-pointer {calculationType === 'fixed' ? 'bg-primary/10 text-primary border-primary shadow-xs' : 'bg-card text-muted-foreground border-border hover:bg-muted/50'}"
                        onclick={() => (calculationType = 'fixed')}
                    >
                        <DollarSign class="size-4" />
                        <span>Monto Fijo ($)</span>
                    </button>
                </div>
            </div>

            <!-- Valor Numérico -->
            <div class="space-y-1.5">
                <Label for="adj-value" class="text-xs font-semibold text-foreground">
                    {calculationType === 'percentage' ? 'Porcentaje a Aplicar (%)' : 'Monto Fijo ($)'}
                </Label>
                <div class="relative">
                    <Input
                        id="adj-value"
                        type="number"
                        step="0.01"
                        min="0.01"
                        max={calculationType === 'percentage' ? 100 : undefined}
                        bind:value
                        placeholder={calculationType === 'percentage' ? '15.00' : '2500.00'}
                        class="min-h-[44px] rounded-xl text-xs sm:text-sm font-sans tabular-nums pr-10 {formErrors.value ? 'border-destructive' : ''}"
                    />
                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-muted-foreground">
                        {#if calculationType === 'percentage'}
                            <Percent class="size-4" />
                        {:else}
                            <DollarSign class="size-4" />
                        {/if}
                    </div>
                </div>
                {#if formErrors.value}
                    <p class="text-[11px] text-destructive">{formErrors.value}</p>
                {/if}
            </div>

            <!-- Previsualización Dinámica de Impacto -->
            {#if numericValue > 0}
                <div class="p-3 rounded-xl border {type === 'discount' ? 'bg-emerald-500/5 border-emerald-500/30' : 'bg-amber-500/5 border-amber-500/30'} flex items-center justify-between text-xs font-sans tabular-nums">
                    <div>
                        <span class="text-muted-foreground block text-[11px]">Impacto sobre el pedido:</span>
                        <span class="font-bold {type === 'discount' ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'}">
                            {type === 'discount' ? '-' : '+'}{formatCurrency(calculatedAmount)}
                        </span>
                    </div>
                    {#if netProductsAmount > 0}
                        <div class="text-right text-[11px] text-muted-foreground">
                            <span>Base neta: {formatCurrency(netProductsAmount)}</span>
                        </div>
                    {/if}
                </div>
            {/if}

            <!-- Motivo / Justificación (Opcional) -->
            <div class="space-y-1.5">
                <Label for="adj-reason" class="text-xs font-semibold text-foreground">
                    Motivo o Justificación <span class="text-muted-foreground font-normal">(Opcional)</span>
                </Label>
                <textarea
                    id="adj-reason"
                    bind:value={reason}
                    rows="2"
                    placeholder="Detalle o justificación para el cliente o auditoría..."
                    class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs sm:text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring resize-none"
                ></textarea>
            </div>

            <!-- Checkbox Visibilidad en Remito -->
            <label class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-muted/40 transition-colors cursor-pointer">
                <input
                    type="checkbox"
                    bind:checked={showInReceipt}
                    class="size-4 rounded border-border text-primary focus:ring-primary shrink-0"
                />
                <div class="flex items-center gap-1.5">
                    <FileText class="size-3.5 text-primary shrink-0" />
                    <span class="text-xs font-medium text-foreground">
                        Imprimir este concepto en el remito / comprobante
                    </span>
                </div>
            </label>
        </DialogBody>

        <DialogFooter class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-border/60 shrink-0">
            <Button
                type="button"
                variant="outline"
                disabled={isSubmitting}
                class="min-h-[44px] px-4 rounded-xl text-xs sm:text-sm font-semibold cursor-pointer w-full sm:w-auto"
                onclick={() => (open = false)}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                variant="default"
                disabled={isSubmitting || numericValue <= 0}
                class="min-h-[44px] px-5 rounded-xl text-xs sm:text-sm font-semibold cursor-pointer w-full sm:w-auto gap-2"
                onclick={handleSubmit}
            >
                {#if isSubmitting}
                    <Loader2 class="size-4 animate-spin" />
                    <span>Aplicando...</span>
                {:else}
                    <Tag class="size-4" />
                    <span>Aplicar Ajuste</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
