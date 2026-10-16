<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import ProductImageUploader from '@/components/tenant/products/ProductImageUploader.svelte';
    import SaleTypeChangeAlertDialog from '@/components/common/SaleTypeChangeAlertDialog.svelte';
    import Icon from '@iconify/svelte';
    import { router, useForm } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import Package from 'lucide-svelte/icons/package';
    import Scale from 'lucide-svelte/icons/scale';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import X from 'lucide-svelte/icons/x';

    export interface CategoryOption {
        id: number;
        name: string;
    }

    export interface WarehouseOption {
        id: number;
        name: string;
        code: string;
        is_default?: boolean;
    }

    export interface ProductData {
        id?: number;
        name?: string;
        sale_type?: 'unit' | 'weight';
        category_id?: number | string | null;
        sku?: string | null;
        barcode?: string | null;
        description?: string | null;
        price?: string | number | null;
        net_price?: string | number | null;
        cost_price?: string | number | null;
        iva_rate?: number;
        stock?: number;
        min_stock?: number;
        units_per_package?: number;
        package_quantity?: number;
        image_url?: string | null;
        image_position?: { zoom?: number; x?: number; y?: number } | null;
        is_active?: boolean;
        is_incomplete?: boolean;
    }

    let {
        open = $bindable(false),
        mode = 'create', // 'create' | 'edit'
        product = null,
        categories = [],
        warehouses = [],
        onsuccess,
    }: {
        open: boolean;
        mode?: 'create' | 'edit';
        product?: ProductData | null;
        categories: CategoryOption[];
        warehouses?: WarehouseOption[];
        onsuccess?: () => void;
    } = $props();

    const form = useForm({
        name: '',
        sale_type: 'unit' as 'unit' | 'weight',
        category_id: '' as string | number,
        warehouse_id: '' as string | number,
        sku: '',
        barcode: '',
        description: '',
        price: '',
        net_price: '',
        cost_price: '',
        iva_rate: 21.00,
        stock: 0,
        min_stock: 5,
        units_per_package: 1,
        package_quantity: 1,
        image: null as File | null,
        remove_image: false,
        image_url: '',
        image_position: null as { zoom?: number; x?: number; y?: number } | null,
        is_active: true,
    });

    let lastLoadedProductId = $state<number | null>(null);
    let initialSaleType = $state<'unit' | 'weight'>('unit');
    let isConfirmAlertOpen = $state(false);
    let isProductIncomplete = $derived(mode === 'edit' && Boolean(product?.is_incomplete || !product?.category_id || Number(product?.price) <= 0 || (product?.net_price !== undefined && Number(product?.net_price) <= 0)));

    $effect(() => {
        if (!open) {
            lastLoadedProductId = null;
            form.image = null;
            form.remove_image = false;
        }
    });

    $effect(() => {
        if (product && product.id !== lastLoadedProductId) {
            lastLoadedProductId = product.id ?? null;
            initialSaleType = (product.sale_type ?? 'unit') as 'unit' | 'weight';
            form.name = product.name ?? '';
            form.sale_type = initialSaleType;
            form.category_id = product.category_id ?? '';
            form.sku = product.sku ?? '';
            form.barcode = product.barcode ?? '';
            form.description = product.description ?? '';
            form.iva_rate = product.iva_rate !== undefined && product.iva_rate !== null ? Number(product.iva_rate) : 21.00;
            
            if (product.net_price !== null && product.net_price !== undefined && product.net_price !== '') {
                form.net_price = String(product.net_price);
            } else if (product.price !== null && product.price !== undefined && Number(product.price) > 0) {
                const iva = Number(product.iva_rate ?? 21.00);
                form.net_price = (Math.round((Number(product.price) / (1 + iva / 100)) * 100) / 100).toFixed(2);
            } else {
                form.net_price = '';
            }

            form.price = product.price !== null && product.price !== undefined ? String(product.price) : '';
            form.cost_price = product.cost_price !== null && product.cost_price !== undefined ? String(product.cost_price) : '';
            form.stock = product.stock ?? 0;
            form.min_stock = product.min_stock ?? 5;
            form.units_per_package = product.units_per_package ?? product.package_quantity ?? 1;
            form.package_quantity = product.package_quantity ?? product.units_per_package ?? 1;
            form.image = null;
            form.remove_image = false;
            form.image_url = product.image_url ?? '';
            form.image_position = (product as any).image_position ?? null;
            form.is_active = product.is_active !== undefined ? Boolean(product.is_active) : true;
            form.clearErrors();
        } else if (!product && lastLoadedProductId !== null) {
            lastLoadedProductId = null;
            initialSaleType = 'unit';
            form.reset();
            form.image = null;
            form.remove_image = false;
            form.image_position = null;
            form.warehouse_id = warehouses.find(w => w.is_default)?.id ?? (warehouses[0]?.id ?? '');
            form.clearErrors();
        } else if (!product && !form.warehouse_id && warehouses.length > 0) {
            form.warehouse_id = warehouses.find(w => w.is_default)?.id ?? warehouses[0].id;
        }
    });

    // Recálculo automático bidireccional entre precio neto y precio de venta
    function updateCalculatedGrossPrice() {
        const net = parseFloat(String(form.net_price));
        if (!isNaN(net) && net >= 0) {
            const iva = Number(form.iva_rate) || 0;
            const gross = Math.round(net * (1 + iva / 100) * 100) / 100;
            form.price = gross.toFixed(2);
        } else {
            form.price = '';
        }
    }

    function updateCalculatedNetPrice() {
        const gross = parseFloat(String(form.price));
        if (!isNaN(gross) && gross >= 0) {
            const iva = Number(form.iva_rate) || 0;
            const net = Math.round((gross / (1 + iva / 100)) * 100) / 100;
            form.net_price = net.toFixed(2);
        } else {
            form.net_price = '';
        }
    }

    function handleIvaChange() {
        if (form.price !== '' && !isNaN(Number(form.price))) {
            updateCalculatedNetPrice();
        } else if (form.net_price !== '' && !isNaN(Number(form.net_price))) {
            updateCalculatedGrossPrice();
        }
    }


    function formatPrice(amount: number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(amount);
    }

    let calculatedPackagePrice = $derived.by(() => {
        const unitPrice = Number(form.price) || 0;
        const ratio = form.sale_type === 'weight'
            ? (Number(form.package_quantity) || 1)
            : (Number(form.units_per_package) || 1);
        return unitPrice * ratio;
    });

    function doSubmit() {
        if (form.sale_type === 'weight') {
            form.units_per_package = form.package_quantity;
        } else {
            form.package_quantity = form.units_per_package;
        }

        if (mode === 'edit' && product?.id) {
            if (form.image) {
                form.transform((data) => ({
                    ...data,
                    _method: 'put',
                })).post(`/tenant/productos/${product.id}`, {
                    onSuccess: () => {
                        isConfirmAlertOpen = false;
                        open = false;
                        onsuccess?.();
                    },
                });
            } else {
                form.put(`/tenant/productos/${product.id}`, {
                    onSuccess: () => {
                        isConfirmAlertOpen = false;
                        open = false;
                        onsuccess?.();
                    },
                });
            }
        } else {
            form.post('/tenant/productos', {
                onSuccess: () => {
                    open = false;
                    form.reset();
                    onsuccess?.();
                },
            });
        }
    }


    function submit(e: SubmitEvent) {
        e.preventDefault();

        if (mode === 'edit' && product?.id && form.sale_type !== initialSaleType) {
            isConfirmAlertOpen = true;
            return;
        }

        doSubmit();
    }

    function handleClose() {
        open = false;
        form.clearErrors();
    }
