<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import SaleTypeChangeAlertDialog from '@/components/common/SaleTypeChangeAlertDialog.svelte';
    import ProductImageUploader from '@/components/tenant/products/ProductImageUploader.svelte';
    import { Link, useForm } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Check from 'lucide-svelte/icons/check';
    import Package from 'lucide-svelte/icons/package';
    import Scale from 'lucide-svelte/icons/scale';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import UploadCloud from 'lucide-svelte/icons/upload-cloud';

    interface CategoryOption {
        id: number;
        name: string;
    }

    interface ProductData {
        id: number;
        name: string;
        sale_type?: 'unit' | 'weight';
        category_id: number | null;
        sku: string | null;
        barcode: string | null;
        description: string | null;
        price: string | number;
        cost_price: string | number | null;
        iva_rate: string | number;
        stock: number;
        min_stock: number;
        units_per_package?: number;
        package_quantity?: number;
        bulk_discount_type?: string;
        custom_bulk_discount_percentage?: number | string | null;
        image_url: string | null;
        is_active: boolean;
    }

    let {
        product,
        categories = [],
        tenantDefaultBulkDiscount = 0,
    }: {
        product: ProductData;
        categories: CategoryOption[];
        tenantDefaultBulkDiscount?: number;
    } = $props();

    let initialSaleType = $derived((product.sale_type ?? 'unit') as 'unit' | 'weight');
    let isConfirmAlertOpen = $state(false);

    const form = useForm(() => ({
        name: product.name,
        sale_type: initialSaleType,
        category_id: product.category_id ?? ('' as string | number),
        sku: product.sku ?? '',
        barcode: product.barcode ?? '',
        description: product.description ?? '',
        price: product.price,
        cost_price: product.cost_price ?? '',
        iva_rate: Number(product.iva_rate ?? 21.00),
        stock: product.stock,
        min_stock: product.min_stock,
        units_per_package: product.units_per_package ?? product.package_quantity ?? 1,
        package_quantity: product.package_quantity ?? product.units_per_package ?? 1,
        bulk_discount_type: product.bulk_discount_type ?? 'inherited',
        custom_bulk_discount_percentage: product.custom_bulk_discount_percentage ?? '',
        image: null as File | null,
        remove_image: false,
        image_url: product.image_url ?? '',
        is_active: product.is_active,
    }));

    function formatPrice(amount: number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(amount);
    }

    let effectiveDiscountPercent = $derived.by(() => {
        if (form.bulk_discount_type === 'none') return 0;
        if (form.bulk_discount_type === 'custom') return Number(form.custom_bulk_discount_percentage) || 0;
        return Number(tenantDefaultBulkDiscount) || 0;
    });

    let calculatedPackagePrice = $derived.by(() => {
        const unitPrice = Number(form.price) || 0;
        const ratio = Number(form.sale_type === 'weight' ? (form.package_quantity || form.units_per_package) : form.units_per_package) || 1;
        const discountMultiplier = (1 - (effectiveDiscountPercent / 100));
        return unitPrice * ratio * discountMultiplier;
    });

    function doSubmit() {
        if (form.sale_type === 'weight') {
            form.units_per_package = form.package_quantity || form.units_per_package || 1;
        } else {
            form.package_quantity = form.units_per_package || 1;
        }

        if (form.image) {
            form.transform((data) => ({
                ...data,
                _method: 'put',
            })).post(`/tenant/productos/${product.id}`, {
                onSuccess: () => {
                    isConfirmAlertOpen = false;
                },
            });
        } else {
            form.put(`/tenant/productos/${product.id}`, {
                onSuccess: () => {
                    isConfirmAlertOpen = false;
                },
            });
        }
    }

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        // Si cambió la modalidad de venta respecto al valor inicial, alertar al usuario
        if (form.sale_type !== initialSaleType) {
            isConfirmAlertOpen = true;
            return;
        }
        doSubmit();
    }
</script>

