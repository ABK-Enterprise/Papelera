<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import HighlightMatch from '@/components/common/HighlightMatch.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';

    import { router } from '@inertiajs/svelte';
    import { fade, scale } from 'svelte/transition';
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
        sale_type?: string;
        cost_price?: string | number | null;
        price?: string | number;
        stock?: number;
        units_per_package?: number;
    }

    interface SupplierOrderItemLine {
        id: string;
        product_id: number | null;
        product_name: string;
        sale_type?: string;
        quantity: number;
        unit_cost: number | '';
        search_query: string;
        is_dropdown_open?: boolean;
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
            sale_type: undefined,
            quantity: 1,
            unit_cost: '',
            search_query: '',
            is_dropdown_open: false,
        },
    ]);

    function hasEmptyProductRow(): boolean {
        return items.some((it) => !it.product_name.trim() && !it.search_query.trim());
    }

    function addItem() {
        if (hasEmptyProductRow()) {
            return;
        }
        items = [
            ...items,
            {
                id: 'item_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                product_id: null,
                product_name: '',
                sale_type: undefined,
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
                    sale_type: undefined,
                    quantity: 1,
                    unit_cost: '',
                    search_query: '',
                    is_dropdown_open: false,
                },
            ];
        }
    }

    function incrementQuantity(index: number) {
        const isWeight = items[index].sale_type === 'weight';
        const current = Number(items[index].quantity) || 0;
        if (isWeight) {
            if (current < 10000) {
                items[index].quantity = Number((current + 0.1).toFixed(2));
            }
        } else {
            if (current < 10000) {
                items[index].quantity = Math.min(10000, Math.floor(current) + 1);
            }
        }
    }

    function decrementQuantity(index: number) {
        const isWeight = items[index].sale_type === 'weight';
        const current = Number(items[index].quantity) || 0;
        if (isWeight) {
            if (current > 0.1) {
                items[index].quantity = Number((current - 0.1).toFixed(2));
            } else if (current > 0.01) {
                items[index].quantity = 0.01;
            }
        } else {
            if (current > 1) {
                items[index].quantity = Math.max(1, Math.floor(current) - 1);
            }
        }
    }

    function getFilteredProducts(query: string): ProductOption[] {
        const q = query.trim().toLowerCase();
        if (!q) return availableProducts.slice(0, 6);
        return availableProducts
            .filter((p) => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)))
            .slice(0, 6);
    }

    function selectProduct(index: number, product: ProductOption) {
        items[index].product_id = product.id;
        items[index].product_name = product.name;
        items[index].search_query = product.name;
        items[index].sale_type = product.sale_type;
        items[index].is_dropdown_open = false;

        // Cargar automáticamente el precio/costo del producto seleccionado
        if (product.cost_price !== undefined && product.cost_price !== null && product.cost_price !== '') {
            items[index].unit_cost = Number(product.cost_price);
        } else if (product.price !== undefined && product.price !== null && product.price !== '') {
            items[index].unit_cost = Number(product.price);
        } else {
            items[index].unit_cost = '';
        }

        if (product.sale_type === 'weight' && (!items[index].quantity || items[index].quantity <= 0)) {
            items[index].quantity = 1;
        }
    }

    function handleSearchInput(index: number, value: string) {
        items[index].search_query = value;
        items[index].product_name = value;
        items[index].product_id = null;
        items[index].sale_type = undefined;
        items[index].is_dropdown_open = true;

        const match = availableProducts.find(
            (p) => p.name.toLowerCase().trim() === value.toLowerCase().trim()
        );
        if (match) {
            items[index].product_id = match.id;
            items[index].sale_type = match.sale_type;
            if (match.cost_price !== undefined && match.cost_price !== null && match.cost_price !== '') {
                items[index].unit_cost = Number(match.cost_price);
            } else if (match.price !== undefined && match.price !== null && match.price !== '') {
                items[index].unit_cost = Number(match.price);
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
            '/tenant/proveedores/ordenes',
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

{#if open}
    <!-- Backdrop oscuro accesible -->
    <div
        transition:fade={{ duration: 150 }}
        class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50"
        onclick={closeModal}
        aria-hidden="true"
    ></div>

    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto pointer-events-none">
        <div
            transition:scale={{ start: 0.97, duration: 150 }}
            class="relative w-full max-w-3xl rounded-2xl sm:rounded-3xl bg-card border border-border shadow-2xl p-4 sm:p-6 max-h-[85vh] sm:max-h-[88vh] flex flex-col my-auto pointer-events-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="supplier-order-modal-title"
        >
            <!-- Header Bento (shrink-0) -->
            <div class="flex items-start justify-between gap-3 border-b pb-3.5 sm:pb-4 shrink-0">
                <div class="min-w-0 flex-1 flex items-start gap-2.5">
                    <div class="shrink-0 mt-0.5">
                        <IconContainer size="md" variant="primary" icon="solar:clipboard-list-bold-duotone" />
                    </div>
                    <h2 id="supplier-order-modal-title" class="text-base sm:text-lg font-bold text-foreground leading-snug line-clamp-2 break-words">
                        Nueva Orden de Compra a Proveedor
                    </h2>
                </div>

                <button
                    type="button"
                    onclick={closeModal}
                    class="size-10 min-h-[40px] min-w-[40px] rounded-xl flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer shrink-0"
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
                    <h3 class="min-w-0 flex-1 flex items-start gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground break-words">
                        <Truck class="size-3.5 text-primary shrink-0 mt-0.5" />
                        <span>Proveedor Destino</span>
                    </h3>

                    <div class="space-y-1">
                        <Label for="supplier-select" class="text-xs font-semibold text-foreground">
                            Seleccionar Proveedor <span class="text-destructive">*</span>
                        </Label>
                        <select
                            id="supplier-select"
                            bind:value={selectedSupplierId}
                            class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
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
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                        <h3 class="min-w-0 flex-1 text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary shrink-0" />
                            <span class="truncate">Productos a Solicitar</span>
                        </h3>

                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            class="w-full sm:w-auto min-h-[40px] sm:h-8 text-xs gap-1.5 font-semibold shrink-0 justify-center shadow-2xs"
                            onclick={addItem}
                            disabled={hasEmptyProductRow()}
                        >
                            <Plus class="size-3.5 shrink-0" />
                            <span>Añadir producto</span>
                        </Button>
                    </div>

                    <!-- Lista de Productos -->
                    <div class="space-y-2.5">
                        {#each items as item, index (item.id || index)}
                            {@const lineSubtotal = (Number(item.quantity) || 0) * (Number(item.unit_cost) || 0)}
                            {@const isWeight = item.sale_type === 'weight'}
                            <div
                                class="flex flex-col gap-2.5 p-3.5 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs relative {item.is_dropdown_open ? 'z-40' : 'z-10'}"
                                onclick={(e) => e.stopPropagation()}
                            >
                                <!-- Fila 1: Buscador de Producto + Botón Eliminar -->
                                <div class="flex items-center justify-between gap-2.5">
                                    <div class="flex-1 min-w-0 relative">
                                        <SearchCombobox
                                            value={item.search_query}
                                            items={getFilteredProducts(item.search_query)}
                                            placeholder="Buscar producto por nombre o SKU..."
                                            showDropdown={true}
                                            onsearch={(val) => handleSearchInput(index, val)}
                                            onselect={(prod) => selectProduct(index, prod)}
                                            onclear={() => {
                                                items[index].product_id = null;
                                                items[index].product_name = '';
                                                items[index].search_query = '';
                                                items[index].sale_type = undefined;
                                                items[index].unit_cost = '';
                                                items[index].is_dropdown_open = false;
                                            }}
                                            inputClass="h-9 sm:h-10 text-xs font-medium"
                                        >
                                            {#snippet itemSnippet(prod)}
                                                <div class="flex items-start justify-between gap-2.5 w-full text-left py-0.5">
                                                    <div class="min-w-0 flex-1 space-y-0.5">
                                                        <div class="flex items-start gap-1.5 flex-wrap">
                                                            <span class="font-semibold text-foreground text-xs leading-snug line-clamp-2 break-words">
                                                                <HighlightMatch text={prod.name} query={item.search_query} />
                                                            </span>
                                                            {#if prod.sale_type === 'weight'}
                                                                <span class="text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 px-1.5 py-0.5 rounded shrink-0">Kg</span>
                                                            {/if}
                                                        </div>
                                                    </div>
                                                    <div class="text-[11px] text-muted-foreground font-sans tabular-nums shrink-0 text-right min-w-[70px] pl-1">
                                                        {#if prod.cost_price}
                                                            <span class="font-bold text-foreground block">{formatPrice(Number(prod.cost_price))}</span>
                                                        {:else}
                                                            <span class="block">Stock: {prod.stock ?? 0}</span>
                                                        {/if}
                                                    </div>
                                                </div>
                                            {/snippet}

                                        </SearchCombobox>
                                    </div>

                                    <button
                                        type="button"
                                        class="size-10 min-h-[40px] min-w-[40px] rounded-xl flex items-center justify-center text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors shrink-0"
                                        onclick={() => removeItem(index)}
                                        aria-label="Eliminar producto"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>

                                <!-- Separador Sutil -->
                                <div class="border-t border-border/50"></div>

                                <!-- Fila 2: Costo Unitario, Stepper Cantidad y Subtotal -->
                                <!-- SECCIÓN CONTROLES: MOBILE (< sm) -->
                                <div class="flex flex-col gap-2.5 sm:hidden w-full pt-1">
                                    <div class="grid grid-cols-2 gap-3 w-full">
                                        <!-- Costo Unitario Mobile -->
                                        <div class="flex flex-col gap-1 w-full">
                                            <Label for={`prod-cost-mob-${index}`} class="text-[11px] font-semibold text-muted-foreground">
                                                Costo Unit:
                                            </Label>
                                            <div class="relative w-full">
                                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-muted-foreground font-sans tabular-nums">$</span>
                                                <Input
                                                    id={`prod-cost-mob-${index}`}
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    max="100000000"
                                                    bind:value={item.unit_cost}
                                                    placeholder="0.00"
                                                    class="h-10 text-xs pl-6 font-sans tabular-nums text-right rounded-xl w-full"
                                                    required
                                                />
                                            </div>
                                        </div>

                                        <!-- Cantidad Mobile -->
                                        <div class="flex flex-col gap-1 w-full">
                                            <div class="flex items-center justify-between text-[11px] font-semibold text-muted-foreground">
                                                <Label>Cant:</Label>
                                                <span>{isWeight ? 'kg' : 'unid.'}</span>
                                            </div>
                                            <div class="flex items-center border rounded-xl bg-background shadow-2xs h-10 w-full overflow-hidden justify-between">
                                                <button
                                                    type="button"
                                                    class="size-10 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 disabled:cursor-not-allowed shrink-0"
                                                    onclick={() => decrementQuantity(index)}
                                                    disabled={item.quantity <= (isWeight ? 0.01 : 1)}
                                                    aria-label="Disminuir cantidad"
                                                >
                                                    <Minus class="size-3.5" />
                                                </button>
                                                <input
                                                    type="number"
                                                    step={isWeight ? '0.01' : '1'}
                                                    min={isWeight ? '0.01' : '1'}
                                                    max="10000"
                                                    maxlength="5"
                                                    bind:value={item.quantity}
                                                    oninput={(e) => {
                                                        const val = (e.target as HTMLInputElement).value;
                                                        if (val.length > 5) (e.target as HTMLInputElement).value = val.slice(0, 5);
                                                        if (Number(val) > 10000) item.quantity = 10000;
                                                    }}
                                                    onblur={() => {
                                                        let val = Number(item.quantity);
                                                        if (isNaN(val) || val < (isWeight ? 0.01 : 1)) {
                                                            item.quantity = isWeight ? 0.01 : 1;
                                                        } else if (val > 10000) {
                                                            item.quantity = 10000;
                                                        } else {
                                                            item.quantity = isWeight ? Math.round(val * 100) / 100 : Math.floor(val);
                                                        }
                                                    }}
                                                    onpaste={(e) => {
                                                        const pasted = e.clipboardData?.getData('text') || '';
                                                        const cleaned = parseFloat(pasted.replace(/[^0-9.]/g, ''));
                                                        if (!isNaN(cleaned)) {
                                                            e.preventDefault();
                                                            item.quantity = Math.max(isWeight ? 0.01 : 1, Math.min(cleaned, 10000));
                                                        }
                                                    }}
                                                    class="w-14 h-full text-xs font-bold font-sans tabular-nums text-center bg-transparent border-0 focus:ring-0 focus:outline-none p-0 flex-1 min-w-0"
                                                />
                                                <button
                                                    type="button"
                                                    class="size-10 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 shrink-0"
                                                    onclick={() => incrementQuantity(index)}
                                                    disabled={item.quantity >= 10000}
                                                    aria-label="Aumentar cantidad"
                                                >
                                                    <Plus class="size-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="w-full flex items-center justify-between pt-2 border-t border-border/40 text-xs">
                                        <span class="text-muted-foreground font-medium">Subtotal Estimado:</span>
                                        <span class="font-sans tabular-nums text-sm font-bold text-foreground">{formatPrice(lineSubtotal)}</span>
                                    </div>
                                </div>

                                <!-- SECCIÓN CONTROLES: DESKTOP (>= sm) -->
                                <div class="hidden sm:flex sm:items-center justify-between gap-4 w-full pt-1">
                                    <div class="flex items-center gap-4">
                                        <!-- Costo Unitario Desktop -->
                                        <div class="flex items-center gap-2">
                                            <Label for={`prod-cost-desk-${index}`} class="text-[11px] font-semibold text-muted-foreground shrink-0">
                                                Costo Unit:
                                            </Label>
                                            <div class="relative w-32">
                                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-muted-foreground font-sans tabular-nums">$</span>
                                                <Input
                                                    id={`prod-cost-desk-${index}`}
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    max="100000000"
                                                    bind:value={item.unit_cost}
                                                    placeholder="0.00"
                                                    class="h-9 text-xs pl-6 font-sans tabular-nums text-right"
                                                    required
                                                />
                                            </div>
                                        </div>

                                        <!-- Cantidad Desktop -->
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <Label class="text-[11px] font-semibold text-muted-foreground shrink-0">
                                                    Cant:
                                                </Label>
                                                <div class="flex items-center border rounded-lg bg-background shadow-2xs h-9 overflow-hidden">
                                                    <button
                                                        type="button"
                                                        class="size-9 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 disabled:cursor-not-allowed shrink-0"
                                                        onclick={() => decrementQuantity(index)}
                                                        disabled={item.quantity <= (isWeight ? 0.01 : 1)}
                                                        aria-label="Disminuir cantidad"
                                                    >
                                                        <Minus class="size-3.5" />
                                                    </button>
                                                    <input
                                                        type="number"
                                                        step={isWeight ? '0.01' : '1'}
                                                        min={isWeight ? '0.01' : '1'}
                                                        max="10000"
                                                        maxlength="5"
                                                        bind:value={item.quantity}
                                                        oninput={(e) => {
                                                            const val = (e.target as HTMLInputElement).value;
                                                            if (val.length > 5) (e.target as HTMLInputElement).value = val.slice(0, 5);
                                                            if (Number(val) > 10000) item.quantity = 10000;
                                                        }}
                                                        onblur={() => {
                                                            let val = Number(item.quantity);
                                                            if (isNaN(val) || val < (isWeight ? 0.01 : 1)) {
                                                                item.quantity = isWeight ? 0.01 : 1;
                                                            } else if (val > 10000) {
                                                                item.quantity = 10000;
                                                            } else {
                                                                item.quantity = isWeight ? Math.round(val * 100) / 100 : Math.floor(val);
                                                            }
                                                        }}
                                                        onpaste={(e) => {
                                                            const pasted = e.clipboardData?.getData('text') || '';
                                                            const cleaned = parseFloat(pasted.replace(/[^0-9.]/g, ''));
                                                            if (!isNaN(cleaned)) {
                                                                e.preventDefault();
                                                                item.quantity = Math.max(isWeight ? 0.01 : 1, Math.min(cleaned, 10000));
                                                            }
                                                        }}
                                                        class="w-16 h-full text-xs font-bold font-sans tabular-nums text-center bg-transparent border-0 focus:ring-0 focus:outline-none p-0"
                                                    />
                                                    <button
                                                        type="button"
                                                        class="size-9 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 shrink-0"
                                                        onclick={() => incrementQuantity(index)}
                                                        disabled={item.quantity >= 10000}
                                                        aria-label="Aumentar cantidad"
                                                    >
                                                        <Plus class="size-3.5" />
                                                    </button>
                                                </div>
                                                <span class="text-[11px] font-medium text-muted-foreground whitespace-nowrap">
                                                    {isWeight ? 'cant. kg' : 'cant. unid.'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Subtotal Desktop -->
                                    <div class="flex items-center gap-1.5 ml-auto shrink-0">
                                        <span class="text-[11px] text-muted-foreground">Subtotal:</span>
                                        <span class="font-sans tabular-nums text-sm font-bold text-foreground">
                                            {formatPrice(lineSubtotal)}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>

                </div>

                <!-- Bento Card 3: Observaciones -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-2">
                    <Label for="supplier-order-notes" class="text-xs font-semibold text-foreground break-words">
                        Observaciones / Notas de Entrega <span class="text-muted-foreground font-normal whitespace-nowrap">(Opcional)</span>
                    </Label>
                    <textarea
                        id="supplier-order-notes"
                        bind:value={notes}
                        rows="2"
                        placeholder="ej. Entrega pactada para el viernes por la tarde..."
                        class="w-full rounded-xl border border-border/80 bg-background p-3 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary resize-none"
                    ></textarea>

                </div>
            </form>

            <!-- Footer Bento: Total Estimado & Botones (shrink-0) -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 border-t border-border/70 pt-4 shrink-0 w-full">
                <div class="flex flex-wrap items-baseline justify-between sm:justify-start gap-2 w-full sm:w-auto">
                    <span class="text-xs font-semibold text-muted-foreground">Total Estimado:</span>
                    <span class="text-xl sm:text-2xl font-bold font-sans tabular-nums text-primary break-all">{formatPrice(computedTotal)}</span>
                </div>

                <div class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:w-auto sm:items-center">
                    <Button type="button" variant="outline" class="min-h-[44px] px-5 rounded-xl text-xs sm:text-sm font-semibold justify-center w-full sm:w-auto whitespace-nowrap shadow-xs" onclick={closeModal}>
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        form="create-supplier-order-form"
                        class="min-h-[44px] px-5 rounded-xl text-xs sm:text-sm font-semibold justify-center w-full sm:w-auto whitespace-nowrap shadow-xs gap-1.5"
                        disabled={isSubmitting}
                    >
                        {#if isSubmitting}
                            <span>Guardando...</span>
                        {:else}
                            <Check class="size-4 shrink-0" />
                            <span>Crear Orden</span>
                        {/if}
                    </Button>
                </div>
            </div>
        </div>
    </div>
{/if}
