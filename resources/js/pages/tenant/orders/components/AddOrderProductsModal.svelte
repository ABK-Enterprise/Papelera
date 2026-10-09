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
    import PackagePlus from 'lucide-svelte/icons/package-plus';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Trash2 from 'lucide-svelte/icons/trash-2';

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

    function handleClose() {
        if (isSubmitting) return;
        open = false;
        items = [];
        searchQuery = '';
        errorMessage = null;
        onclose?.();
    }

    async function handleSubmit() {
        if (!order) return;
        if (items.length === 0) {
            errorMessage = 'Debe seleccionar al menos un producto para agregar al pedido.';
            return;
        }

        // Validación de precios positivos en ítems al vuelo
        for (const it of items) {
            if (it.unit_price <= 0) {
                errorMessage = `El producto "${it.product_name}" debe tener un precio unitario mayor a cero.`;
                return;
            }
        }

        isSubmitting = true;
        errorMessage = null;

        try {
            const csrfToken =
                (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

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

            const res = await fetch(`/tenant/pedidos/${order.id}/agregar-productos`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                const err = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
                throw new Error(err || 'Error al agregar productos al pedido.');
            }

            toast.success(data.message || 'Productos agregados al pedido exitosamente.');

            if (data.order) {
                onSuccess?.(data.order);
            }

            router.reload({ only: ['orders', 'stats'] });
            handleClose();
        } catch (err: any) {
            errorMessage = err.message || 'Ocurrió un error inesperado al procesar la solicitud.';
            toast.error(errorMessage);
        } finally {
            isSubmitting = false;
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-2xl w-full p-5 sm:p-6 space-y-4 rounded-2xl max-h-[92vh] flex flex-col">
        <!-- Header -->
        <DialogHeader class="shrink-0 pb-1 border-b border-border/60">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <PackagePlus class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <DialogTitle class="text-xl font-bold text-foreground">
                        Agregar productos al pedido
                    </DialogTitle>
                    {#if order}
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Pedido #{order.order_number} • <strong class="text-foreground font-semibold">{order.customer_name}</strong>
                        </p>
                    {/if}
                </div>
            </div>
        </DialogHeader>

        <!-- Body con scroll elástico -->
        <DialogBody class="flex-1 overflow-y-auto space-y-4 py-2 pr-1">
            {#if errorMessage}
                <div class="flex items-center gap-2 p-3 text-sm rounded-xl bg-destructive/10 border border-destructive/20 text-destructive">
                    <AlertTriangle class="size-4 shrink-0" />
                    <span>{errorMessage}</span>
                </div>
            {/if}

            <!-- Buscador Combobox de Productos -->
            <div class="space-y-1.5">
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

            <!-- Listado de Productos a Agregar -->
            <div class="space-y-2 pt-1">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-foreground">
                        Productos a incorporar ({items.length})
                    </h3>
                    {#if items.length > 0}
                        <span class="text-xs text-muted-foreground">
                            Subtotal nuevos: <strong class="text-primary font-bold tabular-nums">{formatPrice(newItemsSubtotal)}</strong>
                        </span>
                    {/if}
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
                            <div class="p-3.5 rounded-xl border border-border/80 bg-card shadow-2xs space-y-2.5">
                                <!-- Fila 1: Título, SKU y Botón Quitar -->
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="text-sm font-bold text-foreground line-clamp-1 break-words">
                                                {item.product_name}
                                            </h4>
                                            {#if item.is_on_the_fly}
                                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-muted text-muted-foreground border border-border/80">
                                                    Al vuelo
                                                </span>
                                            {/if}
                                            {#if item.sku}
                                                <span class="text-xs font-medium px-1.5 py-0.5 rounded bg-muted text-muted-foreground tabular-nums">
                                                    {item.sku}
                                                </span>
                                            {/if}
                                        </div>
                                        <div class="text-xs text-muted-foreground mt-0.5">
                                            Stock disponible: <span class="font-semibold tabular-nums text-foreground">{item.stock}</span> {item.sale_type === 'weight' ? 'Kg' : 'u'}
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="size-8 rounded-lg flex items-center justify-center text-destructive hover:bg-destructive/10 transition-colors cursor-pointer shrink-0"
                                        onclick={() => removeItem(index)}
                                        title="Quitar producto"
                                        aria-label="Quitar producto"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>

                                <!-- Fila 2: Controles (Empaque, Cantidad, Precio y Subtotal) -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 items-end pt-1 border-t border-border/50">
                                    <!-- Selector de Empaque -->
                                    <div class="space-y-1">
                                        <label for="packaging-{index}" class="text-xs font-semibold text-muted-foreground block">
                                            Presentación
                                        </label>
                                        <select
                                            id="packaging-{index}"
                                            bind:value={item.packaging_type}
                                            class="w-full h-9 rounded-lg border border-border bg-background px-2.5 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer"
                                        >
                                            {#if item.sale_type === 'weight'}
                                                <option value="kg">Kilogramo (Kg)</option>
                                                <option value="gram">Gramos</option>
                                                <option value="bulto">Bulto cerrado</option>
                                            {:else}
                                                <option value="unidad">Unidad</option>
                                                <option value="bulto">Bulto ({item.units_per_package} u)</option>
                                                <option value="bolson">Bolsón</option>
                                                <option value="paquete">Paquete</option>
                                            {/if}
                                        </select>
                                    </div>

                                    <!-- Stepper de Cantidad -->
                                    <div class="space-y-1">
                                        <label for="qty-{index}" class="text-xs font-semibold text-muted-foreground block">
                                            Cantidad
                                        </label>
                                        <div class="flex items-center h-9 rounded-lg border border-border bg-background overflow-hidden">
                                            <button
                                                type="button"
                                                onclick={() => updateQuantity(index, -1)}
                                                class="px-2.5 h-full flex items-center justify-center hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                                aria-label="Disminuir cantidad"
                                            >
                                                <Minus class="size-3.5" />
                                            </button>
                                            <input
                                                id="qty-{index}"
                                                type="number"
                                                step={item.sale_type === 'weight' ? '0.1' : '1'}
                                                min="0.01"
                                                bind:value={item.quantity}
                                                class="w-full text-center text-xs font-bold text-foreground bg-transparent focus:outline-none tabular-nums"
                                            />
                                            <button
                                                type="button"
                                                onclick={() => updateQuantity(index, 1)}
                                                class="px-2.5 h-full flex items-center justify-center hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                                aria-label="Aumentar cantidad"
                                            >
                                                <Plus class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Precio Unitario y Subtotal -->
                                    <div class="space-y-1">
                                        <label for="price-{index}" class="text-xs font-semibold text-muted-foreground block">
                                            Precio unit. ($)
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <input
                                                id="price-{index}"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                bind:value={item.unit_price}
                                                class="w-full h-9 rounded-lg border border-border bg-background px-2.5 text-xs font-bold text-foreground tabular-nums focus:outline-none focus:ring-1 focus:ring-primary"
                                            />
                                            <div class="text-right shrink-0">
                                                <span class="text-xs font-bold text-primary tabular-nums block whitespace-nowrap">
                                                    {formatPrice(itemLineSubtotal)}
                                                </span>
                                            </div>
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
                        <span class="font-bold text-foreground">Nuevo total proyectado:</span>
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
                onclick={handleSubmit}
            >
                {#if isSubmitting}
                    <div class="size-4 rounded-full border-2 border-white/30 border-t-white animate-spin"></div>
                    <span>Guardando...</span>
                {:else}
                    <Check class="size-4" />
                    <span>Guardar y agregar productos</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