<AppHead title={`Editar ${product.name} - Comercio`} />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-6xl mx-auto w-full">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">
                Editar Producto
            </h1>
        </div>
        <Link href="/tenant/productos">
            <Button class="gap-2 text-sm rounded-xl font-medium min-h-[42px] h-auto py-2.5 px-4 shadow-2xs leading-snug text-center flex items-center justify-center">
                <ArrowLeft class="size-4 shrink-0" />
                <span>Volver al catálogo</span>
            </Button>
        </Link>
    </div>



    <!-- Contenedor 2 Columnas Stitch Layout -->
    <div class="bg-card text-card-foreground rounded-3xl border border-border/80 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] overflow-hidden flex flex-col md:flex-row">
        <!-- Columna Izquierda: Imagen y Empaque (40%) -->
        <div class="w-full md:w-5/12 bg-muted/25 p-6 sm:p-8 flex flex-col border-b md:border-b-0 md:border-r border-border/70 shrink-0">
            <div class="mb-4">
                <h2 class="text-base sm:text-lg font-semibold text-foreground flex items-center gap-2 leading-snug">
                    <Package class="size-5 text-primary" />
                    Imagen y empaque
                </h2>
            </div>

            <!-- Product Image Uploader -->
            <ProductImageUploader
                initialImageUrl={product.image_url}
                bind:file={form.image}
                bind:removeImage={form.remove_image}
                error={form.errors.image}
                disabled={form.processing}
            />

            <!-- Resumen de Bulto -->
            <div class="mt-5 p-4 rounded-2xl bg-primary/5 border border-primary/20 space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-primary uppercase tracking-wide flex items-center gap-1.5">
                        <Sparkles class="size-3.5" />
                        Cálculo de bulto
                    </span>
                    {#if form.is_active}
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            Activo
                        </span>
                    {:else}
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                            Inactivo
                        </span>
                    {/if}
                </div>

                <div class="grid grid-cols-2 gap-3 pt-1 text-xs">
                    <div>
                        <span class="text-[10px] text-muted-foreground block">
                            {form.sale_type === 'weight' ? 'Kg por bulto' : 'Unidades por bulto'}
                        </span>
                        <span class="font-semibold text-foreground tabular-nums">
                            {form.sale_type === 'weight' ? (form.package_quantity || form.units_per_package || 1) : (form.units_per_package || 1)} {form.sale_type === 'weight' ? 'Kg' : (Number(form.units_per_package) === 1 ? 'unidad' : 'unidades')}
                        </span>
                    </div>
                    <div>
                        <span class="text-[10px] text-muted-foreground block">Precio del bulto</span>
                        <span class="font-bold text-primary tabular-nums">
                            {formatPrice(calculatedPackagePrice)}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Formulario (60%) -->
        <div class="w-full md:w-7/12 bg-card p-6 sm:p-8 flex flex-col">
            <form onsubmit={handleSubmit} class="space-y-5">
                <!-- 1. Información Básica con Segmented Pill Switcher -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2 flex-wrap pb-1">
                        <h3 class="text-xs font-semibold text-primary uppercase tracking-wide">1. Información básica</h3>
                        
                        <!-- Segmented Pill Switcher Minimalista -->
                        <div class="inline-flex items-center p-0.5 rounded-xl bg-muted/80 border border-border/80 text-xs">
                            <button
                                type="button"
                                onclick={() => form.sale_type = 'unit'}
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs transition-all {form.sale_type === 'unit' ? 'bg-background text-foreground font-bold shadow-2xs' : 'text-muted-foreground hover:text-foreground font-medium'}"
                            >
                                <Package class="size-3.5 text-primary" />
                                <span>Por unidad</span>
                            </button>
                            <button
                                type="button"
                                onclick={() => form.sale_type = 'weight'}
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs transition-all {form.sale_type === 'weight' ? 'bg-background text-foreground font-bold shadow-2xs' : 'text-muted-foreground hover:text-foreground font-medium'}"
                            >
                                <Scale class="size-3.5 text-sky-600 dark:text-sky-400" />
                                <span>Por pesaje (Kg)</span>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="sm:col-span-2 space-y-1">
                            <Label for="edit-prod-name" class="text-xs font-semibold">
                                Nombre del Producto <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="edit-prod-name"
                                bind:value={form.name}
                                placeholder="ej. Resma Chambril A4 75g"
                                class="h-9 text-xs rounded-xl"
                                required
                            />
                            {#if form.errors.name}
                                <p class="text-xs text-destructive">{form.errors.name}</p>
                            {/if}
                        </div>

                        <div class="space-y-1">
                            <Label for="edit-prod-sku" class="text-xs font-semibold">SKU / Código</Label>
                            <Input
                                id="edit-prod-sku"
                                bind:value={form.sku}
                                placeholder="ej. RES-A4-75"
                                class="h-9 text-xs rounded-xl tracking-wide"
                            />
                            {#if form.errors.sku}
                                <p class="text-xs text-destructive">{form.errors.sku}</p>
                            {/if}
                        </div>

                        <div class="space-y-1">
                            <Label for="edit-prod-cat" class="text-xs font-semibold">Categoría</Label>
                            <select
                                id="edit-prod-cat"
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

                <!-- 2. Precios & Fiscal -->
                <div class="space-y-3">
                    <h3 class="text-xs font-semibold text-primary uppercase tracking-wide">2. Precios y facturación</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="space-y-1">
                            <Label for="edit-prod-price" class="text-xs font-semibold">
                                Precio Venta ({form.sale_type === 'weight' ? '$ / Kg' : '$ / unid.'}) <span class="text-destructive">*</span>
                            </Label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">$</span>
                                <Input
                                    id="edit-prod-price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    bind:value={form.price}
                                    placeholder="0.00"
                                    class="h-9 pl-7 text-xs rounded-xl tabular-nums"
                                    required
                                />
                            </div>
                            {#if form.errors.price}
                                <p class="text-xs text-destructive">{form.errors.price}</p>
                            {/if}
                        </div>

                        <div class="space-y-1">
                            <Label for="edit-prod-cost" class="text-xs font-semibold">
                                Costo ({form.sale_type === 'weight' ? '$ / Kg' : '$ / unid.'})
                            </Label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">$</span>
                                <Input
                                    id="edit-prod-cost"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    bind:value={form.cost_price}
                                    placeholder="0.00"
                                    class="h-9 pl-7 text-xs rounded-xl tabular-nums"
                                />
                            </div>
                            {#if form.errors.cost_price}
                                <p class="text-xs text-destructive">{form.errors.cost_price}</p>
                            {/if}
                        </div>

                        <div class="space-y-1">
                            <Label for="edit-prod-iva" class="text-xs font-semibold">IVA (%)</Label>
                            <select
                                id="edit-prod-iva"
                                bind:value={form.iva_rate}
                                class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary tabular-nums"
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

                <!-- Descuento por Bulto -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-semibold text-primary uppercase tracking-wide">Descuento por Bulto</h3>
                        {#if effectiveDiscountPercent > 0}
                            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                -{effectiveDiscountPercent}% aplicado
                            </span>
                        {/if}
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <Label for="edit-prod-discount-type" class="text-xs font-semibold">Modalidad de Descuento</Label>
                            <select
                                id="edit-prod-discount-type"
                                bind:value={form.bulk_discount_type}
                                class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
                            >

                                <option value="inherited">Heredar global ({tenantDefaultBulkDiscount}%)</option>
                                <option value="custom">Personalizado (% específico)</option>
                                <option value="none">Sin descuento por bulto (0%)</option>
                            </select>
                        </div>


                        {#if form.bulk_discount_type === 'custom'}
                            <div class="space-y-1">
                                <Label for="edit-prod-custom-discount" class="text-xs font-semibold">
                                    Porcentaje Personalizado (%) <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="edit-prod-custom-discount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    bind:value={form.custom_bulk_discount_percentage}
                                    placeholder="0.00"
                                    class="h-9 text-xs rounded-xl tabular-nums font-semibold"
                                    required
                                />
                            </div>
                        {/if}
                    </div>
                </div>

                <hr class="border-border/60" />

                <!-- 3. Inventario y Empaque -->
                <div class="space-y-3">
                    <h3 class="text-xs font-semibold text-primary uppercase tracking-wide">3. Inventario y empaque</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <Label for="edit-prod-stock" class="text-xs font-semibold">
                                    Stock Actual ({form.sale_type === 'weight' ? 'Kg' : 'unid.'})
                                </Label>
                                <span class="text-[10px] text-muted-foreground font-medium">Vía Kardex</span>
                            </div>
                            <Input
                                id="edit-prod-stock"
                                type="number"
                                step={form.sale_type === 'weight' ? '0.001' : '1'}
                                min="0"
                                bind:value={form.stock}
                                disabled
                                class="h-10 text-xs rounded-xl text-center font-bold bg-transparent border-dashed text-muted-foreground/60 cursor-not-allowed tabular-nums"
                                title="El stock físico se ajusta mediante Ingresos/Egresos auditados en Kardex"
                            />
                            {#if form.errors.stock}
                                <p class="text-xs text-destructive">{form.errors.stock}</p>
                            {/if}
                        </div>

                        <div class="space-y-1">
                            <Label for="edit-prod-min-stock" class="text-xs font-semibold">
                                Stock Mínimo ({form.sale_type === 'weight' ? 'Kg' : 'unid.'})
                            </Label>
                            <Input
                                id="edit-prod-min-stock"
                                type="number"
                                step={form.sale_type === 'weight' ? '0.001' : '1'}
                                min="0"
                                bind:value={form.min_stock}
                                class="h-9 text-xs rounded-xl text-center tabular-nums"
                            />
                            {#if form.errors.min_stock}
                                <p class="text-xs text-destructive">{form.errors.min_stock}</p>
                            {/if}
                        </div>

                        <div class="space-y-1">
                            {#if form.sale_type === 'weight'}
                                <Label for="edit-prod-units-pack" class="text-xs font-semibold">Kg por Bulto Cerrado</Label>
                                <Input
                                    id="edit-prod-units-pack"
                                    type="number"
                                    step="0.01"
                                    min="0.1"
                                    bind:value={form.package_quantity}
                                    class="h-9 text-xs rounded-xl text-center font-bold tabular-nums"
                                />
                            {:else}
                                <Label for="edit-prod-units-pack" class="text-xs font-semibold">Unidades por Bulto</Label>
                                <Input
                                    id="edit-prod-units-pack"
                                    type="number"
                                    min="1"
                                    bind:value={form.units_per_package}
                                    class="h-9 text-xs rounded-xl text-center font-bold tabular-nums"
                                />
                            {/if}
                            {#if form.errors.units_per_package || form.errors.package_quantity}
                                <p class="text-xs text-destructive">{form.errors.units_per_package || form.errors.package_quantity}</p>
                            {/if}
                        </div>
                    </div>
                </div>

                <hr class="border-border/60" />

                <!-- 4. Código de Barras, Descripción & Estado -->
                <div class="space-y-3">
                    <h3 class="text-xs font-semibold text-primary uppercase tracking-wide">4. Código de barras, descripción y estado</h3>
                    <div class="space-y-1">
                        <Label for="edit-prod-barcode" class="text-xs font-semibold">Código de Barras (EAN / UPC)</Label>
                        <Input
                            id="edit-prod-barcode"
                            bind:value={form.barcode}
                            placeholder="ej. 7791234567890"
                            class="h-9 text-xs rounded-xl tracking-wide"
                        />
                    </div>

                    <div class="space-y-1">
                        <Label for="edit-prod-desc" class="text-xs font-semibold">Descripción</Label>
                        <textarea
                            id="edit-prod-desc"
                            bind:value={form.description}
                            rows="2"
                            placeholder="Especificaciones de tamaño, gramaje, características..."
                            class="w-full bg-background rounded-xl p-3 text-xs border border-border/80 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary resize-none"
                        ></textarea>

                    </div>


                    <div class="flex items-center gap-2 pt-1">
                        <input
                            type="checkbox"
                            id="edit-prod-active"
                            bind:checked={form.is_active}
                            class="size-4 rounded border-input text-primary focus:ring-primary"
                        />
                        <Label for="edit-prod-active" class="text-xs font-medium cursor-pointer">
                            Producto activo para ventas y pedidos
                        </Label>
                    </div>
                </div>

                <!-- Footer Acciones -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <Link href="/tenant/productos">
                        <Button type="button" variant="outline" class="min-h-[44px] px-5 rounded-xl text-sm font-medium border-border/70 hover:bg-muted/60 transition-colors">
                            Cancelar
                        </Button>
                    </Link>

                    <Button
                        type="submit"
                        class="min-h-[44px] px-6 rounded-xl text-sm font-semibold gap-2 shadow-xs min-w-[160px]"
                        disabled={form.processing}
                    >
                        <Check class="size-4" />
                        <span>Guardar cambios</span>
                    </Button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Alert Dialog de Bits UI para Confirmar Cambio de Modalidad de Venta -->
<SaleTypeChangeAlertDialog
    bind:open={isConfirmAlertOpen}
    productName={product.name}
    fromType={initialSaleType}
    toType={form.sale_type}
    currentStock={product.stock}
    loading={form.processing}
    onconfirm={doSubmit}
/>
