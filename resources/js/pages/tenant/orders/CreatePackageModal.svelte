<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Check from 'lucide-svelte/icons/check';
    import Layers from 'lucide-svelte/icons/layers';
    import Minus from 'lucide-svelte/icons/minus';
    import Package from 'lucide-svelte/icons/package';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import X from 'lucide-svelte/icons/x';

    export interface ProductOption {
        id: number;
        name: string;
        sku?: string | null;
        price?: string | number;
        cost_price?: string | number | null;
        stock?: number;
        units_per_package?: number;
    }

    export interface PackageComponent {
        product_id: number;
        product_name: string;
        sku?: string | null;
        quantity_in_package: number;
        unit_price: number;
        units_per_package: number;
        available_stock: number;
    }

    export interface CreatedPackage {
        id: string;
        name: string;
        short_description: string;
        package_quantity: number;
        components: PackageComponent[];
        unit_price: number;
        subtotal: number;
    }

    let {
        open = $bindable(false),
        availableProducts = [],
        mode = 'customer', // 'customer' | 'supplier'
        onAddPackage,
        onaddpackage,
    }: {
        open: boolean;
        availableProducts: ProductOption[];
        mode?: 'customer' | 'supplier';
        onAddPackage?: (pkg: CreatedPackage) => void;
        onaddpackage?: (pkg: CreatedPackage) => void;
    } = $props();

    // Autocomplete & Search State
    let searchQuery = $state('');
    let isSearchOpen = $state(false);
    let selectedProductToAdd = $state<ProductOption | null>(null);
    let quantityInPack = $state(1);

    // Package Configuration
    let packageQuantity = $state(1);
    let components = $state<PackageComponent[]>([]);
    let error = $state<string | null>(null);

    let searchResults = $derived.by(() => {
        const q = searchQuery.toLowerCase().trim();
        if (!q) {
            return availableProducts.slice(0, 5);
        }
        return availableProducts
            .filter((p) =>
                p.name.toLowerCase().includes(q) ||
                (p.sku && p.sku.toLowerCase().includes(q))
            )
            .slice(0, 5);
    });

    function selectProductFromSearch(prod: ProductOption) {
        if (mode === 'customer' && (prod.stock ?? 0) <= 0) {
            error = `El producto "${prod.name}" no cuenta con stock disponible.`;
            return;
        }

        error = null;
        selectedProductToAdd = prod;
        searchQuery = prod.name;
        isSearchOpen = false;
        quantityInPack = Math.min(1, prod.stock ?? 1);
    }

    function addComponentToPackage() {
        error = null;
        if (!selectedProductToAdd) {
            error = 'Debes seleccionar un producto válido del catálogo para agregarlo.';
            return;
        }

        const prod = selectedProductToAdd;
        const availableStock = prod.stock ?? 9999;

        if (mode === 'customer' && availableStock <= 0) {
            error = `El producto "${prod.name}" no tiene stock disponible.`;
            return;
        }

        const qtyToAdd = Math.max(1, Number(quantityInPack) || 1);
        const existingIndex = components.findIndex((c) => c.product_id === prod.id);
        const currentInPack = existingIndex >= 0 ? components[existingIndex].quantity_in_package : 0;
        const totalNeeded = (currentInPack + qtyToAdd) * packageQuantity;

        if (mode === 'customer' && totalNeeded > availableStock) {
            error = `Stock insuficiente para "${prod.name}". Se requieren ${totalNeeded}u pero hay ${availableStock}u disponibles.`;
            return;
        }

        const unitPrice = mode === 'supplier'
            ? Number(prod.cost_price || prod.price || 0)
            : Number(prod.price || 0);

        if (existingIndex >= 0) {
            components[existingIndex].quantity_in_package += qtyToAdd;
        } else {
            components = [
                ...components,
                {
                    product_id: prod.id,
                    product_name: prod.name,
                    sku: prod.sku,
                    quantity_in_package: qtyToAdd,
                    unit_price: unitPrice,
                    units_per_package: prod.units_per_package || 1,
                    available_stock: availableStock,
                },
            ];
        }

        selectedProductToAdd = null;
        searchQuery = '';
        quantityInPack = 1;
    }

    function removeComponent(index: number) {
        components = components.filter((_, i) => i !== index);
    }

    function getMaxQuantityPerPack(comp: PackageComponent): number {
        if (mode === 'supplier') return 9999;
        const stock = comp.available_stock || 0;
        return Math.max(1, Math.floor(stock / Math.max(1, packageQuantity)));
    }

    function incrementComponentQty(index: number) {
        const comp = components[index];
        const max = getMaxQuantityPerPack(comp);
        if (comp.quantity_in_package < max) {
            comp.quantity_in_package += 1;
            error = null;
        } else {
            error = `Se alcanzó el límite de stock (${comp.available_stock}u) para "${comp.product_name}".`;
        }
    }

    function decrementComponentQty(index: number) {
        if (components[index].quantity_in_package > 1) {
            components[index].quantity_in_package -= 1;
            error = null;
        }
    }

    let maxAllowedPackages = $derived.by(() => {
        if (mode === 'supplier' || components.length === 0) return 999;
        let max = 999;
        for (const c of components) {
            const possible = Math.floor(c.available_stock / c.quantity_in_package);
            if (possible < max) {
                max = possible;
            }
        }
        return Math.max(1, max);
    });

    function incrementPackageQuantity() {
        if (packageQuantity < maxAllowedPackages) {
            packageQuantity += 1;
            error = null;
        } else {
            error = `No es posible agregar más bolsones: se alcanzó el stock límite de los componentes.`;
        }
    }

    function decrementPackageQuantity() {
        if (packageQuantity > 1) {
            packageQuantity -= 1;
            error = null;
        }
    }

    function formatCurrency(amount: number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(amount);
    }

    let singlePackageTotal = $derived(
        components.reduce((sum, c) => sum + c.quantity_in_package * c.unit_price, 0)
    );

    let overallTotal = $derived(singlePackageTotal * (Number(packageQuantity) || 1));

    function formatDescription(items: PackageComponent[]): string {
        const full = items
            .map((c) => `(${c.quantity_in_package}u) ${c.product_name}`)
            .join(', ');

        if (full.length > 55) {
            return full.slice(0, 52) + '...';
        }
        return full;
    }

    function handleConfirm() {
        error = null;
        if (components.length === 0) {
            error = 'Debes agregar al menos un producto al bolsón.';
            return;
        }

        // Validate final stock consistency across all components
        if (mode === 'customer') {
            for (const c of components) {
                const totalUnits = c.quantity_in_package * packageQuantity;
                if (totalUnits > c.available_stock) {
                    error = `Stock insuficiente para "${c.product_name}". Requiere ${totalUnits}u pero hay ${c.available_stock}u disponibles.`;
                    return;
                }
            }
        }

        const shortDesc = formatDescription(components);
        const packageName = components.length === 1
            ? `Bolsón: ${components[0].product_name} (x${components[0].quantity_in_package}u)`
            : `Combo Bolsón (${components.length} productos)`;

        const created: CreatedPackage = {
            id: 'pkg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
            name: packageName,
            short_description: shortDesc,
            package_quantity: Number(packageQuantity) || 1,
            components: [...components],
            unit_price: singlePackageTotal,
            subtotal: overallTotal,
        };

        const callback = onAddPackage || onaddpackage;
        if (callback) {
            callback(created);
        }

        handleClose();
    }

    function handleClose() {
        open = false;
        searchQuery = '';
        selectedProductToAdd = null;
        isSearchOpen = false;
        quantityInPack = 1;
        packageQuantity = 1;
        components = [];
        error = null;
    }
