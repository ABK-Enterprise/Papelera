<script lang="ts">
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogDescription,
        DialogBody,
        DialogFooter,
        DialogClose,
    } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { page, router } from '@inertiajs/svelte';
    import Receipt from 'lucide-svelte/icons/receipt';
    import Printer from 'lucide-svelte/icons/printer';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';

    export interface CustomerInfo {
        id: number;
        name: string;
        tax_id?: string | null;
        tax_condition?: string | null;
        phone?: string | null;
        address?: string | null;
        is_defaulter?: boolean;
    }

    export interface OrderData {
        id: number;
        order_number: string;
        customer_name: string;
        customer_phone?: string | null;
        delivery_address?: string | null;
        status: string;
        fiscal_status?: string;
        fiscal_error_message?: string | null;
        active_fiscal_invoice?: any;
        total_amount: string | number;
        notes?: string | null;
        created_at?: string;
        customer?: CustomerInfo | null;
        items?: any[];
    }

    let {
        open = $bindable(false),
        order = null,
        tenantTaxCondition = undefined,
        onSuccess = undefined,
    }: {
        open: boolean;
        order: OrderData | null;
        tenantTaxCondition?: string | null;
        onSuccess?: ((invoice?: any) => void) | undefined;
    } = $props();

    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    // Identificación del receptor para montos superiores al umbral legal ($500.000)
    let docTipo = $state<'96' | '80'>('96'); // 96: DNI, 80: CUIT
    let docNro = $state('');
    let docError = $state<string | null>(null);

    function formatCurrency(amount: number | string): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    }

    const totalAmount = $derived(Number(order?.total_amount ?? 0));

    // Determinar condición fiscal del emisor y tipo de comprobante
    const currentTenantTaxCondition = $derived(
        tenantTaxCondition ?? (page.props as any)?.auth?.tenant?.tax_condition ?? 'responsable_inscripto'
    );
    const isMonotributoIssuer = $derived(
        currentTenantTaxCondition === 'monotributo' || currentTenantTaxCondition === 'responsable_monotributo'
    );

    const isCustomerResponsableInscripto = $derived(
        order?.customer?.tax_condition === 'responsable_inscripto'
    );
    const invoiceTypeName = $derived(
        isMonotributoIssuer
            ? 'Factura C'
            : (isCustomerResponsableInscripto ? 'Factura A' : 'Factura B')
    );
    const taxConditionLabel = $derived(
        order?.customer?.tax_condition === 'responsable_inscripto'
            ? 'Responsable Inscripto'
            : (order?.customer?.tax_condition === 'monotributo'
                ? 'Monotributista'
                : (order?.customer?.tax_condition === 'exento'
                    ? 'Exento'
                    : 'Consumidor Final'))
    );

    // Cálculos de IVA para Factura A (desglose neto e IVA 21%)
    const netoEstimado = $derived(
        Math.round((totalAmount / 1.21) * 100) / 100
    );
    const ivaEstimado = $derived(
        Math.round((totalAmount - netoEstimado) * 100) / 100
    );

    // Comprobación de DNI / CUIT registrado
    const existingTaxId = $derived(order?.customer?.tax_id?.trim() ?? '');
    const hasRegisteredDoc = $derived(existingTaxId.length >= 7);

    // Umbral de consumidor final sin identificar: $500.000
    const LEGAL_THRESHOLD = 500000;
    const requiresDocIdentification = $derived(
        totalAmount >= LEGAL_THRESHOLD && !hasRegisteredDoc && !(isCustomerResponsableInscripto && !isMonotributoIssuer)
    );

    function validateDoc(): boolean {
        if (!requiresDocIdentification) {
            docError = null;
            return true;
        }

        const clean = docNro.replace(/\D/g, '');
        if (!clean) {
            docError = 'Para ventas superiores a $500.000 es obligatorio indicar DNI o CUIT.';
            return false;
        }

        if (docTipo === '96') {
            if (clean.length < 7 || clean.length > 8) {
                docError = 'El DNI debe contener 7 u 8 dígitos numéricos.';
                return false;
            }
        } else if (docTipo === '80') {
            if (clean.length !== 11) {
                docError = 'El CUIT debe contener exactamente 11 dígitos numéricos.';
                return false;
            }
        }

        docError = null;
        return true;
    }

    function handleDocInput(e: Event) {
        const target = e.target as HTMLInputElement;
        docNro = target.value;
        if (docError) {
            validateDoc();
        }
    }

    $effect(() => {
        if (open) {
            errorMessage = null;
            docError = null;
            docNro = '';
            docTipo = '96';
            isSubmitting = false;
        }
    });

    function submitEmitInvoice() {
        if (!order || isSubmitting) return;

        if (requiresDocIdentification && !validateDoc()) {
            return;
        }

        isSubmitting = true;
        errorMessage = null;

        const payload: Record<string, any> = {};

        if (requiresDocIdentification || (docNro.trim() && docNro.replace(/\D/g, '').length >= 7)) {
            payload.doc_tipo = parseInt(docTipo, 10);
            payload.doc_nro = docNro.replace(/\D/g, '');
        }

        router.post(`/tenant/pedidos/${order.id}/facturar`, payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (pageProps: any) => {
                isSubmitting = false;
                open = false;
                onSuccess?.(pageProps);
            },
            onError: (errors) => {
                isSubmitting = false;
                errorMessage =
                    errors.fiscal_error ||
                    errors.order ||
                    errors.message ||
                    errors.error ||
                    (Object.values(errors)[0] as string) ||
                    'Ocurrió un error inesperado al emitir la factura electrónica en ARCA.';
            },
        });
    }
