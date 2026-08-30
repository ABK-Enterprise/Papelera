<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import Layers from 'lucide-svelte/icons/layers';
    import Minus from 'lucide-svelte/icons/minus';
    import Package from 'lucide-svelte/icons/package';
    import Plus from 'lucide-svelte/icons/plus';
    import Scale from 'lucide-svelte/icons/scale';
    import Search from 'lucide-svelte/icons/search';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import User from 'lucide-svelte/icons/user';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import X from 'lucide-svelte/icons/x';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';

    export interface ProductOption {
        id: number;
        name: string;
        sku: string | null;
        sale_type?: 'unit' | 'weight';
        price: string | number;
        stock: number;
        units_per_package: number;
        package_quantity?: number;
    }

    export interface CustomerOption {
        id: number;
        name: string;
        tax_id: string | null;
        tax_condition: string;
        phone: string | null;
        address: string | null;
        is_defaulter: boolean;
    }

    export interface OrderItemLine {
        id: string;
        product_id: number;
        product_name: string;
        sku?: string | null;
        sale_type: 'unit' | 'weight';
        stock: number;
        packaging_type: 'unidad' | 'bulto' | 'gram' | 'kg';
        quantity: number;
        unit_price: number;
        units_per_package: number;
        package_quantity: number;
    }

    let {
        open = $bindable(false),
        availableProducts = [],
        availableCustomers = [],
    }: {
        open: boolean;
        availableProducts: ProductOption[];
        availableCustomers?: CustomerOption[];
    } = $props();

    let selectedCustomerId = $state<number | null>(null);
    let selectedCustomer = $state<CustomerOption | null>(null);
    let customerSearchQuery = $state('');

    let customerName = $state('');
    let customerPhone = $state('');
    let deliveryAddress = $state('');
    let notes = $state('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    // Search / Autocomplete state
    let searchQuery = $state('');
    let selectedProductToAdd = $state<ProductOption | null>(null);

    let items = $state<OrderItemLine[]>([]);

    function getItemQuantityBase(it: OrderItemLine): number {
        if (it.sale_type === 'weight') {
            if (it.packaging_type === 'gram') {
                return it.quantity / 1000;
            }
            if (it.packaging_type === 'bulto') {
                return it.quantity * (it.package_quantity || it.units_per_package || 1);
            }
            return it.quantity;
        } else {
            if (it.packaging_type === 'bulto') {
                return it.quantity * (it.units_per_package || 1);
            }
            return it.quantity;
        }
    }

    let usedStockMap = $derived.by(() => {
        const map: Record<number, number> = {};
        for (const it of items) {
            if (it.product_id) {
                const baseQty = getItemQuantityBase(it);
                map[it.product_id] = (map[it.product_id] || 0) + baseQty;
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
            return availableProductsWithRemainingStock.slice(0, 8);
        }
        return availableProductsWithRemainingStock
            .filter((p) => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)))
            .slice(0, 8);
    });

    let customerSearchResults = $derived.by(() => {
        const q = customerSearchQuery.toLowerCase().trim();
        if (!q) {
            return availableCustomers.slice(0, 8);
        }
        return availableCustomers
            .filter((c) =>
                c.name.toLowerCase().includes(q) ||
                (c.tax_id && c.tax_id.toLowerCase().includes(q)) ||
                (c.phone && c.phone.toLowerCase().includes(q))
            )
            .slice(0, 8);
    });

    function selectCustomerFromSearch(cust: CustomerOption) {
        selectedCustomerId = cust.id;
        selectedCustomer = cust;
        customerName = cust.name;
        customerSearchQuery = cust.name;
        customerPhone = cust.phone ?? '';
        deliveryAddress = cust.address ?? '';
    }

    function selectUnregisteredCustomer(typedName: string) {
        selectedCustomerId = null;
        selectedCustomer = null;
        customerName = typedName;
        customerSearchQuery = typedName;
    }

    function clearCustomerSelection() {
        selectedCustomerId = null;
        selectedCustomer = null;
        customerName = '';
        customerSearchQuery = '';
        customerPhone = '';
        deliveryAddress = '';
    }

    function selectProductFromSearch(prod: ProductOption) {
        selectedProductToAdd = prod;
        searchQuery = prod.name;
    }

    function addSelectedProduct() {
        if (!selectedProductToAdd) return;
        const prod = selectedProductToAdd;

        const remainingStock = prod.stock - (usedStockMap[prod.id] || 0);
        if (remainingStock <= 0) {
            errorMessage = `No hay stock disponible para agregar "${prod.name}".`;
            return;
        }

        errorMessage = null;

        const isWeighable = prod.sale_type === 'weight';
        const defaultPackaging = isWeighable ? 'kg' : 'unidad';
        const defaultQuantity = isWeighable ? (remainingStock >= 1 ? 1 : Math.round(remainingStock * 1000) / 1000) : 1;

        const existingIndex = items.findIndex(
            (it) => it.product_id === prod.id && it.packaging_type === defaultPackaging
        );

        if (existingIndex !== -1) {
            incrementQuantity(existingIndex);
        } else {
            items = [
                ...items,
                {
                    id: crypto.randomUUID(),
                    product_id: prod.id,
                    product_name: prod.name,
                    sku: prod.sku,
                    sale_type: prod.sale_type ?? 'unit',
                    stock: prod.stock,
                    packaging_type: defaultPackaging,
                    quantity: defaultQuantity,
                    unit_price: Number(prod.price),
                    units_per_package: prod.units_per_package || 1,
                    package_quantity: prod.package_quantity || prod.units_per_package || 1,
                },
            ];
        }

        selectedProductToAdd = null;
        searchQuery = '';
    }

    function removeItem(index: number) {
        items = items.filter((_, i) => i !== index);
    }

    function getMaxAllowedQuantityForItem(index: number): number {
        const targetItem = items[index];
        if (!targetItem) return 1;

        let consumedByOthers = 0;
        items.forEach((it, idx) => {
            if (idx === index) return;
            if (it.product_id === targetItem.product_id) {
                consumedByOthers += getItemQuantityBase(it);
            }
        });

        const prod = availableProducts.find((p) => p.id === targetItem.product_id);
        const totalStock = prod ? prod.stock : targetItem.stock;
        const freeStock = Math.max(0, totalStock - consumedByOthers);

        if (targetItem.sale_type === 'weight') {
            if (targetItem.packaging_type === 'gram') {
                return Math.floor(freeStock * 1000);
            }
            if (targetItem.packaging_type === 'bulto') {
                const pkgSize = targetItem.package_quantity || targetItem.units_per_package || 1;
                return Math.max(1, Math.floor(freeStock / pkgSize));
            }
            return freeStock;
        } else {
            const ratio = targetItem.packaging_type === 'bulto' ? (targetItem.units_per_package || 1) : 1;
            return Math.max(1, Math.floor(freeStock / ratio));
        }
    }

    function incrementQuantity(index: number) {
        const item = items[index];
        const max = getMaxAllowedQuantityForItem(index);

        if (item.sale_type === 'weight') {
            if (item.packaging_type === 'gram') {
                const step = 50;
                if (item.quantity + step <= max) {
                    item.quantity += step;
                    errorMessage = null;
                } else {
                    item.quantity = max;
                    errorMessage = `Se alcanzó el límite de stock para "${item.product_name}".`;
                }
            } else if (item.packaging_type === 'kg') {
                const step = 0.5;
                if (item.quantity + step <= max) {
                    item.quantity = Math.round((item.quantity + step) * 1000) / 1000;
                    errorMessage = null;
                } else {
                    item.quantity = max;
                    errorMessage = `Se alcanzó el límite de stock para "${item.product_name}".`;
                }
            } else {
                if (item.quantity < max) {
                    item.quantity += 1;
                    errorMessage = null;
                } else {
                    errorMessage = `Se alcanzó el límite de bultos disponibles para "${item.product_name}".`;
                }
            }
        } else {
            if (item.quantity < max) {
                item.quantity += 1;
                errorMessage = null;
            } else {
                errorMessage = `Se alcanzó el límite de stock disponible para "${item.product_name}".`;
            }
        }
    }

    function decrementQuantity(index: number) {
        const item = items[index];
        if (item.sale_type === 'weight') {
            if (item.packaging_type === 'gram') {
                if (item.quantity > 50) {
                    item.quantity -= 50;
                    errorMessage = null;
                }
            } else if (item.packaging_type === 'kg') {
                if (item.quantity > 0.5) {
                    item.quantity = Math.round((item.quantity - 0.5) * 1000) / 1000;
                    errorMessage = null;
                }
            } else {
                if (item.quantity > 1) {
                    item.quantity -= 1;
                    errorMessage = null;
                }
            }
        } else {
            if (item.quantity > 1) {
                item.quantity -= 1;
                errorMessage = null;
            }
        }
    }

    function handlePackagingChange(index: number, newType: 'unidad' | 'bulto' | 'gram' | 'kg') {
        const item = items[index];
        item.packaging_type = newType;

        if (newType === 'gram') {
            item.quantity = 500;
        } else if (newType === 'kg') {
            item.quantity = 1;
        } else {
            item.quantity = 1;
        }

        const max = getMaxAllowedQuantityForItem(index);
        if (item.quantity > max) {
            item.quantity = Math.max(newType === 'gram' ? 50 : 1, max);
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
            const baseQty = getItemQuantityBase(it);
            total += baseQty * it.unit_price;
        }
        return total;
    });

    function resetForm() {
        selectedCustomerId = null;
        selectedCustomer = null;
        customerName = '';
        customerSearchQuery = '';
        customerPhone = '';
        deliveryAddress = '';
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

        if (items.length === 0) {
            errorMessage = 'Debes agregar al menos un producto al pedido.';
            return;
        }

        if (!customerName.trim()) {
            errorMessage = 'Por favor ingresa o selecciona un cliente para el pedido.';
            return;
        }

        isSubmitting = true;
        errorMessage = null;

        const payloadItems: {
            product_id: number;
            packaging_type: 'unidad' | 'bulto' | 'gram' | 'kg';
            quantity: number;
        }[] = [];

        for (const it of items) {
            payloadItems.push({
                product_id: it.product_id,
                packaging_type: it.packaging_type,
                quantity: it.quantity,
            });
        }

        // Validate client-side total stock
        const totalDemands: Record<number, number> = {};
        for (const pItem of payloadItems) {
            const prod = availableProducts.find((p) => p.id === pItem.product_id);
            const isWeighable = prod?.sale_type === 'weight';
            let requiredBase = 0;

            if (isWeighable) {
                if (pItem.packaging_type === 'gram') {
                    requiredBase = pItem.quantity / 1000;
                } else if (pItem.packaging_type === 'bulto') {
                    requiredBase = pItem.quantity * (prod?.package_quantity || prod?.units_per_package || 1);
                } else {
                    requiredBase = pItem.quantity;
                }
            } else {
                if (pItem.packaging_type === 'bulto') {
                    requiredBase = pItem.quantity * (prod?.units_per_package || 1);
                } else {
                    requiredBase = pItem.quantity;
                }
            }

            totalDemands[pItem.product_id] = (totalDemands[pItem.product_id] || 0) + requiredBase;

            if (prod && totalDemands[pItem.product_id] > prod.stock) {
                const unitLabel = isWeighable ? 'kg' : 'unidades';
                errorMessage = `Stock insuficiente para "${prod.name}". Requerido en total: ${totalDemands[pItem.product_id]} ${unitLabel}, Disponible: ${prod.stock} ${unitLabel}.`;
                isSubmitting = false;
                return;
            }
        }

        router.post(
            '/tenant/orders',
            {
                customer_id: selectedCustomerId,
                customer_name: customerName,
                customer_phone: customerPhone,
                delivery_address: deliveryAddress,
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
                    errorMessage = typeof firstError === 'string' ? firstError : 'Ocurrió un error al crear el pedido.';
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
            aria-labelledby="modal-title"
        >
            <!-- Header Bento -->
            <div class="flex items-center justify-between border-b pb-3.5 sm:pb-4 shrink-0">
                <div class="flex items-center gap-2.5">
                    <IconContainer size="md" variant="primary" icon="solar:bill-list-bold-duotone" />
                    <div>
                        <h2 id="modal-title" class="text-base font-bold text-foreground">
                            Nuevo Pedido de Cliente
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

            <!-- Error Banner -->
            {#if errorMessage}
                <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-center gap-2 shrink-0">
                    <AlertCircle class="size-4 shrink-0" />
                    <span class="font-medium">{errorMessage}</span>
                </div>
            {/if}

            <!-- Form Content (Scrollable) -->
            <form id="create-order-form" onsubmit={submitOrder} class="space-y-4 overflow-y-auto flex-1 min-h-0 pr-1">
                <!-- Bento Card 1: Cliente & Destino con Autocomplete -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <User class="size-3.5 text-primary" />
                            Datos del Cliente
                        </h3>

                        {#if selectedCustomer}
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                                <UserCheck class="size-3" />
                                Cliente Registrado
                            </span>
                        {/if}
                    </div>

                    <!-- Buscador Autocomplete de Clientes con SearchCombobox -->
                    <div class="space-y-1">
                        <Label class="text-xs font-semibold text-foreground mb-1 block">
                            Buscar Cliente Registrado o Escribir Nuevo
                        </Label>
                        <SearchCombobox
                            id="cust-search"
                            bind:value={customerSearchQuery}
                            items={customerSearchResults}
                            placeholder="Escribe el nombre, CUIT/DNI o teléfono para autocompletar..."
                            showDropdown={true}
                            onsearch={(q) => {
                                customerSearchQuery = q;
                                customerName = q;
                            }}
                            onselect={selectCustomerFromSearch}
                            onclear={clearCustomerSelection}
                            inputClass="h-9 text-xs font-medium"
                        >
                            {#snippet itemSnippet(cust)}
                                <div class="flex items-center justify-between gap-3 w-full">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-foreground truncate">
                                                {cust.name}
                                            </span>
                                            {#if cust.tax_id}
                                                <span class="text-[10px] font-sans tabular-nums bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                                                    {cust.tax_id}
                                                </span>
                                            {/if}
                                            {#if cust.is_defaulter}
                                                <span class="inline-flex items-center gap-0.5 text-[9px] font-bold text-rose-600 bg-rose-500/10 px-1 py-0.2 rounded border border-rose-500/20">
                                                    Moroso
                                                </span>
                                            {/if}
                                        </div>
                                        <p class="text-[11px] text-muted-foreground mt-0.5 truncate">
                                            {cust.phone || 'Sin teléfono'} • {cust.address || 'Sin dirección'}
                                        </p>
                                    </div>

                                    <div class="text-right shrink-0">
                                        <span class="text-[10px] font-semibold text-muted-foreground uppercase">
                                            {cust.tax_condition.replace('_', ' ')}
                                        </span>
                                    </div>
                                </div>
                            {/snippet}
                            {#snippet emptySnippet()}
                                {#if customerSearchQuery.trim().length > 0}
                                    <button
                                        type="button"
                                        class="w-full px-1 py-1 text-left text-xs hover:bg-muted/60 transition-colors flex items-center gap-2 text-primary font-semibold cursor-pointer"
                                        onclick={() => selectUnregisteredCustomer(customerSearchQuery.trim())}
                                    >
                                        <Plus class="size-3.5 shrink-0" />
                                        <span class="truncate">Usar "<strong>{customerSearchQuery.trim()}</strong>" como cliente no registrado</span>
                                    </button>
                                {:else}
                                    <span class="text-xs text-muted-foreground italic">No hay clientes registrados coincidentes.</span>
                                {/if}
                            {/snippet}
                        </SearchCombobox>
                    </div>

                    <!-- Alerta de Morosidad si aplica -->
                    {#if selectedCustomer?.is_defaulter}
                        <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 text-xs flex items-center gap-2">
                            <AlertTriangle class="size-4 shrink-0 text-amber-600" />
                            <span><strong>Atención:</strong> Este cliente posee condición de moroso o saldo pendiente de regularización.</span>
                        </div>
                    {/if}

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 pt-1">
                        <div class="sm:col-span-5 space-y-1">
                            <Label for="cust-name" class="text-xs font-semibold text-foreground">
                                Nombre / Razón Social <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="cust-name"
                                bind:value={customerName}
                                placeholder="ej. Ana García / Librería Central"
                                class="h-9 text-xs font-medium"
                                required
                            />
                        </div>
                        <div class="sm:col-span-3 space-y-1">
                            <Label for="cust-phone" class="text-xs font-semibold text-foreground">Teléfono / Contacto</Label>
                            <Input
                                id="cust-phone"
                                bind:value={customerPhone}
                                placeholder="+54 9 11 4455-6677"
                                class="h-9 text-xs"
                            />
                        </div>
                        <div class="sm:col-span-4 space-y-1">
                            <Label for="cust-address" class="text-xs font-semibold text-foreground">Dirección de Entrega</Label>
                            <Input
                                id="cust-address"
                                bind:value={deliveryAddress}
                                placeholder="Dirección o Retiro en local"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>
                </div>

                <!-- Bento Card 2: Productos del Pedido -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3.5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary" />
                            Productos del Pedido
                        </h3>
                    </div>

                    <!-- Buscador Flotante de Productos con SearchCombobox -->
                    <div class="flex gap-2 items-start">
                        <div class="flex-1">
                            <SearchCombobox
                                bind:value={searchQuery}
                                items={searchResults}
                                placeholder="Escribe el nombre o SKU del producto para buscar..."
                                showDropdown={true}
                                onsearch={(q) => (searchQuery = q)}
                                onselect={selectProductFromSearch}
                                onclear={() => {
                                    searchQuery = '';
                                    selectedProductToAdd = null;
                                }}
                                inputClass="h-9 text-xs font-medium"
                            >
                                {#snippet itemSnippet(prod)}
                                    <div class="flex items-center justify-between gap-3 w-full">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-foreground truncate">
                                                    {prod.name}
                                                </span>
                                                {#if prod.sale_type === 'weight'}
                                                    <span class="inline-flex items-center gap-0.5 text-[10px] font-medium text-sky-600 bg-sky-500/10 px-1.5 py-0.2 rounded border border-sky-500/20">
                                                        <Scale class="size-2.5" />
                                                        Pesaje
                                                    </span>
                                                {/if}
                                            </div>
                                            {#if prod.sku}
                                                <div class="text-[11px] font-sans tabular-nums text-muted-foreground mt-0.5">
                                                    SKU: {prod.sku}
                                                </div>
                                            {/if}
                                        </div>

                                        <div class="text-right shrink-0">
                                            <span class="font-bold font-sans tabular-nums text-primary text-xs block">
                                                {formatPrice(Number(prod.price))}
                                            </span>
                                            <span class="text-[10px] font-sans tabular-nums text-muted-foreground block mt-0.5">
                                                {#if prod.sale_type === 'weight'}
                                                    Stock: {prod.stock} Kg • Bulto: {prod.package_quantity || prod.units_per_package || 1} Kg
                                                {:else}
                                                    Stock: {prod.stock} u • Bulto: x{prod.units_per_package} u
                                                {/if}
                                            </span>
                                        </div>
                                    </div>
                                {/snippet}
                                {#snippet emptySnippet()}
                                    <span class="text-xs text-muted-foreground italic">
                                        No hay coincidencias en el catálogo. No es posible crear pedidos con productos inexistentes.
                                    </span>
                                {/snippet}
                            </SearchCombobox>
                        </div>

                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            class="h-10 text-xs gap-1.5 font-semibold shrink-0"
                            onclick={addSelectedProduct}
                            disabled={!selectedProductToAdd}
                        >
                            <Plus class="size-3.5" />
                            Agregar al Pedido
                        </Button>
                    </div>

                    <!-- Lista de Ítems del Pedido -->
                    <div class="space-y-2.5 pt-1">
                        {#each items as item, index (item.id || index)}
                            {@const maxAllowed = getMaxAllowedQuantityForItem(index)}
                            {@const isAtStockLimit = item.quantity >= maxAllowed}
                            {@const baseQty = getItemQuantityBase(item)}
                            {@const lineSubtotal = baseQty * item.unit_price}

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <IconContainer
                                        size="md"
                                        variant={item.sale_type === 'weight' ? 'info' : 'primary'}
                                        icon={item.sale_type === 'weight' ? 'solar:scale-bold-duotone' : 'solar:box-minimalistic-bold-duotone'}
                                    />

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-xs text-foreground truncate">{item.product_name}</h4>
                                            {#if item.sku}
                                                <span class="text-[10px] font-sans tabular-nums bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                                                    {item.sku}
                                                </span>
                                            {/if}
                                        </div>

                                        <div class="flex items-center gap-2 text-[11px] text-muted-foreground mt-0.5">
                                            <span>Precio base: <strong class="font-sans tabular-nums text-foreground">{formatPrice(item.unit_price)} {item.sale_type === 'weight' ? '/Kg' : '/u'}</strong></span>
                                            <span>•</span>
                                            <span>Stock: <strong class="font-sans tabular-nums text-foreground">{item.stock} {item.sale_type === 'weight' ? 'Kg' : 'u'}</strong></span>
                                            {#if isAtStockLimit}
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-600 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20">
                                                    Tope
                                                </span>
                                            {/if}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between sm:justify-end gap-2.5 sm:gap-3 w-full sm:w-auto pt-2 sm:pt-0 border-t sm:border-t-0 shrink-0">
                                    <!-- Selector de Presentación (Gramos / Kg / Bulto o Unidad / Bulto) -->
                                    <select
                                        value={item.packaging_type}
                                        onchange={(e) => handlePackagingChange(index, (e.target as HTMLSelectElement).value as any)}
                                        class="h-9 sm:h-10 rounded-lg border border-input bg-background px-2.5 text-xs font-semibold text-foreground focus:ring-1 focus:ring-primary outline-none"
                                    >
                                        {#if item.sale_type === 'weight'}
                                            <option value="gram">Gramos (g)</option>
                                            <option value="kg">Kilogramos (Kg)</option>
                                            <option value="bulto" disabled={item.stock < (item.package_quantity || item.units_per_package || 1)}>
                                                Bulto ({item.package_quantity || item.units_per_package || 1} Kg)
                                            </option>
                                        {:else}
                                            <option value="unidad">Unidad</option>
                                            <option value="bulto" disabled={item.stock < item.units_per_package}>
                                                Bulto (x{item.units_per_package}u)
                                            </option>
                                        {/if}
                                    </select>

                                    <!-- Stepper Táctil / Input Cantidad (36x36px a 40x40px touch targets) -->
                                    <div class="flex items-center border rounded-lg bg-background shadow-2xs">
                                        <button
                                            type="button"
                                            class="size-9 sm:size-10 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted rounded-l-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                            onclick={() => decrementQuantity(index)}
                                            disabled={item.packaging_type === 'gram' ? item.quantity <= 50 : item.packaging_type === 'kg' ? item.quantity <= 0.1 : item.quantity <= 1}
                                            aria-label="Disminuir cantidad"
                                        >
                                            <Minus class="size-4" />
                                        </button>

                                        <input
                                            type="number"
                                            step={item.packaging_type === 'gram' ? '50' : item.packaging_type === 'kg' ? '0.1' : '1'}
                                            min={item.packaging_type === 'gram' ? '50' : item.packaging_type === 'kg' ? '0.01' : '1'}
                                            max={maxAllowed}
                                            bind:value={item.quantity}
                                            class="w-12 sm:w-14 h-9 sm:h-10 text-xs sm:text-sm font-bold font-sans tabular-nums text-center bg-transparent border-0 outline-none p-0"
                                        />

                                        <button
                                            type="button"
                                            class="size-9 sm:size-10 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted rounded-r-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                            onclick={() => incrementQuantity(index)}
                                            disabled={isAtStockLimit}
                                            title={isAtStockLimit ? 'Se alcanzó el stock máximo disponible' : 'Aumentar'}
                                            aria-label="Aumentar cantidad"
                                        >
                                            <Plus class="size-4" />
                                        </button>
                                    </div>

                                    <span class="font-sans tabular-nums text-xs sm:text-sm font-bold text-foreground w-20 sm:w-24 text-right">
                                        {formatPrice(lineSubtotal)}
                                    </span>

                                    <button
                                        type="button"
                                        class="size-9 sm:size-10 flex items-center justify-center text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-lg transition-colors"
                                        onclick={() => removeItem(index)}
                                        aria-label="Eliminar producto"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </div>
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

            <!-- Footer Bento: Total & Botones (shrink-0) -->
            <div class="flex items-center justify-between border-t pt-3.5 shrink-0">
                <div class="flex items-baseline gap-2">
                    <span class="text-xs text-muted-foreground">Total del Pedido:</span>
                    <span class="text-lg sm:text-xl font-bold font-sans tabular-nums text-primary">{formatPrice(computedTotal)}</span>
                </div>

                <div class="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onclick={closeModal}>
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        form="create-order-form"
                        size="sm"
                        class="gap-1.5 font-semibold"
                        disabled={isSubmitting || items.length === 0}
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
{/if}
