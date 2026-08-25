<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import Layers from 'lucide-svelte/icons/layers';
    import Minus from 'lucide-svelte/icons/minus';
    import Package from 'lucide-svelte/icons/package';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import CreatePackageModal, {
        type CreatedPackage,
        type PackageComponent,
    } from './CreatePackageModal.svelte';

    export interface ProductOption {
        id: number;
        name: string;
        sku: string | null;
        price: string | number;
        stock: number;
        units_per_package: number;
    }

    export interface OrderItemLine {
        id: string;
        is_package: boolean;
        product_id: number;
        product_name: string;
        sku?: string | null;
        stock: number;
        packaging_type: 'paquete' | 'bolson';
        quantity: number;
        unit_price: number;
        units_per_package: number;
        description?: string;
        components?: PackageComponent[];
    }

    let {
        open = $bindable(false),
        availableProducts = [],
    }: {
        open: boolean;
        availableProducts: ProductOption[];
    } = $props();

    let customerName = $state('');
    let customerPhone = $state('');
    let deliveryAddress = $state('');
    let notes = $state('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    let isPackageModalOpen = $state(false);

    // Search / Autocomplete state
    let searchQuery = $state('');
    let isSearchOpen = $state(false);
    let selectedProductToAdd = $state<ProductOption | null>(null);

    let items = $state<OrderItemLine[]>([]);

    let usedStockMap = $derived.by(() => {
        const map: Record<number, number> = {};
        for (const it of items) {
            if (it.is_package && it.components) {
                for (const comp of it.components) {
                    map[comp.product_id] = (map[comp.product_id] || 0) + (comp.quantity_in_package * it.quantity);
                }
            } else if (it.product_id) {
                const ratio = it.packaging_type === 'bolson' ? it.units_per_package : 1;
                map[it.product_id] = (map[it.product_id] || 0) + (it.quantity * ratio);
            }
        }
        return map;
    });

    let availableProductsWithRemainingStock = $derived.by(() => {
        return availableProducts.map((p) => {
            const used = usedStockMap[p.id] || 0;
            return {
                ...p,
                stock: Math.max(0, p.stock - used),
            };
        });
    });

    let searchResults = $derived.by(() => {
        const q = searchQuery.toLowerCase().trim();
        if (!q) {
            return availableProductsWithRemainingStock.slice(0, 5);
        }
        return availableProductsWithRemainingStock
            .filter((p) => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)))
            .slice(0, 5);
    });

    function selectProductFromSearch(prod: ProductOption) {
        selectedProductToAdd = prod;
        searchQuery = prod.name;
        isSearchOpen = false;
    }

    function addSelectedProduct() {
        if (!selectedProductToAdd) return;

        const prod = selectedProductToAdd;
        if (prod.stock <= 0) {
            errorMessage = `El producto "${prod.name}" no cuenta con stock disponible.`;
            return;
        }

        errorMessage = null;

        // Check if already in items
        const existingIndex = items.findIndex(
            (it) => !it.is_package && it.product_id === prod.id && it.packaging_type === 'paquete'
        );

        if (existingIndex !== -1) {
            const currentQty = items[existingIndex].quantity;
            if (currentQty < prod.stock) {
                items[existingIndex].quantity += 1;
            } else {
                errorMessage = `Se alcanzó el stock máximo disponible (${prod.stock}u) para "${prod.name}".`;
            }
        } else {
            items = [
                ...items,
                {
                    id: 'item_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                    is_package: false,
                    product_id: prod.id,
                    product_name: prod.name,
                    sku: prod.sku,
                    stock: prod.stock,
                    packaging_type: 'paquete',
                    quantity: 1,
                    unit_price: Number(prod.price),
                    units_per_package: prod.units_per_package || 1,
                },
            ];
        }

        selectedProductToAdd = null;
        searchQuery = '';
    }

    function handleAddPackage(pkg: CreatedPackage) {
        errorMessage = null;
        items = [
            ...items,
            {
                id: pkg.id,
                is_package: true,
                product_id: 0,
                product_name: 'Bolsón Personalizado',
                stock: 9999,
                packaging_type: 'bolson',
                quantity: pkg.package_quantity,
                unit_price: pkg.unit_price,
                units_per_package: 1,
                description: pkg.short_description,
                components: pkg.components,
            },
        ];
    }

    function removeItem(index: number) {
        items = items.filter((_, i) => i !== index);
    }

    function getMaxStockForItem(item: OrderItemLine): number {
        if (item.is_package) return 999;
        if (item.packaging_type === 'bolson') {
            const ratio = item.units_per_package || 1;
            return Math.floor(item.stock / ratio);
        }
        return item.stock;
    }

    function incrementQuantity(index: number) {
        const item = items[index];
        const max = getMaxStockForItem(item);
        if (item.quantity < max) {
            item.quantity += 1;
        }
    }

    function decrementQuantity(index: number) {
        if (items[index].quantity > 1) {
            items[index].quantity -= 1;
        }
    }

    function handlePackagingChange(index: number, newType: 'paquete' | 'bolson') {
        const item = items[index];
        item.packaging_type = newType;
        const max = getMaxStockForItem(item);
        if (item.quantity > max) {
            item.quantity = Math.max(1, max);
        }
    }

    function formatPrice(amount: number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(amount);
    }

    let computedTotal = $derived.by(() => {
        let total = 0;
        for (const it of items) {
            if (it.is_package) {
                total += it.quantity * it.unit_price;
            } else {
                const ratio = it.packaging_type === 'bolson' ? it.units_per_package : 1;
                total += it.quantity * ratio * it.unit_price;
            }
        }
        return total;
    });

    function resetForm() {
        customerName = '';
        customerPhone = '';
        deliveryAddress = '';
        notes = '';
        errorMessage = null;
        searchQuery = '';
        selectedProductToAdd = null;
        isSearchOpen = false;
        items = [];
    }

    function closeModal() {
        open = false;
        resetForm();
    }

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errorMessage = null;

        if (!customerName.trim()) {
            errorMessage = 'Debes ingresar el nombre o razón social del cliente.';
            return;
        }

        if (items.length === 0) {
            errorMessage = 'Debes agregar al menos un producto o bolsón al pedido.';
            return;
        }

        const payloadItems: Array<{
            product_id: number;
            packaging_type: 'paquete' | 'bolson';
            quantity: number;
        }> = [];

        // Flatten packages into individual product items for backend processing & stock validation
        for (const it of items) {
            if (it.is_package && it.components) {
                for (const comp of it.components) {
                    payloadItems.push({
                        product_id: comp.product_id,
                        packaging_type: 'paquete',
                        quantity: comp.quantity_in_package * it.quantity,
                    });
                }
            } else if (it.product_id) {
                payloadItems.push({
                    product_id: Number(it.product_id),
                    packaging_type: it.packaging_type,
                    quantity: it.quantity,
                });
            }
        }

        if (payloadItems.length === 0) {
            errorMessage = 'Debes seleccionar al menos un producto o bolsón para el pedido.';
            return;
        }

        // Validate local stock
        const totalNeededPerProduct: Record<number, number> = {};
        for (const pItem of payloadItems) {
            const prod = availableProducts.find((p) => p.id === pItem.product_id);
            const ratio = pItem.packaging_type === 'bolson' ? (prod?.units_per_package || 1) : 1;
            const units = pItem.quantity * ratio;
            totalNeededPerProduct[pItem.product_id] = (totalNeededPerProduct[pItem.product_id] || 0) + units;
        }

        for (const [pIdStr, needed] of Object.entries(totalNeededPerProduct)) {
            const pId = Number(pIdStr);
            const prod = availableProducts.find((p) => p.id === pId);
            if (prod && needed > prod.stock) {
                errorMessage = `Stock insuficiente para "${prod.name}". Se requieren ${needed}u pero hay ${prod.stock}u disponibles.`;
                return;
            }
        }

        isSubmitting = true;

        router.post(
            '/tenant/orders',
            {
                customer_name: customerName.trim(),
                customer_phone: customerPhone.trim() || null,
                delivery_address: deliveryAddress.trim() || null,
                notes: notes.trim() || null,
                items: payloadItems,
            },
            {
                onSuccess: () => {
                    isSubmitting = false;
                    closeModal();
                },
                onError: (errs) => {
                    isSubmitting = false;
                    const firstKey = Object.keys(errs)[0];
                    errorMessage = errs[firstKey] || 'Ocurrió un error al procesar el pedido.';
                },
            }
        );
    }
