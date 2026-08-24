<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router, useForm } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';
    import ImageIcon from 'lucide-svelte/icons/image';
    import Layers from 'lucide-svelte/icons/layers';
    import Package from 'lucide-svelte/icons/package';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import UploadCloud from 'lucide-svelte/icons/upload-cloud';
    import X from 'lucide-svelte/icons/x';

    export interface CategoryOption {
        id: number;
        name: string;
    }

    export interface ProductData {
        id?: number;
        name?: string;
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
        image_url?: string | null;
        is_active?: boolean;
    }

    let {
        open = $bindable(false),
        mode = 'create', // 'create' | 'edit'
        product = null,
        categories = [],
        onsuccess,
    }: {
        open: boolean;
        mode?: 'create' | 'edit';
        product?: ProductData | null;
        categories: CategoryOption[];
        onsuccess?: () => void;
    } = $props();

    const form = useForm({
        name: '',
        category_id: '' as string | number,
        sku: '',
        barcode: '',
        description: '',
        price: '',
        cost_price: '',
        iva_rate: 21.00,
        stock: 0,
        min_stock: 5,
        units_per_package: 1,
        image_url: '',
        is_active: true,
    });

    let lastLoadedProductId = $state<number | null>(null);

    $effect(() => {
        if (product && product.id !== lastLoadedProductId) {
            lastLoadedProductId = product.id ?? null;
            form.name = product.name ?? '';
            form.category_id = product.category_id ?? '';
            form.sku = product.sku ?? '';
            form.barcode = product.barcode ?? '';
            form.description = product.description ?? '';
            form.price = product.price !== null && product.price !== undefined ? String(product.price) : '';
            form.cost_price = product.cost_price !== null && product.cost_price !== undefined ? String(product.cost_price) : '';
            form.iva_rate = product.iva_rate ?? 21.00;
            form.stock = product.stock ?? 0;
            form.min_stock = product.min_stock ?? 5;
            form.units_per_package = product.units_per_package ?? 1;
            form.image_url = product.image_url ?? '';
            form.is_active = product.is_active !== undefined ? Boolean(product.is_active) : true;
            form.clearErrors();
        } else if (!product && lastLoadedProductId !== null) {
            lastLoadedProductId = null;
            form.reset();
            form.clearErrors();
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
        const ratio = Number(form.units_per_package) || 1;
        return unitPrice * ratio;
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();

        if (mode === 'edit' && product?.id) {
            form.put(`/tenant/products/${product.id}`, {
                onSuccess: () => {
                    open = false;
                    onsuccess?.();
                },
            });
        } else {
            form.post('/tenant/products', {
                onSuccess: () => {
                    open = false;
                    form.reset();
                    onsuccess?.();
                },
            });
        }
    }

    function handleClose() {
        open = false;
        form.clearErrors();
    }
</script>

{#if open}
    <div
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
        onclick={(e) => e.target === e.currentTarget && handleClose()}
        onkeydown={(e) => e.key === 'Escape' && handleClose()}
        role="button"
        tabindex="0"
        aria-label="Cerrar modal"
    >
        <!-- Modal Card 2 Columnas Desktop Stitch Layout -->
        <div class="bg-card text-card-foreground w-full max-w-5xl rounded-3xl border border-border shadow-2xl overflow-hidden flex flex-col md:flex-row max-h-[90vh] my-6 animate-in fade-in zoom-in-95 duration-200">
            <!-- Columna Izquierda: Imagen, Empaque & Resumen Visual (40%) -->
            <div class="w-full md:w-5/12 bg-muted/30 p-5 sm:p-7 flex flex-col border-b md:border-b-0 md:border-r border-border/80 relative overflow-y-auto shrink-0">
                <div class="mb-4">
                    <h3 class="text-lg font-bold text-foreground flex items-center gap-2">
                        <Package class="size-5 text-primary" />
                        Empaque & Multimedia
                    </h3>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Configura la vista previa del artículo y su presentación por bolsón.
                    </p>
                </div>

                <!-- Imagen / Preview Area -->
                <div class="flex-1 flex flex-col items-center justify-center border-2 border-dashed border-border rounded-2xl bg-card hover:bg-muted/40 transition-colors p-5 min-h-[190px] relative overflow-hidden group">
                    {#if form.image_url}
                        <img
                            src={form.image_url}
                            alt={form.name || 'Producto'}
                            class="w-full h-44 object-contain rounded-xl"
                            onerror={() => (form.image_url = '')}
                        />
                    {:else}
                        <div class="size-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mb-3 group-hover:scale-105 transition-transform border border-primary/20">
                            <UploadCloud class="size-7" />
                        </div>
                        <p class="text-xs font-semibold text-foreground text-center">URL de imagen o foto</p>
                        <p class="text-[11px] text-muted-foreground text-center mt-0.5">JPG, PNG, WebP o enlace web</p>
                    {/if}
                </div>

                <!-- Input para URL de imagen -->
                <div class="mt-3 space-y-1">
                    <Label for="prod-modal-image" class="text-[11px] font-semibold text-muted-foreground">Enlace de Imagen</Label>
                    <Input
                        id="prod-modal-image"
                        bind:value={form.image_url}
                        placeholder="https://ejemplo.com/foto.jpg"
                        class="h-8 text-xs bg-background"
                    />
                </div>

                <!-- Tarjeta Bento de Resumen de Bolsón / Presentación -->
                <div class="mt-4 p-3.5 rounded-2xl bg-primary/5 border border-primary/20 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-primary uppercase tracking-wider flex items-center gap-1.5">
                            <Sparkles class="size-3.5" />
                            Cálculo de Bolsón
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
                            <span class="text-[10px] text-muted-foreground block">Ratio por Bolsón</span>
                            <span class="font-bold text-foreground">
                                {form.units_per_package || 1} {Number(form.units_per_package) === 1 ? 'unidad' : 'unidades'}
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] text-muted-foreground block">Precio del Bolsón</span>
                            <span class="font-bold font-mono text-primary">
                                {formatPrice(calculatedPackagePrice)}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Formulario Estructurado (60%) -->
            <div class="w-full md:w-7/12 bg-card p-5 sm:p-7 flex flex-col relative overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between pb-3 border-b border-border/80 mb-4 shrink-0">
                    <div>
                        <h2 class="text-xl font-bold text-foreground">
                            {mode === 'edit' ? 'Editar Producto' : 'Alta de Producto'}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {mode === 'edit' ? 'Modifica los parámetros y precios del catálogo.' : 'Ingresa los detalles para incorporar el artículo al catálogo.'}
                        </p>
                    </div>
                    <button
                        type="button"
                        onclick={handleClose}
                        class="rounded-full p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                        aria-label="Cerrar"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <!-- Form Scrollable Content -->
                <form onsubmit={submit} class="flex-1 overflow-y-auto pr-1 space-y-4">
                    <!-- 1. Información Básica -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold text-primary uppercase tracking-wider">1. Información Básica</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2 space-y-1">
                                <Label for="modal-prod-name" class="text-xs font-semibold">
                                    Nombre del Producto <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="modal-prod-name"
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
                                <Label for="modal-prod-sku" class="text-xs font-semibold">SKU / Código</Label>
                                <Input
                                    id="modal-prod-sku"
                                    bind:value={form.sku}
                                    placeholder="ej. RES-A4-75"
                                    class="h-9 text-xs rounded-xl"
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
                                    class="h-9 w-full rounded-xl border border-input bg-background px-3 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary outline-none"
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

                    <!-- 2. Precios e Impuestos -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold text-primary uppercase tracking-wider">2. Precios & Facturación (ARCA)</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <Label for="modal-prod-price" class="text-xs font-semibold">
                                    Precio Venta ($) <span class="text-destructive">*</span>
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
                                        class="h-9 pl-7 text-xs rounded-xl font-mono"
                                        required
                                    />
                                </div>
                                {#if form.errors.price}
                                    <p class="text-xs text-destructive">{form.errors.price}</p>
                                {/if}
                            </div>

                            <div class="space-y-1">
                                <Label for="modal-prod-cost" class="text-xs font-semibold">Costo ($)</Label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">$</span>
                                    <Input
                                        id="modal-prod-cost"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        bind:value={form.cost_price}
                                        placeholder="0.00"
                                        class="h-9 pl-7 text-xs rounded-xl font-mono"
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
                                    class="h-9 w-full rounded-xl border border-input bg-background px-3 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary outline-none"
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

                    <!-- 3. Inventario y Empaque -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold text-primary uppercase tracking-wider">3. Inventario & Empaque</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <Label for="modal-prod-stock" class="text-xs font-semibold">Stock Actual</Label>
                                <Input
                                    id="modal-prod-stock"
                                    type="number"
                                    min="0"
                                    bind:value={form.stock}
                                    class="h-9 text-xs rounded-xl text-center font-bold"
                                />
                                {#if form.errors.stock}
                                    <p class="text-xs text-destructive">{form.errors.stock}</p>
                                {/if}
                            </div>

                            <div class="space-y-1">
                                <Label for="modal-prod-min-stock" class="text-xs font-semibold">Stock Mínimo</Label>
                                <Input
                                    id="modal-prod-min-stock"
                                    type="number"
                                    min="0"
                                    bind:value={form.min_stock}
                                    class="h-9 text-xs rounded-xl text-center"
                                />
                                {#if form.errors.min_stock}
                                    <p class="text-xs text-destructive">{form.errors.min_stock}</p>
                                {/if}
                            </div>

                            <div class="space-y-1">
                                <Label for="modal-prod-units-pack" class="text-xs font-semibold">Unidades / Bolsón</Label>
                                <Input
                                    id="modal-prod-units-pack"
                                    type="number"
                                    min="1"
                                    bind:value={form.units_per_package}
                                    class="h-9 text-xs rounded-xl text-center font-bold"
                                />
                                {#if form.errors.units_per_package}
                                    <p class="text-xs text-destructive">{form.errors.units_per_package}</p>
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
                                class="w-full bg-background rounded-xl p-2.5 text-xs border border-input focus:ring-1 focus:ring-primary outline-none resize-none"
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

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border/80">
                        <Button type="button" variant="outline" size="sm" class="rounded-full px-5 text-xs" onclick={handleClose}>
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                            class="rounded-full px-6 text-xs font-semibold gap-1.5 min-w-36"
                        >
                            {#if form.processing}
                                <span>Guardando...</span>
                            {:else}
                                <Check class="size-3.5" />
                                <span>{mode === 'edit' ? 'Guardar Cambios' : 'Crear Producto'}</span>
                            {/if}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
{/if}
