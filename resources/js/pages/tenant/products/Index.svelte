<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Link, router } from '@inertiajs/svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Filter from 'lucide-svelte/icons/filter';
    import Percent from 'lucide-svelte/icons/percent';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import X from 'lucide-svelte/icons/x';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import SortSelect from '@/components/common/SortSelect.svelte';
    import BulkDiscountConfigModal from './BulkDiscountConfigModal.svelte';
    import ProductModal from './ProductModal.svelte';
    import CategorySidebarDesktop from './desktop/CategorySidebarDesktop.svelte';
    import ProductListDesktop from './desktop/ProductListDesktop.svelte';
    import ProductListMobile from './mobile/ProductListMobile.svelte';
    import ProductFilterModalMobile from './mobile/ProductFilterModalMobile.svelte';

    interface CategoryItem {
        id: number;
        name: string;
        slug: string;
        icon: string | null;
        products_count?: number;
    }

    interface ProductItem {
        id: number;
        name: string;
        sku: string | null;
        barcode: string | null;
        price: string | number;
        cost_price: string | number | null;
        stock: number;
        min_stock: number;
        units_per_package?: number;
        package_quantity?: number;
        bulk_discount_type?: string;
        custom_bulk_discount_percentage?: number | string | null;
        effective_bulk_discount_percentage?: number;
        bulk_discount_price?: number;
        image_url: string | null;
        is_incomplete?: boolean;
        category?: { id: number; name: string } | null;
    }

    interface Stats {
        total: number;
        in_stock: number;
        low_stock: number;
        out_of_stock: number;
        incomplete?: number;
    }

    interface FilterProps {
        search?: string | null;
        category_id?: number | null;
        stock_status?: string | null;
        info_status?: string | null;
        sort_by?: string | null;
    }

    let {
        products = [],
        categories = [],
        stats = { total: 0, in_stock: 0, low_stock: 0, out_of_stock: 0, incomplete: 0 },
        incompleteCount = 0,
        tenantDefaultBulkDiscount = 0,
        filters = {},
    }: {
        products: ProductItem[];
        categories: CategoryItem[];
        stats?: Stats;
        incompleteCount?: number;
        tenantDefaultBulkDiscount?: number;
        filters?: FilterProps;
    } = $props();

    let searchTerm = $state('');
    let selectedCategoryId = $state<number | null>(null);
    let selectedStockStatus = $state('');
    let selectedInfoStatus = $state('');
    let sortBy = $state('latest');

    $effect(() => {
        searchTerm = filters?.search ?? '';
        selectedCategoryId = filters?.category_id ?? null;
        selectedStockStatus = filters?.stock_status ?? '';
        selectedInfoStatus = filters?.info_status ?? '';
        sortBy = filters?.sort_by ?? 'latest';
    });
    let isBulkDiscountModalOpen = $state(false);
    let isFilterModalOpen = $state(false);
    let isProductModalOpen = $state(false);
    let selectedProductForModal = $state<any>(null);

    function formatPrice(amount: string | number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    }

    function handleSelectProductSuggestion(product: ProductItem) {
        selectedProductForModal = product;
        isProductModalOpen = true;
    }

    const sortOptions = [
        { value: 'latest', label: 'Más recientes primero' },
        { value: 'oldest', label: 'Más antiguos primero' },
        { value: 'name_asc', label: 'Nombre (A-Z)' },
        { value: 'name_desc', label: 'Nombre (Z-A)' },
        { value: 'price_high', label: 'Mayor precio' },
        { value: 'price_low', label: 'Menor precio' },
        { value: 'stock_high', label: 'Mayor stock' },
        { value: 'stock_low', label: 'Menor stock' },
    ];

    function applyFilters() {
        router.get(
            '/tenant/products',
            {
                search: searchTerm || undefined,
                category_id: selectedCategoryId ?? undefined,
                stock_status: selectedStockStatus || undefined,
                info_status: selectedInfoStatus || undefined,
                sort_by: sortBy !== 'latest' ? sortBy : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    }

    function handleSelectCategory(catId: number | null) {
        selectedCategoryId = catId;
        applyFilters();
    }

    function filterIncompleteQuick() {
        selectedInfoStatus = 'incomplete';
        applyFilters();
    }

    function handleApplyMobileFilters(f: {
        category_id: number | null;
        stock_status: string;
        info_status: string;
        sort_by: string;
    }) {
        selectedCategoryId = f.category_id;
        selectedStockStatus = f.stock_status;
        selectedInfoStatus = f.info_status;
        sortBy = f.sort_by;
        applyFilters();
    }

    function resetFilters() {
        searchTerm = '';
        selectedCategoryId = null;
        selectedStockStatus = '';
        selectedInfoStatus = '';
        sortBy = 'latest';
        router.get('/tenant/products', {}, { preserveState: true, preserveScroll: true });
    }

    let hasActiveFilters = $derived(
        Boolean(searchTerm || selectedCategoryId !== null || selectedStockStatus || selectedInfoStatus || (sortBy && sortBy !== 'latest'))
    );

    let activeFiltersCount = $derived(
        (selectedCategoryId !== null ? 1 : 0) +
        (selectedStockStatus ? 1 : 0) +
        (selectedInfoStatus ? 1 : 0) +
        (sortBy && sortBy !== 'latest' ? 1 : 0)
    );
</script>

<AppHead title="Catálogo de Productos - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Header de la Página -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight leading-tight text-foreground">
                Catálogo de Productos
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5 max-w-prose">
                Gestiona y organiza el catálogo de tu comercio y parámetros de empaque.
            </p>
        </div>

        <div class="flex items-center justify-end gap-2.5 flex-wrap">
            <Button
                variant="outline"
                class="gap-1.5 font-medium border-border/80 shadow-2xs"
                onclick={() => (isBulkDiscountModalOpen = true)}
            >
                <Percent class="size-4 text-primary" />
                <span>Desc. por Bulto</span>
                <span class="tabular-nums font-bold text-primary">({tenantDefaultBulkDiscount}%)</span>
            </Button>

            <Link href="/tenant/products/create">
                <Button class="gap-2 shadow-sm font-semibold">
                    <Plus class="size-4" />
                    Crear producto
                </Button>
            </Link>
        </div>
    </div>

    <!-- Banner Wide Card de Productos Incompletos -->
    {#if incompleteCount > 0}
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-amber-900 dark:text-amber-200">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-amber-500/20 text-amber-600 dark:text-amber-400 shrink-0">
                    <AlertTriangle class="size-5" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-foreground">
                        Hay <span class="tabular-nums font-bold">{incompleteCount}</span> {incompleteCount === 1 ? 'producto con datos faltantes' : 'productos con datos faltantes'}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Se recomienda completar su categoría y precio de venta para habilitar su venta a clientes.
                    </p>
                </div>
            </div>
            <Button
                variant="outline"
                size="sm"
                class="shrink-0 text-xs font-semibold bg-background hover:bg-muted border-amber-500/40 text-foreground"
                onclick={filterIncompleteQuick}
            >
                Mostrar productos faltantes
            </Button>
        </div>
    {/if}

    <!-- Barra de Búsqueda y Filtros -->
    <Card class="rounded-xl p-3 sm:p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] border border-border/60">
        <!-- Vista Desktop (>= 768px): Buscador + Selects Directos -->
        <div class="hidden md:flex items-center justify-between gap-3">
            <!-- Buscador por Nombre, SKU o Código de Barras -->
            <div class="w-full md:w-[448px]">

                <SearchCombobox
                    bind:value={searchTerm}
                    items={products}
                    placeholder="Buscar por nombre, SKU o barra..."
                    onsearch={(q) => {
                        searchTerm = q;
                        applyFilters();
                    }}
                    onselect={handleSelectProductSuggestion}
                    onclear={resetFilters}
                >
                    {#snippet itemSnippet(prod)}
                        <div class="flex items-center justify-between gap-3 w-full min-w-0">
                            <div class="flex flex-col min-w-0">
                                <span class="truncate font-medium text-foreground">{prod.name}</span>
                                {#if prod.sku}
                                    <span class="text-[11px] text-muted-foreground tabular-nums">SKU: {prod.sku}</span>
                                {/if}
                            </div>
                            <span class="tabular-nums font-semibold text-primary text-xs shrink-0">{formatPrice(prod.price)}</span>
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>

            <!-- Selectores de Filtro en Desktop -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <!-- Selector de Ordenamiento -->
                <SortSelect
                    value={sortBy}
                    options={sortOptions}
                    onchange={(val) => {
                        sortBy = val;
                        applyFilters();
                    }}
                />

                <!-- Filtro Categoría -->
                <select
                    bind:value={selectedCategoryId}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[130px]"
                >
                    <option value={null}>Todas las Categorías</option>
                    {#each categories as cat}
                        <option value={cat.id}>{cat.name}</option>
                    {/each}
                </select>

                <!-- Filtro Disponibilidad de Stock -->
                <select
                    bind:value={selectedStockStatus}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[130px]"
                >
                    <option value="">Stock: Todos</option>
                    <option value="in_stock">En Stock ({stats.in_stock})</option>
                    <option value="low_stock">Bajo Stock ({stats.low_stock})</option>
                    <option value="out_of_stock">Agotado ({stats.out_of_stock})</option>
                </select>

                <!-- Filtro Estado de Información -->
                <select
                    bind:value={selectedInfoStatus}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[130px]"
                >
                    <option value="">Datos: Todos</option>
                    <option value="complete">Completos</option>
                    <option value="incomplete">Incompletos ({incompleteCount})</option>
                </select>

                {#if hasActiveFilters}
                    <Button variant="ghost" size="sm" class="h-10 px-2 text-xs text-muted-foreground hover:text-foreground gap-1" onclick={resetFilters}>
                        <X class="size-3.5" />
                        Limpiar
                    </Button>
                {/if}
            </div>
        </div>

        <!-- Vista Mobile (< 768px): Searchbar Expandido + Botón de Filtros con Badge -->
        <div class="flex md:hidden items-center gap-2">
            <div class="flex-1">
                <SearchCombobox
                    bind:value={searchTerm}
                    items={products}
                    placeholder="Buscar producto, SKU o código..."
                    onsearch={(q) => {
                        searchTerm = q;
                        applyFilters();
                    }}
                    onselect={handleSelectProductSuggestion}
                    onclear={resetFilters}
                >
                    {#snippet itemSnippet(prod)}
                        <div class="flex items-center justify-between gap-3 w-full min-w-0">
                            <div class="flex flex-col min-w-0">
                                <span class="truncate font-medium text-foreground">{prod.name}</span>
                                {#if prod.sku}
                                    <span class="text-[11px] text-muted-foreground tabular-nums">SKU: {prod.sku}</span>
                                {/if}
                            </div>
                            <span class="tabular-nums font-semibold text-primary text-xs shrink-0">{formatPrice(prod.price)}</span>
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>

            <!-- Botón Disparador de Modal de Filtros Mobile -->
            <Button
                type="button"
                variant={activeFiltersCount > 0 ? 'secondary' : 'outline'}
                size="icon"
                onclick={() => (isFilterModalOpen = true)}
                class="relative h-11 w-11 shrink-0 rounded-xl border border-border/70 shadow-2xs"
                aria-label="Abrir filtros"
            >
                <SlidersHorizontal class="h-4 w-4 text-foreground" />
                {#if activeFiltersCount > 0}
                    <span class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-primary-foreground tabular-nums shadow-xs">
                        {activeFiltersCount}
                    </span>
                {/if}
            </Button>
        </div>
    </Card>

    <!-- Bento Grid Principal (Desktop) -->
    <div class="hidden md:grid md:grid-cols-12 gap-6 items-start">
        <div class="md:col-span-3 sticky top-20">
            <CategorySidebarDesktop
                {categories}
                {selectedCategoryId}
                onSelectCategory={handleSelectCategory}
            />
        </div>

        <div class="md:col-span-9">
            <ProductListDesktop {products} />
        </div>
    </div>

    <!-- Vista Mobile -->
    <div class="block md:hidden space-y-4">
        <ProductListMobile {products} />
    </div>

    <!-- Modal de Configuración Rápida de Descuento por Bulto -->
    <BulkDiscountConfigModal
        bind:open={isBulkDiscountModalOpen}
        currentDiscount={tenantDefaultBulkDiscount}
    />

    <!-- Modal de Filtros Split para Mobile -->
    <ProductFilterModalMobile
        bind:open={isFilterModalOpen}
        {categories}
        {stats}
        {incompleteCount}
        {selectedCategoryId}
        {selectedStockStatus}
        {selectedInfoStatus}
        {sortBy}
        {sortOptions}
        onapply={handleApplyMobileFilters}
        onreset={resetFilters}
    />

    <!-- Modal de Edición/Detalle de Producto al Seleccionar Sugerencia -->
    <ProductModal
        bind:open={isProductModalOpen}
        mode="edit"
        product={selectedProductForModal}
        {categories}
    />

</div>
