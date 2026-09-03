<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import SaleTypeChangeAlertDialog from '@/components/common/SaleTypeChangeAlertDialog.svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import { router, useForm } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import Package from 'lucide-svelte/icons/package';
    import Scale from 'lucide-svelte/icons/scale';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import UploadCloud from 'lucide-svelte/icons/upload-cloud';
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
        cost_price?: string | number | null;
        iva_rate?: number;
        stock?: number;
        min_stock?: number;
        units_per_package?: number;
        package_quantity?: number;
        image_url?: string | null;
        is_active?: boolean;
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
        cost_price: '',
        iva_rate: 21.00,
        stock: 0,
        min_stock: 5,
        units_per_package: 1,
        package_quantity: 1,
        image_url: '',
        is_active: true,
    });

    let lastLoadedProductId = $state<number | null>(null);
    let initialSaleType = $state<'unit' | 'weight'>('unit');
    let isConfirmAlertOpen = $state(false);

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
            form.price = product.price !== null && product.price !== undefined ? String(product.price) : '';
            form.cost_price = product.cost_price !== null && product.cost_price !== undefined ? String(product.cost_price) : '';
            form.iva_rate = product.iva_rate ?? 21.00;
            form.stock = product.stock ?? 0;
            form.min_stock = product.min_stock ?? 5;
            form.units_per_package = product.units_per_package ?? product.package_quantity ?? 1;
            form.package_quantity = product.package_quantity ?? product.units_per_package ?? 1;
            form.image_url = product.image_url ?? '';
            form.is_active = product.is_active !== undefined ? Boolean(product.is_active) : true;
            form.clearErrors();
        } else if (!product && lastLoadedProductId !== null) {
            lastLoadedProductId = null;
            initialSaleType = 'unit';
            form.reset();
            form.warehouse_id = warehouses.find(w => w.is_default)?.id ?? (warehouses[0]?.id ?? '');
            form.clearErrors();
        } else if (!product && !form.warehouse_id && warehouses.length > 0) {
            form.warehouse_id = warehouses.find(w => w.is_default)?.id ?? warehouses[0].id;
        }
    });


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
            form.put(`/tenant/productos/${product.id}`, {
                onSuccess: () => {
                    isConfirmAlertOpen = false;
                    open = false;
                    onsuccess?.();
                },
            });
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
    <DialogContent class="max-w-5xl max-h-[85vh]">
        <!-- 1. Header -->
        <DialogHeader>
            <div class="flex items-center gap-3 min-w-0">
                <IconContainer
                    size="md"
                    variant={form.sale_type === 'weight' ? 'info' : 'primary'}
                    icon={form.sale_type === 'weight' ? 'solar:scale-bold-duotone' : 'solar:box-minimalistic-bold-duotone'}
                />
                <div class="min-w-0">
                    <DialogTitle class="text-xl font-bold text-foreground">
                        {mode === 'edit' ? 'Editar Producto' : 'Crear Producto'}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                        {mode === 'edit' ? 'Modifica los parámetros y precios del catálogo.' : 'Ingresa los detalles para incorporar el artículo al catálogo.'}
                    </DialogDescription>
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
        </DialogHeader>


        <!-- 2. Body -->
        <DialogBody>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <!-- Columna Izquierda: Empaque, Multimedia & Resumen Visual (5/12) -->
                <div class="md:col-span-5 space-y-4">
                    <div class="space-y-1">
                        <h3 class="text-xs font-bold text-foreground uppercase tracking-wider flex items-center gap-1.5">
                            <Package class="size-4 text-primary" />
                            Empaque & Multimedia
                        </h3>
                        <p class="text-[11px] text-muted-foreground">
                            Configura la vista previa del artículo y su presentación por bulto.
                        </p>
                    </div>

                    <!-- Preview de Imagen -->
                    <div class="flex flex-col items-center justify-center border-2 border-dashed border-border rounded-2xl bg-card hover:bg-muted/40 transition-colors p-4 min-h-[180px] relative overflow-hidden group">
                        {#if form.image_url}
                            <img
                                src={form.image_url}
                                alt={form.name || 'Producto'}
                                class="w-full h-40 object-contain rounded-xl"
                                onerror={() => (form.image_url = '')}
                            />
                        {:else}
                            <div class="size-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-2 group-hover:scale-105 transition-transform border border-primary/20">
                                <UploadCloud class="size-6" />
                            </div>
                            <p class="text-xs font-semibold text-foreground text-center">URL de imagen o foto</p>
                            <p class="text-[10px] text-muted-foreground text-center mt-0.5">JPG, PNG, WebP o enlace web</p>
                        {/if}
                    </div>

                    <!-- Input URL Imagen -->
                    <div class="space-y-1">
                        <Label for="prod-modal-image" class="text-xs font-semibold text-muted-foreground">Enlace de Imagen</Label>
                        <Input
                            id="prod-modal-image"
                            bind:value={form.image_url}
                            placeholder="https://ejemplo.com/foto.jpg"
                            class="h-8 text-xs bg-background"
                        />
                    </div>

                    <!-- Bento Card Cálculo de Bulto -->
                    <div class="p-3.5 rounded-xl bg-primary/5 border border-primary/20 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-primary uppercase tracking-wider flex items-center gap-1.5">
                                <Sparkles class="size-3.5" />
                                Cálculo de Bulto
                            </span>
                            {#if form.is_active}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                    Activo
                                </span>
                            {:else}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                    Inactivo
                                </span>
                            {/if}
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-1 text-xs">
                            <div>
                                <span class="text-[10px] text-muted-foreground block">
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
                                <span class="text-[10px] text-muted-foreground block">Precio del Bulto</span>
                                <span class="font-bold font-sans tabular-nums text-primary">
                                    {formatPrice(calculatedPackagePrice)}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Formulario (7/12) -->
                <div class="md:col-span-7">
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
                                    <Label for="modal-prod-name" class="text-xs font-semibold">
                                        Nombre del Producto <span class="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="modal-prod-name"
                                        bind:value={form.name}
                                        placeholder={form.sale_type === 'weight' ? 'ej. Bolsas de Polietileno a Granel' : 'ej. Resma Chambril A4 75g'}
                                        class="h-9 text-xs rounded-xl"
                                        required
                                    />
                                    {#if form.errors.name}
                                        <p class="text-xs text-destructive">{form.errors.name}</p>
                                    {/if}
                                </div>

                                <div class="space-y-1">
                                    <Label for="modal-prod-sku" class="text-xs font-semibold">SKU / Código</Label>
                                    <Input
                                        id="modal-prod-sku"
                                        bind:value={form.sku}
                                        placeholder="ej. RES-A4-75"
                                        class="h-9 text-xs rounded-xl font-sans tabular-nums"
                                    />
                                    {#if form.errors.sku}
                                        <p class="text-xs text-destructive">{form.errors.sku}</p>
                                    {/if}
                                </div>

                                <div class="space-y-1">
                                    <Label for="modal-prod-cat" class="text-xs font-semibold">Categoría</Label>
                                    <select
                                        id="modal-prod-cat"
                                        bind:value={form.category_id}
                                        class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
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
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="space-y-1">
                                    <Label for="modal-prod-price" class="text-xs font-semibold">
                                        Precio Venta ({form.sale_type === 'weight' ? '$ / Kg' : '$ / unid.'}) <span class="text-destructive">*</span>
                                    </Label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">$</span>
                                        <Input
                                            id="modal-prod-price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            bind:value={form.price}
                                            placeholder="0.00"
                                            class="h-9 pl-7 text-xs rounded-xl font-sans tabular-nums"
                                            required
                                        />
                                    </div>
                                    {#if form.errors.price}
                                        <p class="text-xs text-destructive">{form.errors.price}</p>
                                    {/if}
                                </div>

                                <div class="space-y-1">
                                    <Label for="modal-prod-cost" class="text-xs font-semibold">
                                        Costo ({form.sale_type === 'weight' ? '$ / Kg' : '$ / unid.'})
                                    </Label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">$</span>
                                        <Input
                                            id="modal-prod-cost"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            bind:value={form.cost_price}
                                            placeholder="0.00"
                                            class="h-9 pl-7 text-xs rounded-xl font-sans tabular-nums"
                                        />
                                    </div>
                                    {#if form.errors.cost_price}
                                        <p class="text-xs text-destructive">{form.errors.cost_price}</p>
                                    {/if}
                                </div>

                                <div class="space-y-1">
                                    <Label for="modal-prod-iva" class="text-xs font-semibold">IVA (%)</Label>
                                    <select
                                        id="modal-prod-iva"
                                        bind:value={form.iva_rate}
                                        class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary font-sans tabular-nums"
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
                                    <Label for="modal-prod-warehouse" class="text-xs font-semibold">
                                        Depósito Destino (Asignación de Stock Inicial)
                                    </Label>
                                    <select
                                        id="modal-prod-warehouse"
                                        bind:value={form.warehouse_id}
                                        class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
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
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <Label for="modal-prod-stock" class="text-xs font-semibold">
                                            {form.sale_type === 'weight' ? 'Stock Actual (Kg)' : 'Stock Actual (Unid)'}
                                        </Label>
                                        {#if product}
                                            <span class="text-[10px] text-muted-foreground font-medium">Vía Kardex</span>
                                        {/if}
                                    </div>
                                    <Input
                                        id="modal-prod-stock"
                                        type="number"
                                        step={form.sale_type === 'weight' ? '0.001' : '1'}
                                        min="0"
                                        bind:value={form.stock}
                                        disabled={!!product}
                                        class="h-9 text-xs rounded-xl text-center font-bold font-sans tabular-nums {product ? 'bg-muted/50 cursor-not-allowed opacity-80' : ''}"
                                        title={product ? 'El stock físico se ajusta mediante Ingresos/Egresos auditados en Kardex' : 'Stock inicial'}
                                    />
                                    {#if form.errors.stock}
                                        <p class="text-xs text-destructive">{form.errors.stock}</p>
                                    {/if}
                                </div>

                                <div class="space-y-1">
                                    <Label for="modal-prod-min-stock" class="text-xs font-semibold">
                                        {form.sale_type === 'weight' ? 'Stock Mín. (Kg)' : 'Stock Mín. (Unid)'}
                                    </Label>
                                    <Input
                                        id="modal-prod-min-stock"
                                        type="number"
                                        step={form.sale_type === 'weight' ? '0.001' : '1'}
                                        min="0"
                                        bind:value={form.min_stock}
                                        class="h-9 text-xs rounded-xl text-center font-sans tabular-nums"
                                    />
                                    {#if form.errors.min_stock}
                                        <p class="text-xs text-destructive">{form.errors.min_stock}</p>
                                    {/if}
                                </div>

                                <div class="space-y-1">
                                    <Label for="modal-prod-units-pack" class="text-xs font-semibold">
                                        {form.sale_type === 'weight' ? 'Peso Bulto (Kg)' : 'Unidades / Bulto'}
                                    </Label>
                                    {#if form.sale_type === 'weight'}
                                        <Input
                                            id="modal-prod-units-pack"
                                            type="number"
                                            step="0.001"
                                            min="0.001"
                                            bind:value={form.package_quantity}
                                            class="h-9 text-xs rounded-xl text-center font-bold font-sans tabular-nums"
                                        />
                                    {:else}
                                        <Input
                                            id="modal-prod-units-pack"
                                            type="number"
                                            step="1"
                                            min="1"
                                            bind:value={form.units_per_package}
                                            class="h-9 text-xs rounded-xl text-center font-bold font-sans tabular-nums"
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
                                <Label for="modal-prod-desc" class="text-xs font-semibold">Descripción o Detalles</Label>
                                <textarea
                                    id="modal-prod-desc"
                                    bind:value={form.description}
                                    rows="2"
                                    placeholder="Especificaciones técnicas, medidas, gramaje..."
                                    class="w-full bg-background rounded-xl p-3 text-xs border border-border/80 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary resize-none"
                                ></textarea>


                            </div>

                            <!-- Toggle de Estado Activo -->
                            <div class="flex items-center justify-between p-3 rounded-xl bg-muted/20 border border-border/70">
                                <div>
                                    <span class="text-xs font-bold text-foreground block">Producto Activo</span>
                                    <span class="text-[11px] text-muted-foreground">Disponible para ventas y pedidos en el sistema.</span>
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
                class="min-h-[44px] px-6 rounded-xl text-sm font-semibold gap-2 shadow-xs min-w-[160px]"
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
