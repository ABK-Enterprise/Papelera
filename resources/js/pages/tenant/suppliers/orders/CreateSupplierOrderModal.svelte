<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
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
        is_dropdown_open: boolean;
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
            is_dropdown_open: false,
        },
    ]);

    function addItem() {
        items = [
            ...items,
            {
                id: 'item_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                product_id: null,
                product_name: '',
                quantity: 1,
                unit_cost: '',
                search_query: '',
                is_dropdown_open: false,
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
                    is_dropdown_open: false,
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
        if (!q) return availableProducts.slice(0, 5);
        return availableProducts.filter((p) => p.name.toLowerCase().includes(q)).slice(0, 5);
    }

    function selectProduct(index: number, product: ProductOption) {
        items[index].product_id = product.id;
        items[index].product_name = product.name;
        items[index].search_query = product.name;
        if (product.cost_price && !items[index].unit_cost) {
            items[index].unit_cost = Number(product.cost_price);
        }
        items[index].is_dropdown_open = false;
    }

    function handleSearchInput(index: number, value: string) {
        items[index].search_query = value;
        items[index].product_name = value;
        items[index].product_id = null;
        items[index].is_dropdown_open = true;

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

    function closeAllDropdowns() {
        for (const it of items) {
            it.is_dropdown_open = false;
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
                is_dropdown_open: false,
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

        const payloadItems: {
            product_id: number | null;
            product_name: string;
            quantity: number;
            unit_cost: number;
        }[] = [];

        for (const it of validItems) {
            payloadItems.push({
                product_id: it.product_id,
                product_name: it.product_name,
                quantity: it.quantity,
                unit_cost: Number(it.unit_cost),
            });
        }

        router.post(
            '/tenant/suppliers/orders',
            {
                supplier_id: Number(selectedSupplierId),
                notes: notes,
                items: payloadItems,
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

<svelte:window onclick={closeAllDropdowns} />

{#if open}
    <!-- Backdrop oscuro accesible -->
    <div
        class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50 transition-opacity"
        onclick={closeModal}
        aria-hidden="true"
    ></div>

    <div class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
        <div
            class="relative w-full max-w-3xl rounded-2xl bg-card border border-border shadow-2xl p-4 sm:p-6 space-y-4 max-h-[92vh] flex flex-col"
            role="dialog"
            aria-modal="true"
            aria-labelledby="supplier-order-modal-title"
        >
            <!-- Header Bento -->
            <div class="flex items-center justify-between border-b pb-3.5 shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20">
                        <Truck class="size-5" />
                    </div>
                    <div>
                        <h2 id="supplier-order-modal-title" class="text-lg font-bold tracking-tight text-foreground">
                            Emitir orden de compra
                        </h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Registra el pedido de reposición de mercadería para actualizar costos e inventario al recibirlo.
                        </p>
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

            <!-- Error Banner -->
            {#if errorMessage}
                <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-center gap-2 shrink-0">
                    <AlertCircle class="size-4 shrink-0" />
                    <span class="font-medium">{errorMessage}</span>
                </div>
            {/if}

            <!-- Form Content (Scrollable) -->
            <form onsubmit={submitOrder} class="space-y-4 overflow-y-auto pr-1 flex-1">
                <!-- Bento Card 1: Proveedor -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <Truck class="size-3.5 text-primary" />
                        Proveedor Destino
                    </h3>

                    <div class="space-y-1">
                        <Label for="supplier-select" class="text-sm font-medium text-foreground">
                            Seleccionar Proveedor <span class="text-destructive">*</span>
                        </Label>
                        <select
                            id="supplier-select"
                            bind:value={selectedSupplierId}
                            class="h-9 w-full rounded-lg border border-input bg-background px-3 text-xs font-normal text-foreground focus:ring-1 focus:ring-primary outline-none"
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
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary" />
                            Productos a Solicitar
                        </h3>

                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            class="h-8 text-xs gap-1 font-medium"
                            onclick={addItem}
                        >
                            <Plus class="size-3.5" />
                            <span>Añadir producto</span>
                        </Button>
                    </div>

                    <!-- Lista de Productos -->
                    <div class="space-y-2.5">
                        {#each items as item, index (item.id || index)}
                            <div
                                class="flex flex-col gap-2 p-3 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs relative"
                                onclick={(e) => e.stopPropagation()}
                            >
                                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">
                                    <!-- Input / Dropdown Buscador -->
                                    <div class="sm:col-span-6 relative">
                                        <Label for={`prod-name-${index}`} class="text-xs font-medium text-muted-foreground sm:hidden mb-1 block">
                                            Producto
                                        </Label>
                                        <Input
                                            id={`prod-name-${index}`}
                                            type="text"
                                            value={item.search_query}
                                            oninput={(e) => handleSearchInput(index, (e.target as HTMLInputElement).value)}
                                            onfocus={() => {
                                                closeAllDropdowns();
                                                item.is_dropdown_open = true;
                                            }}
                                            placeholder="Buscar producto o escribir nuevo..."
                                            class="h-8 text-xs font-normal"
                                        />

                                        <!-- Autocomplete flotante -->
                                        {#if item.is_dropdown_open}
                                            <div
                                                class="absolute left-0 right-0 top-full mt-1 bg-popover text-popover-foreground rounded-lg border shadow-lg z-50 max-h-48 overflow-y-auto"
                                            >
                                                {#each getFilteredProducts(item.search_query) as prod}
                                                    <button
                                                        type="button"
                                                        class="w-full px-3 py-1.5 text-left text-xs hover:bg-muted transition-colors flex items-center justify-between gap-2 border-b last:border-0"
                                                        onclick={() => selectProduct(index, prod)}
                                                    >
                                                        <span class="font-medium truncate">{prod.name}</span>
                                                        <div class="text-xs text-muted-foreground font-mono shrink-0 tabular-nums">
                                                            {#if prod.cost_price}
                                                                <span>Costo: {formatPrice(Number(prod.cost_price))}</span>
                                                            {:else}
                                                                <span>Stock: {prod.stock ?? 0}</span>
                                                            {/if}
                                                        </div>
                                                    </button>
                                                {/each}
                                            </div>
                                        {/if}
                                    </div>

                                    <!-- Costo Unitario -->
                                    <div class="sm:col-span-3">
                                        <Label for={`prod-cost-${index}`} class="text-xs font-medium text-muted-foreground sm:hidden mb-1 block">
                                            Costo Unit.
                                        </Label>
                                        <div class="relative">
                                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-muted-foreground font-mono">$</span>
                                            <Input
                                                id={`prod-cost-${index}`}
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                bind:value={item.unit_cost}
                                                placeholder="0.00"
                                                class="h-8 text-xs pl-6 font-mono tabular-nums text-right"
                                                required
                                            />
                                        </div>
                                    </div>

                                    <!-- Stepper Cantidad & Botón Eliminar -->
                                    <div class="sm:col-span-3 flex items-center justify-between sm:justify-end gap-2">
                                        <div class="flex items-center border rounded-lg bg-background shadow-2xs">
                                            <button
                                                type="button"
                                                class="p-1 text-muted-foreground hover:text-foreground hover:bg-muted rounded-l-lg transition-colors"
                                                onclick={() => decrementQuantity(index)}
                                                disabled={item.quantity <= 1}
                                                aria-label="Disminuir cantidad"
                                            >
                                                <Minus class="size-3" />
                                            </button>
                                            <span class="px-2 text-xs font-semibold font-mono tabular-nums min-w-6 text-center">
                                                {item.quantity}
                                            </span>
                                            <button
                                                type="button"
                                                class="p-1 text-muted-foreground hover:text-foreground hover:bg-muted rounded-r-lg transition-colors"
                                                onclick={() => incrementQuantity(index)}
                                                aria-label="Aumentar cantidad"
                                            >
                                                <Plus class="size-3" />
                                            </button>
                                        </div>

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
                            </div>
                        {/each}
                    </div>
                </div>

                <!-- Bento Card 3: Observaciones -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-1.5">
                    <Label for="supplier-order-notes" class="text-sm font-medium text-foreground">Notas Adicionales</Label>
                    <textarea
                        id="supplier-order-notes"
                        bind:value={notes}
                        rows="2"
                        placeholder="ej. Entrega pactada para el viernes por la tarde..."
                        class="w-full rounded-lg border border-input bg-background p-2.5 text-xs text-foreground placeholder:text-muted-foreground focus:ring-1 focus:ring-primary outline-none resize-none"
                    ></textarea>
                </div>
            </form>

            <!-- Footer Bento: Total Estimado & Botones -->
            <div class="flex items-center justify-between border-t pt-3.5 shrink-0">
                <div class="flex items-baseline gap-2">
                    <span class="text-xs text-muted-foreground">Total Estimado:</span>
                    <span class="text-xl font-bold font-mono tabular-nums text-primary">{formatPrice(computedTotal)}</span>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onclick={closeModal} class="text-xs font-medium">
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="gap-1.5 font-medium text-xs"
                        disabled={isSubmitting}
                        onclick={(e) => {
                            const form = document.querySelector('form');
                            if (form) form.requestSubmit();
                        }}
                    >
                        {#if isSubmitting}
                            <span>Guardando...</span>
                        {:else}
                            <Check class="size-4" />
                            <span>Emitir orden</span>
                        {/if}
                    </Button>
                </div>
            </div>
        </div>
    </div>
{/if}
