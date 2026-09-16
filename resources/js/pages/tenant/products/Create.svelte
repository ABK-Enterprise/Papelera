<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
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

    interface WarehouseOption {
        id: number;
        name: string;
        code: string;
        is_default?: boolean;
    }

    let {
        categories = [],
        warehouses = [],
        tenantDefaultBulkDiscount = 0,
    }: {
        categories: CategoryOption[];
        warehouses?: WarehouseOption[];
        tenantDefaultBulkDiscount?: number;
    } = $props();

    const form = useForm({
        name: '',
        sale_type: 'unit' as 'unit' | 'weight',
        category_id: '' as string | number,
        warehouse_id: (warehouses.find(w => w.is_default)?.id ?? warehouses[0]?.id ?? '') as string | number,
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
        bulk_discount_type: 'inherited',
        custom_bulk_discount_percentage: '',
        image_url: '',
        is_active: true,
    });


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

    function submit(e: SubmitEvent) {
        e.preventDefault();
        if (form.sale_type === 'weight') {
            form.units_per_package = form.package_quantity || form.units_per_package || 1;
        } else {
            form.package_quantity = form.units_per_package || 1;
        }
        form.post('/tenant/productos');
    }
</script>

<AppHead title="Alta de Producto - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-6xl mx-auto w-full">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight leading-tight text-foreground">
                Alta de Producto
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

            <!-- Preview Card -->
            <div class="flex-1 flex flex-col items-center justify-center border-2 border-dashed border-border rounded-2xl bg-card hover:bg-muted/40 transition-colors p-6 min-h-[220px] relative overflow-hidden group">
                {#if form.image_url}
                    <img
                        src={form.image_url}
                        alt={form.name || 'Producto'}
                        class="w-full h-48 object-contain rounded-xl"
                        onerror={() => (form.image_url = '')}
                    />
                {:else}
                    <div class="size-16 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mb-3 group-hover:scale-105 transition-transform border border-primary/20">
                        <UploadCloud class="size-8" />
                    </div>
                    <p class="text-xs font-semibold text-foreground text-center">URL de imagen o foto</p>
                    <p class="text-[11px] text-muted-foreground text-center mt-0.5">JPG, PNG, WebP o enlace web</p>
                {/if}
            </div>

            <!-- Input URL -->
            <div class="mt-4 space-y-1">
                <Label for="prod-image-url" class="text-xs font-semibold text-muted-foreground">Enlace de Imagen</Label>
                <Input
                    id="prod-image-url"
                    bind:value={form.image_url}
                    placeholder="https://ejemplo.com/foto.jpg"
                    class="h-9 text-xs rounded-xl bg-background"
                />
            </div>

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
            <form onsubmit={submit} class="space-y-5">
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
                            <Label for="create-prod-name" class="text-xs font-semibold">
                                Nombre del Producto <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="create-prod-name"
                                bind:value={form.name}
                                placeholder={form.sale_type === 'weight' ? 'ej. Papel Sulfito en Bobina / Bolsas Kraft por Kg' : 'ej. Resma Chambril A4 75g'}
                                class="h-9 text-xs rounded-xl"
                                required
                            />
                            {#if form.errors.name}
                                <p class="text-xs text-destructive">{form.errors.name}</p>
                            {/if}
                        </div>

                        <div class="space-y-1">
                            <Label for="create-prod-sku" class="text-xs font-semibold">SKU / Código</Label>
                            <Input
                                id="create-prod-sku"
                                bind:value={form.sku}
                                placeholder="ej. RES-A4-75"
                                class="h-9 text-xs rounded-xl tracking-wide"
                            />
                            {#if form.errors.sku}
                                <p class="text-xs text-destructive">{form.errors.sku}</p>
                            {/if}
                        </div>

                        <div class="space-y-1">
                            <Label for="create-prod-cat" class="text-xs font-semibold">Categoría</Label>
                            <select
                                id="create-prod-cat"
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
                            <Label for="create-prod-price" class="text-xs font-semibold">
                                Precio Venta ({form.sale_type === 'weight' ? '$ / Kg' : '$ / unid.'}) <span class="text-destructive">*</span>
                            </Label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">$</span>
                                <Input
                                    id="create-prod-price"
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
                            <Label for="create-prod-cost" class="text-xs font-semibold">
                                Costo ({form.sale_type === 'weight' ? '$ / Kg' : '$ / unid.'})
                            </Label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">$</span>
                                <Input
                                    id="create-prod-cost"
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
                            <Label for="create-prod-iva" class="text-xs font-semibold">IVA (%)</Label>
                            <select
                                id="create-prod-iva"
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
                            <Label for="create-prod-discount-type" class="text-xs font-semibold">Modalidad de Descuento</Label>
                            <select
                                id="create-prod-discount-type"
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
                                <Label for="create-prod-custom-discount" class="text-xs font-semibold">
                                    Porcentaje Personalizado (%) <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="create-prod-custom-discount"
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
                    <!-- 3. Inventario & Empaque -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-semibold text-primary uppercase tracking-wide">3. Inventario y empaque</h3>

                        {#if warehouses.length > 0}
                            <div class="space-y-1">
                                <Label for="create-prod-warehouse" class="text-xs font-semibold">
                                    Depósito Destino (Asignación de Stock Inicial)
                                </Label>
                                <select
                                    id="create-prod-warehouse"
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
                                <Label for="create-prod-stock" class="text-xs font-semibold">
                                    Stock Inicial ({form.sale_type === 'weight' ? 'Kg' : 'unid.'})
                                </Label>
                                <Input
                                    id="create-prod-stock"
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
                                    class="h-9 text-xs rounded-xl font-semibold tabular-nums"
                                    placeholder="0"
                                />
                                {#if form.errors.stock}
                                    <p class="text-xs text-destructive">{form.errors.stock}</p>
                                {/if}
                            </div>

                            <div class="space-y-1">
                                <Label for="create-prod-min-stock" class="text-xs font-semibold">
                                    Alerta Stock Mínimo
                                </Label>
                                <Input
                                    id="create-prod-min-stock"
                                    type="number"
                                    step={form.sale_type === 'weight' ? '0.001' : '1'}
                                    min="0"
                                    bind:value={form.min_stock}
                                    placeholder="0"
                                    class="h-9 text-xs rounded-xl font-semibold tabular-nums"
                                />
                                {#if form.errors.min_stock}
                                    <p class="text-xs text-destructive">{form.errors.min_stock}</p>
                                {/if}
                            </div>

                            <div class="space-y-1">
                                {#if form.sale_type === 'weight'}
                                    <Label for="create-prod-units-pack" class="text-xs font-semibold">Kg por Bulto Cerrado</Label>
                                    <Input
                                        id="create-prod-units-pack"
                                        type="number"
                                        step="0.01"
                                        min="0.1"
                                        bind:value={form.package_quantity}
                                        class="h-9 text-xs rounded-xl font-semibold tabular-nums"
                                        placeholder="1"
                                    />
                                {:else}
                                    <Label for="create-prod-units-pack" class="text-xs font-semibold">Unidades por Bulto</Label>
                                    <Input
                                        id="create-prod-units-pack"
                                        type="number"
                                        step="1"
                                        min="1"
                                        bind:value={form.units_per_package}
                                        class="h-9 text-xs rounded-xl font-semibold tabular-nums"
                                        placeholder="1"
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
                            <Label for="create-prod-barcode" class="text-xs font-semibold">Código de Barras (EAN / UPC)</Label>
                            <Input
                                id="create-prod-barcode"
                                bind:value={form.barcode}
                                placeholder="ej. 7791234567890"
                                class="h-9 text-xs rounded-xl tracking-wide"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label for="create-prod-desc" class="text-xs font-semibold">Descripción</Label>
                            <textarea
                                id="create-prod-desc"
                                bind:value={form.description}
                                rows="2"
                                placeholder="Especificaciones de tamaño, gramaje, características..."
                                class="w-full bg-background rounded-xl p-3 text-xs border border-border/80 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary resize-none"
                            ></textarea>

                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <input
                                type="checkbox"
                                id="create-prod-active"
                                bind:checked={form.is_active}
                                class="size-4 rounded border-input text-primary focus:ring-primary"
                            />
                            <Label for="create-prod-active" class="text-xs font-medium cursor-pointer">
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
                            <span>Guardar producto</span>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>


