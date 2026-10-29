<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import PdfPreviewModal from '@/components/common/PdfPreviewModal.svelte';
    import { ClearFilterButton, ModuleHeader } from '@/components/common';
    import ReceiptDetailModal from './ReceiptDetailModal.svelte';
    import ReceiptListDesktop, { type ReceiptItem } from './desktop/ReceiptListDesktop.svelte';
    import ReceiptListMobile from './mobile/ReceiptListMobile.svelte';
    import { router } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Ban from 'lucide-svelte/icons/ban';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';

    interface Props {
        receipts: {
            data: ReceiptItem[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
        filters?: {
            search?: string | null;
            payment_method?: string | null;
            status?: string | null;
            date_from?: string | null;
            date_to?: string | null;
        };
    }

    let { receipts, filters = {} }: Props = $props();

    let searchTerm = $state(filters.search || '');
    let selectedStatus = $state(filters.status || 'all');
    let selectedMethod = $state(filters.payment_method || 'all');
    let dateFrom = $state(filters.date_from || '');
    let dateTo = $state(filters.date_to || '');

    let receiptToVoid = $state<ReceiptItem | null>(null);
    let voidReason = $state('');
    let isVoidModalOpen = $state(false);
    let isSubmittingVoid = $state(false);
    let voidErrorMessage = $state<string | null>(null);

    let isPdfModalOpen = $state(false);
    let selectedReceiptForPdf = $state<ReceiptItem | null>(null);

    function handlePreviewPdf(receipt: ReceiptItem) {
        selectedReceiptForPdf = receipt;
        isPdfModalOpen = true;
    }

    let isDetailModalOpen = $state(false);
    let selectedReceiptForDetail = $state<ReceiptItem | null>(null);

    function handleViewDetail(receipt: ReceiptItem) {
        selectedReceiptForDetail = receipt;
        isDetailModalOpen = true;
    }

    const paymentMethods = [
        { value: 'all', label: 'Todos los métodos' },
        { value: 'efectivo', label: 'Efectivo' },
        { value: 'transferencia', label: 'Transferencia' },
        { value: 'mercadopago', label: 'Mercado Pago' },
        { value: 'cheque', label: 'Cheque' },
        { value: 'debito', label: 'Débito' },
        { value: 'credito', label: 'Crédito' },
    ];

    const statusOptions = [
        { value: 'all', label: 'Todos los estados' },
        { value: 'valid', label: 'Válidos' },
        { value: 'cancelled', label: 'Anulados' },
    ];

    function applyFilters() {
        router.get(
            '/tenant/cobranzas/recibos',
            {
                search: searchTerm || undefined,
                payment_method: selectedMethod !== 'all' ? selectedMethod : undefined,
                status: selectedStatus !== 'all' ? selectedStatus : undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }

    function formatPrice(val: number | string) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(val) || 0);
    }

    function resetFilters() {
        searchTerm = '';
        selectedStatus = 'all';
        selectedMethod = 'all';
        dateFrom = '';
        dateTo = '';
        router.get('/tenant/cobranzas/recibos', {}, { preserveState: true, preserveScroll: true });
    }

    let hasActiveFilters = $derived(
        Boolean(
            searchTerm ||
            (selectedStatus && selectedStatus !== 'all') ||
            (selectedMethod && selectedMethod !== 'all') ||
            dateFrom ||
            dateTo
        )
    );

    function requestVoid(rc: ReceiptItem) {
        receiptToVoid = rc;
        voidReason = '';
        voidErrorMessage = null;
        isVoidModalOpen = true;
    }

    function handleVoidSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (!receiptToVoid || !voidReason.trim()) return;

        isSubmittingVoid = true;
        voidErrorMessage = null;

        router.post(
            `/tenant/cobranzas/recibos/${receiptToVoid.id}/anular`,
            {
                reason: voidReason.trim(),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isSubmittingVoid = false;
                    isVoidModalOpen = false;
                    receiptToVoid = null;
                    voidReason = '';
                },
                onError: (errors) => {
                    isSubmittingVoid = false;
                    const first = Object.values(errors)[0];
                    voidErrorMessage = typeof first === 'string' ? first : 'Error al anular el recibo.';
                },
            }
        );
    }
