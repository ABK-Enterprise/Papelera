<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import PdfPreviewModal from '@/components/common/PdfPreviewModal.svelte';
    import InvoiceDetailModal from './InvoiceDetailModal.svelte';
    import InvoiceFilterModalMobile from './mobile/InvoiceFilterModalMobile.svelte';
    import InvoiceListDesktop, { type FiscalInvoiceItem } from './desktop/InvoiceListDesktop.svelte';
    import InvoiceListMobile from './mobile/InvoiceListMobile.svelte';
    import { ClearFilterButton, ModuleHeader } from '@/components/common';
    import { router } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';

    interface Props {
        invoices: {
            data: FiscalInvoiceItem[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
            links: any[];
        };
        filters?: {
            search?: string | null;
            cbte_tipo?: string | null;
            date_from?: string | null;
            date_to?: string | null;
            sort_by?: string | null;
        };
    }

    let { invoices, filters = {} }: Props = $props();

    let searchTerm = $state(filters.search || '');
    let selectedCbteTipo = $state(filters.cbte_tipo || 'all');
    let dateFrom = $state(filters.date_from || '');
    let dateTo = $state(filters.date_to || '');
    let sortBy = $state(filters.sort_by || 'latest');

    let isFilterModalOpen = $state(false);

    let isPdfModalOpen = $state(false);
    let selectedInvoiceForPdf = $state<FiscalInvoiceItem | null>(null);

    function handlePreviewPdf(inv: FiscalInvoiceItem) {
        selectedInvoiceForPdf = inv;
        isPdfModalOpen = true;
    }

    let isDetailModalOpen = $state(false);
    let selectedInvoiceForDetail = $state<FiscalInvoiceItem | null>(null);

    function handleViewDetail(inv: FiscalInvoiceItem) {
        selectedInvoiceForDetail = inv;
        isDetailModalOpen = true;
    }

    const cbteTipoOptions = [
        { value: 'all', label: 'Todos los comprobantes' },
        { value: '1', label: 'Factura A (001)' },
        { value: '6', label: 'Factura B (006)' },
        { value: '11', label: 'Factura C (011)' },
        { value: '3', label: 'Nota de Crédito A (003)' },
        { value: '8', label: 'Nota de Crédito B (008)' },
        { value: '13', label: 'Nota de Crédito C (013)' },
    ];

    const sortOptions = [
        { value: 'latest', label: 'Más recientes' },
        { value: 'oldest', label: 'Más antiguos' },
        { value: 'amount_desc', label: 'Mayor importe' },
        { value: 'amount_asc', label: 'Menor importe' },
    ];

    function applyFilters() {
        router.get(
            '/tenant/cobranzas/facturas',
            {
                search: searchTerm || undefined,
                cbte_tipo: selectedCbteTipo !== 'all' ? selectedCbteTipo : undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                sort_by: sortBy !== 'latest' ? sortBy : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }

    function handleApplyMobileFilters(f: { cbte_tipo: string; date_from: string; date_to: string; sort_by: string }) {
        selectedCbteTipo = f.cbte_tipo;
        dateFrom = f.date_from;
        dateTo = f.date_to;
        sortBy = f.sort_by;
        applyFilters();
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
        selectedCbteTipo = 'all';
        dateFrom = '';
        dateTo = '';
        sortBy = 'latest';
        router.get('/tenant/cobranzas/facturas', {}, { preserveState: true, preserveScroll: true });
    }

    let activeFiltersCount = $derived(
        (selectedCbteTipo && selectedCbteTipo !== 'all' ? 1 : 0) +
        (dateFrom ? 1 : 0) +
        (dateTo ? 1 : 0) +
        (sortBy && sortBy !== 'latest' ? 1 : 0)
    );

    let hasActiveFilters = $derived(
        Boolean(
            searchTerm ||
            activeFiltersCount > 0
        )
    );
</script>

<AppHead title="Historial de Facturas ARCA" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1440px] mx-auto w-full font-sans">
    <!-- Header del Módulo Escalonado -->
    <ModuleHeader title="Historial de Facturas">
        {#snippet actions()}
            <Button
                variant="secondary"
                class="min-h-[38px] px-3.5 rounded-xl font-medium gap-2 shadow-2xs hover:bg-secondary/90 border border-border/30 transition-all w-full sm:w-auto justify-center"
                onclick={() => router.get('/tenant/cobranzas')}
            >
                <ArrowLeft class="size-4" />
                <span>Volver a Cobranzas</span>
            </Button>
        {/snippet}
    </ModuleHeader>

    <!-- Barra de Filtros Mobile: SearchCombobox + Botón SlidersHorizontal -->
    <div class="flex items-center gap-2.5 w-full md:hidden">
        <div class="flex-1 min-w-0">
            <SearchCombobox
                value={searchTerm}
                items={invoices.data}
                searchKeys={['formatted_number', 'receptor_name', 'cae', 'doc_nro']}
                placeholder="Buscar comprobante, receptor, CAE..."
                onsearch={(q) => {
                    searchTerm = q;
                    applyFilters();
                }}
                onselect={(item) => {
                    searchTerm = item.formatted_number;
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
                                {item.formatted_number}
                            </span>
                            <span class="text-xs text-muted-foreground truncate">
                                {item.receptor_name}
                            </span>
                        </div>
                        <span class="font-sans font-bold text-sm text-primary tabular-nums shrink-0 ml-2">
                            {formatPrice(item.importe_total)}
                        </span>
                    </div>
                {/snippet}
            </SearchCombobox>
        </div>

        <Button
            type="button"
            variant={activeFiltersCount > 0 ? 'secondary' : 'outline'}
            size="icon"
            onclick={() => (isFilterModalOpen = true)}
            class="relative h-11 w-11 min-h-[44px] min-w-[44px] shrink-0 rounded-xl border border-border/70 shadow-2xs"
            aria-label="Abrir filtros avanzados"
        >
            <SlidersHorizontal class="size-4 text-foreground" />
            {#if activeFiltersCount > 0}
                <span class="absolute -top-1 -right-1 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-primary px-1 text-xs font-bold text-primary-foreground tabular-nums shadow-xs">
                    {activeFiltersCount}
                </span>
            {/if}
        </Button>
    </div>

    <!-- Barra de Filtros Desktop -->
    <Card class="hidden md:block rounded-2xl border border-border/80 bg-card p-4 shadow-xs">
        <div class="flex flex-wrap items-center gap-3">
            <!-- Buscador Elástico -->
            <div class="flex-1 min-w-[280px]">
                <SearchCombobox
                    value={searchTerm}
                    items={invoices.data}
                    searchKeys={['formatted_number', 'receptor_name', 'cae', 'doc_nro']}
                    placeholder="Buscar por Nº comprobante, receptor, CAE o CUIT..."
                    onsearch={(q) => {
                        searchTerm = q;
                        applyFilters();
                    }}
                    onselect={(item) => {
                        searchTerm = item.formatted_number;
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
                                    {item.formatted_number}
                                </span>
                                <span class="text-xs text-muted-foreground truncate">
                                    {item.receptor_name}
                                </span>
                            </div>
                            <span class="font-sans font-bold text-sm text-primary tabular-nums shrink-0 ml-2">
                                {formatPrice(item.importe_total)}
                            </span>
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>

            <!-- Tipo de Comprobante -->
            <div class="w-auto min-w-[170px]">
                <select
                    bind:value={selectedCbteTipo}
                    onchange={applyFilters}
                    class="h-11 w-full rounded-xl border border-border/80 bg-select-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary cursor-pointer"
                >
                    {#each cbteTipoOptions as opt}
                        <option value={opt.value}>{opt.label}</option>
                    {/each}
                </select>
            </div>

            <!-- Rango Fecha Desde con Label Explícito -->
            <div class="flex items-center gap-1.5 bg-background border border-border/80 rounded-xl px-3 h-11 shadow-2xs">
                <span class="text-xs font-semibold text-muted-foreground whitespace-nowrap">Desde:</span>
                <input
                    type="date"
                    bind:value={dateFrom}
                    onchange={applyFilters}
                    class="bg-transparent border-0 p-0 text-xs font-medium text-foreground focus:outline-none focus:ring-0 cursor-pointer"
                    aria-label="Fecha desde"
                />
            </div>

            <!-- Rango Fecha Hasta con Label Explícito -->
            <div class="flex items-center gap-1.5 bg-background border border-border/80 rounded-xl px-3 h-11 shadow-2xs">
                <span class="text-xs font-semibold text-muted-foreground whitespace-nowrap">Hasta:</span>
                <input
                    type="date"
                    bind:value={dateTo}
                    onchange={applyFilters}
                    class="bg-transparent border-0 p-0 text-xs font-medium text-foreground focus:outline-none focus:ring-0 cursor-pointer"
                    aria-label="Fecha hasta"
                />
            </div>

            <!-- Criterio de Ordenamiento -->
            <div class="w-auto min-w-[145px]">
                <select
                    bind:value={sortBy}
                    onchange={applyFilters}
                    class="h-11 w-full rounded-xl border border-border/80 bg-select-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary cursor-pointer"
                    title="Criterio de Ordenamiento"
                >
                    {#each sortOptions as s}
                        <option value={s.value}>{s.label}</option>
                    {/each}
                </select>
            </div>

            <ClearFilterButton show={hasActiveFilters} onclick={resetFilters} />
        </div>
    </Card>

    <!-- Listado Desktop -->
    <div class="hidden md:block">
        <InvoiceListDesktop
            invoices={invoices.data}
            onViewDetail={handleViewDetail}
            onPreviewPdf={handlePreviewPdf}
        />
    </div>

    <!-- Listado Mobile -->
    <div class="block md:hidden">
        <InvoiceListMobile
            invoices={invoices.data}
            onViewDetail={handleViewDetail}
            onPreviewPdf={handlePreviewPdf}
        />
    </div>

    <!-- Paginación -->
    {#if invoices.total > invoices.per_page}
        <div class="pt-2">
            <AppPagination pagination={invoices} />
        </div>
    {/if}
</div>

<!-- Modal de Filtros Avanzados Mobile -->
<InvoiceFilterModalMobile
    bind:open={isFilterModalOpen}
    selectedCbteTipo={selectedCbteTipo}
    dateFrom={dateFrom}
    dateTo={dateTo}
    sortBy={sortBy}
    onapply={handleApplyMobileFilters}
    onreset={resetFilters}
/>

<!-- Modal de Detalle de Factura (Solo Lectura) -->
<InvoiceDetailModal
    bind:open={isDetailModalOpen}
    invoice={selectedInvoiceForDetail}
    onPreviewPdf={handlePreviewPdf}
/>

<!-- Modal de Previsualización de PDF -->
{#if selectedInvoiceForPdf}
    <PdfPreviewModal
        bind:open={isPdfModalOpen}
        title={`Factura ${selectedInvoiceForPdf.formatted_number}`}
        orderNumber={selectedInvoiceForPdf.formatted_number}
        previewUrl={`/tenant/cobranzas/facturas/${selectedInvoiceForPdf.id}/previsualizar-pdf?v=${selectedInvoiceForPdf.updated_at ? new Date(selectedInvoiceForPdf.updated_at).getTime() : Date.now()}`}
        viewerUrl={`/tenant/cobranzas/facturas/${selectedInvoiceForPdf.id}/visor?v=${selectedInvoiceForPdf.updated_at ? new Date(selectedInvoiceForPdf.updated_at).getTime() : Date.now()}`}
        downloadUrl={`/tenant/cobranzas/facturas/${selectedInvoiceForPdf.id}/pdf?v=${selectedInvoiceForPdf.updated_at ? new Date(selectedInvoiceForPdf.updated_at).getTime() : Date.now()}`}
    />
{/if}
