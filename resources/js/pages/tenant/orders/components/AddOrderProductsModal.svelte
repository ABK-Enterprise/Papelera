<script lang="ts">
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogBody,
        DialogFooter,
    } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import { router } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Check from 'lucide-svelte/icons/check';
    import Minus from 'lucide-svelte/icons/minus';
    import Package from 'lucide-svelte/icons/package';
    import PackagePlus from 'lucide-svelte/icons/package-plus';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import EditItemPriceModal from '@/components/tenant/products/EditItemPriceModal.svelte';

    export interface ProductOption {
        id: number;
        name: string;
        sku: string | null;
        price: string | number;
        stock: number;
        units_per_package?: number;
        sale_type?: string;
        package_size?: number;
        bulk_discount_price?: string | number;
    }

    export interface OrderData {
        id: number;
        order_number: string;
        customer_name: string;
        status: string;
        total_amount: string | number;
        subtotal_amount?: string | number;
        warehouse_id?: number | null;
        customer_discount_percentage?: string | number;
        items?: any[];
    }

    interface ItemToAdd {
        id: string;
        product_id: number | null;
        is_on_the_fly: boolean;
        product_name: string;
        sku: string | null;
        sale_type: string;
        stock: number;
        packaging_type: string;
        quantity: number;
        unit_price: number;
        regular_price: number;
        units_per_package: number;
        is_already_in_order?: boolean;
        existing_order_quantity?: number;
        is_price_modified?: boolean;
    }

    interface Props {
        open?: boolean;
        order: OrderData | null;
        availableProducts?: ProductOption[];
        onSuccess?: (updatedOrder: any) => void;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        order = null,
        availableProducts = [],
        onSuccess = undefined,
        onclose = undefined,
    }: Props = $props();

    let items = $state<ItemToAdd[]>([]);
    let searchQuery = $state('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);
    let isConfirmAlertOpen = $state(false);
    let editingPriceItemIndex = $state<number | null>(null);
    let isEditItemPriceModalOpen = $state(false);
    let collidingModifiedProducts = $state<Array<{ name: string; oldPrice: number; newPrice: number }>>([]);

    // Búsqueda reactiva de productos
    let searchResults = $derived.by(() => {
        const q = searchQuery.toLowerCase().trim();
        if (!q) {
            return availableProducts.slice(0, 8);
        }
        return availableProducts
            .filter((p) => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)))
            .slice(0, 8);
    });

    // Subtotal de los nuevos productos añadidos
    let newItemsSubtotal = $derived.by(() => {
        return items.reduce((sum, it) => {
            const qty = Number(it.quantity) || 0;
            const price = Number(it.unit_price) || 0;
            return sum + (qty * price);
        }, 0);
    });

    let currentOrderTotal = $derived(Number(order?.total_amount) || 0);
    let projectedNewTotal = $derived(currentOrderTotal + newItemsSubtotal);

    function formatPrice(val: number | string): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(val) || 0);
    }

    function addProductToItems(prod: ProductOption) {
        errorMessage = null;
        const isWeighable = prod.sale_type === 'weight';
        const defaultPackaging = isWeighable ? 'kg' : 'unidad';
        const regularPrice = Number(prod.price) || 0;

        const existingIndex = items.findIndex(
            (it) => it.product_id === prod.id && it.packaging_type === defaultPackaging
        );

        const existingInOrder = order?.items?.find((oi: any) => oi.product_id === prod.id);
        const existingOrderQty = existingInOrder ? Number(existingInOrder.quantity) || 0 : 0;

        if (existingIndex !== -1) {
            items[existingIndex].quantity += 1;
        } else {
            items = [
                {
                    id: 'item_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                    product_id: prod.id,
                    is_on_the_fly: false,
                    product_name: prod.name,
                    sku: prod.sku,
                    sale_type: prod.sale_type ?? 'unit',
                    stock: prod.stock,
                    packaging_type: defaultPackaging,
                    quantity: 1,
                    unit_price: regularPrice,
                    regular_price: regularPrice,
                    units_per_package: prod.units_per_package || prod.package_size || 1,
                    is_already_in_order: !!existingInOrder,
                    existing_order_quantity: existingOrderQty,
                    is_price_modified: false,
                },
                ...items,
            ];
        }

        searchQuery = '';
    }

    function addOnTheFlyProduct(name: string) {
        if (!name.trim()) return;
        errorMessage = null;

        items = [
            {
                id: 'item_otf_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                product_id: null,
                is_on_the_fly: true,
                product_name: name.trim(),
                sku: null,
                sale_type: 'unit',
                stock: 0,
                packaging_type: 'unidad',
                quantity: 1,
                unit_price: 0,
                regular_price: 0,
                units_per_package: 1,
                is_already_in_order: false,
                existing_order_quantity: 0,
                is_price_modified: false,
            },
            ...items,
        ];

        searchQuery = '';
    }

    function removeItem(index: number) {
        items = items.filter((_, i) => i !== index);
    }

    function updateQuantity(index: number, delta: number) {
        const item = items[index];
        if (!item) return;
        const step = item.sale_type === 'weight' && item.packaging_type === 'kg' ? 0.5 : 1;
        const newQty = Math.max(0.1, Number((item.quantity + delta * step).toFixed(2)));
        item.quantity = newQty;
    }

    function openEditItemPriceDialog(index: number) {
        editingPriceItemIndex = index;
        isEditItemPriceModalOpen = true;
    }

    function handleOrderItemPriceUpdated(newPrice: number, mode: 'order_only' | 'catalog') {
        if (editingPriceItemIndex === null || !items[editingPriceItemIndex]) return;
        const it = items[editingPriceItemIndex];
        it.unit_price = newPrice;
        it.regular_price = newPrice;
        it.is_price_modified = true;
        if (mode === 'catalog' && it.product_id) {
            const p = availableProducts.find((prod) => prod.id === it.product_id);
            if (p) {
                p.price = newPrice;
            }
        }
        editingPriceItemIndex = null;
        isEditItemPriceModalOpen = false;
    }

    function handleClose() {
        if (isSubmitting) return;
        open = false;
        items = [];
        searchQuery = '';
        errorMessage = null;
        isConfirmAlertOpen = false;
        editingPriceItemIndex = null;
        isEditItemPriceModalOpen = false;
        collidingModifiedProducts = [];
        onclose?.();
    }

    function promptConfirmSubmit() {
        if (!order) return;
        if (items.length === 0) {
            errorMessage = 'Debe seleccionar al menos un producto para agregar al pedido.';
            return;
        }

        errorMessage = null;

        // Detectar productos coincidentes en el pedido cuyo precio haya sido modificado
        const collisions: Array<{ name: string; oldPrice: number; newPrice: number }> = [];

        for (const it of items) {
            if (!it.product_id || !order.items) continue;
            const existingOrderItem = order.items.find((oi: any) => oi.product_id === it.product_id);
            if (!existingOrderItem) continue;

            const oldPrice = Number(existingOrderItem.unit_price) || 0;
            const newPrice = Number(it.unit_price) || 0;

            if (Math.abs(newPrice - oldPrice) > 0.001) {
                if (!collisions.some((c) => c.name === it.product_name)) {
                    collisions.push({
                        name: it.product_name,
                        oldPrice,
                        newPrice,
                    });
                }
            }
        }

        collidingModifiedProducts = collisions;
        isConfirmAlertOpen = true;
    }

    function executeSubmit() {
        if (!order || items.length === 0) return;

        isSubmitting = true;
        errorMessage = null;

        const payload = {
            items: items.map((it) => ({
                product_id: it.product_id,
                product_name: it.is_on_the_fly ? it.product_name : undefined,
                warehouse_id: order.warehouse_id || undefined,
                packaging_type: it.packaging_type,
                quantity: it.quantity,
                unit_price: it.unit_price,
                units_per_package: it.units_per_package,
            })),
        };

        router.post(`/tenant/pedidos/${order.id}/agregar-productos`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Productos agregados al pedido exitosamente.');
                isConfirmAlertOpen = false;
                open = false;
                items = [];
                searchQuery = '';
                errorMessage = null;
                editingPriceItemIndex = null;
                isEditItemPriceModalOpen = false;
                collidingModifiedProducts = [];
                onclose?.();
            },
            onError: (errors) => {
                isConfirmAlertOpen = false;
                const err = Object.values(errors).flat().join(' ');
                errorMessage = err || 'Error al agregar productos al pedido.';
                toast.error(errorMessage);
            },
            onFinish: () => {
                isSubmitting = false;
            },
        });
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-2xl w-full p-5 sm:p-6 space-y-4 rounded-2xl min-h-[500px] sm:min-h-[540px] max-h-[92vh] flex flex-col">
        <!-- Header -->
        <DialogHeader class="pb-3 border-b border-border/60 shrink-0">
            <div class="flex items-center gap-2.5 sm:gap-3.5">
                <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                    <PackagePlus class="size-5 sm:size-6 text-primary" />
                </div>
                <div class="min-w-0 flex-1 flex flex-col justify-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                        Agregar productos al pedido
                    </DialogTitle>
                    {#if order}
                        <p class="text-xs text-muted-foreground mt-1 tabular-nums">
                            Pedido #{order.order_number}
                        </p>
                    {/if}
                </div>
            </div>
        </DialogHeader>

        {#if errorMessage}
            <div class="flex items-center gap-2 p-3 text-sm rounded-xl bg-destructive/10 border border-destructive/20 text-destructive shrink-0">
                <AlertTriangle class="size-4 shrink-0" />
                <span>{errorMessage}</span>
            </div>
        {/if}

        <!-- Buscador Combobox de Productos (Fijo en cabecera con z-index elevado para no ser recortado por scroll) -->
        <div class="space-y-1.5 shrink-0 relative z-30">
            <Label class="text-sm font-semibold text-foreground">
                Buscar producto en el catálogo
            </Label>
            <SearchCombobox
                id="add-order-products-combobox"
                bind:value={searchQuery}
                items={searchResults}
                filterLocally={false}
                placeholder="Escriba el nombre del producto o código SKU..."
                showDropdown={true}
                onsearch={(q) => (searchQuery = q)}
                onselect={(p) => addProductToItems(p)}
                onclear={() => {
                    searchQuery = '';
                }}
                inputClass="h-11 sm:h-10 min-h-[44px] sm:min-h-[40px] text-xs sm:text-sm font-medium w-full"
            >
                {#snippet itemSnippet(prod)}
                    <div class="flex items-start justify-between gap-2.5 w-full text-left py-0.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground line-clamp-1 break-words">
                                {prod.name}
                            </p>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-muted-foreground">
                                {#if prod.sku}
                                    <span class="tabular-nums">SKU: {prod.sku}</span>
                                {/if}
                                <span class="tabular-nums">• Stock: {prod.stock}</span>
                            </div>
                        </div>
                        <span class="text-xs font-bold text-foreground tabular-nums shrink-0">
                            {formatPrice(prod.price)}
                        </span>
                    </div>
                {/snippet}
                {#snippet emptySnippet()}
                    <div class="p-3 text-center text-sm text-muted-foreground">
                        <p>No se encontraron productos coincidentes.</p>
                        {#if searchQuery.trim()}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="mt-2 text-xs font-semibold rounded-lg cursor-pointer min-h-[36px]"
                                onclick={() => addOnTheFlyProduct(searchQuery)}
                            >
                                <Plus class="size-3.5 mr-1 text-primary" />
                                Crear "{searchQuery.trim()}" al vuelo
                            </Button>
                        {/if}
                    </div>
                {/snippet}
            </SearchCombobox>
        </div>

        <!-- Body con scroll elástico para la lista de productos agregados -->
        <DialogBody class="flex-1 overflow-y-auto space-y-4 py-2 pr-1 relative z-10 min-h-[160px]">
            <!-- Listado de Productos a Agregar -->
            <div class="space-y-2 pt-1">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-foreground">
                        Productos a incorporar ({items.length})
                    </h3>
                </div>

                {#if items.length === 0}
                    <div class="border border-dashed border-border/80 rounded-xl p-6 text-center text-muted-foreground flex flex-col items-center justify-center gap-2 bg-muted/20">
                        <PackagePlus class="size-8 opacity-40 text-primary" />
                        <p class="text-sm font-medium">No has seleccionado ningún producto todavía.</p>
                        <p class="text-xs text-muted-foreground">Utilizá el buscador de arriba para añadir artículos a este pedido.</p>
                    </div>
                {:else}
                    <div class="space-y-2.5">
                        {#each items as item, index (item.id)}
                            {@const itemLineSubtotal = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0)}
                            <div class="order-item-card flex flex-col gap-2.5 p-3.5 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs">
                                <!-- Nivel 1: Información (Icono, Nombre, SKU, Badges, Botón Quitar, Precio Base con Lápiz y Stock) -->
                                <div class="flex flex-col gap-2">
                                    <div class="flex items-start justify-between gap-2.5 min-w-0">
                                        <div class="flex items-start gap-2.5 min-w-0 flex-1">
                                            <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                                                <Package class="size-4" />
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 min-w-0 flex-1">
                                                <h4 class="text-base font-bold text-foreground line-clamp-2 break-words">
                                                    {item.product_name}
                                                </h4>
                                                {#if item.is_already_in_order}
                                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30 shrink-0">
                                                        Ya en pedido ({item.existing_order_quantity} {item.sale_type === 'weight' ? 'Kg' : 'u'})
                                                    </span>
                                                {/if}
                                                {#if item.is_on_the_fly}
                                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-muted text-muted-foreground border border-border/80">
                                                        Al Vuelo
                                                    </span>
                                                {/if}
                                                {#if item.sku}
                                                    <span class="text-sm font-sans tabular-nums bg-muted px-2 py-0.5 rounded text-muted-foreground shrink-0">
                                                        {item.sku}
                                                    </span>
                                                {/if}
                                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full shrink-0 {item.sale_type === 'weight' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground border border-border/70'}">
                                                    {item.sale_type === 'weight' ? 'Pesaje' : 'Unidad'}
                                                </span>
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            class="size-8 rounded-lg flex items-center justify-center bg-destructive/10 text-destructive hover:bg-destructive/20 border border-destructive/20 transition-colors cursor-pointer shrink-0"
                                            onclick={() => removeItem(index)}
                                            title="Quitar producto"
                                            aria-label="Quitar producto"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>

                                    <!-- Metadatos de precio base y stock -->
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm min-w-0">
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <span class="text-sm font-semibold text-foreground whitespace-nowrap font-sans tabular-nums">
                                                {formatPrice(item.unit_price)} {item.sale_type === 'weight' ? '/Kg' : '/u'}
                                            </span>
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

                                        <div class="flex items-center gap-1.5 text-sm text-muted-foreground shrink-0">
                                            <span>Stock: <strong class="font-sans tabular-nums text-foreground">{item.stock} {item.sale_type === 'weight' ? 'Kg' : 'u'}</strong></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Separador Horizontal (2px) -->
                                <div class="border-t border-border/50 my-[2px]"></div>

                                <!-- Nivel 2: Resultado (Subtotal alineado a la derecha) -->
                                <div class="flex justify-end items-baseline gap-2 py-0.5">
                                    <span class="font-sans tabular-nums text-base sm:text-lg font-bold text-foreground">
                                        {formatPrice(itemLineSubtotal)}
                                    </span>
                                </div>

                                <!-- Separador Horizontal (2px) -->
                                <div class="border-t border-border/50 my-[2px]"></div>

                                <!-- Nivel 3: Acciones [presentación y stepper de cantidad] -->
                                <div class="flex flex-wrap items-center justify-between gap-2 w-full pt-0.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-muted-foreground shrink-0">Presentación:</span>
                                        <select
                                            bind:value={item.packaging_type}
                                            class="h-9 rounded-xl border border-border/80 bg-background px-2.5 text-sm font-semibold text-foreground focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer"
                                        >
                                            {#if item.sale_type === 'weight'}
                                                <option value="kg">Kg</option>
                                                <option value="gram">Gramos</option>
                                                <option value="bulto">Bulto</option>
                                            {:else}
                                                <option value="unidad">Unidad</option>
                                                <option value="bulto">Bulto ({item.units_per_package} u)</option>
                                            {/if}
                                        </select>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-muted-foreground shrink-0">Cantidad:</span>
                                        <div class="flex items-center border rounded-xl bg-background shadow-2xs h-9 overflow-hidden">
                                            <button
                                                type="button"
                                                class="w-8 sm:w-9 h-full flex items-center justify-center shrink-0 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                                                onclick={() => updateQuantity(index, -1)}
                                                aria-label="Disminuir cantidad"
                                            >
                                                <Minus class="size-3.5" />
                                            </button>
                                            <input
                                                type="number"
                                                step={item.sale_type === 'weight' ? '0.1' : '1'}
                                                min="0.01"
                                                bind:value={item.quantity}
                                                class="w-12 sm:w-14 text-center text-sm font-bold text-foreground bg-transparent focus:outline-none tabular-nums"
                                            />
                                            <button
                                                type="button"
                                                class="w-8 sm:w-9 h-full flex items-center justify-center shrink-0 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                                                onclick={() => updateQuantity(index, 1)}
                                                aria-label="Aumentar cantidad"
                                            >
                                                <Plus class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>

            <!-- Resumen Financiero -->
            {#if items.length > 0}
                <div class="p-3.5 rounded-xl border border-border/80 bg-muted/40 dark:bg-slate-900/60 space-y-1.5">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Total actual del pedido:</span>
                        <span class="font-semibold tabular-nums text-foreground">{formatPrice(currentOrderTotal)}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Nuevos productos a sumar:</span>
                        <span class="font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">+{formatPrice(newItemsSubtotal)}</span>
                    </div>
                    <div class="border-t border-border/60 my-1"></div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-bold text-foreground">Nuevo Total:</span>
                        <span class="font-bold tabular-nums text-primary text-base">{formatPrice(projectedNewTotal)}</span>
                    </div>
                </div>
            {/if}
        </DialogBody>

        <!-- Footer -->
        <DialogFooter class="shrink-0 pt-2 border-t border-border/70 flex flex-col sm:flex-row gap-2 justify-end">
            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] rounded-xl text-sm font-semibold cursor-pointer w-full sm:w-auto"
                disabled={isSubmitting}
                onclick={handleClose}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                variant="default"
                class="min-h-[44px] rounded-xl text-sm font-semibold cursor-pointer w-full sm:w-auto gap-2"
                disabled={items.length === 0 || isSubmitting}
                onclick={promptConfirmSubmit}
            >
                {#if isSubmitting}
                    <div class="size-4 rounded-full border-2 border-white/30 border-t-white animate-spin"></div>
                    <span>Guardando...</span>
                {:else}
                    <Check class="size-4" />
                    <span>Agregar productos</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<ConfirmActionModal
    bind:open={isConfirmAlertOpen}
    title={collidingModifiedProducts.length > 0 ? 'Actualización de precios' : 'Confirmar adición de productos'}
    description={collidingModifiedProducts.length > 0
        ? 'Hay productos en el pedido que coinciden con los que querés agregar, y las modificaciones de precios se aplicarán en ellos también. Los productos coincidentes son:'
        : 'Si el pedido se irá a facturar, el monto de estos productos formará parte de la factura y de los impuestos. ¿Quieres continuar?'}
    confirmText="Aceptar"
    cancelText="Cancelar"
    variant="warning"
    loading={isSubmitting}
    onconfirm={executeSubmit}
>
    {#if collidingModifiedProducts.length > 0}
        <ul class="my-2.5 space-y-1.5 pl-4 list-disc text-xs sm:text-sm text-foreground font-medium">
            {#each collidingModifiedProducts as p}
                <li>
                    <span class="font-bold">{p.name}</span>
                    <span class="text-muted-foreground tabular-nums">
                        ({formatPrice(p.oldPrice)} &rarr; {formatPrice(p.newPrice)})
                    </span>
                </li>
            {/each}
        </ul>
        <p class="text-xs sm:text-sm text-muted-foreground pt-1.5 border-t border-border/60">
            Si el pedido se irá a facturar, el monto de estos productos formará parte de la factura y de los impuestos. ¿Quieres continuar?
        </p>
    {/if}
</ConfirmActionModal>

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