</script>

<AppHead title="Historial y Auditoría de Recibos" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1440px] mx-auto w-full">
    <!-- Header del Módulo -->
    <ModuleHeader title="Historial de Recibos">
        {#snippet actions()}
            <Button
                variant="secondary"
                class="min-h-[38px] px-3.5 rounded-xl font-medium gap-2 shadow-2xs hover:bg-secondary/90 border border-border/30 transition-all"
                onclick={() => router.get('/tenant/cobranzas')}
            >
                <ArrowLeft class="size-4" />
                <span>Volver a Cobranzas</span>
            </Button>
        {/snippet}
    </ModuleHeader>

    <!-- Barra de Filtros y Búsqueda -->
    <Card class="rounded-2xl border border-border/80 bg-card p-4 shadow-xs space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <!-- Buscador -->
            <div class="sm:col-span-4">
                <SearchCombobox
                    value={searchTerm}
                    items={receipts.data}
                    searchKeys={['receipt_number', 'reference_number']}
                    placeholder="Buscar por Nº de recibo, cliente..."
                    onsearch={(q) => {
                        searchTerm = q;
                        applyFilters();
                    }}
                    onselect={(item) => {
                        searchTerm = item.receipt_number;
                        applyFilters();
                    }}
                    onclear={() => {
                        searchTerm = '';
                        applyFilters();
                    }}
                >
                    {#snippet itemSnippet(item)}
                        <div class="flex items-center justify-between w-full py-0.5">
                            <div class="flex flex-col min-w-0">
                                <span class="font-sans font-bold text-xs text-foreground tabular-nums">
                                    {item.receipt_number}
                                </span>
                                <span class="text-xs text-muted-foreground truncate">
                                    {item.customer?.name || 'Consumidor Final'}
                                </span>
                            </div>
                            <span class="font-sans font-semibold text-xs text-primary tabular-nums shrink-0 ml-2">
                                {formatPrice(item.amount)}
                            </span>
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>

            <!-- Método de Pago -->
            <div class="sm:col-span-3">
                <select
                    bind:value={selectedMethod}
                    onchange={applyFilters}
                    class="h-11 w-full rounded-xl border border-border/80 bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
                >
                    {#each paymentMethods as m}
                        <option value={m.value}>{m.label}</option>
                    {/each}
                </select>
            </div>

            <!-- Estado -->
            <div class="sm:col-span-3">
                <select
                    bind:value={selectedStatus}
                    onchange={applyFilters}
                    class="h-11 w-full rounded-xl border border-border/80 bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
                >
                    {#each statusOptions as s}
                        <option value={s.value}>{s.label}</option>
                    {/each}
                </select>
            </div>

            <!-- Botón Limpiar Filtros -->
            <div class="sm:col-span-2 flex items-center justify-end">
                <ClearFilterButton show={hasActiveFilters} onclick={resetFilters} />
            </div>
        </div>
    </Card>

    <!-- Lista Desktop -->
    <div class="hidden md:block">
        <ReceiptListDesktop
            receipts={receipts.data}
            onViewDetail={handleViewDetail}
            onRequestVoid={requestVoid}
            onPreviewPdf={handlePreviewPdf}
        />
    </div>

    <!-- Lista Mobile -->
    <div class="block md:hidden">
        <ReceiptListMobile
            receipts={receipts.data}
            onViewDetail={handleViewDetail}
            onRequestVoid={requestVoid}
            onPreviewPdf={handlePreviewPdf}
        />
    </div>

    <!-- Paginación -->
    {#if receipts.total > receipts.per_page}
        <div class="pt-2 flex justify-center">
            <AppPagination pagination={receipts} />
        </div>
    {/if}
</div>

<!-- Modal de Detalle Completo de Recibo (Progressive Disclosure) -->
<ReceiptDetailModal
    bind:open={isDetailModalOpen}
    receipt={selectedReceiptForDetail}
    onPreviewPdf={handlePreviewPdf}
    onRequestVoid={requestVoid}
/>

<!-- Modal de Previsualización y Descarga de Recibo Oficial (PDF) -->
{#if selectedReceiptForPdf}
    <PdfPreviewModal
        bind:open={isPdfModalOpen}
        title="Recibo de Cobro"
        orderNumber={selectedReceiptForPdf.receipt_number}
        previewUrl={`/tenant/cobranzas/recibos/${selectedReceiptForPdf.id}/previsualizar-pdf?v=${selectedReceiptForPdf.updated_at ? new Date(selectedReceiptForPdf.updated_at).getTime() : Date.now()}`}
        viewerUrl={`/tenant/cobranzas/recibos/${selectedReceiptForPdf.id}/visor?v=${selectedReceiptForPdf.updated_at ? new Date(selectedReceiptForPdf.updated_at).getTime() : Date.now()}`}
        downloadUrl={`/tenant/cobranzas/recibos/${selectedReceiptForPdf.id}/pdf?v=${selectedReceiptForPdf.updated_at ? new Date(selectedReceiptForPdf.updated_at).getTime() : Date.now()}`}
    />
{/if}

<!-- Modal de Anulación de Recibo -->
<Dialog bind:open={isVoidModalOpen}>
    <DialogContent class="w-[95vw] max-w-md p-6 space-y-4 rounded-2xl sm:rounded-3xl bg-card border-border shadow-2xl">
        <DialogHeader class="space-y-1.5 text-left">
            <div class="flex items-center gap-2.5">
                <div class="size-9 rounded-xl bg-destructive/10 text-destructive flex items-center justify-center shrink-0">
                    <Ban class="size-5" />
                </div>
                <div>
                    <DialogTitle class="text-base font-bold text-foreground">
                        Anular Recibo {receiptToVoid?.receipt_number}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Esta acción restituirá el saldo deudor de los pedidos imputados y emitirá un contra-asiento en cuenta corriente.
                    </DialogDescription>
                </div>
            </div>
        </DialogHeader>

        {#if voidErrorMessage}
            <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-center gap-2">
                <AlertCircle class="size-4 shrink-0" />
                <span class="font-medium">{voidErrorMessage}</span>
            </div>
        {/if}

        <form onsubmit={handleVoidSubmit} class="space-y-4">
            <div class="space-y-1.5">
                <Label for="void-reason" class="text-xs font-semibold text-foreground">
                    Motivo de Anulación <span class="text-destructive">*</span>
                </Label>
                <textarea
                    id="void-reason"
                    bind:value={voidReason}
                    rows="3"
                    maxlength="255"
                    placeholder="Ej. Cheque rechazado, error en monto imputado, duplicado..."
                    class="w-full rounded-xl border border-border/80 bg-background p-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
                    required
                ></textarea>
            </div>

            <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-2">
                <Button
                    type="button"
                    variant="secondary"
                    class="h-11 px-5 rounded-xl font-semibold w-full sm:w-auto min-h-[44px]"
                    onclick={() => (isVoidModalOpen = false)}
                    disabled={isSubmittingVoid}
                >
                    Cancelar
                </Button>

                <Button
                    type="submit"
                    variant="destructive"
                    class="h-11 px-5 rounded-xl font-bold w-full sm:w-auto min-h-[44px] gap-2"
                    disabled={!voidReason.trim() || isSubmittingVoid}
                >
                    <Ban class="size-4" />
                    <span>Confirmar Anulación</span>
                </Button>
            </div>
        </form>
    </DialogContent>
</Dialog>
