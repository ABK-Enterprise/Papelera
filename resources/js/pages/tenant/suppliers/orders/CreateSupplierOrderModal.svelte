<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import HighlightMatch from '@/components/common/HighlightMatch.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';

    import { router } from '@inertiajs/svelte';
    import { fade, scale } from 'svelte/transition';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Check from 'lucide-svelte/icons/check';
    import Layers from 'lucide-svelte/icons/layers';
    import Minus from 'lucide-svelte/icons/minus';
    import MoreVertical from 'lucide-svelte/icons/more-vertical';
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
        is_on_the_fly?: boolean;
        product_name: string;
        sku?: string | null;
        sale_type?: string;
        quantity: number;
        unit_cost: number | '';
    }

    let {
        open = $bindable(false),
        suppliers = [],
        availableProducts = [],
    }: {
        open: boolean;
        suppliers: SupplierOption[];
        availableProducts?: ProductOption[];
    } = $props();

    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    let selectedSupplierId = $state('');
    let notes = $state('');

    let searchQuery = $state('');
    let selectedProductToAdd = $state<ProductOption | null>(null);
    let items = $state<SupplierOrderItemLine[]>([]);

    function getFilteredProducts(query: string): ProductOption[] {
        const q = query.trim().toLowerCase();
        if (!q) return availableProducts.slice(0, 10);
        return availableProducts
            .filter((p) => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)))
            .slice(0, 10);
    }

    let searchResults = $derived.by(() => getFilteredProducts(searchQuery));

    function selectProductFromSearch(prod: ProductOption) {
        addSelectedProduct(prod);
    }

    function addOnTheFlyProduct(name: string) {
        const trimmedName = name.trim();
        if (!trimmedName) return;
        errorMessage = null;

        const existingIndex = items.findIndex((it) => it.product_name.toLowerCase() === trimmedName.toLowerCase());
        if (existingIndex !== -1) {
            incrementQuantity(existingIndex);
        } else {
            items = [
                {
                    id: 'item_otf_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                    product_id: null,
                    is_on_the_fly: true,
                    product_name: trimmedName,
                    sku: null,
                    sale_type: 'unit',
                    quantity: 1,
                    unit_cost: '',
                },
                ...items,
            ];
        }

        searchQuery = '';
        selectedProductToAdd = null;
    }

    function addSelectedProduct(prod?: ProductOption) {
        const target = prod || selectedProductToAdd || (searchResults.length > 0 ? searchResults[0] : null);
        if (!target) {
            if (searchQuery.trim()) {
                addOnTheFlyProduct(searchQuery.trim());
            }
            return;
        }

        const existingIndex = items.findIndex((it) => it.product_id === target.id);
        if (existingIndex !== -1) {
            incrementQuantity(existingIndex);
        } else {
            let initialCost: number | '' = '';
            if (target.cost_price !== undefined && target.cost_price !== null && target.cost_price !== '') {
                initialCost = Number(target.cost_price);
            } else if (target.price !== undefined && target.price !== null && target.price !== '') {
                initialCost = Number(target.price);
            }

            items = [
                {
                    id: 'item_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                    product_id: target.id,
                    product_name: target.name,
                    sku: target.sku ?? null,
                    sale_type: target.sale_type,
                    quantity: 1,
                    unit_cost: initialCost,
                },
                ...items,
            ];
        }

        searchQuery = '';
        selectedProductToAdd = null;
        errorMessage = null;
    }

    function removeItem(index: number) {
        items = items.filter((_, i) => i !== index);
    }

    function incrementQuantity(index: number) {
        if (!items[index]) return;
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
        if (!items[index]) return;
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
            const qty = Number(it.quantity) || 0;
            total += qty * cost;
        }
        return total;
    });

    function resetForm() {
        selectedSupplierId = '';
        notes = '';
        errorMessage = null;
        searchQuery = '';
        selectedProductToAdd = null;
        items = [];
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

        if (items.length === 0) {
            errorMessage = 'Debes añadir al menos un producto a la orden de compra.';
            return;
        }

        for (const it of items) {
            if (!it.product_name || !it.product_name.trim()) {
                errorMessage = 'Todos los productos deben tener un nombre válido.';
                return;
            }
            if (it.unit_cost === '' || Number(it.unit_cost) <= 0) {
                errorMessage = `El producto "${it.product_name}" debe tener un costo unitario válido mayor a 0.`;
                return;
            }
            if (!it.quantity || Number(it.quantity) <= 0) {
                errorMessage = `El producto "${it.product_name}" debe tener una cantidad válida mayor a 0.`;
                return;
            }
        }

        isSubmitting = true;
        errorMessage = null;

        const payloadItems = items.map((it) => ({
            product_id: it.product_id || null,
            product_name: it.product_name.trim(),
            quantity: Number(it.quantity),
            unit_cost: Number(it.unit_cost),
        }));

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
                        <Label for="supplier-select" class="text-sm font-medium text-foreground">
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
                    <div class="flex items-center justify-between gap-2.5">
                        <h3 class="min-w-0 flex-1 text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary shrink-0" />
                            <span class="truncate">Productos a Solicitar</span>
                        </h3>
                        {#if items.length > 0}
                            <span class="text-xs font-medium text-muted-foreground font-sans tabular-nums">
                                {items.length} {items.length === 1 ? 'producto' : 'productos'}
                            </span>
                        {/if}
                    </div>

                    <!-- Buscador Global de Productos con SearchCombobox -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-start gap-2.5 w-full">
                        <div class="w-full flex-1 min-w-0">
                            <SearchCombobox
                                bind:value={searchQuery}
                                items={searchResults}
                                filterLocally={false}
                                placeholder="Buscar producto por nombre o SKU..."
                                showDropdown={true}
                                onsearch={(q) => (searchQuery = q)}
                                onselect={selectProductFromSearch}
                                onclear={() => {
                                    searchQuery = '';
                                    selectedProductToAdd = null;
                                }}
                                inputClass="h-10 text-xs font-medium w-full"
                            >
                                {#snippet itemSnippet(prod)}
                                    <div class="flex flex-col gap-1 w-full text-left py-1">
                                        <!-- Fila superior: Título y Precio -->
                                        <div class="flex justify-between items-start gap-2 w-full">
                                            <span class="font-semibold text-foreground text-xs leading-snug min-w-0 flex-1 line-clamp-2 break-words">
                                                <HighlightMatch text={prod.name} query={searchQuery} />
                                            </span>
                                            <div class="shrink-0 text-right font-sans tabular-nums font-bold min-w-[70px] text-xs text-foreground">
                                                {#if prod.cost_price !== undefined && prod.cost_price !== null && prod.cost_price !== ''}
                                                    {formatPrice(Number(prod.cost_price))}
                                                {:else if prod.price !== undefined && prod.price !== null && prod.price !== ''}
                                                    {formatPrice(Number(prod.price))}
                                                {:else}
                                                    <span class="text-muted-foreground font-normal text-xs">$ 0,00</span>
                                                {/if}
                                            </div>
                                        </div>
                                        <!-- Fila inferior: Metadatos -->
                                        <div class="flex items-center gap-2 flex-wrap text-xs text-muted-foreground">
                                            {#if prod.sale_type === 'weight'}
                                                <span class="font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-1.5 py-0.5 rounded shrink-0">
                                                    Kg
                                                </span>
                                            {:else}
                                                <span class="font-medium bg-muted px-1.5 py-0.5 rounded text-muted-foreground shrink-0">
                                                    Unidad
                                                </span>
                                            {/if}
                                            {#if prod.sku}
                                                <span class="tabular-nums">
                                                    SKU: <HighlightMatch text={prod.sku} query={searchQuery} />
                                                </span>
                                            {/if}
                                            <span>
                                                Stock: <strong class="text-foreground font-sans tabular-nums">{prod.stock ?? 0} {prod.sale_type === 'weight' ? 'kg' : 'u'}</strong>
                                            </span>
                                        </div>
                                    </div>
                                {/snippet}
                                {#snippet emptySnippet()}
                                    <div class="flex flex-col gap-2 p-2 w-full text-left">
                                        <span class="text-xs text-muted-foreground italic">
                                            No se encontraron productos coincidentes.
                                        </span>
                                        {#if searchQuery.trim()}
                                            <button
                                                type="button"
                                                class="flex items-center gap-1.5 text-xs text-primary font-semibold hover:underline cursor-pointer text-left"
                                                onclick={() => addOnTheFlyProduct(searchQuery.trim())}
                                            >
                                                <Plus class="size-3.5 shrink-0" />
                                                <span>Crear producto "{searchQuery.trim()}" al vuelo</span>
                                            </button>
                                        {/if}
                                    </div>
                                {/snippet}
                            </SearchCombobox>
                            {#if searchQuery.trim() && !searchResults.some((p) => p.name.toLowerCase() === searchQuery.trim().toLowerCase())}
                                <button
                                    type="button"
                                    class="mt-1.5 flex items-center gap-1.5 text-xs text-primary font-medium hover:underline cursor-pointer text-left"
                                    onclick={() => addOnTheFlyProduct(searchQuery.trim())}
                                >
                                    <Plus class="size-3.5 shrink-0" />
                                    <span>¿Crear producto nuevo "<strong>{searchQuery.trim()}</strong>" al vuelo?</span>
                                </button>
                            {/if}
                        </div>

                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            class="hidden sm:inline-flex w-full sm:w-auto min-h-[44px] sm:min-h-[40px] px-4 rounded-xl text-xs gap-1.5 font-semibold shrink-0 justify-center shadow-2xs cursor-pointer"
                            onclick={() => addSelectedProduct()}
                            disabled={!selectedProductToAdd && !searchQuery.trim()}
                        >
                            <Plus class="size-4 shrink-0" />
                            <span>Añadir producto</span>
                        </Button>
                    </div>

                    <!-- Lista de Productos Seleccionados -->
                    {#if items.length === 0}
                        <div class="flex flex-col items-center justify-center py-8 px-4 text-center rounded-xl border border-dashed border-border/70 bg-card/40">
                            <Package class="size-8 text-muted-foreground/50 mb-2" />
                            <p class="text-xs font-medium text-foreground">No hay productos añadidos a la orden</p>
                            <p class="text-xs text-muted-foreground mt-0.5">Utiliza el buscador superior para seleccionar productos a solicitar</p>
                        </div>
                    {:else}
                        <div class="space-y-2.5">
                            {#each items as item, index (item.id || index)}
                                {@const lineSubtotal = (Number(item.quantity) || 0) * (Number(item.unit_cost) || 0)}
                                {@const isWeight = item.sale_type === 'weight'}
                                <div class="flex flex-col gap-2.5 p-3.5 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs">
                                    <!-- Fila 1: Texto estático limpio (Nombre, Badges, SKU, Botón Eliminar) -->
                                    <div class="flex items-start justify-between gap-2.5">
                                        <div class="flex items-center gap-2 min-w-0 flex-1 flex-wrap">
                                            <IconContainer
                                                size="sm"
                                                variant={isWeight ? 'info' : 'primary'}
                                                icon={isWeight ? 'solar:scale-bold-duotone' : 'solar:box-minimalistic-bold-duotone'}
                                            />
                                            <div class="flex items-center gap-2 min-w-0 flex-1 flex-wrap">
                                                <span class="text-base font-bold text-foreground leading-snug line-clamp-2 break-words">
                                                    {item.product_name}
                                                </span>
                                                {#if item.is_on_the_fly}
                                                    <span class="text-xs font-semibold bg-primary/10 text-primary px-2 py-0.5 rounded-full border border-primary/20 shrink-0">
                                                        Al vuelo
                                                    </span>
                                                {/if}
                                                {#if item.sku}
                                                    <span class="text-sm tabular-nums bg-muted px-2 py-0.5 rounded text-muted-foreground shrink-0">
                                                        {item.sku}
                                                    </span>
                                                {/if}
                                                {#if isWeight}
                                                    <span class="text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-1.5 py-0.5 rounded shrink-0">
                                                        Kg
                                                    </span>
                                                {/if}
                                            </div>
                                        </div>

                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                {#snippet children(props)}
                                                    <button
                                                        {...props}
                                                        type="button"
                                                        class="size-9 min-h-[36px] min-w-[36px] rounded-xl flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer shrink-0"
                                                        aria-label="Opciones de producto"
                                                    >
                                                        <MoreVertical class="size-4" />
                                                    </button>
                                                {/snippet}
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-44 shadow-lg">
                                                <DropdownMenuItem
                                                    class="flex items-center gap-2 text-xs font-medium text-destructive focus:text-destructive focus:bg-destructive/10 hover:text-destructive hover:bg-destructive/10 cursor-pointer py-2.5"
                                                    onclick={() => removeItem(index)}
                                                >
                                                    <Trash2 class="size-3.5 shrink-0" />
                                                    <span>Quitar de la lista</span>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>

                                    <!-- Separador Sutil -->
                                    <div class="border-t border-border/50"></div>

                                    <!-- Fila 2: Inputs inline (Costo, Cantidad, Subtotal) -->
                                    <div class="flex flex-wrap items-center gap-3 pt-1">
                                        <!-- Costo Unitario -->
                                        <div class="flex items-center gap-2 min-w-0">
                                            <Label for={`prod-cost-${index}`} class="text-sm font-medium text-foreground whitespace-nowrap shrink-0">Costo:</Label>
                                            <div class="relative">
                                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-muted-foreground font-sans tabular-nums font-medium pointer-events-none">$</span>
                                                <Input
                                                    id={`prod-cost-${index}`}
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    max="100000000"
                                                    bind:value={item.unit_cost}
                                                    placeholder="0.00"
                                                    class="h-9 text-sm font-medium font-sans tabular-nums pl-6 text-right rounded-xl w-28 min-w-[96px]"
                                                    required
                                                />
                                            </div>
                                        </div>

                                        <!-- Separador -->
                                        <div class="h-6 w-px bg-border/60 shrink-0 hidden sm:block"></div>

                                        <!-- Cantidad con stepper -->
                                        <div class="flex items-center gap-2 min-w-0">
                                            <Label for={`prod-qty-${index}`} class="text-sm font-medium text-foreground whitespace-nowrap shrink-0">Cant.:</Label>
                                            <div class="flex items-center border rounded-xl bg-background shadow-2xs h-9 overflow-hidden">
                                                <button
                                                    type="button"
                                                    class="size-9 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 disabled:cursor-not-allowed shrink-0 cursor-pointer"
                                                    onclick={() => decrementQuantity(index)}
                                                    disabled={item.quantity <= (isWeight ? 0.01 : 1)}
                                                    aria-label="Disminuir cantidad"
                                                >
                                                    <Minus class="size-3.5" />
                                                </button>
                                                <input
                                                    id={`prod-qty-${index}`}
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
                                                    class="w-12 h-full text-sm font-medium font-sans tabular-nums text-center bg-transparent border-0 focus:ring-0 focus:outline-none p-0"
                                                />
                                                <button
                                                    type="button"
                                                    class="size-9 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 shrink-0 cursor-pointer"
                                                    onclick={() => incrementQuantity(index)}
                                                    disabled={item.quantity >= 10000}
                                                    aria-label="Aumentar cantidad"
                                                >
                                                    <Plus class="size-3.5" />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Subtotal inline a la derecha -->
                                        <div class="flex items-center gap-2 ml-auto shrink-0">
                                            <span class="text-sm text-muted-foreground font-medium whitespace-nowrap">Subtotal:</span>
                                            <span class="text-sm font-bold tabular-nums text-foreground">{formatPrice(lineSubtotal)}</span>
                                        </div>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>

                <!-- Bento Card 3: Observaciones -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-2">
                    <Label for="supplier-order-notes" class="text-sm font-medium text-foreground break-words">
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
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[44px] h-auto py-3 px-4 sm:px-5 rounded-xl text-sm font-medium justify-center w-full sm:w-auto shadow-xs leading-snug text-center"
                        onclick={closeModal}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        form="create-supplier-order-form"
                        class="min-h-[44px] h-auto py-3 px-4 sm:px-5 rounded-xl text-sm font-medium justify-center w-full sm:w-auto shadow-xs leading-snug text-center gap-1.5"
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
