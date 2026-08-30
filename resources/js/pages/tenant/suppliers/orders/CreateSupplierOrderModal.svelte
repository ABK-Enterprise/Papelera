<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import { router } from '@inertiajs/svelte';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Check from 'lucide-svelte/icons/check';
    import Edit3 from 'lucide-svelte/icons/edit-3';
    import Layers from 'lucide-svelte/icons/layers';
    import Minus from 'lucide-svelte/icons/minus';
    import Package from 'lucide-svelte/icons/package';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Truck from 'lucide-svelte/icons/truck';
    import X from 'lucide-svelte/icons/x';

    interface SupplierOption {
        id: number;
        name: string;
        contact_number?: string | null;
    }

    interface ProductOption {
        id: number;
        name: string;
        sku?: string | null;
        cost_price?: string | number | null;
        price?: string | number;
        stock?: number;
        units_per_package?: number;
    }

    interface SupplierOrderItemLine {
        id: string;
        product_id: number | null;
        product_name: string;
        quantity: number;
        unit_cost: number | '';
        search_query: string;
    }

    let {
        open = $bindable(false),
        suppliers = [],
        availableProducts = [],
    }: {
        open: boolean;
        suppliers: SupplierOption[];
        availableProducts: ProductOption[];
    } = $props();

    let selectedSupplierId = $state<number | ''>('');
    let notes = $state('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    let items = $state<SupplierOrderItemLine[]>([
        {
            id: 'item_' + Date.now(),
            product_id: null,
            product_name: '',
            quantity: 1,
            unit_cost: '',
            search_query: '',
        },
    ]);

    function addItem() {
        items = [
            ...items,
            {
                id: 'item_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8),
                product_id: null,
                product_name: '',
                quantity: 1,
                unit_cost: '',
                search_query: '',
            },
        ];
    }

    function removeItem(index: number) {
        if (items.length > 1) {
            items = items.filter((_, i) => i !== index);
        } else {
            items = [
                {
                    id: 'item_' + Date.now(),
                    product_id: null,
                    product_name: '',
                    quantity: 1,
                    unit_cost: '',
                    search_query: '',
                },
            ];
        }
    }

    function incrementQuantity(index: number) {
        items[index].quantity += 1;
    }

    function decrementQuantity(index: number) {
        if (items[index].quantity > 1) {
            items[index].quantity -= 1;
        }
    }

    function getFilteredProducts(query: string): ProductOption[] {
        const q = query.trim().toLowerCase();
        if (!q) return availableProducts.slice(0, 10);
        return availableProducts
            .filter((p) => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)))
            .slice(0, 10);
    }

    function selectProduct(index: number, product: ProductOption) {
        items[index].product_id = product.id;
        items[index].product_name = product.name;
        items[index].search_query = product.name;
        if (product.cost_price && !items[index].unit_cost) {
            items[index].unit_cost = Number(product.cost_price);
        }
    }

    function handleSearchInput(index: number, value: string) {
        items[index].search_query = value;
        items[index].product_name = value;
        items[index].product_id = null;

        const match = availableProducts.find(
            (p) => p.name.toLowerCase().trim() === value.toLowerCase().trim()
        );
        if (match) {
            items[index].product_id = match.id;
            if (match.cost_price && !items[index].unit_cost) {
                items[index].unit_cost = Number(match.cost_price);
            }
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
            const cost = Number(it.unit_cost) || 0;
            total += it.quantity * cost;
        }
        return total;
    });

    function resetForm() {
        selectedSupplierId = '';
        notes = '';
        errorMessage = null;
        items = [
            {
                id: 'item_' + Date.now(),
                product_id: null,
                product_name: '',
                quantity: 1,
                unit_cost: '',
                search_query: '',
            },
        ];
    }

    function closeModal() {
        open = false;
        resetForm();
    }

    function submitOrder(e: Event) {
        e.preventDefault();

        if (!selectedSupplierId) {
            errorMessage = 'Por favor selecciona un proveedor.';
            return;
        }

        const validItems = items.filter((it) => it.product_name.trim() !== '');

        if (validItems.length === 0) {
            errorMessage = 'Debes incluir al menos un producto con nombre válido.';
            return;
        }

        for (const it of validItems) {
            if (it.unit_cost === '' || Number(it.unit_cost) <= 0) {
                errorMessage = `El producto "${it.product_name}" debe tener un costo unitario válido mayor a 0.`;
                return;
            }
        }

        isSubmitting = true;
        errorMessage = null;

        router.post(
            '/tenant/suppliers/orders',
            {
                supplier_id: selectedSupplierId,
                notes: notes,
                items: validItems.map((it) => ({
                    product_id: it.product_id,
                    product_name: it.product_name,
                    quantity: it.quantity,
                    unit_cost: Number(it.unit_cost),
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal();
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    errorMessage = typeof firstError === 'string' ? firstError : 'Ocurrió un error al crear la orden de compra.';
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            }
        );
    }
</script>

{#if open}
    <!-- Backdrop oscuro y accesible -->
    <div
        class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 transition-opacity"
        onclick={closeModal}
        aria-hidden="true"
    ></div>

    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
        <div
            class="relative w-full max-w-3xl rounded-2xl sm:rounded-3xl bg-card border border-border shadow-2xl p-4 sm:p-6 max-h-[85vh] sm:max-h-[88vh] flex flex-col my-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="supplier-order-modal-title"
        >
            <!-- Header Bento (shrink-0) -->
            <div class="flex items-center justify-between border-b pb-3.5 sm:pb-4 shrink-0">
                <div class="flex items-center gap-2.5">
                    <IconContainer size="md" variant="primary" icon="solar:clipboard-list-bold-duotone" />
                    <div>
                        <h2 id="supplier-order-modal-title" class="text-base font-bold text-foreground">
                            Nueva Orden de Compra a Proveedor
                        </h2>
                    </div>
                </div>

                <button
                    type="button"
                    onclick={closeModal}
                    class="rounded-lg p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                    aria-label="Cerrar modal"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Error Banner (shrink-0) -->
            {#if errorMessage}
                <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-center gap-2 shrink-0">
                    <AlertCircle class="size-4 shrink-0" />
                    <span class="font-medium">{errorMessage}</span>
                </div>
            {/if}

            <!-- Form Content (Scrollable) -->
            <form id="create-supplier-order-form" onsubmit={submitOrder} class="space-y-4 overflow-y-auto flex-1 min-h-0 pr-1">
                <!-- Bento Card 1: Proveedor -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <Truck class="size-3.5 text-primary" />
                        Proveedor Destino
                    </h3>

                    <div class="space-y-1">
                        <Label for="supplier-select" class="text-xs font-semibold text-foreground">
                            Seleccionar Proveedor <span class="text-destructive">*</span>
                        </Label>
                        <select
                            id="supplier-select"
                            bind:value={selectedSupplierId}
                            class="h-9 w-full rounded-lg border border-input bg-background px-3 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary outline-none"
                            required
                        >
                            <option value="">Seleccionar proveedor de la lista...</option>
                            {#each suppliers as sup}
                                <option value={sup.id}>{sup.name} {sup.contact_number ? `(${sup.contact_number})` : ''}</option>
                            {/each}
                        </select>
                    </div>
                </div>

                <!-- Bento Card 2: Productos -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3.5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary" />
                            Productos a Solicitar
                        </h3>

                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            class="h-8 text-xs gap-1 font-semibold rounded-xl"
                            onclick={addItem}
                        >
                            <Plus class="size-3.5" />
                            Añadir producto
                        </Button>
                    </div>

                    <!-- Lista de Productos (Filas Continuas con CSS Grid) -->
                    <div class="pt-1">
                        <div class="divide-y divide-border/50 border border-border/70 rounded-xl bg-card overflow-hidden">
                            {#each items as item, index (item.id || index)}
                                {@const lineSubtotal = item.quantity * (Number(item.unit_cost) || 0)}
                                {@const isProductSelected = !!item.product_name && (!!item.product_id || item.search_query === item.product_name)}
                                {@const matchedProd = item.product_id ? availableProducts.find((p) => p.id === item.product_id) : null}

                                <!-- Fila Desktop (CSS Grid estricto de 12 columnas) -->
                                <div class="hidden sm:grid sm:grid-cols-12 gap-3 items-center p-3 hover:bg-muted/20 transition-colors">
                                    <!-- Columna Producto (6 columnas = doble de espacio) -->
                                    <div class="sm:col-span-6 relative">
                                        {#if isProductSelected}
                                            <!-- Producto seleccionado como texto estático limpio -->
                                            <div class="flex items-center justify-between gap-2.5 rounded-xl bg-background border border-input px-3 py-1.5 min-h-[40px] shadow-2xs">
                                                <div class="min-w-0 flex items-center gap-2.5">
                                                    <IconContainer size="sm" variant="primary" icon="solar:box-minimalistic-bold-duotone" />
                                                    <div class="min-w-0">
                                                        <span class="font-bold text-xs text-foreground truncate block">
                                                            {item.product_name}
                                                        </span>
                                                        {#if matchedProd}
                                                            <span class="text-[10px] text-muted-foreground font-sans tabular-nums truncate block">
                                                                {#if matchedProd.sku}SKU: {matchedProd.sku} • {/if}Stock actual: {matchedProd.stock ?? 0}
                                                            </span>
                                                        {/if}
                                                    </div>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors shrink-0"
                                                    onclick={() => {
                                                        items[index].product_id = null;
                                                        items[index].product_name = '';
                                                        items[index].search_query = '';
                                                    }}
                                                    title="Cambiar producto"
                                                    aria-label="Cambiar producto"
                                                >
                                                    <Edit3 class="size-3.5" />
                                                </button>
                                            </div>
                                        {:else}
                                            <!-- Buscador cuando no está seleccionado -->
                                            <SearchCombobox
                                                value={item.search_query}
                                                items={getFilteredProducts(item.search_query)}
                                                placeholder="Buscar producto en catálogo o tipear..."
                                                showDropdown={true}
                                                onsearch={(val) => handleSearchInput(index, val)}
                                                onselect={(prod) => selectProduct(index, prod)}
                                                onclear={() => {
                                                    items[index].product_id = null;
                                                    items[index].product_name = '';
                                                    items[index].search_query = '';
                                                }}
                                                inputClass="h-10 text-xs font-medium bg-background"
                                            >
                                                {#snippet itemSnippet(prod)}
                                                    <div class="flex items-center justify-between gap-2 w-full">
                                                        <div class="min-w-0">
                                                            <span class="font-medium text-foreground truncate block">{prod.name}</span>
                                                            {#if prod.sku}
                                                                <span class="text-[10px] text-muted-foreground font-sans tabular-nums">SKU: {prod.sku}</span>
                                                            {/if}
                                                        </div>
                                                        <div class="text-[11px] text-muted-foreground font-sans tabular-nums shrink-0 text-right">
                                                            {#if prod.cost_price}
                                                                <span class="font-semibold text-foreground">Costo: {formatPrice(Number(prod.cost_price))}</span>
                                                            {:else}
                                                                <span>Stock: {prod.stock ?? 0}</span>
                                                            {/if}
                                                        </div>
                                                    </div>
                                                {/snippet}
                                            </SearchCombobox>
                                        {/if}
                                    </div>

                                    <!-- Columna Costo Unitario (3 columnas) -->
                                    <div class="sm:col-span-3">
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted-foreground font-sans tabular-nums">$</span>
                                            <Input
                                                id={`prod-cost-${index}`}
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                bind:value={item.unit_cost}
                                                placeholder="0.00"
                                                class="h-10 text-xs pl-7 text-right font-sans tabular-nums font-semibold bg-background border-input"
                                                required
                                            />
                                        </div>
                                    </div>

                                    <!-- Columna Stepper + Subtotal + Ghost Delete (3 columnas) -->
                                    <div class="sm:col-span-3 flex items-center justify-end gap-2">
                                        <!-- Stepper Táctil -->
                                        <div class="flex items-center border rounded-lg bg-background shadow-2xs">
                                            <button
                                                type="button"
                                                class="size-9 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted rounded-l-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                                onclick={() => decrementQuantity(index)}
                                                disabled={item.quantity <= 1}
                                                aria-label="Disminuir cantidad"
                                            >
                                                <Minus class="size-3.5" />
                                            </button>
                                            <input
                                                type="number"
                                                min="1"
                                                bind:value={item.quantity}
                                                class="w-10 h-9 text-xs font-bold font-sans tabular-nums text-center bg-transparent border-0 outline-none p-0"
                                            />
                                            <button
                                                type="button"
                                                class="size-9 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted rounded-r-lg transition-colors"
                                                onclick={() => incrementQuantity(index)}
                                                aria-label="Aumentar cantidad"
                                            >
                                                <Plus class="size-3.5" />
                                            </button>
                                        </div>

                                        <span class="font-sans tabular-nums text-xs sm:text-sm font-bold text-foreground w-20 text-right">
                                            {formatPrice(lineSubtotal)}
                                        </span>

                                        <button
                                            type="button"
                                            class="p-1.5 rounded-lg text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors shrink-0"
                                            onclick={() => removeItem(index)}
                                            aria-label="Eliminar producto"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Fila Mobile (Apilada) -->
                                <div class="block sm:hidden p-3 space-y-2 hover:bg-muted/20 transition-colors">
                                    <!-- Arriba: Producto o Buscador + Botones -->
                                    {#if isProductSelected}
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <IconContainer size="sm" variant="primary" icon="solar:box-minimalistic-bold-duotone" />
                                                <div class="min-w-0">
                                                    <span class="font-bold text-xs text-foreground truncate block">
                                                        {item.product_name}
                                                    </span>
                                                    {#if matchedProd?.sku}
                                                        <span class="text-[10px] text-muted-foreground font-sans tabular-nums">SKU: {matchedProd.sku}</span>
                                                    {/if}
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-1 shrink-0">
                                                <button
                                                    type="button"
                                                    class="p-1.5 text-muted-foreground hover:text-foreground rounded-md transition-colors"
                                                    onclick={() => {
                                                        items[index].product_id = null;
                                                        items[index].product_name = '';
                                                        items[index].search_query = '';
                                                    }}
                                                    aria-label="Editar producto"
                                                >
                                                    <Edit3 class="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="p-1.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-md transition-colors"
                                                    onclick={() => removeItem(index)}
                                                    aria-label="Eliminar producto"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    {:else}
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1">
                                                <SearchCombobox
                                                    value={item.search_query}
                                                    items={getFilteredProducts(item.search_query)}
                                                    placeholder="Buscar o escribir producto..."
                                                    showDropdown={true}
                                                    onsearch={(val) => handleSearchInput(index, val)}
                                                    onselect={(prod) => selectProduct(index, prod)}
                                                    inputClass="h-9 text-xs"
                                                />
                                            </div>
                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-destructive shrink-0"
                                                onclick={() => removeItem(index)}
                                                aria-label="Eliminar fila"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    {/if}

                                    <!-- Abajo: Costo con $ + Stepper + Subtotal (sin labels superiores redundantes) -->
                                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-border/30">
                                        <div class="flex items-center gap-2">
                                            <div class="relative w-24">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[11px] font-semibold text-muted-foreground font-sans tabular-nums">$</span>
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    bind:value={item.unit_cost}
                                                    placeholder="0.00"
                                                    class="h-8 text-xs pl-5 text-right font-sans tabular-nums font-semibold bg-background"
                                                    required
                                                />
                                            </div>

                                            <div class="flex items-center border rounded-lg bg-background">
                                                <button
                                                    type="button"
                                                    class="size-8 flex items-center justify-center text-muted-foreground hover:bg-muted rounded-l-lg transition-colors disabled:opacity-40"
                                                    onclick={() => decrementQuantity(index)}
                                                    disabled={item.quantity <= 1}
                                                >
                                                    <Minus class="size-3" />
                                                </button>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    bind:value={item.quantity}
                                                    class="w-9 h-8 text-xs font-bold font-sans tabular-nums text-center bg-transparent border-0 outline-none p-0"
                                                />
                                                <button
                                                    type="button"
                                                    class="size-8 flex items-center justify-center text-muted-foreground hover:bg-muted rounded-r-lg transition-colors"
                                                    onclick={() => incrementQuantity(index)}
                                                >
                                                    <Plus class="size-3" />
                                                </button>
                                            </div>
                                        </div>

                                        <span class="font-sans tabular-nums text-xs font-bold text-foreground text-right">
                                            {formatPrice(lineSubtotal)}
                                        </span>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </div>
                </div>

                <!-- Bento Card 3: Observaciones -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-1.5">
                    <Label for="supplier-order-notes" class="text-xs font-semibold text-foreground">Notas Adicionales</Label>
                    <textarea
                        id="supplier-order-notes"
                        bind:value={notes}
                        rows="2"
                        placeholder="ej. Entrega pactada para el viernes por la tarde..."
                        class="w-full rounded-lg border border-input bg-background p-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary outline-none resize-none"
                    ></textarea>
                </div>
            </form>

            <!-- Footer Bento Responsive (Mobile apilado vs Desktop horizontal) -->
            <div class="border-t pt-3.5 shrink-0">
                <!-- Footer Mobile (Apilado, Total arriba, Botón full-width abajo, sin botón Cancelar) -->
                <div class="sm:hidden space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground font-medium">Total Estimado:</span>
                        <span class="text-lg font-bold font-sans tabular-nums text-primary">{formatPrice(computedTotal)}</span>
                    </div>
                    <Button
                        type="submit"
                        form="create-supplier-order-form"
                        class="w-full min-h-[44px] gap-2 font-semibold text-sm rounded-xl shadow-xs"
                        disabled={isSubmitting}
                    >
                        {#if isSubmitting}
                            <span>Guardando...</span>
                        {:else}
                            <Check class="size-4" />
                            <span>Crear Orden de Compra</span>
                        {/if}
                    </Button>
                </div>

                <!-- Footer Desktop -->
                <div class="hidden sm:flex sm:items-center sm:justify-between">
                    <div class="flex items-baseline gap-2">
                        <span class="text-xs text-muted-foreground">Total Estimado:</span>
                        <span class="text-xl font-bold font-sans tabular-nums text-primary">{formatPrice(computedTotal)}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button type="button" variant="outline" size="sm" onclick={closeModal} class="min-h-[40px] px-4 rounded-xl text-xs font-medium">
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            form="create-supplier-order-form"
                            size="sm"
                            class="min-h-[40px] px-4 rounded-xl text-xs font-semibold gap-1.5 shadow-xs"
                            disabled={isSubmitting}
                        >
                            {#if isSubmitting}
                                <span>Guardando...</span>
                            {:else}
                                <Check class="size-4" />
                                <span>Crear Orden</span>
                            {/if}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}
