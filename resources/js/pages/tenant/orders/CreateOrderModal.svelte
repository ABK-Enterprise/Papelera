<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import Icon from '@iconify/svelte';
    import HighlightMatch from '@/components/common/HighlightMatch.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { toast } from 'svelte-sonner';

    import { Link, router } from '@inertiajs/svelte';
    import { fade, scale } from 'svelte/transition';
    import Check from 'lucide-svelte/icons/check';
    import Layers from 'lucide-svelte/icons/layers';
    import Minus from 'lucide-svelte/icons/minus';
    import Package from 'lucide-svelte/icons/package';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Scale from 'lucide-svelte/icons/scale';
    import Search from 'lucide-svelte/icons/search';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import User from 'lucide-svelte/icons/user';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Banknote from 'lucide-svelte/icons/banknote';
    import Receipt from 'lucide-svelte/icons/receipt';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import X from 'lucide-svelte/icons/x';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Percent from 'lucide-svelte/icons/percent';
    import Warehouse from 'lucide-svelte/icons/warehouse';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import MoreVertical from 'lucide-svelte/icons/more-vertical';
    import QrCode from 'lucide-svelte/icons/qr-code';
    import Tag from 'lucide-svelte/icons/tag';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import MercadoPagoQrModal from '@/components/common/MercadoPagoQrModal.svelte';
    import EditItemPriceModal from '@/components/tenant/products/EditItemPriceModal.svelte';
    import CreateCustomAdjustmentModal from './components/CreateCustomAdjustmentModal.svelte';
    import ChooseAdjustmentActionModal from './components/ChooseAdjustmentActionModal.svelte';
    import SelectExistingAdjustmentModal from './components/SelectExistingAdjustmentModal.svelte';
    import { preventInvalidNumericKeys, sanitizeNumericInput } from '@/lib/numericSanitizer';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogFooter,
        DialogHeader,
        DialogTitle,
        DialogDescription,
    } from '@/components/ui/dialog';

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
        current_balance?: number | string | null;
        credit_limit?: number | string | null;
        has_credit_balance?: boolean;
        credit_balance_available?: number | string | null;
    }

    export interface WarehouseOption {
        id: number;
        name: string;
        code: string;
        is_default?: boolean;
    }

    export interface ShippingZoneOption {
        id: number;
        name: string;
        surcharge_percentage: number;
    }

    export interface OrderItemLine {
        id: string;
        product_id?: number | null;
        is_on_the_fly?: boolean;
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
        discount_percentage?: number;
        is_custom_discount?: boolean;
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
    let chargeWithQr = $state(true);
    let isQrModalOpen = $state(false);
    let createdOrderForQr = $state<any>(null);
    let targetQrAmountForModal = $state<number | null>(null);

    let isMpInstantCharge = $derived(
        paymentMethod === 'mercadopago' && (paymentType === 'contado' || paymentType === 'parcial')
    );

    $effect(() => {
        if (!selectedCustomerId && paymentType === 'cuenta_corriente') {
            paymentType = 'contado';
        }
    });

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
                return it.quantity * (it.units_per_package || it.package_quantity || 1);
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
                stock: p.stock - used,
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
        addSelectedProduct(prod);
    }

    function addSelectedProduct(prodParam?: ProductOption) {
        const prod = prodParam || selectedProductToAdd;
        if (!prod) return;

        errorMessage = null;

        const isWeighable = prod.sale_type === 'weight';
        const defaultPackaging = isWeighable ? 'kg' : 'unidad';
        const defaultQuantity = 1;
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
                {
                    id: 'item_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8),
                    product_id: prod.id,
                    is_on_the_fly: false,
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
                    discount_percentage: 0,
                    is_custom_discount: false,
                    apply_customer_discount: !isBulk,
                },
                ...items,
            ];
        }

        selectedProductToAdd = null;
        searchQuery = '';
    }

    function addOnTheFlyProduct(name: string) {
        if (!name.trim()) return;
        errorMessage = null;

        const defaultPackaging = 'unidad';
        const defaultQuantity = 1;

        items = [
            {
                id: 'item_otf_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8),
                product_id: null,
                is_on_the_fly: true,
                warehouse_id: selectedWarehouseId,
                product_name: name.trim(),
                sku: null,
                sale_type: 'unit',
                stock: 0,
                packaging_type: defaultPackaging,
                quantity: defaultQuantity,
                unit_price: 0,
                regular_price: 0,
                bulk_discount_price: 0,
                bulk_discount_percentage: 0,
                bulk_discount_type: 'none',
                units_per_package: 1,
                package_quantity: 1,
                discount_percentage: 0,
                is_custom_discount: false,
                apply_customer_discount: false,
            },
            ...items,
        ];

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

    function getMaxAllowedQuantityForItem(_index: number): number {
        return 10000;
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
        return items.some((it) => {
            return Number(it.quantity) > 10000 || Number(it.quantity) <= 0;
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
                } else {
                    item.quantity = max;
                }
            } else if (item.packaging_type === 'kg') {
                const step = 0.5;
                if (item.quantity + step <= max) {
                    item.quantity = Math.round((item.quantity + step) * 1000) / 1000;
                } else {
                    item.quantity = max;
                }
            } else {
                if (item.quantity < max) {
                    item.quantity += 1;
                }
            }
        } else {
            if (item.quantity < max) {
                item.quantity += 1;
            }
        }
        errorMessage = null;
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

    function getItemLineDiscount(it: OrderItemLine): {
        discountAmount: number;
        finalSubtotal: number;
        baseSubtotal: number;
        itemDiscountAmount: number;
        customerDiscountAmount: number;
        netLineSubtotal: number;
    } {
        const baseQty = getItemQuantityBase(it);
        const baseSubtotal = baseQty * it.unit_price;

        const itemDiscountPct = Number(it.discount_percentage) || 0;
        const itemDiscountAmount = Math.round(baseSubtotal * (itemDiscountPct / 100) * 100) / 100;
        const netLineSubtotal = Math.round((baseSubtotal - itemDiscountAmount) * 100) / 100;

        const isEligible = applyCustomerDiscount && it.apply_customer_discount && customerDiscountPercentage > 0;
        const customerDiscountAmount = isEligible
            ? Math.round(netLineSubtotal * (customerDiscountPercentage / 100) * 100) / 100
            : 0;

        const finalSubtotal = Math.round((netLineSubtotal - customerDiscountAmount) * 100) / 100;
        const totalDiscountAmount = Math.round((itemDiscountAmount + customerDiscountAmount) * 100) / 100;

        return {
            discountAmount: totalDiscountAmount,
            finalSubtotal,
            baseSubtotal,
            itemDiscountAmount,
            customerDiscountAmount,
            netLineSubtotal,
        };
    }

    let computedTotals = $derived.by(() => {
        let baseTotal = 0;
        let itemDiscountTotal = 0;
        let customerDiscountTotal = 0;
        let finalTotal = 0;

        for (const it of items) {
            const { baseSubtotal, itemDiscountAmount, customerDiscountAmount, finalSubtotal } = getItemLineDiscount(it);
            baseTotal += baseSubtotal;
            itemDiscountTotal += itemDiscountAmount;
            customerDiscountTotal += customerDiscountAmount;
            finalTotal += finalSubtotal;
        }

        return {
            baseTotal: Math.round(baseTotal * 100) / 100,
            itemDiscountTotal: Math.round(itemDiscountTotal * 100) / 100,
            customerDiscountTotal: Math.round(customerDiscountTotal * 100) / 100,
            discountTotal: Math.round((itemDiscountTotal + customerDiscountTotal) * 100) / 100,
            subtotalProducts: Math.round((baseTotal - itemDiscountTotal) * 100) / 100,
            finalTotal: Math.round(finalTotal * 100) / 100,
        };
    });

    // Mobile discount dialog state
    let mobileDiscountItemIndex = $state<number | null>(null);
    let mobileDiscountPreset = $state<number | string>(0);
    let isMobileDiscountCustom = $state(false);
    let mobileDiscountCustomVal = $state<number | string>('');
    let isMobileDiscountModalOpen = $state(false);

    // Units per package ephemeral dialog state
    let editingUnitsItemIndex = $state<number | null>(null);
    let editingUnitsValue = $state<number | string>(1);
    let isUnitsModalOpen = $state(false);

    function openUnitsPerPackageDialog(index: number) {
        editingUnitsItemIndex = index;
        editingUnitsValue = items[index]?.units_per_package || 1;
        isUnitsModalOpen = true;
    }

    function saveUnitsPerPackage() {
        if (editingUnitsItemIndex !== null && items[editingUnitsItemIndex]) {
            const parsed = Math.max(1, Math.floor(Number(editingUnitsValue) || 1));
            items[editingUnitsItemIndex] = {
                ...items[editingUnitsItemIndex],
                units_per_package: parsed,
                package_quantity: parsed,
            };
        }
        isUnitsModalOpen = false;
        editingUnitsItemIndex = null;
    }

    function openMobileDiscountDialog(index: number) {
        mobileDiscountItemIndex = index;
        const currentPct = items[index]?.discount_percentage || 0;
        const commonPresets = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 100];
        if (commonPresets.includes(currentPct)) {
            mobileDiscountPreset = currentPct;
            isMobileDiscountCustom = false;
            mobileDiscountCustomVal = '';
        } else {
            mobileDiscountPreset = 'custom';
            isMobileDiscountCustom = true;
            mobileDiscountCustomVal = currentPct;
        }
        isMobileDiscountModalOpen = true;
    }

    function applyMobileDiscount() {
        if (mobileDiscountItemIndex !== null && items[mobileDiscountItemIndex]) {
            let pct = 0;
            if (isMobileDiscountCustom || mobileDiscountPreset === 'custom') {
                pct = Math.max(0, Math.min(100, Number(mobileDiscountCustomVal) || 0));
                items[mobileDiscountItemIndex].is_custom_discount = true;
            } else {
                pct = Number(mobileDiscountPreset) || 0;
                items[mobileDiscountItemIndex].is_custom_discount = false;
            }
            items[mobileDiscountItemIndex].discount_percentage = pct;
        }
        isMobileDiscountModalOpen = false;
        mobileDiscountItemIndex = null;
    }

    // Edit item price dialog state
    let editingPriceItemIndex = $state<number | null>(null);
    let isEditItemPriceModalOpen = $state(false);

    function openEditItemPriceDialog(index: number) {
        editingPriceItemIndex = index;
        isEditItemPriceModalOpen = true;
    }

    function handleOrderItemPriceUpdated(newPrice: number, mode: 'order_only' | 'catalog') {
        if (editingPriceItemIndex === null || !items[editingPriceItemIndex]) return;
        const it = items[editingPriceItemIndex];
        it.unit_price = newPrice;
        it.regular_price = newPrice;
        if (mode === 'catalog' && it.product_id) {
            const p = availableProducts.find((prod) => prod.id === it.product_id);
            if (p) {
                p.price = newPrice;
            }
        }
        editingPriceItemIndex = null;
        isEditItemPriceModalOpen = false;
    }

    let priceAdjustments = $state<any[]>([]);
    let showAccountInReceipt = $state(false);
    let isChooseAdjustmentModalOpen = $state(false);
    let isCreateAdjustmentModalOpen = $state(false);
    let isSelectExistingAdjustmentModalOpen = $state(false);

    let shippingCost = $derived.by(() => {
        if (!selectedShippingZone) return 0;
        const pct = Number(selectedShippingZone.surcharge_percentage) || 0;
        return Math.round(computedTotals.finalTotal * (pct / 100) * 100) / 100;
    });

    function getAdjustmentAppliedAmount(adj: any, productsSubtotal: number): number {
        const val = Number(adj.value) || 0;
        if (adj.calculation_type === 'percentage') {
            return Math.round(productsSubtotal * (val / 100) * 100) / 100;
        }
        return Math.round(val * 100) / 100;
    }

    let adjustmentsTotals = $derived.by(() => {
        let disc = 0;
        let sur = 0;
        for (const adj of priceAdjustments) {
            const applied = getAdjustmentAppliedAmount(adj, computedTotals.finalTotal);
            if (adj.type === 'discount') {
                disc += applied;
            } else {
                sur += applied;
            }
        }
        return {
            discountTotal: Math.round(disc * 100) / 100,
            surchargeTotal: Math.round(sur * 100) / 100,
        };
    });

    let grossTotal = $derived(
        Math.max(
            0,
            Math.round(
                (computedTotals.finalTotal +
                    shippingCost +
                    adjustmentsTotals.surchargeTotal -
                    adjustmentsTotals.discountTotal) *
                    100
            ) / 100
        )
    );

    // Saldo a Favor de Cuenta Corriente
    let isCreditBalanceModalOpen = $state(false);
    let useCreditBalance = $state(false);
    let creditBalanceApplied = $state<number>(0);
    let showCreditAppliedInReceipt = $state(false);
    let editCreditMode = $state<'fixed' | 'percentage'>('fixed');
    let editCreditFixedValue = $state<number | string>('');
    let editCreditPercentageValue = $state<number | string>('');

    const availableCustomerCredit = $derived(
        selectedCustomer && Number(selectedCustomer.current_balance ?? 0) < 0
            ? Math.abs(Number(selectedCustomer.current_balance ?? 0))
            : 0
    );
    const hasAvailableCredit = $derived(availableCustomerCredit > 0);
    const effectiveCreditBalanceApplied = $derived(
        paymentType === 'cuenta_corriente' && useCreditBalance
            ? Math.min(Number(creditBalanceApplied) || 0, availableCustomerCredit, grossTotal)
            : 0
    );
    let grandTotal = $derived(
        Math.max(0, Math.round((grossTotal - effectiveCreditBalanceApplied) * 100) / 100)
    );
    const remainingToPay = $derived(grandTotal);
    const canShowCreditAppliedInReceipt = $derived(
        useCreditBalance && effectiveCreditBalanceApplied > 0
    );

    // Cobro de Deuda Anterior de Cuenta Corriente (Solo en Cobro Total)
    let isDebtBalanceModalOpen = $state(false);
    let collectPreviousDebt = $state(false);
    let previousDebtCollected = $state<number>(0);
    let showDebtCollectedInReceipt = $state(false);
    let editDebtMode = $state<'fixed' | 'percentage'>('fixed');
    let editDebtFixedValue = $state<number | string>('');
    let editDebtPercentageValue = $state<number | string>('');

    const availableCustomerDebt = $derived(
        selectedCustomer && Number(selectedCustomer.current_balance ?? 0) > 0
            ? Number(selectedCustomer.current_balance ?? 0)
            : 0
    );
    const hasAvailableDebt = $derived(availableCustomerDebt > 0);
    const effectivePreviousDebtCollected = $derived(
        paymentType === 'contado' && collectPreviousDebt
            ? Math.min(Math.max(0, Number(previousDebtCollected) || 0), availableCustomerDebt)
            : 0
    );
    const canShowDebtCollectedInReceipt = $derived(
        collectPreviousDebt && effectivePreviousDebtCollected > 0
    );

    $effect(() => {
        if (paymentType !== 'cuenta_corriente' || !selectedCustomer || !hasAvailableCredit) {
            useCreditBalance = false;
            creditBalanceApplied = 0;
            showCreditAppliedInReceipt = false;
        }
    });

    $effect(() => {
        if (!canShowCreditAppliedInReceipt && showCreditAppliedInReceipt) {
            showCreditAppliedInReceipt = false;
        }
    });

    $effect(() => {
        if (paymentType !== 'contado' || !selectedCustomer || !hasAvailableDebt) {
            collectPreviousDebt = false;
            previousDebtCollected = 0;
            showDebtCollectedInReceipt = false;
        }
    });

    $effect(() => {
        if (!canShowDebtCollectedInReceipt && showDebtCollectedInReceipt) {
            showDebtCollectedInReceipt = false;
        }
    });

    let showAccountBalanceInOrderSummary = $state(false);

    let customerRawBalance = $derived(Number(selectedCustomer?.current_balance ?? 0));

    let accountCurrentBalanceFormatted = $derived.by(() => {
        if (!selectedCustomer) return '';
        if (customerRawBalance < 0) {
            return `${formatPrice(Math.abs(customerRawBalance))} (a favor)`;
        } else if (customerRawBalance > 0) {
            return `${formatPrice(customerRawBalance)} (deudor)`;
        }
        return `${formatPrice(0)} (al día)`;
    });

    let accountBalanceCheckboxLabel = $derived.by(() => {
        if (customerRawBalance < 0) {
            return 'Mostrar como ítem en el subtotal el saldo a favor del cliente';
        }
        if (customerRawBalance > 0) {
            return 'Mostrar como ítem en el subtotal el saldo deudor del cliente';
        }
        return 'Mostrar como ítem en el subtotal el saldo de cuenta corriente del cliente';
    });

    let accountProjectedBalanceFormatted = $derived.by(() => {
        if (!selectedCustomer) return null;
        if (paymentType === 'cuenta_corriente') {
            if (grossTotal <= 0) return null;
            const projected = customerRawBalance + grossTotal;
            if (projected < 0) {
                return `${formatPrice(Math.abs(projected))} (a favor)`;
            } else if (projected > 0) {
                return `${formatPrice(projected)} (deudor)`;
            }
            return `${formatPrice(0)} (al día)`;
        } else if (paymentType === 'contado') {
            if (effectivePreviousDebtCollected > 0) {
                const projectedDebt = Math.max(0, availableCustomerDebt - effectivePreviousDebtCollected);
                if (projectedDebt > 0) {
                    return `${formatPrice(projectedDebt)} (deudor)`;
                }
                return `${formatPrice(0)} (al día)`;
            }
            return null;
        } else if (paymentType === 'parcial') {
            if (grossTotal <= 0) return null;
            const numericPaid = Math.max(0, Number(paidAmount) || 0);
            const unpaidAmount = Math.max(0, grossTotal - numericPaid);
            const projected = customerRawBalance + unpaidAmount;
            if (projected < 0) {
                return `${formatPrice(Math.abs(projected))} (a favor)`;
            } else if (projected > 0) {
                return `${formatPrice(projected)} (deudor)`;
            }
            return `${formatPrice(0)} (al día)`;
        }
        return null;
    });

    function toggleUseCreditBalance() {
        if (!hasAvailableCredit || paymentType !== 'cuenta_corriente') return;
        useCreditBalance = !useCreditBalance;
        if (useCreditBalance) {
            creditBalanceApplied = Math.min(availableCustomerCredit, grossTotal);
            showCreditAppliedInReceipt = true;
        } else {
            creditBalanceApplied = 0;
            showCreditAppliedInReceipt = false;
        }
    }

    function applyFullCreditBalance() {
        if (!hasAvailableCredit) return;
        creditBalanceApplied = Math.min(availableCustomerCredit, grossTotal);
        toast.success(`Se aplicó el saldo a favor: ${formatPrice(creditBalanceApplied)}`);
    }

    function handleStrictPositiveNumericKeydown(e: KeyboardEvent, allowDecimal = true) {
        if (['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Enter'].includes(e.key)) {
            return;
        }
        if (e.ctrlKey || e.metaKey) {
            return;
        }
        if (allowDecimal && (e.key === '.' || e.key === ',')) {
            const input = e.currentTarget as HTMLInputElement;
            if (input.value.includes('.') || input.value.includes(',')) {
                e.preventDefault();
            }
            return;
        }
        if (!/^[0-9]$/.test(e.key)) {
            e.preventDefault();
        }
    }

    function handleStrictFixedAmountInput(e: Event, maxLimit: number, assign: (val: number | string) => void) {
        const input = e.target as HTMLInputElement;
        let clean = input.value.replace(/,/g, '.').replace(/[^0-9.]/g, '');
        const parts = clean.split('.');
        if (parts.length > 2) {
            clean = parts[0] + '.' + parts.slice(1).join('');
        }
        if (clean.includes('.')) {
            const [intPart, decPart] = clean.split('.');
            clean = intPart + '.' + decPart.slice(0, 2);
        }
        const num = Number(clean) || 0;
        if (num > maxLimit) {
            clean = String(maxLimit);
        }
        input.value = clean;
        assign(clean);
    }

    function handleStrictPercentageInput(e: Event, assign: (val: number | string) => void) {
        const input = e.target as HTMLInputElement;
        let clean = input.value.replace(/[^0-9]/g, '');
        let num = Number(clean) || 0;
        if (num > 100) {
            num = 100;
            clean = '100';
        }
        input.value = clean;
        assign(clean);
    }

    function openEditCreditBalanceModal() {
        editCreditMode = 'fixed';
        const currentTarget = effectiveCreditBalanceApplied > 0 ? effectiveCreditBalanceApplied : Math.min(availableCustomerCredit, grossTotal);
        editCreditFixedValue = currentTarget;
        editCreditPercentageValue = availableCustomerCredit > 0
            ? Math.min(100, Math.max(0, Math.round((currentTarget / availableCustomerCredit) * 100)))
            : 0;
        isCreditBalanceModalOpen = true;
    }

    function saveCreditBalanceEdit() {
        let target = 0;
        if (editCreditMode === 'fixed') {
            target = Math.max(0, Number(editCreditFixedValue) || 0);
        } else {
            const pct = Math.max(0, Math.min(100, Number(editCreditPercentageValue) || 0));
            target = Math.round(availableCustomerCredit * (pct / 100) * 100) / 100;
        }
        if (target > availableCustomerCredit) {
            toast.warning(`El monto supera el saldo a favor disponible (${formatPrice(availableCustomerCredit)}). Se ajustó al máximo disponible.`);
            target = availableCustomerCredit;
        }
        if (target > grossTotal) {
            toast.warning(`El monto supera el total del pedido (${formatPrice(grossTotal)}). Se imputará hasta cubrir el pedido.`);
            target = grossTotal;
        }
        creditBalanceApplied = Math.max(0, target);
        isCreditBalanceModalOpen = false;
    }

    function toggleCollectPreviousDebt() {
        if (!hasAvailableDebt || paymentType !== 'contado') return;
        collectPreviousDebt = !collectPreviousDebt;
        if (collectPreviousDebt) {
            previousDebtCollected = availableCustomerDebt;
            showDebtCollectedInReceipt = true;
        } else {
            previousDebtCollected = 0;
            showDebtCollectedInReceipt = false;
        }
    }

    function applyFullPreviousDebt() {
        if (!hasAvailableDebt) return;
        previousDebtCollected = availableCustomerDebt;
        toast.success(`Se aplicó el cobro total de deuda: ${formatPrice(previousDebtCollected)}`);
    }

    function openEditDebtBalanceModal() {
        editDebtMode = 'fixed';
        const currentTarget = effectivePreviousDebtCollected > 0 ? effectivePreviousDebtCollected : availableCustomerDebt;
        editDebtFixedValue = currentTarget;
        editDebtPercentageValue = availableCustomerDebt > 0
            ? Math.min(100, Math.max(0, Math.round((currentTarget / availableCustomerDebt) * 100)))
            : 0;
        isDebtBalanceModalOpen = true;
    }

    function saveDebtBalanceEdit() {
        let target = 0;
        if (editDebtMode === 'fixed') {
            target = Math.max(0, Number(editDebtFixedValue) || 0);
        } else {
            const pct = Math.max(0, Math.min(100, Number(editDebtPercentageValue) || 0));
            target = Math.round(availableCustomerDebt * (pct / 100) * 100) / 100;
        }
        if (target > availableCustomerDebt) {
            toast.warning(`El monto supera la deuda del cliente (${formatPrice(availableCustomerDebt)}). Se ajustó al máximo disponible.`);
            target = availableCustomerDebt;
        }
        previousDebtCollected = Math.max(0, target);
        isDebtBalanceModalOpen = false;
    }

    let isDeleteAdHocModalOpen = $state(false);
    let adHocToDelete = $state<any | null>(null);

    function confirmDeleteAdHoc(adj: any) {
        adHocToDelete = adj;
        isDeleteAdHocModalOpen = true;
    }

    function executeDeleteAdHoc() {
        if (!adHocToDelete) return;
        priceAdjustments = priceAdjustments.filter((a) => a.id !== adHocToDelete.id);
        toast.success('Ajuste al vuelo eliminado.');
        isDeleteAdHocModalOpen = false;
        adHocToDelete = null;
    }

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
        paymentType = selectedCustomerId ? 'cuenta_corriente' : 'contado';
        paymentMethod = 'efectivo';
        paidAmount = '';
        errorMessage = null;
        searchQuery = '';
        selectedProductToAdd = null;
        items = [];
        priceAdjustments = [];
        showAccountInReceipt = false;
        showAccountBalanceInOrderSummary = false;
        useCreditBalance = false;
        creditBalanceApplied = 0;
        showCreditAppliedInReceipt = false;
        isCreditBalanceModalOpen = false;
        collectPreviousDebt = false;
        previousDebtCollected = 0;
        showDebtCollectedInReceipt = false;
        isDebtBalanceModalOpen = false;
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

        for (const it of items) {
            if ((!it.product_id || it.is_on_the_fly) && (!it.unit_price || Number(it.unit_price) <= 0)) {
                errorMessage = `Por favor ingresa un precio unitario válido mayor a 0 para "${it.product_name}".`;
                return;
            }
        }

        isSubmitting = true;
        errorMessage = null;

        const payloadItems: {
            product_id?: number | null;
            product_name?: string;
            unit_price?: number;
            warehouse_id?: number | null;
            packaging_type: 'unidad' | 'bulto' | 'gram' | 'kg';
            quantity: number;
            units_per_package?: number;
            discount_percentage?: number;
            apply_customer_discount: boolean;
        }[] = [];

        for (const it of items) {
            payloadItems.push({
                product_id: it.product_id || null,
                product_name: it.product_name,
                unit_price: Number(it.unit_price),
                warehouse_id: it.warehouse_id || selectedWarehouseId,
                packaging_type: it.packaging_type,
                quantity: it.quantity,
                units_per_package: it.packaging_type === 'bulto' ? (Number(it.units_per_package) || 1) : undefined,
                discount_percentage: Number(it.discount_percentage) || 0,
                apply_customer_discount: it.apply_customer_discount,
            });
        }

        let numericPaidAmount: number | null = null;
        if (paymentType === 'parcial') {
            const raw = Number(paidAmount) || 0;
            if (raw <= 0) {
                errorMessage = 'Por favor ingresa un monto válido a cobrar para el pago parcial.';
                isSubmitting = false;
                return;
            }
            if (raw >= remainingToPay) {
                errorMessage = 'El pago parcial debe ser menor al total a pagar. Para cobrar el total, selecciona "Cobro Total en el Momento".';
                isSubmitting = false;
                return;
            }
            numericPaidAmount = raw;
        }

        const shouldChargeWithQr = isMpInstantCharge && chargeWithQr;

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
                price_adjustments: priceAdjustments.map((adj) => ({
                    ...adj,
                    applied_amount: getAdjustmentAppliedAmount(adj, computedTotals.finalTotal),
                })),
                credit_balance_applied: paymentType === 'cuenta_corriente' ? effectiveCreditBalanceApplied : 0,
                previous_debt_collected: paymentType === 'contado' ? effectivePreviousDebtCollected : 0,
                show_credit_applied_in_receipt: canShowCreditAppliedInReceipt ? showCreditAppliedInReceipt : false,
                show_debt_collected_in_receipt: canShowDebtCollectedInReceipt ? showDebtCollectedInReceipt : false,
                show_account_in_receipt: showAccountInReceipt,
                show_account_status_in_receipt: true,
                show_account_balance_in_receipt: true,
                mp_qr_checkout: shouldChargeWithQr ? true : undefined,
                items: payloadItems,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (page.props as any)?.flash;
                    const createdOrder = flash?.created_order;
                    const targetAmount = flash?.target_qr_amount;

                    if (shouldChargeWithQr && createdOrder) {
                        createdOrderForQr = createdOrder;
                        targetQrAmountForModal = targetAmount || (paymentType === 'parcial' ? numericPaidAmount : (grandTotal + effectivePreviousDebtCollected));
                        isQrModalOpen = true;
                        open = false;
                        resetForm();
                    } else {
                        closeModal();
                    }
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
            <div class="flex items-center justify-between gap-3 border-b pb-3.5 sm:pb-4 shrink-0">
                <div class="min-w-0 flex-1 flex items-center gap-2.5">
                    <div class="shrink-0">
                        <IconContainer size="md" variant="primary" icon="solar:bill-list-bold-duotone" />
                    </div>
                    <div class="min-w-0 flex-1">
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
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                                <UserCheck class="size-3" />
                                Cliente Registrado
                            </span>
                        {/if}
                    </div>

                    <!-- Buscador Autocomplete de Clientes con SearchCombobox -->
                    <div class="space-y-1">
                        <Label class="text-sm font-medium text-foreground mb-1 block">
                            Buscar Cliente Registrado o Escribir Nuevo
                        </Label>
                        <SearchCombobox
                            id="cust-search"
                            bind:value={customerSearchQuery}
                            items={customerSearchResults}
                            filterLocally={false}
                            placeholder="Escribe el nombre, CUIT/DNI o teléfono para autocompletar..."
                            showDropdown={true}
                            onsearch={(q) => {
                                customerSearchQuery = q;
                                customerName = q;
                            }}
                            onselect={selectCustomerFromSearch}
                            onclear={clearCustomerSelection}
                            inputClass="h-9 text-sm font-medium"
                        >
                            {#snippet itemSnippet(cust)}
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 sm:gap-3 w-full py-0.5">
                                    <!-- Main data block -->
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <!-- Row 1: Name + Moroso badge -->
                                        <div class="flex items-start justify-between gap-2 w-full">
                                            <span class="min-w-0 flex-1 font-bold text-foreground line-clamp-2 break-words text-sm leading-snug">
                                                <HighlightMatch text={cust.name} query={customerSearchQuery} />
                                            </span>
                                            {#if cust.is_defaulter}
                                                <span class="shrink-0 inline-flex items-center gap-0.5 text-xs font-semibold text-rose-600 bg-rose-500/10 px-1.5 py-0.5 rounded border border-rose-500/20 mt-0.5">
                                                    Moroso
                                                </span>
                                            {/if}
                                        </div>

                                        <!-- Row 2: Tax ID + Tax condition -->
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            {#if cust.tax_id}
                                                <span class="text-xs font-sans tabular-nums bg-muted px-1.5 py-0.5 rounded text-muted-foreground shrink-0">
                                                    <HighlightMatch text={cust.tax_id} query={customerSearchQuery} />
                                                </span>
                                            {/if}
                                            <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                                                {cust.tax_condition.replace('_', ' ')}
                                            </span>
                                        </div>

                                        <!-- Row 3: Contact -->
                                        <p class="text-xs text-muted-foreground line-clamp-1 break-words">
                                            {cust.phone || 'Sin teléfono'} • {cust.address || 'Sin dirección'}
                                        </p>
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
                            <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-foreground shrink-0">
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
                            <Label for="cust-name" class="text-sm font-medium text-foreground">
                                Nombre / Razón Social <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="cust-name"
                                bind:value={customerName}
                                placeholder="ej. Ana García / Librería Central"
                                class="h-9 text-sm font-medium"
                                required
                            />
                        </div>
                        <div class="sm:col-span-3 space-y-1">
                            <Label for="cust-phone" class="text-sm font-medium text-foreground">Teléfono / Contacto</Label>
                            <Input
                                id="cust-phone"
                                bind:value={customerPhone}
                                placeholder="+54 9 11 4455-6677"
                                class="h-9 text-sm font-medium"
                            />
                        </div>
                        <div class="sm:col-span-4 space-y-1">
                            <Label for="cust-address" class="text-sm font-medium text-foreground">Dirección de Entrega</Label>
                            <Input
                                id="cust-address"
                                bind:value={deliveryAddress}
                                maxlength="255"
                                placeholder="Dirección o Retiro en local"
                                class="h-9 text-sm font-medium"
                            />
                        </div>
                    </div>

                    <!-- Selector de Depósito de Despacho si hay depósitos -->
                    {#if warehouses && warehouses.length > 0}
                        <div class="pt-3 mt-1.5 border-t border-border/50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                            <Label for="order-warehouse" class="text-sm font-medium text-foreground flex items-center gap-1.5 shrink-0">
                                <Warehouse class="size-3.5 text-primary" />
                                Depósito de Origen / Despacho:
                            </Label>
                            <select
                                id="order-warehouse"
                                bind:value={selectedWarehouseId}
                                class="h-10 w-full sm:w-64 rounded-xl border border-border/80 bg-background px-3 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
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
                        <Label for="order-shipping-zone" class="text-sm font-medium text-foreground flex items-center gap-1.5 shrink-0">
                            <MapPin class="size-3.5 text-primary" />
                            Zona de Envío / Flete:
                        </Label>
                        <select
                            id="order-shipping-zone"
                            bind:value={selectedShippingZoneId}
                            class="h-10 w-full sm:w-64 rounded-xl border border-border/80 bg-background px-3 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
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
                    <div class="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-center w-full">
                        <div class="w-full flex-1 min-w-0">
                            <SearchCombobox
                                bind:value={searchQuery}
                                items={searchResults}
                                filterLocally={false}
                                placeholder="Escribe el nombre o SKU del producto para buscar..."
                                showDropdown={true}
                                onsearch={(q) => (searchQuery = q)}
                                onselect={selectProductFromSearch}
                                onclear={() => {
                                    searchQuery = '';
                                    selectedProductToAdd = null;
                                }}
                                inputClass="h-11 sm:h-10 min-h-[44px] sm:min-h-[40px] text-xs sm:text-sm font-medium w-full"
                            >
                                {#snippet itemSnippet(prod)}
                                    <div class="flex items-start justify-between gap-2.5 w-full text-left py-0.5">
                                        <div class="min-w-0 flex-1 space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-foreground text-xs leading-snug line-clamp-2 break-words">
                                                    <HighlightMatch text={prod.name} query={searchQuery} />
                                                </span>
                                                {#if prod.sale_type === 'weight'}
                                                    <span class="shrink-0 inline-flex items-center gap-0.5 text-xs font-medium text-sky-600 bg-sky-500/10 px-1.5 py-0.2 rounded border border-sky-500/20">
                                                        <Scale class="size-2.5" />
                                                        Pesaje
                                                    </span>
                                                {/if}
                                            </div>
                                            {#if prod.sku}
                                                <div class="text-xs font-sans tabular-nums text-muted-foreground mt-0.5">
                                                    SKU: <HighlightMatch text={prod.sku} query={searchQuery} />
                                                </div>
                                            {/if}
                                        </div>


                                        <div class="text-right shrink-0 min-w-[76px] pl-1">
                                            <span class="font-bold font-sans tabular-nums text-primary text-xs sm:text-sm block">
                                                {formatPrice(Number(prod.price))}
                                            </span>
                                            <span class="text-xs font-sans tabular-nums text-muted-foreground block mt-0.5 whitespace-nowrap">
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
                                        No se encontraron coincidencias en el catálogo.
                                    </span>
                                {/snippet}
                                {#snippet footerSnippet()}
                                    {#if searchQuery.trim().length > 0}
                                        <div class="p-1.5 border-t border-border/60 bg-muted/20">
                                            <button
                                                type="button"
                                                class="w-full flex items-center justify-between gap-2 p-2 rounded-lg text-left text-xs text-primary hover:bg-primary/10 transition-colors cursor-pointer"
                                                onclick={() => addOnTheFlyProduct(searchQuery.trim())}
                                            >
                                                <span class="font-semibold flex items-center gap-1.5 truncate">
                                                    <Plus class="size-3.5 shrink-0" />
                                                    <span>Crear "{searchQuery.trim()}" (al vuelo)</span>
                                                </span>
                                                <span class="text-xs bg-primary/10 text-primary border border-primary/20 px-1.5 py-0.5 rounded font-bold uppercase tracking-wider shrink-0">
                                                    Incompleto
                                                </span>
                                            </button>
                                        </div>
                                    {/if}
                                {/snippet}
                            </SearchCombobox>
                        </div>

                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            class="hidden sm:inline-flex w-full sm:w-auto min-h-[44px] sm:min-h-[40px] sm:h-10 px-5 rounded-xl text-xs sm:text-sm gap-2 font-semibold shrink-0 justify-center shadow-2xs bg-primary-500 hover:bg-primary-600 text-white border-transparent dark:bg-secondary dark:text-secondary-foreground dark:hover:bg-secondary/80 dark:border-secondary/40"
                            onclick={() => {
                                if (selectedProductToAdd) {
                                    addSelectedProduct();
                                } else if (searchQuery.trim().length > 0) {
                                    addOnTheFlyProduct(searchQuery.trim());
                                }
                            }}
                            disabled={!selectedProductToAdd && searchQuery.trim().length === 0}
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

                            <div class="order-item-card flex flex-col gap-2.5 p-3.5 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs">
                                <!-- Nivel 1: Información (Nombre, SKU, Badges, Precio Base y Stock) -->
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-start justify-between gap-2.5 min-w-0">
                                        <div class="flex items-start gap-2.5 min-w-0 flex-1">
                                            <div class="shrink-0 mt-0.5">
                                                <IconContainer
                                                    size="sm"
                                                    variant={item.is_on_the_fly ? 'neutral' : (item.sale_type === 'weight' ? 'info' : 'primary')}
                                                    icon={item.is_on_the_fly ? 'solar:question-mark-bold-duotone' : (item.sale_type === 'weight' ? 'solar:scale-bold-duotone' : 'solar:box-minimalistic-bold-duotone')}
                                                />
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 min-w-0 flex-1">
                                                <h4 class="text-base font-bold text-foreground line-clamp-2 break-words">{item.product_name}</h4>
                                                {#if item.is_on_the_fly}
                                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-muted text-muted-foreground border border-border/80 break-words max-w-full">
                                                        Al Vuelo (Incompleto)
                                                    </span>
                                                {/if}
                                                {#if item.sku}
                                                    <span class="text-sm font-sans tabular-nums bg-muted px-2 py-0.5 rounded text-muted-foreground shrink-0">
                                                        {item.sku}
                                                    </span>
                                                {/if}
                                                <!-- Tipo de venta: Pesaje o Unidad -->
                                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full shrink-0 {item.sale_type === 'weight' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground border border-border/70'}">
                                                    {item.sale_type === 'weight' ? 'Pesaje' : 'Unidad'}
                                                </span>

                                                <!-- Mobile: Pill compacto 'X%' al lado del tipo de venta -->
                                                {#if hasBulkDiscount}
                                                    <span class="sm:hidden inline-flex items-center text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 shrink-0 font-sans tabular-nums" title="Descuento por Bulto {item.bulk_discount_percentage}% ({item.bulk_discount_type === 'custom' ? 'Personalizado' : 'Heredado'})">
                                                        {item.bulk_discount_percentage}%
                                                    </span>
                                                {/if}

                                                <!-- Desktop: Pill descriptivo completo -->
                                                {#if isBulk}
                                                    {#if item.bulk_discount_type !== 'none' && item.bulk_discount_percentage > 0}
                                                        <span class="hidden sm:inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full border bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30">
                                                            <Percent class="size-3" />
                                                            Desc. Bulto {item.bulk_discount_percentage}% ({item.bulk_discount_type === 'custom' ? 'Personalizado' : 'Heredado'})
                                                        </span>
                                                    {:else}
                                                        <span class="hidden sm:inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full border bg-muted text-muted-foreground border-border">
                                                            Sin Descuento por Bulto
                                                        </span>
                                                    {/if}
                                                {/if}
                                                {#if (item.discount_percentage || 0) > 0}
                                                    <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full border bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 shrink-0">
                                                        <Percent class="size-3" />
                                                        Desc. Ítem {item.discount_percentage}%
                                                    </span>
                                                {/if}
                                                {#if customerDiscountPercentage > 0 && applyCustomerDiscount}
                                                    <button
                                                        type="button"
                                                        onclick={() => toggleItemCustomerDiscount(index)}
                                                        class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full border transition-colors cursor-pointer {item.apply_customer_discount ? 'bg-primary/15 text-primary border-primary/30' : 'bg-muted text-muted-foreground border-border line-through'}"
                                                        title="Click para alternar descuento de cliente en este producto"
                                                    >
                                                        <Percent class="size-3" />
                                                        Desc. {customerDiscountPercentage}% {item.apply_customer_discount ? 'Aplicado' : 'Excluido'}
                                                    </button>
                                                {/if}
                                            </div>
                                        </div>

                                        <!-- Mobile: Menú Contextual (...) en la esquina superior derecha -->
                                        <div class="sm:hidden shrink-0">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    {#snippet children(props)}
                                                        <button
                                                            {...props}
                                                            type="button"
                                                            class="size-8 rounded-lg flex items-center justify-center bg-muted/60 text-foreground hover:bg-muted border border-border/60 transition-colors cursor-pointer"
                                                            aria-label="Opciones del producto"
                                                        >
                                                            <MoreVertical class="size-4" />
                                                        </button>
                                                    {/snippet}
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" class="w-48 shadow-lg">
                                                    {#if item.packaging_type === 'bulto'}
                                                        <DropdownMenuItem
                                                            class="flex items-center gap-2 text-xs font-medium cursor-pointer py-2.5"
                                                            onclick={() => openUnitsPerPackageDialog(index)}
                                                        >
                                                            <Pencil class="size-3.5 shrink-0 text-sky-600 dark:text-sky-400" />
                                                            <span>Unidades por bulto ({item.units_per_package || 1} u)</span>
                                                        </DropdownMenuItem>
                                                    {/if}
                                                    <DropdownMenuItem
                                                        class="flex items-center gap-2 text-xs font-medium cursor-pointer py-2.5"
                                                        onclick={() => openEditItemPriceDialog(index)}
                                                    >
                                                        <Tag class="size-3.5 shrink-0 text-primary" />
                                                        <span>Modificar precio</span>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        class="flex items-center gap-2 text-xs font-medium cursor-pointer py-2.5"
                                                        onclick={() => openMobileDiscountDialog(index)}
                                                    >
                                                        <Percent class="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                                        <span>{(item.discount_percentage || 0) > 0 ? 'Modificar descuento' : 'Aplicar descuento'}</span>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
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
                                    </div>

                                    <!-- Metadatos de precio base y stock -->
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm min-w-0">
                                        <!-- Bloque Precio Base -->
                                        <div class="flex items-center gap-1.5 {item.is_on_the_fly ? 'w-full sm:w-auto min-w-0' : 'shrink-0'}">
                                            {#if item.is_on_the_fly}
                                                <div class="flex flex-wrap items-center gap-1.5 w-full sm:w-auto min-w-0">
                                                    <label for="otf_price_{index}" class="text-sm font-medium text-foreground whitespace-nowrap">Precio unitario:</label>
                                                    <input
                                                        id="otf_price_{index}"
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        bind:value={item.unit_price}
                                                        oninput={() => { item.regular_price = Number(item.unit_price) || 0; }}
                                                        placeholder="0.00"
                                                        class="h-8 w-full sm:w-28 min-w-[70px] max-w-[140px] rounded-lg border border-border/80 bg-background px-2 text-sm font-bold font-sans tabular-nums text-foreground focus:outline-none focus:border-primary-500"
                                                    />
                                                </div>
                                            {:else if hasBulkDiscount}
                                                <span class="text-xs line-through text-muted-foreground font-sans tabular-nums" title={formatExactPrice(item.regular_price)}>
                                                    {formatPrice(item.regular_price)}
                                                </span>
                                                <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap font-sans tabular-nums" title={formatExactPrice(item.unit_price)}>
                                                    {formatPrice(item.unit_price)} {item.sale_type === 'weight' ? '/Kg' : '/u'}
                                                </span>
                                            {:else}
                                                <span class="text-sm font-semibold text-foreground whitespace-nowrap font-sans tabular-nums" title={formatExactPrice(item.unit_price)}>
                                                    {formatPrice(item.unit_price)} {item.sale_type === 'weight' ? '/Kg' : '/u'}
                                                </span>
                                            {/if}

                                            {#if !item.is_on_the_fly}
                                                <button
                                                    type="button"
                                                    onclick={() => openEditItemPriceDialog(index)}
                                                    class="inline-flex items-center justify-center size-6 rounded-md hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                                    title="Modificar precio del producto"
                                                    aria-label="Modificar precio del producto"
                                                >
                                                    <Pencil class="size-3" />
                                                </button>
                                            {/if}
                                        </div>

                                        <!-- Bloque Stock -->
                                        {#if item.is_on_the_fly}
                                            <div class="flex items-center gap-1.5 text-sm text-muted-foreground min-w-0">
                                                <span>Stock: <strong class="text-muted-foreground italic font-normal">No catalogado (0)</strong></span>
                                            </div>
                                        {:else}
                                            <div class="flex items-center gap-1.5 text-sm text-muted-foreground shrink-0">
                                                <span>Stock: <strong class="font-sans tabular-nums {item.stock < 0 ? 'text-destructive font-bold' : 'text-foreground'}">{item.stock} {item.sale_type === 'weight' ? 'Kg' : 'u'}</strong></span>
                                                {#if item.stock < 0}
                                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-destructive bg-destructive/10 px-1.5 py-0.5 rounded border border-destructive/20 shrink-0">
                                                        Crítico
                                                    </span>
                                                {:else if isAtStockLimit}
                                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-600 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20 shrink-0">
                                                        Tope
                                                    </span>
                                                {/if}
                                            </div>
                                        {/if}
                                    </div>
                                </div>

                                <!-- Separador Horizontal entre Información y Resultado (2px margen) -->
                                <div class="border-t border-border/50 my-[2px]"></div>

                                <!-- Nivel 2: Resultado (Subtotal alineado a la derecha) -->
                                <div class="flex justify-end items-baseline gap-2 py-0.5">
                                    {#if isDiscountActive || (item.discount_percentage || 0) > 0}
                                        <span class="line-through text-xs text-muted-foreground font-sans tabular-nums" title={formatExactPrice(strikethroughSubtotal)}>
                                            {formatPrice(strikethroughSubtotal)}
                                        </span>
                                        <span class="font-sans tabular-nums text-base sm:text-lg font-bold text-primary" title={formatExactPrice(finalSubtotal)}>
                                            {formatPrice(finalSubtotal)}
                                        </span>
                                    {:else}
                                        <span class="font-sans tabular-nums text-base sm:text-lg font-bold text-foreground" title={formatExactPrice(baseSubtotal)}>
                                            {formatPrice(baseSubtotal)}
                                        </span>
                                    {/if}
                                </div>

                                <!-- Separador Horizontal entre Resultado y Acciones (2px margen) -->
                                <div class="border-t border-border/50 my-[2px]"></div>

                                <!-- Nivel 3: Acciones [trash (desktop), tipo unidad, descuento (desktop), cantidad] -->
                                <div class="order-item-actions flex flex-wrap items-center justify-between gap-2 w-full pt-0.5">
                                    <!-- Grupo Izquierdo: Acciones & Configuración -->
                                    <div class="flex items-center gap-1.5 sm:gap-2 flex-1 sm:flex-initial min-w-[115px] sm:min-w-0">
                                        <!-- Desktop: Trash Button -->
                                        <button
                                            type="button"
                                            class="hidden sm:flex size-8 rounded-lg items-center justify-center bg-destructive/10 text-destructive hover:bg-destructive/20 border border-destructive/20 transition-colors cursor-pointer shrink-0"
                                            onclick={() => removeItem(index)}
                                            title="Quitar de la lista"
                                            aria-label="Quitar producto de la lista"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>

                                        <!-- Label visible solo tras wrap vía Container Query -->
                                        <span class="wrap-field-label text-xs font-semibold text-muted-foreground shrink-0 min-w-[58px]">Tipo:</span>

                                        <!-- Selector de empaque / unidad -->
                                        <select
                                            value={item.packaging_type}
                                            onchange={(e) => handlePackagingChange(index, (e.target as HTMLSelectElement).value as any)}
                                            class="h-9 rounded-xl border border-border/80 bg-background px-2 text-sm font-semibold text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary cursor-pointer flex-1 w-full sm:w-auto min-w-[68px] sm:min-w-[94px] sm:px-2.5"
                                        >
                                            {#if item.sale_type === 'weight'}
                                                <option value="gram">Gramos</option>
                                                <option value="kg">Kg</option>
                                                <option value="bulto">Bulto</option>
                                            {:else}
                                                <option value="unidad">Unidad</option>
                                                <option value="bulto">Bulto</option>
                                            {/if}
                                        </select>

                                        <!-- Botón Edición Efímera de Unidades por Bulto (Icono compacto) -->
                                        {#if item.packaging_type === 'bulto'}
                                            <button
                                                type="button"
                                                onclick={() => openUnitsPerPackageDialog(index)}
                                                class="size-9 rounded-xl border border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 hover:bg-sky-500/20 transition-colors inline-flex items-center justify-center cursor-pointer shrink-0"
                                                title="Modificar unidades por bulto para esta fila ({item.units_per_package || 1} u/bulto)"
                                                aria-label="Modificar unidades por bulto ({item.units_per_package || 1} u)"
                                            >
                                                <Pencil class="size-3.5" />
                                            </button>
                                        {/if}

                                        <!-- Desktop: Selector de Descuento por Ítem -->
                                        <div class="hidden sm:flex items-center gap-1.5">
                                            <select
                                                value={item.is_custom_discount ? 'custom' : (item.discount_percentage || 0)}
                                                onchange={(e) => {
                                                    const val = (e.target as HTMLSelectElement).value;
                                                    if (val === 'custom') {
                                                        item.is_custom_discount = true;
                                                    } else {
                                                        item.is_custom_discount = false;
                                                        item.discount_percentage = Number(val) || 0;
                                                    }
                                                }}
                                                class="h-9 rounded-xl border border-border/80 bg-background px-2 text-sm font-semibold text-foreground min-w-[90px] focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary cursor-pointer"
                                                title="Descuento individual por producto"
                                            >
                                                <option value={0}>Desc. 0%</option>
                                                <option value={5}>Desc. 5%</option>
                                                <option value={10}>Desc. 10%</option>
                                                <option value={15}>Desc. 15%</option>
                                                <option value={20}>Desc. 20%</option>
                                                <option value={25}>Desc. 25%</option>
                                                <option value={30}>Desc. 30%</option>
                                                <option value={35}>Desc. 35%</option>
                                                <option value={40}>Desc. 40%</option>
                                                <option value={45}>Desc. 45%</option>
                                                <option value={50}>Desc. 50%</option>
                                                <option value={100}>Desc. 100%</option>
                                                <option value="custom">Personalizado...</option>
                                            </select>

                                            {#if item.is_custom_discount}
                                                <div class="flex items-center gap-1">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        step="1"
                                                        value={item.discount_percentage}
                                                        onkeydown={(e) => preventInvalidNumericKeys(e)}
                                                        oninput={(e) => {
                                                            sanitizeNumericInput(e, 0, 100, (val) => {
                                                                item.discount_percentage = typeof val === 'number' ? val : (Number(val) || 0);
                                                            });
                                                        }}
                                                        placeholder="0"
                                                        class="h-9 w-14 rounded-xl border border-border/80 bg-background px-2 text-sm font-semibold font-sans tabular-nums text-foreground focus:outline-none focus:border-primary-500 text-center"
                                                        title="Porcentaje de descuento personalizado (0-100%)"
                                                    />
                                                    <span class="text-xs font-bold text-muted-foreground">%</span>
                                                </div>
                                            {/if}
                                        </div>
                                    </div>

                                    <!-- Stepper Táctil Elástico con Label visible solo tras wrap -->
                                    <div class="stepper-wrap-container flex items-center gap-1.5 sm:gap-2 flex-1 sm:flex-initial min-w-[104px] sm:min-w-[120px] sm:w-auto">
                                        <span class="wrap-field-label text-xs font-semibold text-muted-foreground shrink-0 min-w-[58px]">Cantidad:</span>
                                        <div class="flex items-center border rounded-xl bg-background shadow-2xs h-9 overflow-hidden flex-1 sm:flex-initial min-w-[104px] sm:min-w-[120px] sm:w-auto justify-between">
                                            <button
                                                type="button"
                                                class="w-8 sm:w-9 h-full flex items-center justify-center shrink-0 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
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
                                                class="w-7 sm:w-16 min-w-[28px] flex-1 sm:flex-initial h-8 sm:h-9 text-sm font-bold font-sans tabular-nums text-center bg-transparent border-0 outline-none p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none {item.quantity > maxAllowed ? 'text-amber-600 dark:text-amber-400 font-extrabold' : ''}"
                                            />

                                            <button
                                                type="button"
                                                class="w-8 sm:w-9 h-full flex items-center justify-center shrink-0 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                                                onclick={() => incrementQuantity(index)}
                                                disabled={item.quantity >= maxAllowed}
                                                aria-label="Aumentar cantidad"
                                            >
                                                <Plus class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Advertencia de stock insuficiente / saldo negativo -->
                                {#if !item.is_on_the_fly && (item.stock <= 0 || getItemQuantityBase(item) > item.stock)}
                                    <div class="flex items-center gap-1.5 text-xs font-semibold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2.5 py-1 rounded-md border border-amber-500/20">
                                        <AlertTriangle class="size-3.5 shrink-0" />
                                        <span>Stock insuficiente en depósito ({item.stock}). La venta registrará saldo negativo.</span>
                                    </div>
                                {/if}

                                <!-- Advertencia de exceso temporal durante el tipeo -->
                                {#if item.quantity > maxAllowed}
                                    <div class="flex items-center gap-1.5 text-xs font-semibold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2.5 py-1 rounded-md border border-amber-500/20">
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
                                <p class="text-xs">Escribe en el buscador superior para seleccionar y agregar productos al pedido.</p>
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
                            class="flex items-start gap-3 p-3 rounded-xl border transition-all {!selectedCustomerId ? 'opacity-50 cursor-not-allowed bg-muted/20 border-border/50' : (paymentType === 'cuenta_corriente' ? 'border-primary bg-primary/5 text-foreground font-semibold shadow-xs cursor-pointer' : 'border-border/80 bg-background/60 hover:bg-muted/40 text-muted-foreground cursor-pointer')}"
                        >
                            <input
                                type="radio"
                                name="order_payment_type"
                                value="cuenta_corriente"
                                bind:group={paymentType}
                                disabled={!selectedCustomerId}
                                class="mt-0.5 size-4 text-primary focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                            />
                            <div class="min-w-0">
                                <span class="text-xs font-medium block">Cuenta Corriente</span>
                                <span class="text-xs text-muted-foreground block font-normal">
                                    {!selectedCustomerId ? 'Disponible solo para clientes registrados' : 'Se suma al saldo deudor del cliente'}
                                </span>
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
                                <span class="text-xs text-muted-foreground block font-normal">Abonado al momento, no genera deuda</span>
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
                                <span class="text-xs text-muted-foreground block font-normal">Entrega anticipada + saldo a cobrar</span>
                            </div>
                        </label>
                    </div>

                    {#if paymentType === 'contado' || paymentType === 'parcial'}
                        <div class="pt-2.5 mt-1 border-t border-border/50 space-y-3">
                            <div class="space-y-1.5">
                                <Label for="order-payment-method" class="text-sm font-medium text-foreground">
                                    Medio de Pago
                                </Label>
                                <select
                                    id="order-payment-method"
                                    bind:value={paymentMethod}
                                    class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
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
                                            <Label for="order-paid-amount" class="text-sm font-medium text-foreground">
                                                Monto cobrado anticipadamente
                                            </Label>
                                            <p class="text-xs text-muted-foreground">
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
                                                class="h-10 text-sm font-medium rounded-xl font-sans tabular-nums"
                                            />
                                        </div>
                                    </div>
                                </div>
                            {/if}

                            <!-- Cobro Inmediato con QR de Mercado Pago -->
                            {#if isMpInstantCharge}
                                <div class="rounded-xl border border-sky-500/30 bg-sky-500/10 p-3 sm:p-3.5 space-y-2">
                                    <label class="flex items-center justify-between gap-3 cursor-pointer select-none">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="size-8 rounded-lg bg-sky-500/20 text-sky-600 dark:text-sky-300 flex items-center justify-center shrink-0">
                                                <QrCode class="size-4" />
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold text-foreground">Cobro Inmediato con QR</p>
                                                <p class="text-xs text-muted-foreground">Generar el código QR en pantalla al crear el pedido para cobrarlo en el momento</p>
                                            </div>
                                        </div>
                                        <input
                                            type="checkbox"
                                            bind:checked={chargeWithQr}
                                            class="size-4.5 rounded text-primary focus:ring-primary cursor-pointer shrink-0"
                                        />
                                    </label>
                                    {#if chargeWithQr}
                                        <div class="text-xs font-medium text-sky-800 dark:text-sky-200 bg-sky-500/10 px-2.5 py-1.5 rounded-lg border border-sky-500/20">
                                            {#if paymentType === 'contado'}
                                                Se abrirá el QR para cobrar el total de <strong class="tabular-nums">{formatPrice(grandTotal + effectivePreviousDebtCollected)}</strong>.
                                            {:else}
                                                Se abrirá el QR para cobrar el anticipo de <strong class="tabular-nums">{formatPrice(Number(paidAmount) || 0)}</strong>.
                                            {/if}
                                            Al acreditarse el pago, se registrará el recibo oficial y se actualizará el pedido automáticamente.
                                        </div>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>

                {#if paymentType === 'cuenta_corriente'}
                    <!-- Bento Card: Saldo a Favor de Cuenta Corriente (Solo en Cuenta Corriente) -->
                    <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                        <div class="flex items-center justify-between gap-2.5">
                            <div class="flex items-center gap-2">
                                <div class="size-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                    <Banknote class="size-3.5" />
                                </div>
                                <span class="text-xs font-bold text-foreground">Saldo a Favor en Cuenta Corriente</span>
                            </div>
                            {#if hasAvailableCredit}
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 font-sans tabular-nums">
                                    Disponible: {formatPrice(availableCustomerCredit)}
                                </span>
                            {:else}
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-muted text-muted-foreground border border-border/50">
                                    Sin saldo a favor
                                </span>
                            {/if}
                        </div>

                        <!-- Switch / Checkbox de Activación -->
                        <label class="flex items-center justify-between gap-3 p-3 rounded-xl border transition-all {hasAvailableCredit ? (useCreditBalance ? 'border-emerald-500/50 bg-emerald-500/5 shadow-2xs cursor-pointer' : 'border-border/80 bg-background/60 hover:bg-muted/40 cursor-pointer') : 'opacity-50 cursor-not-allowed bg-muted/20 border-border/50'}">
                            <div class="min-w-0 flex-1">
                                <span class="text-xs font-semibold text-foreground block">
                                    Usar saldo a favor de cuenta corriente
                                </span>
                                <span class="text-xs text-muted-foreground block font-normal">
                                    {!selectedCustomerId ? 'Disponible solo para clientes registrados' : (!hasAvailableCredit ? 'El cliente no posee saldo a favor disponible' : 'Deduce el importe directamente del crédito acumulado del cliente')}
                                </span>
                            </div>
                            <input
                                type="checkbox"
                                checked={useCreditBalance}
                                disabled={!hasAvailableCredit}
                                onchange={toggleUseCreditBalance}
                                class="size-4.5 rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50 shrink-0"
                            />
                        </label>

                        {#if useCreditBalance && hasAvailableCredit}
                            <div class="p-3 rounded-xl border border-border/70 bg-background space-y-3">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                    <div class="min-w-0">
                                        <span class="text-xs text-muted-foreground block font-medium">Monto a Imputar:</span>
                                        <span class="text-base sm:text-lg font-bold font-sans tabular-nums text-emerald-600 dark:text-emerald-400">
                                            {formatPrice(effectiveCreditBalanceApplied)}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="min-h-[38px] px-3 rounded-xl text-xs font-semibold cursor-pointer"
                                            onclick={applyFullCreditBalance}
                                            title="Imputar el máximo posible de saldo a favor"
                                        >
                                            Aplicar Total
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="min-h-[38px] px-3 rounded-xl text-xs font-semibold gap-1.5 cursor-pointer"
                                            onclick={openEditCreditBalanceModal}
                                        >
                                            <Pencil class="size-3.5" />
                                            <span>Editar</span>
                                        </Button>
                                    </div>
                                </div>

                                {#if effectiveCreditBalanceApplied >= grossTotal}
                                    <div class="text-xs font-medium text-emerald-800 dark:text-emerald-200 bg-emerald-500/10 px-2.5 py-1.5 rounded-lg border border-emerald-500/20">
                                        El saldo a favor cubre el 100% del pedido. No quedará deuda pendiente.
                                    </div>
                                {/if}

                                <!-- Sub-items de visibilidad en remito -->
                                <div class="pt-2.5 border-t border-border/60 space-y-2">
                                    <!-- Sub-item 1: Cuenta Corriente en encabezado -->
                                    <label class="flex items-start gap-2.5 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            bind:checked={showAccountInReceipt}
                                            class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                        />
                                        <div class="min-w-0">
                                            <span class="text-xs font-semibold text-foreground block">
                                                Incluir resumen de cuenta corriente en el remito impreso
                                            </span>
                                            <span class="text-xs text-muted-foreground block">
                                                Muestra en la ficha del cliente el estado de cuenta y saldo disponible.
                                            </span>
                                        </div>
                                    </label>

                                    <!-- Sub-item 2: Saldo a favor en totales -->
                                    <label class="flex items-start gap-2.5 {canShowCreditAppliedInReceipt ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'}">
                                        <input
                                            type="checkbox"
                                            disabled={!canShowCreditAppliedInReceipt}
                                            bind:checked={showCreditAppliedInReceipt}
                                            class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                        />
                                        <div class="min-w-0">
                                            <span class="text-xs font-semibold text-foreground block">
                                                Mostrar uso de saldo a favor en el remito impreso
                                            </span>
                                            <span class="text-xs text-muted-foreground block">
                                                Muestra en el bloque de totales la línea de saldo a favor imputado.
                                            </span>
                                            {#if !canShowCreditAppliedInReceipt}
                                                <span class="text-xs text-amber-600 dark:text-amber-400 block mt-0.5">
                                                    Disponible solo si se imputa saldo a favor al pedido.
                                                </span>
                                            {/if}
                                        </div>
                                    </label>

                                    <!-- Check: Mostrar el saldo de la Cta. corriente actual en el subtotal del pedido -->
                                    <label class="flex items-start gap-2.5 pt-1 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            bind:checked={showAccountBalanceInOrderSummary}
                                            class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                        />
                                        <div class="min-w-0">
                                            <span class="text-xs font-semibold text-foreground block">
                                                {accountBalanceCheckboxLabel}
                                            </span>
                                            <span class="text-xs text-muted-foreground block">
                                                El saldo que se muestra es de propósito visual e informativo, y no afecta al subtotal del pedido.
                                            </span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        {:else if selectedCustomerId}
                            <!-- Sub-item 1 disponible cuando no se usa saldo a favor pero hay cliente registrado -->
                            <label class="flex items-start gap-2.5 pt-1 cursor-pointer">
                                <input
                                    type="checkbox"
                                    bind:checked={showAccountInReceipt}
                                    class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                />
                                <div class="min-w-0">
                                    <span class="text-xs font-semibold text-foreground block">
                                        Incluir resumen de cuenta corriente en el remito impreso
                                    </span>
                                    <span class="text-xs text-muted-foreground block">
                                        Muestra en la ficha del cliente el estado de cuenta y saldo disponible.
                                    </span>
                                </div>
                            </label>

                            <!-- Check: Mostrar el saldo de la Cta. corriente actual en el subtotal del pedido -->
                            <label class="flex items-start gap-2.5 pt-1 cursor-pointer">
                                <input
                                    type="checkbox"
                                    bind:checked={showAccountBalanceInOrderSummary}
                                    class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                />
                                <div class="min-w-0">
                                    <span class="text-xs font-semibold text-foreground block">
                                        {accountBalanceCheckboxLabel}
                                    </span>
                                    <span class="text-xs text-muted-foreground block">
                                        El saldo que se muestra es de propósito visual e informativo, y no afecta al subtotal del pedido.
                                    </span>
                                </div>
                            </label>
                        {/if}
                    </div>
                {:else if paymentType === 'contado' && selectedCustomerId}
                    <!-- Bento Card: Cobro de Deuda Anterior (Solo en Cobro Total) -->
                    <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                        <div class="flex items-center justify-between gap-2.5">
                            <div class="flex items-center gap-2">
                                <div class="size-7 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                                    <Receipt class="size-3.5" />
                                </div>
                                <span class="text-xs font-bold text-foreground">Cobro de Deuda Anterior</span>
                            </div>
                            {#if hasAvailableDebt}
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30 font-sans tabular-nums">
                                    Deuda Pendiente: {formatPrice(availableCustomerDebt)}
                                </span>
                            {:else}
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                    Al día (Sin deuda)
                                </span>
                            {/if}
                        </div>

                        <!-- Switch / Checkbox de Activación -->
                        <label class="flex items-center justify-between gap-3 p-3 rounded-xl border transition-all {hasAvailableDebt ? (collectPreviousDebt ? 'border-amber-500/50 bg-amber-500/5 shadow-2xs cursor-pointer' : 'border-border/80 bg-background/60 hover:bg-muted/40 cursor-pointer') : 'opacity-50 cursor-not-allowed bg-muted/20 border-border/50'}">
                            <div class="min-w-0 flex-1">
                                <span class="text-xs font-semibold text-foreground block">
                                    Cobrar deuda pendiente junto con este pedido
                                </span>
                                <span class="text-xs text-muted-foreground block font-normal">
                                    {!hasAvailableDebt ? 'El cliente no posee deuda pendiente para cobrar' : 'Permite al cliente saldar su deuda histórica de cuenta corriente en este cobro'}
                                </span>
                            </div>
                            <input
                                type="checkbox"
                                checked={collectPreviousDebt}
                                disabled={!hasAvailableDebt}
                                onchange={toggleCollectPreviousDebt}
                                class="size-4.5 rounded text-amber-600 focus:ring-amber-500 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50 shrink-0"
                            />
                        </label>

                        {#if collectPreviousDebt && hasAvailableDebt}
                            <div class="p-3 rounded-xl border border-border/70 bg-background space-y-3">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                    <div class="min-w-0">
                                        <span class="text-xs text-muted-foreground block font-medium">Deuda a Cobrar:</span>
                                        <span class="text-base sm:text-lg font-bold font-sans tabular-nums text-amber-600 dark:text-amber-400">
                                            +{formatPrice(effectivePreviousDebtCollected)}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="min-h-[38px] px-3 rounded-xl text-xs font-semibold cursor-pointer"
                                            onclick={applyFullPreviousDebt}
                                            title="Cobrar el 100% de la deuda histórica"
                                        >
                                            Cobrar Total Deuda
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="min-h-[38px] px-3 rounded-xl text-xs font-semibold gap-1.5 cursor-pointer"
                                            onclick={openEditDebtBalanceModal}
                                        >
                                            <Pencil class="size-3.5" />
                                            <span>Editar</span>
                                        </Button>
                                    </div>
                                </div>

                                {#if effectivePreviousDebtCollected >= availableCustomerDebt}
                                    <div class="text-xs font-medium text-emerald-800 dark:text-emerald-200 bg-emerald-500/10 px-2.5 py-1.5 rounded-lg border border-emerald-500/20">
                                        Se cobrará la totalidad de la deuda. La cuenta corriente del cliente quedará al día ($0,00).
                                    </div>
                                {:else}
                                    <div class="text-xs font-medium text-muted-foreground bg-muted/40 px-2.5 py-1.5 rounded-lg border border-border/50">
                                        Quedará un saldo deudor remanente de {formatPrice(availableCustomerDebt - effectivePreviousDebtCollected)}.
                                    </div>
                                {/if}
                            </div>
                        {/if}

                        <!-- Sub-item: Cuenta Corriente en encabezado -->
                        <label class="flex items-start gap-2.5 pt-1 cursor-pointer">
                            <input
                                type="checkbox"
                                bind:checked={showAccountInReceipt}
                                class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                            />
                            <div class="min-w-0">
                                <span class="text-xs font-semibold text-foreground block">
                                    Incluir resumen de cuenta corriente en el remito impreso
                                </span>
                                <span class="text-xs text-muted-foreground block">
                                    Muestra en la ficha del cliente el estado de cuenta y saldo disponible.
                                </span>
                            </div>
                        </label>

                        <!-- Sub-item: Cobro de deuda previa en totales -->
                        <label class="flex items-start gap-2.5 pt-1 {canShowDebtCollectedInReceipt ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'}">
                            <input
                                type="checkbox"
                                disabled={!canShowDebtCollectedInReceipt}
                                bind:checked={showDebtCollectedInReceipt}
                                class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                            />
                            <div class="min-w-0">
                                <span class="text-xs font-semibold text-foreground block">
                                    Mostrar cobro de deuda anterior en el remito impreso
                                </span>
                                <span class="text-xs text-muted-foreground block">
                                    Muestra en el bloque de totales la línea de cobro de deuda anterior.
                                </span>
                                {#if !canShowDebtCollectedInReceipt}
                                    <span class="text-xs text-amber-600 dark:text-amber-400 block mt-0.5">
                                        Disponible solo si se incluye cobro de deuda anterior al pedido.
                                    </span>
                                {/if}
                            </div>
                        </label>

                        <!-- Check: Mostrar el saldo de la Cta. corriente actual en el subtotal del pedido -->
                        <label class="flex items-start gap-2.5 pt-1 cursor-pointer">
                            <input
                                type="checkbox"
                                bind:checked={showAccountBalanceInOrderSummary}
                                class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                            />
                            <div class="min-w-0">
                                <span class="text-xs font-semibold text-foreground block">
                                    {accountBalanceCheckboxLabel}
                                </span>
                                <span class="text-xs text-muted-foreground block">
                                    El saldo que se muestra es de propósito visual e informativo, y no afecta al subtotal del pedido.
                                </span>
                            </div>
                        </label>
                    </div>
                {/if}

                <!-- Bento Card: Descuentos y Recargos -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <Tag class="size-3.5" />
                            </div>
                            <span class="text-xs font-bold text-foreground">Descuentos y Recargos</span>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 px-2.5 rounded-lg text-xs font-semibold gap-1 text-primary hover:bg-primary/10 cursor-pointer"
                            onclick={() => (isChooseAdjustmentModalOpen = true)}
                        >
                            <Plus class="size-3.5" />
                            <span>Agregar</span>
                        </Button>
                    </div>

                    <!-- Lista de Ajustes Agregados -->
                    {#if priceAdjustments.length > 0}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                            {#each priceAdjustments as adj (adj.id)}
                                <div class="p-2.5 rounded-xl border flex items-center justify-between text-xs bg-background {adj.type === 'discount' ? 'border-emerald-500/30' : 'border-amber-500/30'}">
                                    <div class="min-w-0 pr-2">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-semibold text-foreground truncate">{adj.name}</span>
                                            {#if adj.calculation_type === 'percentage'}
                                                <span class="text-xs px-1.5 py-0.5 rounded-md bg-muted text-muted-foreground font-sans tabular-nums font-medium">%{adj.value}</span>
                                            {/if}
                                        </div>
                                        {#if adj.reason}
                                            <p class="text-xs text-muted-foreground truncate mt-0.5">{adj.reason}</p>
                                        {/if}
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="font-bold font-sans tabular-nums {adj.type === 'discount' ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'}">
                                            {adj.type === 'discount' ? '-' : '+'}{formatPrice(getAdjustmentAppliedAmount(adj, computedTotals.finalTotal))}
                                        </span>
                                        {#if adj.source === 'ad_hoc'}
                                            <button
                                                type="button"
                                                class="size-6 rounded-md hover:bg-destructive/10 text-muted-foreground hover:text-destructive flex items-center justify-center transition-colors cursor-pointer"
                                                onclick={() => confirmDeleteAdHoc(adj)}
                                                title="Eliminar ajuste al vuelo"
                                            >
                                                <Trash2 class="size-3" />
                                            </button>
                                        {/if}
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {:else}
                        <p class="text-xs text-muted-foreground">
                            Podés aplicar recargos o descuentos especiales a este pedido (fletes, bonificaciones, promociones).
                        </p>
                    {/if}
                </div>

                <!-- Bento Card 4: Notas Adicionales -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-1.5">
                    <Label for="order-notes" class="text-sm font-medium text-foreground">Notas / Instrucciones de Entrega</Label>
                    <textarea
                        id="order-notes"
                        bind:value={notes}
                        rows="2"
                        maxlength="255"
                        placeholder="ej. Entregar por la mañana, timbre 2B, empaquetar con cinta reforzada..."
                        class="w-full rounded-xl border border-border/80 bg-background p-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary resize-none"
                    ></textarea>
                </div>

            </form>

            <!-- Footer Bento: Total & Botones (shrink-0) -->
            <div class="border-t border-border/60 pt-4 mt-2 space-y-3 shrink-0">
                <!-- Contenedor Superior: Resumen de Totales y Descuentos -->
                <div class="rounded-xl bg-card border border-border/70 p-3 sm:p-3.5 space-y-1.5 shadow-2xs">
                    <div class="flex items-center justify-between text-xs sm:text-sm font-medium text-muted-foreground">
                        <span>Subtotal Base:</span>
                        <span class="font-bold font-sans tabular-nums text-foreground">
                            {formatPrice(computedTotals.baseTotal)}
                        </span>
                    </div>

                    {#if computedTotals.itemDiscountTotal > 0}
                        <div class="flex items-center justify-between text-xs text-emerald-600 dark:text-emerald-400 font-semibold font-sans tabular-nums border-t border-border/40 pt-1.5">
                            <span>Descuentos en productos:</span>
                            <span>-{formatPrice(computedTotals.itemDiscountTotal)}</span>
                        </div>
                    {/if}

                    {#if computedTotals.customerDiscountTotal > 0}
                        <div class="flex items-center justify-between text-xs text-emerald-600 dark:text-emerald-400 font-semibold font-sans tabular-nums border-t border-border/40 pt-1.5">
                            <span>Descuento de cliente ({customerDiscountPercentage}%):</span>
                            <span>-{formatPrice(computedTotals.customerDiscountTotal)}</span>
                        </div>
                    {/if}

                    {#if adjustmentsTotals.discountTotal > 0}
                        <div class="flex items-center justify-between text-xs text-emerald-600 dark:text-emerald-400 font-semibold font-sans tabular-nums border-t border-border/40 pt-1.5">
                            <span>Ajustes de descuento:</span>
                            <span>-{formatPrice(adjustmentsTotals.discountTotal)}</span>
                        </div>
                    {/if}

                    {#if adjustmentsTotals.surchargeTotal > 0}
                        <div class="flex items-center justify-between text-xs text-amber-600 dark:text-amber-400 font-semibold font-sans tabular-nums border-t border-border/40 pt-1.5">
                            <span>Ajustes de recargo:</span>
                            <span>+{formatPrice(adjustmentsTotals.surchargeTotal)}</span>
                        </div>
                    {/if}

                    {#if selectedShippingZone && shippingCost > 0}
                        <div class="flex items-center justify-between text-xs text-primary font-semibold border-t border-border/40 pt-1.5">
                            <span class="flex items-center gap-1">
                                <MapPin class="size-3.5" />
                                Envío ({selectedShippingZone.name} - +{selectedShippingZone.surcharge_percentage}%):
                            </span>
                            <span class="font-sans tabular-nums">+{formatPrice(shippingCost)}</span>
                        </div>
                    {/if}

                    {#if showAccountBalanceInOrderSummary && selectedCustomer}
                        <div class="flex items-center justify-between text-xs sm:text-sm font-medium text-muted-foreground border-t border-border/40 pt-1.5">
                            <span>Saldo Cta. Cte. actual:</span>
                            <span class="font-bold font-sans tabular-nums text-foreground">
                                {accountCurrentBalanceFormatted}
                            </span>
                        </div>
                        {#if accountProjectedBalanceFormatted}
                            <div class="flex items-center justify-between text-xs sm:text-sm font-medium text-muted-foreground pt-1">
                                <span>Saldo Cta. Cte. resultante:</span>
                                <span class="font-bold font-sans tabular-nums text-foreground">
                                    {accountProjectedBalanceFormatted}
                                </span>
                            </div>
                        {/if}
                    {/if}

                    {#if effectiveCreditBalanceApplied > 0}
                        <div class="flex items-center justify-between text-xs sm:text-sm font-medium text-muted-foreground border-t border-border/60 pt-2 mt-1">
                            <span>Total Bruto del Pedido:</span>
                            <span class="font-bold font-sans tabular-nums text-foreground">
                                {formatPrice(grossTotal)}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs sm:text-sm font-bold text-emerald-600 dark:text-emerald-400 font-sans tabular-nums pt-0.5">
                            <span>Saldo a favor imputado:</span>
                            <span>-{formatPrice(effectiveCreditBalanceApplied)}</span>
                        </div>
                        <div class="flex flex-wrap items-baseline justify-between gap-2 border-t border-border/60 pt-2 mt-1 w-full">
                            <span class="text-xs sm:text-sm font-bold text-foreground">Total del Pedido:</span>
                            <span class="text-lg sm:text-2xl font-bold font-sans tabular-nums text-primary break-all">
                                {formatPrice(grandTotal)}
                            </span>
                        </div>
                    {:else if effectivePreviousDebtCollected > 0}
                        <div class="flex items-center justify-between text-xs sm:text-sm font-medium text-muted-foreground border-t border-border/60 pt-2 mt-1">
                            <span>Total del Pedido:</span>
                            <span class="font-bold font-sans tabular-nums text-foreground">
                                {formatPrice(grandTotal)}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs sm:text-sm font-bold text-amber-600 dark:text-amber-400 font-sans tabular-nums pt-0.5">
                            <span>Cobro de Deuda Anterior:</span>
                            <span>+{formatPrice(effectivePreviousDebtCollected)}</span>
                        </div>
                        <div class="flex flex-wrap items-baseline justify-between gap-2 border-t border-border/60 pt-2 mt-1 w-full">
                            <span class="text-xs sm:text-sm font-bold text-foreground">Total a Cobrar en el Momento:</span>
                            <span class="text-lg sm:text-2xl font-bold font-sans tabular-nums text-primary break-all">
                                {formatPrice(grandTotal + effectivePreviousDebtCollected)}
                            </span>
                        </div>
                    {:else}
                        <div class="flex flex-wrap items-baseline justify-between gap-2 border-t border-border/60 pt-2 mt-1 w-full">
                            <span class="text-xs sm:text-sm font-bold text-foreground">Total del Pedido:</span>
                            <span class="text-lg sm:text-2xl font-bold font-sans tabular-nums text-primary break-all">
                                {formatPrice(grandTotal)}
                            </span>
                        </div>
                    {/if}
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
                        {:else if isMpInstantCharge && chargeWithQr}
                            <QrCode class="size-4 shrink-0" />
                            <span>Crear y Cobrar QR</span>
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

<!-- Diálogo Táctil Accesible de Descuento por Ítem en Mobile -->
<Dialog bind:open={isMobileDiscountModalOpen}>
    <DialogContent class="sm:max-w-[420px] p-5 sm:p-6 space-y-4 rounded-2xl bg-card border-border shadow-2xl">
        <DialogHeader class="shrink-0 pb-3 border-b border-border/60">
            <div class="flex items-center gap-2.5 sm:gap-3.5">
                <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                    <Icon class="size-5 sm:size-6 text-primary" icon="solar:tag-price-bold-duotone" />
                </div>
                <div class="min-w-0 flex-1 flex items-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                        Descuento de Producto
                    </DialogTitle>
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="space-y-4 py-1">
            {#if mobileDiscountItemIndex !== null && items[mobileDiscountItemIndex]}
                <div class="p-2.5 rounded-xl bg-muted/40 border border-border/60 text-xs">
                    <span class="text-muted-foreground block text-xs">Producto:</span>
                    <strong class="text-foreground font-semibold line-clamp-1">{items[mobileDiscountItemIndex].product_name}</strong>
                </div>
            {/if}
            <!-- Grid de presets rápidos (touch targets mínimos de 44px) -->
            <div class="space-y-1.5">
                <Label class="text-sm font-medium text-foreground">Porcentajes Predefinidos</Label>
                <div class="grid grid-cols-4 gap-2">
                    {#each [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 100] as preset}
                        <button
                            type="button"
                            onclick={() => {
                                mobileDiscountPreset = preset;
                                isMobileDiscountCustom = false;
                            }}
                            class="min-h-[44px] rounded-xl text-xs font-bold border transition-colors flex items-center justify-center cursor-pointer {(!isMobileDiscountCustom && mobileDiscountPreset === preset)
                                ? 'bg-primary text-primary-foreground border-primary shadow-xs'
                                : 'bg-muted/40 text-foreground border-border/80 hover:bg-muted'}"
                        >
                            {preset}%
                        </button>
                    {/each}
                    <button
                        type="button"
                        onclick={() => {
                            isMobileDiscountCustom = true;
                            mobileDiscountPreset = 'custom';
                        }}
                        class="min-h-[44px] rounded-xl text-xs font-bold border transition-colors flex items-center justify-center cursor-pointer {(isMobileDiscountCustom || mobileDiscountPreset === 'custom')
                            ? 'bg-primary text-primary-foreground border-primary shadow-xs'
                            : 'bg-muted/40 text-foreground border-border/80 hover:bg-muted'}"
                    >
                        Otro...
                    </button>
                </div>
            </div>

            <!-- Input de porcentaje personalizado -->
            {#if isMobileDiscountCustom || mobileDiscountPreset === 'custom'}
                <div class="space-y-1.5 p-3 rounded-xl bg-muted/30 border border-border/70">
                    <Label for="custom-discount-input" class="text-sm font-medium text-foreground">Porcentaje personalizado</Label>
                    <div class="flex items-center gap-2">
                        <Input
                            id="custom-discount-input"
                            type="number"
                            min="0"
                            max="100"
                            step="1"
                            value={mobileDiscountCustomVal}
                            onkeydown={(e) => preventInvalidNumericKeys(e)}
                            oninput={(e) => {
                                sanitizeNumericInput(e, 0, 100, (val) => {
                                    mobileDiscountCustomVal = typeof val === 'number' ? val : (Number(val) || 0);
                                });
                            }}
                            placeholder="ej. 12"
                            class="h-10 text-sm font-bold font-sans tabular-nums"
                        />
                        <span class="text-sm font-bold text-muted-foreground">%</span>
                    </div>
                </div>
            {/if}
        </DialogBody>

        <DialogFooter class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:justify-end">
            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] w-full rounded-xl text-sm font-semibold"
                onclick={() => (isMobileDiscountModalOpen = false)}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                class="min-h-[44px] w-full rounded-xl text-sm font-semibold bg-primary hover:bg-primary/90 text-primary-foreground"
                onclick={applyMobileDiscount}
            >
                Aplicar Descuento
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Diálogo Efímero de Edición de Unidades por Bulto -->
<Dialog bind:open={isUnitsModalOpen}>
    <DialogContent class="sm:max-w-[420px] p-5 sm:p-6 space-y-4 rounded-2xl bg-card border-border shadow-2xl">
        <DialogHeader class="shrink-0 pb-3 border-b border-border/60">
            <div class="flex items-center gap-2.5 sm:gap-3.5">
                <div class="size-10 sm:size-12 rounded-xl bg-sky-500/10 text-sky-700 dark:text-sky-400 border border-sky-500/30 flex items-center justify-center shrink-0">
                    <Icon class="size-5 sm:size-6 text-sky-600 dark:text-sky-400" icon="solar:box-minimalistic-bold-duotone" />
                </div>
                <div class="min-w-0 flex-1 flex items-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                        Unidades por Bulto
                    </DialogTitle>
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="space-y-4 py-1">
            {#if editingUnitsItemIndex !== null && items[editingUnitsItemIndex]}
                <div class="p-2.5 rounded-xl bg-muted/40 border border-border/60 text-xs">
                    <span class="text-muted-foreground block text-xs">Producto:</span>
                    <strong class="text-foreground font-semibold line-clamp-1">{items[editingUnitsItemIndex].product_name}</strong>
                </div>
            {/if}
            <div class="space-y-2">
                <Label for="units-per-package-input" class="text-sm font-medium text-foreground">
                    Cantidad de unidades por bulto
                </Label>
                <Input
                    id="units-per-package-input"
                    type="number"
                    min="1"
                    step="1"
                    bind:value={editingUnitsValue}
                    placeholder="ej. 12"
                    class="h-10 text-sm font-bold font-sans tabular-nums"
                />
                <p class="text-xs text-muted-foreground">
                    Este cambio se aplica únicamente a esta línea del pedido y no modifica el producto en el catálogo.
                </p>
            </div>
        </DialogBody>

        <DialogFooter class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:justify-end">
            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] w-full rounded-xl text-sm font-semibold"
                onclick={() => (isUnitsModalOpen = false)}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                class="min-h-[44px] w-full rounded-xl text-sm font-semibold bg-primary hover:bg-primary/90 text-primary-foreground"
                onclick={saveUnitsPerPackage}
            >
                Guardar
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal de Cobro con QR Inmediato de Mercado Pago -->
<MercadoPagoQrModal
    bind:open={isQrModalOpen}
    order={createdOrderForQr}
    initialAmount={targetQrAmountForModal}
    onSuccess={() => {
        isQrModalOpen = false;
        createdOrderForQr = null;
        targetQrAmountForModal = null;
        router.reload({ only: ['orders', 'stats'], preserveScroll: true });
    }}
    onclose={() => {
        isQrModalOpen = false;
        createdOrderForQr = null;
        targetQrAmountForModal = null;
        router.reload({ only: ['orders', 'stats'], preserveScroll: true });
    }}
/>

<!-- Modal de Modificación de Precio de Ítem -->
<EditItemPriceModal
    bind:open={isEditItemPriceModalOpen}
    product={editingPriceItemIndex !== null && items[editingPriceItemIndex] ? {
        id: items[editingPriceItemIndex].product_id,
        name: items[editingPriceItemIndex].product_name,
        price: items[editingPriceItemIndex].unit_price,
        sku: items[editingPriceItemIndex].sku,
    } : null}
    isOrderContext={true}
    onPriceUpdated={handleOrderItemPriceUpdated}
/>

<ChooseAdjustmentActionModal
    bind:open={isChooseAdjustmentModalOpen}
    onCreate={() => {
        isChooseAdjustmentModalOpen = false;
        isCreateAdjustmentModalOpen = true;
    }}
    onSelectExisting={() => {
        isChooseAdjustmentModalOpen = false;
        isSelectExistingAdjustmentModalOpen = true;
    }}
/>

<SelectExistingAdjustmentModal
    bind:open={isSelectExistingAdjustmentModalOpen}
    netProductsAmount={computedTotals.finalTotal}
    currentAdjustments={priceAdjustments}
    onBack={() => {
        isSelectExistingAdjustmentModalOpen = false;
        isChooseAdjustmentModalOpen = true;
    }}
    onApplyLocal={(syncedPresets) => {
        const adHocs = priceAdjustments.filter((a) => a.source === 'ad_hoc');
        priceAdjustments = [...adHocs, ...syncedPresets];
    }}
/>

<CreateCustomAdjustmentModal
    bind:open={isCreateAdjustmentModalOpen}
    netProductsAmount={computedTotals.finalTotal}
    onBack={() => {
        isCreateAdjustmentModalOpen = false;
        isChooseAdjustmentModalOpen = true;
    }}
    onAddLocal={(newAdj) => {
        priceAdjustments = [...priceAdjustments, newAdj];
    }}
/>

<!-- Modal de Confirmación para Eliminar Ajuste al Vuelo -->
<ConfirmActionModal
    bind:open={isDeleteAdHocModalOpen}
    title="¿Eliminar ajuste al vuelo?"
    description="Esta acción no se puede deshacer. Se removerá este ajuste personalizado del pedido. Si lo requerís más adelante, deberás crearlo nuevamente."
    confirmText="Eliminar"
    cancelText="Cancelar"
    variant="destructive"
    onconfirm={executeDeleteAdHoc}
/>

<!-- Diálogo de Edición de Saldo a Favor a Imputar -->
<Dialog bind:open={isCreditBalanceModalOpen}>
    <DialogContent class="sm:max-w-md p-5 sm:p-6 space-y-4 rounded-2xl bg-card border-border shadow-2xl">
        <DialogHeader class="shrink-0 pb-3 border-b border-border/60">
            <div class="flex items-center gap-2.5 sm:gap-3.5">
                <div class="size-10 sm:size-12 rounded-xl bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 flex items-center justify-center shrink-0">
                    <Icon class="size-5 sm:size-6 text-emerald-600 dark:text-emerald-400" icon="solar:wallet-money-bold-duotone" />
                </div>
                <div class="min-w-0 flex-1 flex items-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                        Editar Saldo a Imputar
                    </DialogTitle>
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="space-y-4">
            <!-- Selector de Modo: Monto Fijo o Porcentaje -->
            <div class="grid grid-cols-2 gap-2 p-1 rounded-xl bg-muted/50 border border-border/60">
                <button
                    type="button"
                    class="min-h-[38px] rounded-lg text-xs font-semibold transition-all cursor-pointer {editCreditMode === 'fixed' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => (editCreditMode = 'fixed')}
                >
                    Monto Fijo
                </button>
                <button
                    type="button"
                    class="min-h-[38px] rounded-lg text-xs font-semibold transition-all cursor-pointer {editCreditMode === 'percentage' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => (editCreditMode = 'percentage')}
                >
                    Porcentaje
                </button>
            </div>

            {#if editCreditMode === 'fixed'}
                <div class="space-y-2">
                    <Label for="credit-fixed-amount-input" class="text-sm font-medium text-foreground">
                        Monto a deducir del saldo a favor
                    </Label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-muted-foreground">$</span>
                        <Input
                            id="credit-fixed-amount-input"
                            type="text"
                            inputmode="decimal"
                            bind:value={editCreditFixedValue}
                            onkeydown={(e) => handleStrictPositiveNumericKeydown(e, true)}
                            oninput={(e) => handleStrictFixedAmountInput(e, availableCustomerCredit, (v) => editCreditFixedValue = v)}
                            placeholder="0.00"
                            class="h-10 pl-7 text-sm font-bold font-sans tabular-nums"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Saldo a favor disponible del cliente: <strong class="tabular-nums text-foreground">{formatPrice(availableCustomerCredit)}</strong>
                    </p>
                </div>
            {:else}
                <div class="space-y-2">
                    <Label for="credit-pct-input" class="text-sm font-medium text-foreground">
                        Porcentaje del saldo a favor a utilizar
                    </Label>
                    <div class="relative">
                        <Input
                            id="credit-pct-input"
                            type="text"
                            inputmode="numeric"
                            bind:value={editCreditPercentageValue}
                            onkeydown={(e) => handleStrictPositiveNumericKeydown(e, false)}
                            oninput={(e) => handleStrictPercentageInput(e, (v) => editCreditPercentageValue = v)}
                            placeholder="100"
                            class="h-10 pr-7 text-sm font-bold font-sans tabular-nums"
                        />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-bold text-muted-foreground">%</span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Equivalente: <strong class="tabular-nums text-foreground">{formatPrice(Math.round(availableCustomerCredit * (Math.max(0, Math.min(100, Number(editCreditPercentageValue || 0))) / 100) * 100) / 100)}</strong> de saldo a favor disponible ({formatPrice(availableCustomerCredit)})
                    </p>
                </div>
            {/if}
        </DialogBody>

        <DialogFooter class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:justify-end">
            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] w-full rounded-xl text-sm font-semibold cursor-pointer"
                onclick={() => (isCreditBalanceModalOpen = false)}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                class="min-h-[44px] w-full rounded-xl text-sm font-semibold bg-primary hover:bg-primary/90 text-primary-foreground cursor-pointer"
                onclick={saveCreditBalanceEdit}
            >
                Confirmar
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Diálogo de Edición de Cobro de Deuda Anterior -->
<Dialog bind:open={isDebtBalanceModalOpen}>
    <DialogContent class="sm:max-w-md p-5 sm:p-6 space-y-4 rounded-2xl bg-card border-border shadow-2xl">
        <DialogHeader class="shrink-0 pb-3 border-b border-border/60">
            <div class="flex items-center gap-2.5 sm:gap-3.5">
                <div class="size-10 sm:size-12 rounded-xl bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30 flex items-center justify-center shrink-0">
                    <Icon class="size-5 sm:size-6 text-amber-600 dark:text-amber-400" icon="solar:hand-money-bold-duotone" />
                </div>
                <div class="min-w-0 flex-1 flex items-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                        Editar Deuda a Cobrar
                    </DialogTitle>
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="space-y-4">
            <!-- Selector de Modo: Monto Fijo o Porcentaje -->
            <div class="grid grid-cols-2 gap-2 p-1 rounded-xl bg-muted/50 border border-border/60">
                <button
                    type="button"
                    class="min-h-[38px] rounded-lg text-xs font-semibold transition-all cursor-pointer {editDebtMode === 'fixed' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => (editDebtMode = 'fixed')}
                >
                    Monto Fijo
                </button>
                <button
                    type="button"
                    class="min-h-[38px] rounded-lg text-xs font-semibold transition-all cursor-pointer {editDebtMode === 'percentage' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => (editDebtMode = 'percentage')}
                >
                    Porcentaje
                </button>
            </div>

            {#if editDebtMode === 'fixed'}
                <div class="space-y-2">
                    <Label for="debt-fixed-amount-input" class="text-sm font-medium text-foreground">
                        Monto de deuda anterior a cobrar
                    </Label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-muted-foreground">$</span>
                        <Input
                            id="debt-fixed-amount-input"
                            type="text"
                            inputmode="decimal"
                            bind:value={editDebtFixedValue}
                            onkeydown={(e) => handleStrictPositiveNumericKeydown(e, true)}
                            oninput={(e) => handleStrictFixedAmountInput(e, availableCustomerDebt, (v) => editDebtFixedValue = v)}
                            placeholder="0.00"
                            class="h-10 pl-7 text-sm font-bold font-sans tabular-nums"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Deuda pendiente del cliente: <strong class="tabular-nums text-foreground">{formatPrice(availableCustomerDebt)}</strong>
                    </p>
                </div>
            {:else}
                <div class="space-y-2">
                    <Label for="debt-pct-input" class="text-sm font-medium text-foreground">
                        Porcentaje de la deuda a cobrar
                    </Label>
                    <div class="relative">
                        <Input
                            id="debt-pct-input"
                            type="text"
                            inputmode="numeric"
                            bind:value={editDebtPercentageValue}
                            onkeydown={(e) => handleStrictPositiveNumericKeydown(e, false)}
                            oninput={(e) => handleStrictPercentageInput(e, (v) => editDebtPercentageValue = v)}
                            placeholder="100"
                            class="h-10 pr-7 text-sm font-bold font-sans tabular-nums"
                        />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-bold text-muted-foreground">%</span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Equivalente: <strong class="tabular-nums text-foreground">{formatPrice(Math.round(availableCustomerDebt * (Math.max(0, Math.min(100, Number(editDebtPercentageValue || 0))) / 100) * 100) / 100)}</strong> de deuda pendiente ({formatPrice(availableCustomerDebt)})
                    </p>
                </div>
            {/if}
        </DialogBody>

        <DialogFooter class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:justify-end">
            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] w-full rounded-xl text-sm font-semibold cursor-pointer"
                onclick={() => (isDebtBalanceModalOpen = false)}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                class="min-h-[44px] w-full rounded-xl text-sm font-semibold bg-primary hover:bg-primary/90 text-primary-foreground cursor-pointer"
                onclick={saveDebtBalanceEdit}
            >
                Confirmar
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<style>
    .order-item-card {
        container-type: inline-size;
        container-name: order-item-card;
    }

    .wrap-field-label {
        display: none;
    }

    @container order-item-card (max-width: 280px) {
        .order-item-actions {
            flex-direction: column;
            align-items: stretch;
            gap: 0.625rem;
        }

        .wrap-field-label {
            display: inline-flex;
            align-items: center;
        }

        .stepper-wrap-container {
            width: 100%;
        }
    }
</style>