</script>

<Dialog bind:open>
    <DialogContent class="sm:max-w-6xl max-h-[85vh]">
        <!-- 1. Header -->
        <DialogHeader class="pb-3 border-b border-border/60 shrink-0">
            <div class="flex items-center justify-between gap-2.5 sm:gap-3.5 w-full">
                <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 flex-1">
                    <div class="size-10 sm:size-12 rounded-xl {form.sale_type === 'weight' ? 'bg-sky-500/10 text-sky-700 dark:text-sky-400 border border-sky-500/30' : 'bg-primary/10 text-primary border border-primary/20'} flex items-center justify-center shrink-0">
                        <Icon class="size-5 sm:size-6" icon={form.sale_type === 'weight' ? 'solar:scale-bold-duotone' : 'solar:box-minimalistic-bold-duotone'} />
                    </div>
                    <div class="min-w-0 flex-1 flex items-center">
                        <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                            {mode === 'edit' ? 'Editar Producto' : 'Crear Producto'}
                        </DialogTitle>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={handleClose}
                    class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer shrink-0"
                    aria-label="Cerrar"
                >
                    <X class="size-4" />
                </button>
            </div>
        </DialogHeader>


        <!-- 2. Body -->
        <DialogBody>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <!-- Columna Izquierda: Imagen, Empaque & Resumen Visual (4/12) -->
                <div class="md:col-span-4 space-y-4">
                    <div class="space-y-1">
                        <h3 class="text-xs font-bold text-foreground uppercase tracking-wider flex items-center gap-1.5">
                            <Package class="size-4 text-primary" />
                            Imagen y empaque
                        </h3>
                    </div>

                    <!-- Product Image Uploader -->
                    <ProductImageUploader
                        initialImageUrl={product?.image_url ?? null}
                        initialPosition={(product as any)?.image_position ?? null}
                        bind:file={form.image}
                        bind:position={form.image_position}
                        bind:removeImage={form.remove_image}
                        error={form.errors.image}
                        disabled={form.processing}
                    />

                    <!-- Bento Card Cálculo de Bulto -->
                    <div class="p-3.5 rounded-xl bg-primary/5 border border-primary/20 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-primary uppercase tracking-wider flex items-center gap-1.5">
                                <Sparkles class="size-3.5" />
                                Cálculo de Bulto
                            </span>
                            {#if form.is_active}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    Activo
                                </span>
                            {:else}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                    Inactivo
                                </span>
                            {/if}
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-1 text-xs">
                            <div>
                                <span class="text-xs text-muted-foreground block">
                                    {form.sale_type === 'weight' ? 'Peso por Bulto' : 'Unidades / Bulto'}
                                </span>
                                <span class="font-bold text-foreground font-sans tabular-nums">
                                    {#if form.sale_type === 'weight'}
                                        {form.package_quantity || 1} Kg
                                    {:else}
                                        {form.units_per_package || 1} {Number(form.units_per_package) === 1 ? 'unidad' : 'unidades'}
                                    {/if}
                                </span>
                            </div>
                            <div>
                                <span class="text-xs text-muted-foreground block">Precio del Bulto</span>
                                <span class="font-bold font-sans tabular-nums text-primary">
                                    {formatPrice(calculatedPackagePrice)}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Formulario (8/12) -->
                <div class="md:col-span-8">
                    <form id="product-modal-form" onsubmit={submit} class="space-y-4">
                        <!-- 1. Información Básica & Modalidad de Venta -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between gap-2 flex-wrap pb-1">
                                <h3 class="text-xs font-bold text-primary uppercase tracking-wider">1. Información Básica</h3>

                                <!-- Segmented Switcher Unidad / Peso -->
                                <div class="inline-flex items-center p-0.5 rounded-xl bg-muted/80 border border-border/80 text-xs">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs transition-all {form.sale_type === 'unit' ? 'bg-background text-foreground font-bold shadow-2xs' : 'text-muted-foreground hover:text-foreground font-medium'}"
                                        onclick={() => (form.sale_type = 'unit')}
                                    >
                                        <Package class="size-3.5 text-primary" />
                                        <span>Por Unidad</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs transition-all {form.sale_type === 'weight' ? 'bg-background text-foreground font-bold shadow-2xs' : 'text-muted-foreground hover:text-foreground font-medium'}"
                                        onclick={() => (form.sale_type = 'weight')}
                                    >
                                        <Scale class="size-3.5 text-sky-600 dark:text-sky-400" />
                                        <span>Por Pesaje (Kg)</span>
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="sm:col-span-2 space-y-1">
                                    <Label for="modal-prod-name" class="text-sm font-medium text-foreground">
                                        Nombre del Producto <span class="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="modal-prod-name"
                                        bind:value={form.name}
                                        placeholder={form.sale_type === 'weight' ? 'ej. Bolsas de Polietileno a Granel' : 'ej. Resma Chambril A4 75g'}
                                        class="h-10 text-sm rounded-xl"
                                        required
                                    />
                                    {#if form.errors.name}
                                        <p class="text-xs text-destructive">{form.errors.name}</p>
                                    {/if}
                                </div>

                                <div class="space-y-1">
                                    <Label for="modal-prod-sku" class="text-sm font-medium text-foreground">SKU / Código</Label>
                                    <Input
                                        id="modal-prod-sku"
                                        bind:value={form.sku}
                                        placeholder="ej. RES-A4-75"
                                        class="h-10 text-sm rounded-xl font-sans tabular-nums"
                                    />
                                    {#if form.errors.sku}
                                        <p class="text-xs text-destructive">{form.errors.sku}</p>
                                    {/if}
                                </div>

                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <Label for="modal-prod-cat" class="text-sm font-medium text-foreground">Categoría</Label>
                                        {#if isProductIncomplete && !form.category_id}
                                            <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">Dato faltante</span>
                                        {/if}
                                    </div>
                                    <select
                                        id="modal-prod-cat"
                                        bind:value={form.category_id}
                                        class="h-10 w-full rounded-xl border bg-background px-3 text-sm font-medium text-foreground focus:outline-none transition-colors {isProductIncomplete && !form.category_id ? 'border-amber-500 ring-2 ring-amber-500/20 dark:border-amber-400 focus:border-amber-600' : 'border-border/80 focus:border-primary-500 focus:ring-1 focus:ring-primary'}"
                                    >
                                        <option value="">Sin categoría asignada</option>
                                        {#each categories as cat}
                                            <option value={cat.id}>{cat.name}</option>
                                        {/each}
                                    </select>
                                    {#if form.errors.category_id}
                                        <p class="text-xs text-destructive">{form.errors.category_id}</p>
                                    {/if}
                                </div>
                            </div>
                        </div>

                        <hr class="border-border/60" />

                        <!-- 2. Precios & Facturación (ARCA) -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-bold text-primary uppercase tracking-wider">2. Precios & Facturación (ARCA)</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <!-- 1. Precio de venta (mutable/obligatorio - Desktop col 1, Mobile col 1) -->
                                <div class="space-y-1.5 order-1 sm:order-1">
                                    <div class="min-h-[38px] flex items-end justify-between gap-1 pb-0.5">
                                        <Label for="modal-prod-price" class="text-sm font-medium text-foreground leading-tight">
                                            Precio de venta ({form.sale_type === 'weight' ? 'kilo' : 'unid.'}) <span class="text-destructive">*</span>
                                        </Label>
                                        {#if isProductIncomplete && (!form.price || Number(form.price) <= 0)}
                                            <span class="text-xs font-semibold text-amber-600 dark:text-amber-400 shrink-0">Dato faltante</span>
                                        {/if}
                                    </div>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">$</span>
                                        <Input
                                            id="modal-prod-price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            bind:value={form.price}
                                            oninput={updateCalculatedNetPrice}
                                            placeholder="0.00"
                                            class="h-10 pl-7 text-sm rounded-xl tabular-nums font-sans font-bold text-foreground transition-colors {isProductIncomplete && (!form.price || Number(form.price) <= 0) ? 'border-amber-500 ring-2 ring-amber-500/20 dark:border-amber-400 focus-visible:ring-amber-500' : ''}"
                                            required
                                        />
                                    </div>
                                    {#if form.errors.price}
                                        <p class="text-xs text-destructive">{form.errors.price}</p>
                                    {/if}
                                </div>

                                <!-- 2. Precio neto (mutable/asistido - Desktop col 2, Mobile col 2) -->
                                <div class="space-y-1.5 order-2 sm:order-2">
                                    <div class="min-h-[38px] flex items-end pb-0.5">
                                        <Label for="modal-prod-net-price" class="text-sm font-medium text-foreground leading-tight">
                                            Precio neto ({form.sale_type === 'weight' ? 'kilo' : 'unid.'})
                                        </Label>
                                    </div>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">$</span>
                                        <Input
                                            id="modal-prod-net-price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            bind:value={form.net_price}
                                            oninput={updateCalculatedGrossPrice}
                                            placeholder="0.00"
                                            class="h-10 pl-7 text-sm rounded-xl tabular-nums font-sans font-semibold"
                                        />
                                    </div>
                                    {#if form.errors.net_price}
                                        <p class="text-xs text-destructive">{form.errors.net_price}</p>
                                    {/if}
                                </div>

                                <!-- 3. Costo (Desktop col 3, Mobile col 3) -->
                                <div class="space-y-1.5 order-3 sm:order-3">
                                    <div class="min-h-[38px] flex items-end pb-0.5">
                                        <Label for="modal-prod-cost" class="text-sm font-medium text-foreground leading-tight">
                                            Costo ({form.sale_type === 'weight' ? 'kilo' : 'unid.'})
                                        </Label>
                                    </div>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">$</span>
                                        <Input
                                            id="modal-prod-cost"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            bind:value={form.cost_price}
                                            placeholder="0.00"
                                            class="h-10 pl-7 text-sm rounded-xl font-sans tabular-nums"
                                        />
                                    </div>
                                    {#if form.errors.cost_price}
                                        <p class="text-xs text-destructive">{form.errors.cost_price}</p>
                                    {/if}
                                </div>

                                <!-- 4. IVA (Desktop col 4, Mobile col 4) -->
                                <div class="space-y-1.5 order-4 sm:order-4">
                                    <div class="min-h-[38px] flex items-end pb-0.5">
                                        <Label for="modal-prod-iva" class="text-sm font-medium text-foreground leading-tight">IVA</Label>
                                    </div>
                                    <select
                                        id="modal-prod-iva"
                                        bind:value={form.iva_rate}
                                        onchange={handleIvaChange}
                                        class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary font-sans tabular-nums"
                                    >
                                        <option value={21.00}>21.0% (General)</option>
                                        <option value={10.50}>10.5% (Reducido)</option>
                                        <option value={27.00}>27.0% (Especial)</option>
                                        <option value={0.00}>0.0% (Exento)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr class="border-border/60" />

                        <!-- 3. Inventario & Empaque -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-bold text-primary uppercase tracking-wider">3. Inventario & Empaque</h3>

                            {#if mode === 'create' && warehouses.length > 0}
                                <div class="space-y-1">
                                    <Label for="modal-prod-warehouse" class="text-sm font-medium text-foreground">
                                        Depósito Destino (Asignación de Stock Inicial)
                                    </Label>
                                    <select
                                        id="modal-prod-warehouse"
                                        bind:value={form.warehouse_id}
                                        class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
                                    >

                                        {#each warehouses as wh (wh.id)}
                                            <option value={wh.id}>
                                                {wh.name} ({wh.code}) {wh.is_default ? '— Principal' : ''}
                                            </option>
                                        {/each}
                                    </select>

                                    {#if form.errors.warehouse_id}
                                        <p class="text-xs text-destructive">{form.errors.warehouse_id}</p>
                                    {/if}
                                </div>
                            {/if}

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="space-y-1.5">
                                    <div class="min-h-[38px] flex items-end justify-between gap-1 pb-0.5">
                                        <Label for="modal-prod-stock" class="text-sm font-medium text-foreground leading-tight">
                                            {form.sale_type === 'weight' ? 'Stock Actual (Kg)' : 'Stock Actual (Unid)'}
                                        </Label>
                                        {#if product}
                                            <span class="text-xs text-muted-foreground font-medium shrink-0">Vía Kardex</span>
                                        {/if}
                                    </div>
                                    <Input
                                        id="modal-prod-stock"
                                        type="number"
                                        step={form.sale_type === 'weight' ? '0.001' : '1'}
                                        min="0"
                                        max="10000"
                                        maxlength="5"
                                        oninput={(e) => {
                                             const val = (e.target as HTMLInputElement).value;
                                             if (val.length > 5) (e.target as HTMLInputElement).value = val.slice(0, 5);
                                             if (Number(val) > 10000) form.stock = 10000;
                                        }}
                                        bind:value={form.stock}
                                        disabled={!!product}
                                        class="h-10 text-sm rounded-xl text-center font-semibold font-sans tabular-nums {product ? 'bg-muted/50 cursor-not-allowed opacity-80' : ''}"
                                        title={product ? 'El stock físico se ajusta mediante Ingresos/Egresos auditados en Kardex' : 'Stock inicial'}
                                    />
                                    {#if form.errors.stock}
                                        <p class="text-xs text-destructive">{form.errors.stock}</p>
                                    {/if}
                                </div>

                                <div class="space-y-1.5">
                                    <div class="min-h-[38px] flex items-end pb-0.5">
                                        <Label for="modal-prod-min-stock" class="text-sm font-medium text-foreground leading-tight">
                                            {form.sale_type === 'weight' ? 'Stock Mín. (Kg)' : 'Stock Mín. (Unid)'}
                                        </Label>
                                    </div>
                                    <Input
                                        id="modal-prod-min-stock"
                                        type="number"
                                        step={form.sale_type === 'weight' ? '0.001' : '1'}
                                        min="0"
                                        bind:value={form.min_stock}
                                        class="h-10 text-sm rounded-xl text-center font-sans tabular-nums"
                                    />
                                    {#if form.errors.min_stock}
                                        <p class="text-xs text-destructive">{form.errors.min_stock}</p>
                                    {/if}
                                </div>

                                <div class="space-y-1.5">
                                    <div class="min-h-[38px] flex items-end pb-0.5">
                                        <Label for="modal-prod-units-pack" class="text-sm font-medium text-foreground leading-tight">
                                            {form.sale_type === 'weight' ? 'Peso Bulto (Kg)' : 'Unidades / Bulto'}
                                        </Label>
                                    </div>
                                    {#if form.sale_type === 'weight'}
                                        <Input
                                            id="modal-prod-units-pack"
                                            type="number"
                                            step="0.001"
                                            min="0.001"
                                            bind:value={form.package_quantity}
                                            class="h-10 text-sm rounded-xl text-center font-semibold font-sans tabular-nums"
                                        />
                                    {:else}
                                        <Input
                                            id="modal-prod-units-pack"
                                            type="number"
                                            step="1"
                                            min="1"
                                            bind:value={form.units_per_package}
                                            class="h-10 text-sm rounded-xl text-center font-semibold font-sans tabular-nums"
                                        />
                                    {/if}
                                    {#if form.errors.units_per_package || form.errors.package_quantity}
                                        <p class="text-xs text-destructive">{form.errors.units_per_package || form.errors.package_quantity}</p>
                                    {/if}
                                </div>
                            </div>
                        </div>

                        <hr class="border-border/60" />

                        <!-- 4. Descripción y Estado -->
                        <div class="space-y-3">
                            <div class="space-y-1">
                                <Label for="modal-prod-desc" class="text-sm font-medium text-foreground">Descripción o Detalles</Label>
                                <textarea
                                    id="modal-prod-desc"
                                    bind:value={form.description}
                                    rows="2"
                                    maxlength="255"
                                    placeholder="Especificaciones técnicas, medidas, gramaje..."
                                    class="w-full bg-background rounded-xl p-3 text-sm border border-border/80 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary resize-none"
                                ></textarea>


                            </div>

                            <!-- Toggle de Estado Activo -->
                            <div class="flex items-center justify-between p-3 rounded-xl bg-muted/20 border border-border/70">
                                <div>
                                    <span class="text-sm font-medium text-foreground block">Producto Activo</span>
                                    <span class="text-xs text-muted-foreground">Disponible para ventas y pedidos en el sistema.</span>
                                </div>
                                <input
                                    type="checkbox"
                                    bind:checked={form.is_active}
                                    class="size-5 rounded border-input text-primary focus:ring-primary"
                                />
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </DialogBody>

        <!-- 3. Footer -->
        <DialogFooter class="gap-2 sm:gap-2">
            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] px-5 rounded-xl text-sm font-medium border-border/70 hover:bg-muted/60 transition-colors"
                onclick={handleClose}
                disabled={form.processing}
            >
                Cancelar
            </Button>
            <Button
                type="submit"
                form="product-modal-form"
                disabled={form.processing}
                class="min-h-[44px] px-6 rounded-xl text-sm font-medium gap-2 shadow-xs min-w-[160px]"
            >
                {#if form.processing}
                    <span>Guardando...</span>
                {:else}
                    <Check class="size-4" />
                    <span>{mode === 'edit' ? 'Guardar cambios' : 'Guardar producto'}</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>


<!-- Alert Dialog de Bits UI para Confirmar Cambio de Modalidad de Venta -->
<SaleTypeChangeAlertDialog
    bind:open={isConfirmAlertOpen}
    productName={product?.name ?? 'este producto'}
    fromType={initialSaleType}
    toType={form.sale_type}
    currentStock={product?.stock ?? 0}
    loading={form.processing}
    onconfirm={doSubmit}
/>