</script>

{#if open}
    <div
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto"
        onclick={(e) => e.target === e.currentTarget && closeModal()}
        onkeydown={(e) => e.key === 'Escape' && closeModal()}
        role="button"
        tabindex="0"
        aria-label="Cerrar modal"
    >
        <div class="bg-card text-card-foreground rounded-2xl border border-border shadow-2xl max-w-4xl w-full p-4 sm:p-6 space-y-4 my-6 overflow-hidden flex flex-col max-h-[92vh] relative">
            <!-- Header Bento -->
            <div class="flex items-center justify-between border-b pb-3.5 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
                        <ShoppingBag class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-foreground tracking-tight">Nuevo Pedido</h2>
                        <p class="text-xs text-muted-foreground">Ingresa los datos del cliente y selecciona los productos del catálogo.</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={closeModal}
                    class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                    aria-label="Cerrar"
                >
                    <X class="size-5" />
                </button>
            </div>

            {#if errorMessage}
                <div class="p-3 rounded-xl bg-destructive/10 text-destructive text-xs font-medium border border-destructive/20 shrink-0 flex items-center gap-2">
                    <AlertCircle class="size-4 shrink-0" />
                    <span>{errorMessage}</span>
                </div>
            {/if}

            <form onsubmit={handleSubmit} class="space-y-4 overflow-y-auto pr-1 flex-1">
                <!-- Bento Card 1: Datos del Cliente -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <User class="size-3.5 text-primary" />
                        Datos del Cliente
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="space-y-1">
                            <Label for="cust-name" class="text-xs font-semibold text-foreground">
                                Nombre / Razón Social <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="cust-name"
                                bind:value={customerName}
                                placeholder="ej. Ana García / Librería Central"
                                class="h-9 text-xs"
                                required
                            />
                        </div>
                        <div class="space-y-1">
                            <Label for="cust-phone" class="text-xs font-semibold text-foreground">Teléfono / Contacto</Label>
                            <Input
                                id="cust-phone"
                                bind:value={customerPhone}
                                placeholder="+54 9 11 4455-6677"
                                class="h-9 text-xs"
                            />
                        </div>
                        <div class="space-y-1">
                            <Label for="cust-address" class="text-xs font-semibold text-foreground">Dirección de Entrega</Label>
                            <Input
                                id="cust-address"
                                bind:value={deliveryAddress}
                                placeholder="Av. San Martín 1234 (o Retiro en local)"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>
                </div>

                <!-- Bento Card 2: Productos & Bolsones -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3.5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary" />
                            Productos del Pedido
                        </h3>

                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-8 text-xs gap-1.5 font-semibold text-primary border-primary/30 bg-primary/5 hover:bg-primary/10 self-start sm:self-auto"
                            onclick={() => (isPackageModalOpen = true)}
                        >
                            <Package class="size-3.5" />
                            Crear Bolsón Personalizado
                        </Button>
                    </div>

                    <!-- Buscador Autocomplete Flotante -->
                    <div class="relative z-30">
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                                <input
                                    type="text"
                                    bind:value={searchQuery}
                                    onfocus={() => (isSearchOpen = true)}
                                    oninput={() => (isSearchOpen = true)}
                                    placeholder="Escribe el nombre o SKU del producto para buscar..."
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

                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                class="h-9 text-xs gap-1.5 font-semibold shrink-0"
                                onclick={addSelectedProduct}
                                disabled={!selectedProductToAdd}
                            >
                                <Plus class="size-3.5" />
                                Agregar al Pedido
                            </Button>
                        </div>

                        <!-- Dropdown flotante (Máx 5 items, scrollbar invisible) -->
                        {#if isSearchOpen}
                            <div
                                class="absolute left-0 right-0 top-full mt-1.5 bg-popover text-popover-foreground rounded-xl border border-border/80 shadow-2xl z-50 overflow-hidden"
                            >
                                <div class="max-h-[220px] overflow-y-auto [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden divide-y divide-border/40">
                                    {#each searchResults as prod (prod.id)}
                                        <button
                                            type="button"
                                            class="w-full px-3.5 py-2.5 text-left text-xs hover:bg-muted/60 transition-colors flex items-center justify-between gap-3 group"
                                            onclick={() => selectProductFromSearch(prod)}
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
                                                <p class="text-[11px] text-muted-foreground mt-0.5">
                                                    Stock: <span class="font-bold {prod.stock > 0 ? 'text-foreground' : 'text-destructive'}">{prod.stock}u</span> • Bolsón: x{prod.units_per_package}u
                                                </p>
                                            </div>

                                            <div class="text-right shrink-0">
                                                <span class="font-bold font-mono text-primary text-xs block">
                                                    {formatPrice(Number(prod.price))}
                                                </span>
                                                <span class="text-[10px] text-muted-foreground">por unidad</span>
                                            </div>
                                        </button>
                                    {/each}

                                    {#if searchResults.length === 0}
                                        <div class="p-4 text-center text-xs text-muted-foreground italic">
                                            No hay coincidencias en el catálogo. No es posible crear pedidos con productos inexistentes.
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        {/if}
                    </div>

                    <!-- Lista de Ítems del Pedido -->
                    <div class="space-y-2.5 pt-1">
                        {#each items as item, index (item.id || index)}
                            {#if item.is_package}
                                <!-- Card de Bolsón / Combo -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="size-10 rounded-xl bg-secondary/15 text-secondary flex items-center justify-center shrink-0 border border-secondary/20">
                                            <Package class="size-5" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-secondary/15 text-secondary border border-secondary/30">
                                                    BOLSÓN COMBO
                                                </span>
                                                <p class="text-xs font-bold text-foreground truncate max-w-xs sm:max-w-md">
                                                    {item.description || 'Bolsón de productos'}
                                                </p>
                                            </div>
                                            <p class="text-[11px] text-muted-foreground mt-0.5 font-mono">
                                                {formatPrice(item.unit_price)} por bolsón
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between sm:justify-end gap-3 w-full sm:w-auto pt-2 sm:pt-0 border-t sm:border-t-0">
                                        <!-- Stepper -->
                                        <div class="flex items-center border rounded-lg bg-background shadow-2xs">
                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-l-lg transition-colors"
                                                onclick={() => decrementQuantity(index)}
                                                disabled={item.quantity <= 1}
                                                aria-label="Disminuir cantidad"
                                            >
                                                <Minus class="size-3" />
                                            </button>
                                            <span class="px-3 text-xs font-bold font-mono min-w-8 text-center">
                                                {item.quantity}
                                            </span>
                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-r-lg transition-colors"
                                                onclick={() => incrementQuantity(index)}
                                                aria-label="Aumentar cantidad"
                                            >
                                                <Plus class="size-3" />
                                            </button>
                                        </div>

                                        <span class="font-mono text-xs font-bold text-foreground w-24 text-right">
                                            {formatPrice(item.quantity * item.unit_price)}
                                        </span>

                                        <button
                                            type="button"
                                            class="p-1.5 text-muted-foreground hover:text-destructive rounded transition-colors"
                                            onclick={() => removeItem(index)}
                                            aria-label="Eliminar bolsón"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            {:else}
                                <!-- Card de Producto Individual del Pedido -->
                                {@const maxAllowed = getMaxStockForItem(item)}
                                {@const isAtStockLimit = item.quantity >= maxAllowed}
                                {@const ratio = item.packaging_type === 'bolson' ? item.units_per_package : 1}
                                {@const lineSubtotal = item.quantity * ratio * item.unit_price}

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 border border-primary/20">
                                            <ShoppingBag class="size-5" />
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <h4 class="font-bold text-xs text-foreground truncate">{item.product_name}</h4>
                                                {#if item.sku}
                                                    <span class="text-[10px] font-mono bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                                                        {item.sku}
                                                    </span>
                                                {/if}
                                            </div>

                                            <div class="flex items-center gap-2 text-[11px] text-muted-foreground mt-0.5">
                                                <span>Precio unit.: <strong class="font-mono text-foreground">{formatPrice(item.unit_price)}</strong></span>
                                                <span>•</span>
                                                <span>Stock actual: <strong class="font-mono text-foreground">{item.stock}u</strong></span>
                                                {#if isAtStockLimit}
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-600 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20">
                                                        Tope de stock
                                                    </span>
                                                {/if}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between sm:justify-end gap-3 w-full sm:w-auto pt-2 sm:pt-0 border-t sm:border-t-0 shrink-0">
                                        <!-- Selector de Presentación (Paquete o Bolsón) -->
                                        <select
                                            value={item.packaging_type}
                                            onchange={(e) => handlePackagingChange(index, (e.target as HTMLSelectElement).value as any)}
                                            class="h-8 rounded-lg border border-input bg-background px-2 text-xs font-semibold text-foreground focus:ring-1 focus:ring-primary outline-none"
                                        >
                                            <option value="paquete">Unidad</option>
                                            <option value="bolson" disabled={item.stock < item.units_per_package}>
                                                Bolsón (x{item.units_per_package}u)
                                            </option>
                                        </select>

                                        <!-- Stepper con Límite de Stock -->
                                        <div class="flex items-center border rounded-lg bg-background shadow-2xs">
                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-l-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                                onclick={() => decrementQuantity(index)}
                                                disabled={item.quantity <= 1}
                                                aria-label="Disminuir cantidad"
                                            >
                                                <Minus class="size-3" />
                                            </button>
                                            <span class="px-2.5 text-xs font-bold font-mono min-w-7 text-center">
                                                {item.quantity}
                                            </span>
                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-r-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                                onclick={() => incrementQuantity(index)}
                                                disabled={isAtStockLimit}
                                                title={isAtStockLimit ? 'Se alcanzó el stock máximo disponible' : 'Aumentar'}
                                                aria-label="Aumentar cantidad"
                                            >
                                                <Plus class="size-3" />
                                            </button>
                                        </div>

                                        <span class="font-mono text-xs font-bold text-foreground w-22 text-right">
                                            {formatPrice(lineSubtotal)}
                                        </span>

                                        <button
                                            type="button"
                                            class="p-1.5 text-muted-foreground hover:text-destructive rounded transition-colors"
                                            onclick={() => removeItem(index)}
                                            aria-label="Eliminar producto"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            {/if}
                        {/each}

                        {#if items.length === 0}
                            <div class="rounded-xl border border-dashed p-8 text-center bg-card/40 text-muted-foreground text-xs space-y-1">
                                <ShoppingBag class="mx-auto size-7 text-muted-foreground/40 mb-1" />
                                <p class="font-semibold text-foreground">El pedido está vacío</p>
                                <p class="text-[11px]">Escribe en el buscador superior para seleccionar y agregar productos al pedido.</p>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Bento Card 3: Notas Adicionales -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-1.5">
                    <Label for="order-notes" class="text-xs font-semibold text-foreground">Notas / Instrucciones de Entrega</Label>
                    <textarea
                        id="order-notes"
                        bind:value={notes}
                        rows="2"
                        placeholder="ej. Entregar por la mañana, timbre 2B, empaquetar con cinta reforzada..."
                        class="w-full rounded-lg border border-input bg-background p-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary outline-none resize-none"
                    ></textarea>
                </div>
            </form>

            <!-- Footer Bento: Total & Botones -->
            <div class="flex items-center justify-between border-t pt-3.5 shrink-0">
                <div class="flex items-baseline gap-2">
                    <span class="text-xs text-muted-foreground">Total del Pedido:</span>
                    <span class="text-xl font-bold font-mono text-primary">{formatPrice(computedTotal)}</span>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onclick={closeModal}>
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="gap-1.5 font-semibold"
                        disabled={isSubmitting || items.length === 0}
                        onclick={(e) => {
                            const form = document.querySelector('form');
                            if (form) form.requestSubmit();
                        }}
                    >
                        {#if isSubmitting}
                            <span>Guardando...</span>
                        {:else}
                            <Check class="size-4" />
                            <span>Crear Pedido</span>
                        {/if}
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Creación de Bolsón Combo -->
    <CreatePackageModal
        bind:open={isPackageModalOpen}
        availableProducts={availableProductsWithRemainingStock}
        onAddPackage={handleAddPackage}
    />
{/if}