</script>

<Dialog bind:open>
    <DialogContent class="w-[95vw] max-w-lg p-0 rounded-2xl sm:rounded-3xl bg-card border-border shadow-2xl overflow-hidden flex flex-col">
        <!-- Header -->
        <DialogHeader class="flex-col items-start p-4 sm:p-6 border-b border-border bg-card gap-1">
            <!-- Fila 1: Icono impresora + Título (wrap) a la izquierda | 'X' a la derecha -->
            <div class="flex items-start justify-between gap-2.5 w-full">
                <div class="flex items-center flex-wrap gap-2.5 min-w-0 flex-1">
                    <div class="bg-primary/10 text-primary size-9 sm:size-10 rounded-xl sm:rounded-2xl flex items-center justify-center shrink-0">
                        <Printer class="size-4.5 sm:size-5" />
                    </div>
                    <DialogTitle class="text-base sm:text-lg font-bold text-foreground flex-wrap break-words leading-snug">
                        Factura electrónica ARCA
                    </DialogTitle>
                </div>
                <DialogClose class="size-9 rounded-xl hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer shrink-0 mt-0.5">
                    <X class="size-4" />
                </DialogClose>
            </div>

            <!-- Fila 2: Debajo del icono de la impresora con un par de pixeles de margen vertical, el id del pedido -->
            {#if order}
                <div class="flex items-center mt-1 pl-0.5">
                    <span class="inline-flex items-center font-bold font-sans tabular-nums text-xs px-2.5 py-0.5 rounded-lg bg-muted text-foreground border border-border/70">
                        {order.order_number}
                    </span>
                </div>
            {/if}
        </DialogHeader>

        <!-- Body -->
        <DialogBody class="p-5 sm:p-6 space-y-4">
            {#if errorMessage}
                <div class="p-3.5 rounded-xl bg-destructive/10 border border-destructive/20 flex items-start gap-2.5 text-xs text-destructive">
                    <AlertTriangle class="size-4 shrink-0 mt-0.5" />
                    <div class="flex-1 leading-relaxed">
                        <strong class="font-bold block">Error de facturación:</strong>
                        <span>{errorMessage}</span>
                    </div>
                </div>
            {/if}

            {#if order}
                <!-- Resumen de la Orden y Receptor -->
                <div class="p-3.5 rounded-xl bg-muted/20 border border-border/70 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Número de Pedido:</span>
                        <strong class="font-bold text-foreground font-sans tabular-nums text-sm">
                            {order.order_number}
                        </strong>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground flex items-center gap-1">
                            <User class="size-3.5" /> Cliente:
                        </span>
                        <span class="font-medium text-foreground truncate max-w-[220px]">
                            {order.customer_name}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Condición Fiscal:</span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold {isCustomerResponsableInscripto ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-muted text-muted-foreground border border-border'}">
                            {taxConditionLabel} ({invoiceTypeName})
                        </span>
                    </div>

                    {#if hasRegisteredDoc}
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Identificación Registrada:</span>
                            <span class="font-sans tabular-nums font-semibold text-foreground">
                                {order.customer?.tax_id}
                            </span>
                        </div>
                    {/if}
                </div>

                <!-- Desglose de Importes -->
                <div class="bg-muted/30 p-3.5 rounded-xl border border-border/80 space-y-2 text-xs">
                    <div class="flex items-center justify-between font-medium text-muted-foreground pb-1 border-b border-border/50">
                        <span>Concepto a Emitir:</span>
                        <span class="text-foreground font-semibold">{invoiceTypeName}</span>
                    </div>

                    {#if isCustomerResponsableInscripto && !isMonotributoIssuer}
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span>Neto Gravado (Estimado):</span>
                            <span class="font-sans tabular-nums text-foreground">
                                {formatCurrency(netoEstimado)}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span>IVA 21% (Estimado):</span>
                            <span class="font-sans tabular-nums text-foreground">
                                {formatCurrency(ivaEstimado)}
                            </span>
                        </div>
                    {/if}

                    <div class="flex items-center justify-between pt-1 border-t border-border/50 text-sm">
                        <span class="font-semibold text-foreground">Total a Facturar:</span>
                        <span class="text-base font-bold text-primary font-sans tabular-nums">
                            {formatCurrency(totalAmount)}
                        </span>
                    </div>
                </div>

                <!-- Campo para DNI / CUIT si supera $500.000 y no hay documento registrado -->
                {#if requiresDocIdentification}
                    <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/25 space-y-3">
                        <div class="flex items-start gap-2 text-xs text-amber-800 dark:text-amber-300">
                            <AlertTriangle class="size-4 shrink-0 mt-0.5 text-amber-600" />
                            <div class="leading-relaxed">
                                <strong class="font-bold block">Identificación Obligatoria por Monto</strong>
                                <span>Por normativa de ARCA, ventas a consumidor final superiores a {formatCurrency(LEGAL_THRESHOLD)} requieren identificar al cliente con DNI o CUIT.</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <div class="space-y-1.5">
                                <Label for="doc-type" class="text-xs font-semibold text-foreground">
                                    Tipo Doc.
                                </Label>
                                <select
                                    id="doc-type"
                                    bind:value={docTipo}
                                    class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 py-2 text-xs text-foreground shadow-2xs focus:outline-none focus:ring-1 focus:ring-primary"
                                >
                                    <option value="96">DNI</option>
                                    <option value="80">CUIT</option>
                                </select>
                            </div>

                            <div class="sm:col-span-2 space-y-1.5">
                                <Label for="doc-number" class="text-xs font-semibold text-foreground">
                                    Número de {docTipo === '96' ? 'DNI' : 'CUIT'} *
                                </Label>
                                <Input
                                    id="doc-number"
                                    type="text"
                                    placeholder={docTipo === '96' ? 'Ej: 35123456' : 'Ej: 20351234569'}
                                    value={docNro}
                                    oninput={handleDocInput}
                                    class="h-10 text-xs font-sans tabular-nums {docError ? 'border-destructive focus-visible:ring-destructive' : ''}"
                                />
                            </div>
                        </div>

                        {#if docError}
                            <p class="text-xs text-destructive font-medium">{docError}</p>
                        {/if}
                    </div>
                {/if}
            {/if}
        </DialogBody>

        <!-- Footer con simetría estricta de botones (min-h-[44px]) -->
        <DialogFooter class="p-4 sm:p-6 border-t border-border bg-card flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5">
            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] px-5 rounded-xl font-semibold w-full sm:w-auto cursor-pointer"
                onclick={() => (open = false)}
                disabled={isSubmitting}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                variant="default"
                class="min-h-[44px] px-5 rounded-xl font-bold w-full sm:w-auto gap-2 shadow-xs cursor-pointer"
                onclick={submitEmitInvoice}
                disabled={isSubmitting}
            >
                {#if isSubmitting}
                    <Loader2 class="size-4 animate-spin shrink-0" />
                    <span>Emitiendo Factura...</span>
                {:else}
                    <Receipt class="size-4 shrink-0" />
                    <span>Emitir Factura Electrónica</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
