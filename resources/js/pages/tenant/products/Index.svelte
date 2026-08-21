<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Link, router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Filter from 'lucide-svelte/icons/filter';
    import X from 'lucide-svelte/icons/x';
    import CategorySidebarDesktop from './desktop/CategorySidebarDesktop.svelte';
    import ProductListDesktop from './desktop/ProductListDesktop.svelte';
    import ProductListMobile from './mobile/ProductListMobile.svelte';

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
        image_url: string | null;
        category?: { id: number; name: string } | null;
    }

    interface Stats {
        total: number;
        in_stock: number;
        low_stock: number;
        out_of_stock: number;
    }

    interface FilterProps {
        search?: string | null;
        category_id?: number | null;
        stock_status?: string | null;
    }

    let {
        products = [],
        categories = [],
        stats = { total: 0, in_stock: 0, low_stock: 0, out_of_stock: 0 },
        filters = {},
    }: {
        products: ProductItem[];
        categories: CategoryItem[];
        stats?: Stats;
        filters?: FilterProps;
    } = $props();

    let searchTerm = $state(filters.search ?? '');
    let selectedCategoryId = $state<number | null>(filters.category_id ?? null);
    let selectedStockStatus = $state(filters.stock_status ?? '');

    function applyFilters() {
        router.get(
            '/tenant/products',
            {
                search: searchTerm || undefined,
                category_id: selectedCategoryId ?? undefined,
                stock_status: selectedStockStatus || undefined,
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

    function resetFilters() {
        searchTerm = '';
        selectedCategoryId = null;
        selectedStockStatus = '';
        router.get('/tenant/products');
    }

    let hasActiveFilters = $derived(Boolean(searchTerm || selectedCategoryId !== null || selectedStockStatus));
</script>

<AppHead title="Catálogo de Productos - Comercio" />

<AppLayout
    breadcrumbs={[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Productos', href: '/tenant/products' },
    ]}
>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
        <!-- Header de la Página -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">
                    Catálogo de Productos
                </h1>
                <p class="text-sm text-muted-foreground mt-0.5">
                    Gestiona y organiza tu inventario de forma eficiente.
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <Link href="/tenant/products/create">
                    <Button class="gap-2 shadow-sm font-semibold">
                        <Plus class="h-4 w-4" />
                        Añadir Producto
                    </Button>
                </Link>
            </div>
        </div>

        <!-- Bento Card: Buscador y Filtros Avanzados -->
        <Card class="rounded-xl p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] border border-border/60">
            <div class="flex flex-col md:flex-row items-center justify-between gap-3">
                <!-- Buscador por Nombre, SKU o Código de Barras -->
                <div class="relative w-full md:w-96">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <input
                        type="text"
                        bind:value={searchTerm}
                        onkeydown={(e) => e.key === 'Enter' && applyFilters()}
                        placeholder="Buscar por nombre, SKU o código..."
                        class="h-10 w-full rounded-lg border border-input bg-muted/20 pl-9 pr-4 text-sm placeholder:text-muted-foreground focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary transition-all"
                    />
                </div>

                <!-- Selectores de Filtro -->
                <div class="flex items-center gap-2.5 w-full md:w-auto flex-wrap">
                    <!-- Filtro Categoría -->
                    <select
                        bind:value={selectedCategoryId}
                        onchange={applyFilters}
                        class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[140px]"
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
                        class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[140px]"
                    >
                        <option value="">Disponibilidad: Todas</option>
                        <option value="in_stock">En Stock ({stats.in_stock})</option>
                        <option value="low_stock">Bajo Stock ({stats.low_stock})</option>
                        <option value="out_of_stock">Agotado ({stats.out_of_stock})</option>
                    </select>

                    <Button variant="secondary" size="sm" class="h-10 px-3.5 text-xs gap-1.5" onclick={applyFilters}>
                        <Filter class="h-3.5 w-3.5" />
                        Filtrar
                    </Button>

                    {#if hasActiveFilters}
                        <Button variant="ghost" size="sm" class="h-10 px-2 text-xs text-muted-foreground hover:text-foreground gap-1" onclick={resetFilters}>
                            <X class="h-3.5 w-3.5" />
                            Limpiar
                        </Button>
                    {/if}
                </div>
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
            <!-- Chips de categorías móviles -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
                <button
                    type="button"
                    class="shrink-0 px-3 py-1.5 rounded-full text-xs font-medium border {selectedCategoryId === null ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-border text-muted-foreground'}"
                    onclick={() => handleSelectCategory(null)}
                >
                    Todas ({stats.total})
                </button>
                {#each categories as cat}
                    <button
                        type="button"
                        class="shrink-0 px-3 py-1.5 rounded-full text-xs font-medium border {selectedCategoryId === cat.id ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-border text-muted-foreground'}"
                        onclick={() => handleSelectCategory(cat.id)}
                    >
                        {cat.name} ({cat.products_count ?? 0})
                    </button>
                {/each}
            </div>

            <ProductListMobile {products} />
        </div>
    </div>
</AppLayout>
