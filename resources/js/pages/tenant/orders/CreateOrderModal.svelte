<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import HighlightMatch from '@/components/common/HighlightMatch.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';

    import { Link, router } from '@inertiajs/svelte';
    import { fade, scale } from 'svelte/transition';
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
    import Banknote from 'lucide-svelte/icons/banknote';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import X from 'lucide-svelte/icons/x';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Percent from 'lucide-svelte/icons/percent';
    import Warehouse from 'lucide-svelte/icons/warehouse';
    import MapPin from 'lucide-svelte/icons/map-pin';

    export interface ProductOption {
        id: number;
        name: string;
        sku: string | null;
        sale_type?: 'unit' | 'weight';
        price: string | number;
        stock: number;
        units_per_package: number;
        package_quantity?: number;
        bulk_discount_type?: 'inherited' | 'custom' | 'none';
        custom_bulk_discount_percentage?: number | string | null;
        effective_bulk_discount_percentage?: number;
        bulk_discount_price?: number;
    }

    export interface CustomerOption {
        id: number;
        name: string;
        tax_id: string | null;
        tax_condition: string;
        phone: string | null;
        address: string | null;
        discount_percentage?: number | string | null;
        is_defaulter: boolean;
    }

    export interface WarehouseOption {
        id: number;
        name: string;
        code: string;
        is_default: boolean;
    }

    export interface ShippingZoneOption {
        id: number;
        name: string;
        distance_km: number | string;
        surcharge_percentage: number | string;
    }

    export interface OrderItemLine {
        id: string;
        product_id: number;
        warehouse_id?: number | null;
        product_name: string;
        sku?: string | null;
        sale_type: 'unit' | 'weight';
        stock: number;
        packaging_type: 'unidad' | 'bulto' | 'gram' | 'kg';
        quantity: number;
        unit_price: number;
        regular_price: number;
        bulk_discount_price: number;
        bulk_discount_percentage: number;
        bulk_discount_type: 'inherited' | 'custom' | 'none';
        units_per_package: number;
        package_quantity: number;
        apply_customer_discount: boolean;
    }

    let {
        open = $bindable(false),
        availableProducts = [],
        availableCustomers = [],
        warehouses = [],
        shippingZones = [],
    }: {
        open: boolean;
        availableProducts: ProductOption[];
        availableCustomers?: CustomerOption[];
        warehouses?: WarehouseOption[];
        shippingZones?: ShippingZoneOption[];
    } = $props();

    let selectedCustomerId = $state<number | null>(null);
    let selectedCustomer = $state<CustomerOption | null>(null);
    let selectedWarehouseId = $state<number | null>(null);
    let selectedShippingZoneId = $state<number | null>(null);
    let applyCustomerDiscount = $state(true);
    let customerSearchQuery = $state('');

    let selectedShippingZone = $derived(
        shippingZones.find((z) => z.id === selectedShippingZoneId) || null
    );

    let customerName = $state('');
    let customerPhone = $state('');
    let deliveryAddress = $state('');
    let notes = $state('');
    let paymentType = $state<'cuenta_corriente' | 'contado' | 'parcial'>('cuenta_corriente');
    let paymentMethod = $state<'efectivo' | 'transferencia' | 'mercadopago' | 'debito' | 'credito' | 'otro'>('efectivo');
    let paidAmount = $state<number | string>('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    let customerDiscountPercentage = $derived(Number(selectedCustomer?.discount_percentage) || 0);

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
        const isBulk = defaultPackaging === 'bulto';
        const regularPrice = Number(prod.price) || 0;
        const bulkDiscountPrice = Number(prod.bulk_discount_price ?? prod.price) || regularPrice;
        const bulkDiscountPct = Number(prod.effective_bulk_discount_percentage) || 0;
        const bulkDiscountType = (prod.bulk_discount_type as any) || 'inherited';

        const existingIndex = items.findIndex(
            (it) => it.product_id === prod.id && it.packaging_type === defaultPackaging
        );

        if (existingIndex !== -1) {
            incrementQuantity(existingIndex);
        } else {
            items = [
                ...items,
                {
                    id: 'item_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8),
                    product_id: prod.id,
                    warehouse_id: selectedWarehouseId,
                    product_name: prod.name,
                    sku: prod.sku,
                    sale_type: prod.sale_type ?? 'unit',
                    stock: prod.stock,
                    packaging_type: defaultPackaging,
                    quantity: defaultQuantity,
                    unit_price: isBulk ? bulkDiscountPrice : regularPrice,
                    regular_price: regularPrice,
                    bulk_discount_price: bulkDiscountPrice,
                    bulk_discount_percentage: bulkDiscountPct,
                    bulk_discount_type: bulkDiscountType,
                    units_per_package: prod.units_per_package || 1,
                    package_quantity: prod.package_quantity || prod.units_per_package || 1,
                    apply_customer_discount: !isBulk,
                },
            ];
        }

        selectedProductToAdd = null;
        searchQuery = '';
    }

    function removeItem(index: number) {
        items = items.filter((_, i) => i !== index);
    }

    function toggleItemCustomerDiscount(index: number) {
        if (items[index]) {
            items[index].apply_customer_discount = !items[index].apply_customer_discount;
        }
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

        let calculatedMax = 1;
        if (targetItem.sale_type === 'weight') {
            if (targetItem.packaging_type === 'gram') {
                calculatedMax = Math.floor(freeStock * 1000);
            } else if (targetItem.packaging_type === 'bulto') {
                const pkgSize = targetItem.package_quantity || targetItem.units_per_package || 1;
                calculatedMax = Math.max(1, Math.floor(freeStock / pkgSize));
            } else {
                calculatedMax = freeStock;
            }
        } else {
            const ratio = targetItem.packaging_type === 'bulto' ? (targetItem.units_per_package || 1) : 1;
            calculatedMax = Math.max(1, Math.floor(freeStock / ratio));
        }

        return Math.min(calculatedMax, 10000);
    }

    function getMinAllowedQuantityForItem(item: OrderItemLine): number {
        if (item.sale_type === 'weight') {
            if (item.packaging_type === 'gram') return 50;
            if (item.packaging_type === 'kg') return 0.1;
            return 1;
        }
        return 1;
    }

    function handleQuantityBlur(index: number) {
        const item = items[index];
        if (!item) return;
        const minVal = getMinAllowedQuantityForItem(item);
        const maxVal = getMaxAllowedQuantityForItem(index);
        let val = Number(item.quantity);
        if (isNaN(val) || val < minVal) {
            item.quantity = minVal;
        } else if (val > maxVal) {
            item.quantity = maxVal;
        } else {
            if (item.packaging_type === 'kg') {
                item.quantity = Math.round(val * 1000) / 1000;
            } else if (item.packaging_type === 'gram' || item.packaging_type === 'bulto' || item.packaging_type === 'unidad') {
                item.quantity = Math.floor(val);
            }
        }
    }

    function handleQuantityPaste(index: number, e: ClipboardEvent) {
        const pastedText = e.clipboardData?.getData('text') || '';
        const cleaned = pastedText.replace(/[^0-9.]/g, '');
        if (!cleaned) {
            e.preventDefault();
            return;
        }
        const num = parseFloat(cleaned);
        if (isNaN(num)) {
            e.preventDefault();
            return;
        }
        e.preventDefault();
        const item = items[index];
        if (!item) return;
        const minVal = getMinAllowedQuantityForItem(item);
        const maxVal = getMaxAllowedQuantityForItem(index);
        item.quantity = Math.max(minVal, Math.min(num, maxVal));
    }

    let hasQuantityOverflow = $derived.by(() => {
        return items.some((it, idx) => {
            const max = getMaxAllowedQuantityForItem(idx);
            return Number(it.quantity) > max || Number(it.quantity) > 10000;
        });
    });

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
        if (!item) return;
        item.packaging_type = newType;

        if (newType === 'gram') {
            item.quantity = 500;
            item.unit_price = item.regular_price;
            item.apply_customer_discount = true;
        } else if (newType === 'kg') {
            item.quantity = 1;
            item.unit_price = item.regular_price;
            item.apply_customer_discount = true;
        } else if (newType === 'bulto') {
            item.quantity = 1;
            item.unit_price = item.bulk_discount_price;
            item.apply_customer_discount = false;
        } else {
            item.quantity = 1;
            item.unit_price = item.regular_price;
            item.apply_customer_discount = true;
        }

        const max = getMaxAllowedQuantityForItem(index);
        if (item.quantity > max) {
            item.quantity = Math.max(newType === 'gram' ? 50 : 1, max);
        }
    }

    function formatPrice(amount: number, compact: boolean = false) {
        const val = Number(amount) || 0;
        if (compact || Math.abs(val) >= 1_000_000_000) {
            return new Intl.NumberFormat('es-AR', {
                style: 'currency',
                currency: 'ARS',
                notation: 'compact',
                maximumFractionDigits: 2,
            }).format(val);
        }
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(val);
    }

    function formatExactPrice(amount: number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    }

    function getItemLineDiscount(it: OrderItemLine): { discountAmount: number; finalSubtotal: number; baseSubtotal: number } {
        const baseQty = getItemQuantityBase(it);
        const baseSubtotal = baseQty * it.unit_price;
        const isEligible = applyCustomerDiscount && it.apply_customer_discount && customerDiscountPercentage > 0;
        const discountAmount = isEligible ? Math.round(baseSubtotal * (customerDiscountPercentage / 100) * 100) / 100 : 0;
        const finalSubtotal = Math.round((baseSubtotal - discountAmount) * 100) / 100;
        return { discountAmount, finalSubtotal, baseSubtotal };
    }

    let computedTotals = $derived.by(() => {
        let baseTotal = 0;
        let discountTotal = 0;
        let finalTotal = 0;
        for (const it of items) {
            const { discountAmount, finalSubtotal, baseSubtotal } = getItemLineDiscount(it);
            baseTotal += baseSubtotal;
            discountTotal += discountAmount;
            finalTotal += finalSubtotal;
        }
        return {
            baseTotal: Math.round(baseTotal * 100) / 100,
            discountTotal: Math.round(discountTotal * 100) / 100,
            finalTotal: Math.round(finalTotal * 100) / 100,
        };
    });

    let shippingCost = $derived.by(() => {
        if (!selectedShippingZone) return 0;
        const pct = Number(selectedShippingZone.surcharge_percentage) || 0;
        return Math.round(computedTotals.finalTotal * (pct / 100) * 100) / 100;
    });

    let grandTotal = $derived(
        Math.round((computedTotals.finalTotal + shippingCost) * 100) / 100
    );

    function resetForm() {
        selectedCustomerId = null;
        selectedCustomer = null;
        selectedWarehouseId = null;
        selectedShippingZoneId = null;
        applyCustomerDiscount = true;
        customerName = '';
        customerSearchQuery = '';
        customerPhone = '';
        deliveryAddress = '';
        notes = '';
        paymentType = 'cuenta_corriente';
        paymentMethod = 'efectivo';
        paidAmount = '';
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
            warehouse_id?: number | null;
            packaging_type: 'unidad' | 'bulto' | 'gram' | 'kg';
            quantity: number;
            apply_customer_discount: boolean;
        }[] = [];

        for (const it of items) {
            payloadItems.push({
                product_id: it.product_id,
                warehouse_id: it.warehouse_id || selectedWarehouseId,
                packaging_type: it.packaging_type,
                quantity: it.quantity,
                apply_customer_discount: it.apply_customer_discount,
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

        let numericPaidAmount: number | null = null;
        if (paymentType === 'parcial') {
            const raw = Number(paidAmount) || 0;
            if (raw <= 0) {
                errorMessage = 'Por favor ingresa un monto válido a cobrar para el pago parcial.';
                isSubmitting = false;
                return;
            }
            if (raw >= grandTotal) {
                errorMessage = 'El pago parcial debe ser menor al total del pedido. Para cobrar el total, selecciona "Cobro Total en el Momento".';
                isSubmitting = false;
                return;
            }
            numericPaidAmount = raw;
        }

        router.post(
            '/tenant/pedidos',
            {
                customer_id: selectedCustomerId,
                warehouse_id: selectedWarehouseId,
                shipping_zone_id: selectedShippingZoneId,
                customer_name: customerName,
                customer_phone: customerPhone,
                delivery_address: deliveryAddress,
                notes: notes,
                payment_type: paymentType,
                payment_method: (paymentType === 'contado' || paymentType === 'parcial') ? paymentMethod : null,
                paid_amount: numericPaidAmount,
                payment_reference: null,
                apply_customer_discount: applyCustomerDiscount,
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
            aria-labelledby="modal-title"
        >
            <!-- Header Bento -->
            <div class="flex items-start justify-between gap-3 border-b pb-3.5 sm:pb-4 shrink-0">
                <div class="min-w-0 flex-1 flex items-start gap-2.5">
                    <div class="shrink-0 mt-0.5">
                        <IconContainer size="md" variant="primary" icon="solar:bill-list-bold-duotone" />
                    </div>
                    <div>
                        <h2 id="modal-title" class="text-base sm:text-lg font-bold text-foreground leading-snug line-clamp-2 break-words">
                            Nuevo Pedido de Cliente
                        </h2>
                    </div>
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
                    <div class="flex items-start justify-between gap-2.5">
                        <h3 class="min-w-0 flex-1 break-words text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <User class="size-3.5 text-primary shrink-0" />
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
                                                <HighlightMatch text={cust.name} query={customerSearchQuery} />
                                            </span>
                                            {#if cust.tax_id}
                                                <span class="text-[10px] font-sans tabular-nums bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                                                    <HighlightMatch text={cust.tax_id} query={customerSearchQuery} />
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

                    <!-- Banner de Descuento Habitual de Cliente si aplica -->
                    {#if customerDiscountPercentage > 0}
                        <div class="p-2.5 rounded-lg bg-primary/10 border border-primary/20 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-xs">
                            <div class="flex items-center gap-2">
                                <Percent class="size-4 text-primary shrink-0" />
                                <span class="text-foreground font-medium">
                                    Descuento habitual del cliente: <strong class="text-primary font-bold font-sans tabular-nums">{customerDiscountPercentage}%</strong>
                                </span>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-foreground shrink-0">
                                <input
                                    type="checkbox"
                                    bind:checked={applyCustomerDiscount}
                                    class="size-4 rounded border-input text-primary focus:ring-primary"
                                />
                                <span>Aplicar descuento a este pedido</span>
                            </label>
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

                    <!-- Selector de Depósito de Despacho si hay depósitos -->
                    {#if warehouses && warehouses.length > 0}
                        <div class="pt-3 mt-1.5 border-t border-border/50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                            <Label for="order-warehouse" class="text-xs font-semibold text-foreground flex items-center gap-1.5 shrink-0">
                                <Warehouse class="size-3.5 text-primary" />
                                Depósito de Origen / Despacho:
                            </Label>
                            <select
                                id="order-warehouse"
                                bind:value={selectedWarehouseId}
                                class="h-10 w-full sm:w-64 rounded-xl border border-border/80 bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
                            >
                                <option value={null}>Depósito Principal (Automático)</option>
                                {#each warehouses as wh}
                                    <option value={wh.id}>{wh.name} ({wh.code})</option>
                                {/each}
                            </select>
                        </div>
                    {/if}

                    <!-- Selector de Zona de Envío / Flete -->
                    <div class="pt-3 mt-1.5 border-t border-border/50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                        <Label for="order-shipping-zone" class="text-xs font-semibold text-foreground flex items-center gap-1.5 shrink-0">
                            <MapPin class="size-3.5 text-primary" />
                            Zona de Envío / Flete:
                        </Label>
                        <select
                            id="order-shipping-zone"
                            bind:value={selectedShippingZoneId}
                            class="h-10 w-full sm:w-64 rounded-xl border border-border/80 bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
                        >
                            <option value={null}>Sin envío / Retiro en local ($0.00)</option>
                            {#each shippingZones as zone}
                                <option value={zone.id}>
                                    {zone.name} ({zone.distance_km} km - +{zone.surcharge_percentage}%)
                                </option>
                            {/each}
                        </select>
                    </div>
                </div>


                <!-- Bento Card 2: Productos del Pedido -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3.5">
                    <div class="flex items-start justify-between gap-2.5">
                        <h3 class="min-w-0 flex-1 break-words text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary shrink-0" />
                            Productos del Pedido
                        </h3>
                    </div>

                    <!-- Buscador Flotante de Productos con SearchCombobox -->
                    <div class="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-start w-full">
                        <div class="w-full flex-1 min-w-0">
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
                                inputClass="h-10 sm:h-9 text-xs font-medium w-full"
                            >
                                {#snippet itemSnippet(prod)}
                                    <div class="flex items-start justify-between gap-2.5 w-full text-left py-0.5">
                                        <div class="min-w-0 flex-1 space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-foreground text-xs leading-snug line-clamp-2 break-words">
                                                    <HighlightMatch text={prod.name} query={searchQuery} />
                                                </span>
                                                {#if prod.sale_type === 'weight'}
                                                    <span class="shrink-0 inline-flex items-center gap-0.5 text-[10px] font-medium text-sky-600 bg-sky-500/10 px-1.5 py-0.2 rounded border border-sky-500/20">
                                                        <Scale class="size-2.5" />
                                                        Pesaje
                                                    </span>
                                                {/if}
                                            </div>
                                            {#if prod.sku}
                                                <div class="text-[11px] font-sans tabular-nums text-muted-foreground mt-0.5">
                                                    SKU: <HighlightMatch text={prod.sku} query={searchQuery} />
                                                </div>
                                            {/if}
                                        </div>


                                        <div class="text-right shrink-0 min-w-[76px] pl-1">
                                            <span class="font-bold font-sans tabular-nums text-primary text-xs sm:text-sm block">
                                                {formatPrice(Number(prod.price))}
                                            </span>
                                            <span class="text-[10px] font-sans tabular-nums text-muted-foreground block mt-0.5 whitespace-nowrap">
                                                {#if prod.sale_type === 'weight'}
                                                    Stock: {prod.stock} Kg
                                                {:else}
                                                    Stock: {prod.stock} u
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
                            class="w-full sm:w-auto min-h-[44px] sm:h-9 px-5 rounded-xl text-xs gap-2 font-semibold shrink-0 justify-center shadow-2xs"
                            onclick={addSelectedProduct}
                            disabled={!selectedProductToAdd}
                        >
                            <Plus class="size-4 shrink-0" />
                            <span>Agregar</span>
                        </Button>
                    </div>

                    <!-- Lista de Ítems del Pedido -->
                    <div class="space-y-2.5 pt-1">
                        {#each items as item, index (item.id || index)}
                            {@const maxAllowed = getMaxAllowedQuantityForItem(index)}
                            {@const isAtStockLimit = item.quantity >= maxAllowed}
                            {@const { discountAmount, finalSubtotal, baseSubtotal } = getItemLineDiscount(item)}
                            {@const isCustomerDiscountActive = applyCustomerDiscount && item.apply_customer_discount && customerDiscountPercentage > 0}
                            {@const isBulk = item.packaging_type === 'bulto'}
                            {@const hasBulkDiscount = isBulk && item.bulk_discount_type !== 'none' && (item.bulk_discount_percentage > 0)}
                            {@const regularSubtotal = getItemQuantityBase(item) * item.regular_price}
                            {@const isDiscountActive = isCustomerDiscountActive || hasBulkDiscount}
                            {@const strikethroughSubtotal = hasBulkDiscount ? regularSubtotal : baseSubtotal}

                            <div class="flex flex-col gap-2.5 p-3.5 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs">
                                <!-- Fila 1: Nombre + SKU a la izquierda, Botón Quitar directo a la derecha -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <IconContainer
                                            size="sm"
                                            variant={item.sale_type === 'weight' ? 'info' : 'primary'}
                                            icon={item.sale_type === 'weight' ? 'solar:scale-bold-duotone' : 'solar:box-minimalistic-bold-duotone'}
                                        />
                                        <div class="flex items-center gap-2 min-w-0 flex-wrap">
                                            <h4 class="font-bold text-xs sm:text-sm text-foreground truncate">{item.product_name}</h4>
                                            {#if item.sku}
                                                <span class="text-[10px] font-sans tabular-nums bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                                                    {item.sku}
                                                </span>
                                            {/if}
                                            {#if item.sale_type === 'weight'}
                                                <span class="text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 px-1 rounded">
                                                    Pesaje
                                                </span>
                                            {/if}
                                            {#if isBulk}
                                                {#if item.bulk_discount_type !== 'none' && item.bulk_discount_percentage > 0}
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30">
                                                        <Percent class="size-2.5" />
                                                        Desc. Bulto {item.bulk_discount_percentage}% ({item.bulk_discount_type === 'custom' ? 'Personalizado' : 'Heredado'})
                                                    </span>
                                                {:else}
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-muted text-muted-foreground border-border">
                                                        Sin Descuento por Bulto
                                                    </span>
                                                {/if}
                                            {/if}
                                            {#if customerDiscountPercentage > 0 && applyCustomerDiscount}
                                                <button
                                                    type="button"
                                                    onclick={() => toggleItemCustomerDiscount(index)}
                                                    class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full border transition-colors cursor-pointer {item.apply_customer_discount ? 'bg-primary/15 text-primary border-primary/30' : 'bg-muted text-muted-foreground border-border line-through'}"
                                                    title="Click para alternar descuento de cliente en este producto"
                                                >
                                                    <Percent class="size-2.5" />
                                                    Desc. {customerDiscountPercentage}% {item.apply_customer_discount ? 'Aplicado' : 'Excluido'}
                                                </button>
                                            {/if}
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="size-9 min-h-[36px] min-w-[36px] rounded-xl flex items-center justify-center text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors cursor-pointer shrink-0"
                                        onclick={() => removeItem(index)}
                                        title="Quitar de la lista"
                                        aria-label="Quitar producto de la lista"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>

                                <!-- Fila 2: Metadatos accesibles (Precio Base y Stock) -->
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm min-w-0 pt-2 border-t border-border/50">
                                    <!-- Bloque Precio Base -->
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        {#if hasBulkDiscount}
                                            <span class="text-xs line-through text-muted-foreground" title={formatExactPrice(item.regular_price)}>
                                                {formatPrice(item.regular_price)}
                                            </span>
                                            <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap" title={formatExactPrice(item.unit_price)}>
                                                {formatPrice(item.unit_price)} {item.sale_type === 'weight' ? '/Kg' : '/u'}
                                            </span>
                                        {:else}
                                            <span class="text-sm font-semibold text-foreground whitespace-nowrap" title={formatExactPrice(item.unit_price)}>
                                                {formatPrice(item.unit_price)} {item.sale_type === 'weight' ? '/Kg' : '/u'}
                                            </span>
                                        {/if}
                                    </div>

                                    <!-- Bloque Stock -->
                                    <div class="flex items-center gap-1.5 text-xs sm:text-sm text-muted-foreground shrink-0">
                                        <span>Stock: <strong class="font-sans tabular-nums text-foreground">{item.stock} {item.sale_type === 'weight' ? 'Kg' : 'u'}</strong></span>
                                        {#if isAtStockLimit}
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-600 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20 shrink-0">
                                                Tope
                                            </span>
                                        {/if}
                                    </div>
                                </div>

                                <!-- Fila 3: Fila inferior de Controles (Edición a la izquierda, Resultado a la derecha) -->
                                <div class="flex flex-wrap justify-between items-center w-full gap-3 pt-1">
                                    <!-- Grupo Izquierdo (Edición): Selector de unidad + Stepper táctil -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <select
                                            value={item.packaging_type}
                                            onchange={(e) => handlePackagingChange(index, (e.target as HTMLSelectElement).value as any)}
                                            class="h-9 sm:h-9 rounded-xl border border-border/80 bg-background px-2.5 text-xs font-semibold text-foreground min-w-[88px] sm:min-w-[94px] focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary cursor-pointer shrink-0"
                                        >
                                            {#if item.sale_type === 'weight'}
                                                <option value="gram">Gramos</option>
                                                <option value="kg">Kg</option>
                                                <option value="bulto" disabled={item.stock < (item.package_quantity || item.units_per_package || 1)}>
                                                    Bulto
                                                </option>
                                            {:else}
                                                <option value="unidad">Unidad</option>
                                                <option value="bulto" disabled={item.stock < item.units_per_package}>
                                                    Bulto
                                                </option>
                                            {/if}
                                        </select>

                                        <!-- Stepper Táctil con w-14 y sin flechas nativas -->
                                        <div class="flex items-center border rounded-xl bg-background shadow-2xs h-9 overflow-hidden shrink-0">
                                            <button
                                                type="button"
                                                class="size-9 flex items-center justify-center shrink-0 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                                                onclick={() => decrementQuantity(index)}
                                                disabled={item.packaging_type === 'gram' ? item.quantity <= 50 : item.packaging_type === 'kg' ? item.quantity <= 0.1 : item.quantity <= 1}
                                                aria-label="Disminuir cantidad"
                                            >
                                                <Minus class="size-3.5" />
                                            </button>

                                            <input
                                                type="number"
                                                step={item.packaging_type === 'gram' ? '50' : item.packaging_type === 'kg' ? '0.001' : '1'}
                                                min={item.packaging_type === 'gram' ? 50 : item.packaging_type === 'kg' ? 0.001 : 1}
                                                max={maxAllowed}
                                                maxlength="5"
                                                bind:value={item.quantity}
                                                onblur={() => handleQuantityBlur(index)}
                                                onpaste={(e) => handleQuantityPaste(index, e)}
                                                class="w-14 sm:w-16 h-8 sm:h-9 text-xs sm:text-sm font-bold font-sans tabular-nums text-center bg-transparent border-0 outline-none p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none {item.quantity > maxAllowed ? 'text-amber-600 dark:text-amber-400 font-extrabold' : ''}"
                                            />

                                            <button
                                                type="button"
                                                class="size-9 flex items-center justify-center shrink-0 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                                                onclick={() => incrementQuantity(index)}
                                                disabled={isAtStockLimit}
                                                title={isAtStockLimit ? 'Se alcanzó el stock máximo disponible' : 'Aumentar'}
                                                aria-label="Aumentar cantidad"
                                            >
                                                <Plus class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Grupo Derecho (Resultado): Subtotal -->
                                    <div class="flex flex-col items-end ml-auto text-right shrink-0 whitespace-nowrap min-w-[76px] sm:min-w-[85px]">
                                        {#if isDiscountActive}
                                            <span class="line-through text-[11px] text-muted-foreground font-sans tabular-nums" title={formatExactPrice(strikethroughSubtotal)}>
                                                {formatPrice(strikethroughSubtotal)}
                                            </span>
                                            <span class="font-sans tabular-nums text-xs sm:text-sm font-bold text-primary" title={formatExactPrice(finalSubtotal)}>
                                                {formatPrice(finalSubtotal)}
                                            </span>
                                        {:else}
                                            <span class="font-sans tabular-nums text-xs sm:text-sm font-bold text-foreground" title={formatExactPrice(baseSubtotal)}>
                                                {formatPrice(baseSubtotal)}
                                            </span>
                                        {/if}
                                    </div>
                                </div>

                                <!-- Advertencia de exceso temporal durante el tipeo -->
                                {#if item.quantity > maxAllowed}
                                    <div class="flex items-center gap-1.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2.5 py-1 rounded-md border border-amber-500/20">
                                        <AlertTriangle class="size-3.5 shrink-0" />
                                        <span>La cantidad supera el stock disponible (máximo {maxAllowed} {item.sale_type === 'weight' ? (item.packaging_type === 'gram' ? 'g' : 'Kg') : 'u'}).</span>
                                    </div>
                                {/if}
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

                <!-- Bento Card 3: Condición y Medio de Pago -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                    <div class="flex items-start justify-between gap-2.5">
                        <h3 class="min-w-0 flex-1 break-words text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <CreditCard class="size-3.5 text-primary shrink-0" />
                            Condición de Pago
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <label
                            class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all {paymentType === 'cuenta_corriente' ? 'border-primary bg-primary/5 text-foreground font-semibold shadow-xs' : 'border-border/80 bg-background/60 hover:bg-muted/40 text-muted-foreground'}"
                        >
                            <input
                                type="radio"
                                name="order_payment_type"
                                value="cuenta_corriente"
                                bind:group={paymentType}
                                class="mt-0.5 size-4 text-primary focus:ring-primary"
                            />
                            <div class="min-w-0">
                                <span class="text-xs font-medium block">Cuenta Corriente</span>
                                <span class="text-[11px] text-muted-foreground block font-normal">Se suma al saldo deudor del cliente</span>
                            </div>
                        </label>

                        <label
                            class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all {paymentType === 'contado' ? 'border-primary bg-primary/5 text-foreground font-semibold shadow-xs' : 'border-border/80 bg-background/60 hover:bg-muted/40 text-muted-foreground'}"
                        >
                            <input
                                type="radio"
                                name="order_payment_type"
                                value="contado"
                                bind:group={paymentType}
                                class="mt-0.5 size-4 text-primary focus:ring-primary"
                            />
                            <div class="min-w-0">
                                <span class="text-xs font-medium block">Cobro Total</span>
                                <span class="text-[11px] text-muted-foreground block font-normal">Abonado al momento, no genera deuda</span>
                            </div>
                        </label>

                        <label
                            class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all {paymentType === 'parcial' ? 'border-primary bg-primary/5 text-foreground font-semibold shadow-xs' : 'border-border/80 bg-background/60 hover:bg-muted/40 text-muted-foreground'}"
                        >
                            <input
                                type="radio"
                                name="order_payment_type"
                                value="parcial"
                                bind:group={paymentType}
                                class="mt-0.5 size-4 text-primary focus:ring-primary"
                            />
                            <div class="min-w-0">
                                <span class="text-xs font-medium block">Pago Parcial / Seña</span>
                                <span class="text-[11px] text-muted-foreground block font-normal">Entrega anticipada + saldo a cobrar</span>
                            </div>
                        </label>
                    </div>

                    {#if paymentType === 'contado' || paymentType === 'parcial'}
                        <div class="pt-2.5 mt-1 border-t border-border/50 space-y-3">
                            <div class="space-y-1.5">
                                <Label for="order-payment-method" class="text-xs font-semibold text-foreground">
                                    Medio de Pago
                                </Label>
                                <select
                                    id="order-payment-method"
                                    bind:value={paymentMethod}
                                    class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 text-xs text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
                                >
                                    <option value="efectivo">Efectivo</option>
                                    <option value="transferencia">Transferencia Bancaria</option>
                                    <option value="mercadopago">Mercado Pago</option>
                                    <option value="debito">Tarjeta de Débito</option>
                                    <option value="credito">Tarjeta de Crédito</option>
                                    <option value="otro">Otro medio de pago</option>
                                </select>
                            </div>

                            {#if paymentType === 'parcial'}
                                <div class="space-y-1.5 bg-background/80 p-3 rounded-xl border border-border/80">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div>
                                            <Label for="order-paid-amount" class="text-xs font-semibold text-foreground">
                                                Monto Cobrado Anticipadamente ($)
                                            </Label>
                                            <p class="text-[11px] text-muted-foreground">
                                                Deuda restante que quedará pendiente:
                                                <span class="font-semibold tabular-nums text-foreground">
                                                    {formatPrice(Math.max(0, grandTotal - (Number(paidAmount) || 0)))}
                                                </span>
                                            </p>
                                        </div>
                                        <div class="w-full sm:w-48">
                                            <Input
                                                id="order-paid-amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                max={grandTotal}
                                                maxlength="9"
                                                oninput={(e) => {
                                                    const target = e.target as HTMLInputElement;
                                                    if (target.value.length > 9) {
                                                        target.value = target.value.slice(0, 9);
                                                        paidAmount = target.value;
                                                    }
                                                }}
                                                placeholder={`ej. ${(grandTotal / 2).toFixed(2)}`}
                                                bind:value={paidAmount}
                                                class="h-10 text-xs rounded-xl font-sans tabular-nums"
                                            />
                                        </div>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>

                <!-- Bento Card 4: Notas Adicionales -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-1.5">
                    <Label for="order-notes" class="text-xs font-semibold text-foreground">Notas / Instrucciones de Entrega</Label>
                    <textarea
                        id="order-notes"
                        bind:value={notes}
                        rows="2"
                        placeholder="ej. Entregar por la mañana, timbre 2B, empaquetar con cinta reforzada..."
                        class="w-full rounded-xl border border-border/80 bg-background p-3 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary resize-none"
                    ></textarea>
                </div>

            </form>

            <!-- Footer Bento: Total & Botones (shrink-0) -->
            <div class="border-t border-border/60 pt-4 mt-2 space-y-3 shrink-0">
                <!-- Contenedor Superior: Resumen de Totales y Descuentos -->
                <div class="rounded-xl bg-card border border-border/70 p-3 sm:p-3.5 space-y-1.5 shadow-2xs">
                    <div class="flex items-center justify-between text-xs sm:text-sm font-medium text-muted-foreground">
                        <span>Subtotal Productos:</span>
                        <div class="flex items-baseline gap-2">
                            {#if computedTotals.discountTotal > 0}
                                <span class="line-through text-xs text-muted-foreground font-sans tabular-nums">
                                    {formatPrice(computedTotals.baseTotal)}
                                </span>
                            {/if}
                            <span class="font-bold font-sans tabular-nums text-foreground">
                                {formatPrice(computedTotals.finalTotal)}
                            </span>
                        </div>
                    </div>

                    {#if selectedShippingZone && shippingCost > 0}
                        <div class="flex items-center justify-between text-xs text-primary font-semibold border-t border-border/40 pt-1.5">
                            <span class="flex items-center gap-1">
                                <MapPin class="size-3.5" />
                                Envío ({selectedShippingZone.name} - +{selectedShippingZone.surcharge_percentage}%):
                            </span>
                            <span class="font-sans tabular-nums">+{formatPrice(shippingCost)}</span>
                        </div>
                    {/if}

                    {#if computedTotals.discountTotal > 0}
                        <div class="flex items-center justify-between text-xs text-emerald-600 dark:text-emerald-400 font-semibold font-sans tabular-nums border-t border-border/40 pt-1.5">
                            <span>Ahorro por descuento ({customerDiscountPercentage}%):</span>
                            <span>-{formatPrice(computedTotals.discountTotal)}</span>
                        </div>
                    {/if}

                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-t border-border/60 pt-2 mt-1 w-full">
                        <span class="text-xs sm:text-sm font-bold text-foreground">Total a Pagar:</span>
                        <span class="text-lg sm:text-2xl font-bold font-sans tabular-nums text-primary break-all">
                            {formatPrice(grandTotal)}
                        </span>
                    </div>
                </div>

                <!-- Alerta si hay cantidades que exceden el stock disponible -->
                {#if hasQuantityOverflow}
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-amber-600 dark:text-amber-400 justify-end w-full">
                        <AlertTriangle class="size-4 shrink-0" />
                        <span>Ajustá las cantidades que superan el stock máximo para poder crear el pedido.</span>
                    </div>
                {/if}

                <!-- Fila Inferior: Botones de Acción (ambos simétricos, min-h-[44px], sin text-wrap) -->
                <div class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:items-center sm:justify-end sm:w-auto">
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[44px] h-auto py-3 px-4 sm:px-5 rounded-xl text-xs sm:text-sm font-semibold justify-center w-full sm:w-auto shadow-xs leading-snug text-center"
                        onclick={closeModal}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        form="create-order-form"
                        class="min-h-[44px] h-auto py-3 px-4 sm:px-5 rounded-xl text-xs sm:text-sm font-semibold justify-center w-full sm:w-auto shadow-xs leading-snug text-center gap-2"
                        disabled={isSubmitting || items.length === 0 || hasQuantityOverflow}
                    >
                        {#if isSubmitting}
                            <span>Guardando...</span>
                        {:else}
                            <Check class="size-4 shrink-0" />
                            <span>Crear Pedido</span>
                        {/if}
                    </Button>
                </div>
            </div>
        </div>
    </div>
{/if}