</script>

{#if open}
    <!-- Overlay z-60 para superponerse con fluidez sobre el modal principal -->
    <div
        class="fixed inset-0 z-60 bg-black/70 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto"
        onclick={(e) => e.target === e.currentTarget && handleClose()}
        onkeydown={(e) => e.key === 'Escape' && handleClose()}
        role="button"
        tabindex="0"
        aria-label="Cerrar modal de bolsón"
    >
        <div class="bg-card text-card-foreground rounded-2xl border border-border shadow-2xl max-w-2xl w-full p-4 sm:p-6 space-y-4 my-6 overflow-hidden flex flex-col max-h-[92vh] relative">
            <!-- Header Bento -->
            <div class="flex items-center justify-between border-b pb-3.5 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
                        <Package class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-foreground tracking-tight">Nuevo Bolsón Personalizado</h2>
                        <p class="text-xs text-muted-foreground">Arma un combo cerrado de artículos y define la cantidad a incluir en el pedido.</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={handleClose}
                    class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                    aria-label="Cerrar"
                >
                    <X class="size-5" />
                </button>
            </div>

            {#if error}
                <div class="p-3 rounded-xl bg-destructive/10 text-destructive text-xs font-medium border border-destructive/20 shrink-0 flex items-center gap-2">
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{error}</span>
                </div>
            {/if}

            <div class="space-y-4 overflow-y-auto pr-1 flex-1">
                <!-- Bento Card 1: Buscador Autocomplete de Productos -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <ShoppingBag class="size-3.5 text-primary" />
                        Seleccionar Producto para el Bolsón
                    </h3>

                    <div class="relative z-30">
                        <div class="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-center">
                            <!-- Buscador escrito -->
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                                <input
                                    type="text"
                                    bind:value={searchQuery}
                                    onfocus={() => (isSearchOpen = true)}
                                    oninput={() => (isSearchOpen = true)}
                                    placeholder="Buscar producto por nombre o SKU..."
                                    class="h-9 w-full rounded-lg border border-input bg-background pl-9 pr-8 text-xs font-medium text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary outline-none"
                                />
                                {#if searchQuery}
                                    <button
                                        type="button"
                                        onclick={() => {
                                            searchQuery = '';
                                            selectedProductToAdd = null;
                                        }}
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                        aria-label="Limpiar búsqueda"
                                    >
                                        <X class="size-3.5" />
                                    </button>
                                {/if}
                            </div>

                            <!-- Input Cantidad por bolsón -->
                            <div class="flex items-center gap-2">
                                <div class="flex items-center gap-1.5 bg-background border rounded-lg px-2 h-9">
                                    <span class="text-[11px] font-semibold text-muted-foreground">Cant.:</span>
                                    <input
                                        type="number"
                                        min="1"
                                        max={selectedProductToAdd?.stock ?? 999}
                                        bind:value={quantityInPack}
                                        class="w-12 text-xs font-bold text-center bg-transparent outline-none"
                                    />
                                    <span class="text-[11px] text-muted-foreground">u.</span>
                                </div>

                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    class="h-9 text-xs gap-1.5 font-semibold shrink-0"
                                    onclick={addComponentToPackage}
                                    disabled={!selectedProductToAdd || (mode === 'customer' && (selectedProductToAdd.stock ?? 0) <= 0)}
                                >
                                    <Plus class="size-3.5" />
                                    Agregar al Bolsón
                                </Button>
                            </div>
                        </div>

                        <!-- Dropdown flotante con máx 5 sugerencias y scrollbar invisible -->
                        {#if isSearchOpen}
                            <div
                                class="absolute left-0 right-0 top-full mt-1.5 bg-popover text-popover-foreground rounded-xl border border-border/80 shadow-2xl z-50 overflow-hidden"
                            >
                                <div class="max-h-[200px] overflow-y-auto [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden divide-y divide-border/40">
                                    {#each searchResults as prod (prod.id)}
                                        {@const hasStock = (prod.stock ?? 0) > 0 || mode === 'supplier'}
                                        <button
                                            type="button"
                                            class="w-full px-3.5 py-2.5 text-left text-xs hover:bg-muted/60 transition-colors flex items-center justify-between gap-3 group disabled:opacity-50 disabled:cursor-not-allowed"
                                            onclick={() => selectProductFromSearch(prod)}
                                            disabled={!hasStock && mode === 'customer'}
                                        >
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-foreground group-hover:text-primary transition-colors truncate">
                                                        {prod.name}
                                                    </span>
                                                    {#if prod.sku}
                                                        <span class="text-[10px] font-mono bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                                                            {prod.sku}
                                                        </span>
                                                    {/if}
                                                </div>
                                                <div class="flex items-center gap-2 text-[11px] text-muted-foreground mt-0.5">
                                                    {#if mode === 'customer'}
                                                        <span>
                                                            Stock disponible:
                                                            <strong class="font-mono {hasStock ? 'text-foreground' : 'text-destructive'}">
                                                                {prod.stock ?? 0}u
                                                            </strong>
                                                        </span>
                                                    {/if}
                                                </div>
                                            </div>

                                            <div class="text-right shrink-0">
                                                <span class="font-bold font-mono text-primary text-xs block">
                                                    {formatCurrency(Number(mode === 'supplier' ? prod.cost_price || prod.price : prod.price))}
                                                </span>
                                                <span class="text-[10px] text-muted-foreground">por unidad</span>
                                            </div>
                                        </button>
                                    {/each}

                                    {#if searchResults.length === 0}
                                        <div class="p-3.5 text-center text-xs text-muted-foreground italic">
                                            No hay coincidencias en el catálogo de productos.
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Bento Card 2: Lista de Componentes del Bolsón -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary" />
                            Contenido del Bolsón ({components.length} {components.length === 1 ? 'producto' : 'productos'})
                        </h3>
                        <span class="text-xs font-mono font-semibold text-foreground">
                            Subtotal Bolsón: <strong class="text-primary">{formatCurrency(singlePackageTotal)}</strong>
                        </span>
                    </div>

                    <div class="space-y-2.5">
                        {#each components as comp, index (comp.product_id)}
                            {@const maxPerPack = getMaxQuantityPerPack(comp)}
                            {@const isAtStockLimit = comp.quantity_in_package >= maxPerPack}

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 border border-primary/20">
                                        <Layers class="size-4.5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-xs text-foreground truncate">{comp.product_name}</h4>
                                            {#if comp.sku}
                                                <span class="text-[10px] font-mono bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                                                    {comp.sku}
                                                </span>
                                            {/if}
                                        </div>
                                        <div class="flex items-center gap-2 text-[11px] text-muted-foreground mt-0.5">
                                            <span>Precio: <strong class="font-mono text-foreground">{formatCurrency(comp.unit_price)}</strong></span>
                                            {#if mode === 'customer'}
                                                <span>•</span>
                                                <span>Stock disp.: <strong class="font-mono text-foreground">{comp.available_stock}u</strong></span>
                                                {#if isAtStockLimit}
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-600 bg-amber-500/10 px-1.5 py-0.2 rounded border border-amber-500/20">
                                                        Tope de stock
                                                    </span>
                                                {/if}
                                            {/if}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between sm:justify-end gap-3 w-full sm:w-auto pt-2 sm:pt-0 border-t sm:border-t-0 shrink-0">
                                    <!-- Stepper Cantidad por Bolsón -->
                                    <div class="flex items-center border rounded-lg bg-background shadow-2xs">
                                        <button
                                            type="button"
                                            class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-l-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                            onclick={() => decrementComponentQty(index)}
                                            disabled={comp.quantity_in_package <= 1}
                                            aria-label="Disminuir unidades en bolsón"
                                        >
                                            <Minus class="size-3" />
                                        </button>
                                        <span class="px-2.5 text-xs font-bold font-mono min-w-8 text-center">
                                            {comp.quantity_in_package}u
                                        </span>
                                        <button
                                            type="button"
                                            class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-r-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                            onclick={() => incrementComponentQty(index)}
                                            disabled={isAtStockLimit}
                                            title={isAtStockLimit ? 'Se alcanzó el stock límite disponible' : 'Aumentar unidades'}
                                            aria-label="Aumentar unidades en bolsón"
                                        >
                                            <Plus class="size-3" />
                                        </button>
                                    </div>

                                    <span class="font-mono text-xs font-bold text-foreground w-22 text-right">
                                        {formatCurrency(comp.quantity_in_package * comp.unit_price)}
                                    </span>

                                    <button
                                        type="button"
                                        class="p-1.5 text-muted-foreground hover:text-destructive rounded transition-colors"
                                        onclick={() => removeComponent(index)}
                                        aria-label="Eliminar producto del bolsón"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </div>
                        {/each}

                        {#if components.length === 0}
                            <div class="rounded-xl border border-dashed p-6 text-center bg-card/40 text-muted-foreground text-xs space-y-1">
                                <Package class="mx-auto size-6 text-muted-foreground/40 mb-1" />
                                <p class="font-semibold text-foreground">El bolsón no contiene productos</p>
                                <p class="text-[11px]">Busca productos arriba para agregarlos al bolsón.</p>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Bento Card 3: Multiplicador de Bolsones -->
                <div class="bg-primary/5 p-4 rounded-xl border border-primary/20 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div>
                        <h4 class="text-xs font-bold text-foreground flex items-center gap-1.5">
                            <Package class="size-3.5 text-primary" />
                            Cantidad de Bolsones a Incluir en el Pedido
                        </h4>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Multiplica las unidades de los productos del bolsón por la cantidad elegida.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex items-center border rounded-lg bg-background shadow-2xs">
                            <button
                                type="button"
                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-l-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                onclick={decrementPackageQuantity}
                                disabled={packageQuantity <= 1}
                                aria-label="Disminuir cantidad de bolsones"
                            >
                                <Minus class="size-3.5" />
                            </button>
                            <span class="px-3.5 text-xs font-bold font-mono min-w-8 text-center">
                                {packageQuantity}
                            </span>
                            <button
                                type="button"
                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-r-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                onclick={incrementPackageQuantity}
                                disabled={packageQuantity >= maxAllowedPackages}
                                title={packageQuantity >= maxAllowedPackages ? 'Se alcanzó el tope de bolsones según el stock' : 'Aumentar cantidad'}
                                aria-label="Aumentar cantidad de bolsones"
                            >
                                <Plus class="size-3.5" />
                            </button>
                        </div>
                        <span class="text-xs font-semibold text-muted-foreground">bolsón(es)</span>
                    </div>
                </div>
            </div>

            <!-- Footer Bento: Total & Botones -->
            <div class="flex items-center justify-between border-t pt-3.5 shrink-0">
                <div class="flex items-baseline gap-2">
                    <span class="text-xs text-muted-foreground">Total del Bolsón:</span>
                    <span class="text-xl font-bold font-mono text-primary">{formatCurrency(overallTotal)}</span>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onclick={handleClose}>
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="gap-1.5 font-semibold"
                        disabled={components.length === 0}
                        onclick={handleConfirm}
                    >
                        <Package class="size-4" />
                        <span>Agregar Bolsón al Pedido</span>
                    </Button>
                </div>
            </div>
        </div>
    </div>
{/if}
