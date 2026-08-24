<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import Layers from 'lucide-svelte/icons/layers';
    import Package from 'lucide-svelte/icons/package';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import X from 'lucide-svelte/icons/x';

    export interface ProductOption {
        id: number;
        name: string;
        sku?: string | null;
        price?: string | number;
        cost_price?: string | number | null;
        stock?: number;
        units_per_package?: number;
    }

    export interface PackageComponent {
        product_id: number;
        product_name: string;
        quantity_in_package: number;
        unit_price: number;
        units_per_package: number;
    }

    export interface CreatedPackage {
        id: string;
        name: string;
        short_description: string;
        package_quantity: number;
        components: PackageComponent[];
        unit_price: number;
        subtotal: number;
    }

    let {
        open = $bindable(false),
        availableProducts = [],
        mode = 'customer', // 'customer' | 'supplier'
        onaddpackage,
    }: {
        open: boolean;
        availableProducts: ProductOption[];
        mode?: 'customer' | 'supplier';
        onaddpackage: (pkg: CreatedPackage) => void;
    } = $props();

    let searchTerm = $state('');
    let selectedProductId = $state<number | ''>('');
    let quantityInPack = $state(1);
    let packageQuantity = $state(1);
    let components = $state<PackageComponent[]>([]);
    let error = $state<string | null>(null);

    let filteredProducts = $derived(
        availableProducts.filter((p) =>
            p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (p.sku && p.sku.toLowerCase().includes(searchTerm.toLowerCase()))
        )
    );

    function addComponent() {
        error = null;
        if (!selectedProductId) {
            error = 'Debes seleccionar un producto para agregarlo al bolsón.';
            return;
        }

        const product = availableProducts.find((p) => p.id === selectedProductId);
        if (!product) return;

        const existingIndex = components.findIndex((c) => c.product_id === product.id);
        const unitPrice = mode === 'supplier'
            ? Number(product.cost_price || product.price || 0)
            : Number(product.price || 0);

        if (existingIndex >= 0) {
            components[existingIndex].quantity_in_package += Number(quantityInPack) || 1;
        } else {
            components = [
                ...components,
                {
                    product_id: product.id,
                    product_name: product.name,
                    quantity_in_package: Number(quantityInPack) || (product.units_per_package || 1),
                    unit_price: unitPrice,
                    units_per_package: product.units_per_package || 1,
                },
            ];
        }

        selectedProductId = '';
        quantityInPack = 1;
    }

    function removeComponent(index: number) {
        components = components.filter((_, i) => i !== index);
    }

    function formatCurrency(amount: number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(amount);
    }

    let singlePackageTotal = $derived(
        components.reduce((sum, c) => sum + c.quantity_in_package * c.unit_price, 0)
    );

    let overallTotal = $derived(singlePackageTotal * (Number(packageQuantity) || 1));

    function formatDescription(items: PackageComponent[]): string {
        const full = items
            .map((c) => `(${c.quantity_in_package}) ${c.product_name}`)
            .join(', ');

        if (full.length > 50) {
            return full.slice(0, 47) + '...';
        }
        return full;
    }

    function handleConfirm() {
        error = null;
        if (components.length === 0) {
            error = 'Debes agregar al menos un producto al bolsón.';
            return;
        }

        const shortDesc = formatDescription(components);
        const packageName = components.length === 1
            ? `Bolsón: ${components[0].product_name} (x${components[0].quantity_in_package}u)`
            : `Combo Bolsón (${components.length} productos)`;

        const created: CreatedPackage = {
            id: 'pkg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
            name: packageName,
            short_description: shortDesc,
            package_quantity: Number(packageQuantity) || 1,
            components: [...components],
            unit_price: singlePackageTotal,
            subtotal: overallTotal,
        };

        onaddpackage(created);
        handleClose();
    }

    function handleClose() {
        open = false;
        searchTerm = '';
        selectedProductId = '';
        quantityInPack = 1;
        packageQuantity = 1;
        components = [];
        error = null;
    }
</script>

{#if open}
    <!-- Overlay secundario z-60 para estar por encima del modal principal -->
    <div
        class="fixed inset-0 z-60 bg-black/70 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto animate-in fade-in duration-200"
        onclick={(e) => e.target === e.currentTarget && handleClose()}
        onkeydown={(e) => e.key === 'Escape' && handleClose()}
        role="button"
        tabindex="0"
        aria-label="Cerrar modal de bolsón"
    >
        <div class="bg-card text-card-foreground rounded-2xl border border-border shadow-2xl max-w-xl w-full p-6 space-y-5 my-8 relative">
            <!-- Header -->
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
                        <Package class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-foreground flex items-center gap-2">
                            Crear Bolsón o Combo de Productos
                        </h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Agrupa productos en un bolsón cerrado para descontar o ingresar stock por lote.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={handleClose}
                    class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                    aria-label="Cerrar"
                >
                    <X class="size-5" />
                </button>
            </div>

            {#if error}
                <div class="p-3 rounded-lg bg-destructive/10 text-destructive text-xs font-medium border border-destructive/20">
                    {error}
                </div>
            {/if}

            <!-- Selector y Buscador de Productos para el Bolsón -->
            <div class="space-y-3 p-3.5 bg-muted/20 rounded-xl border border-border/70">
                <span class="text-xs font-bold text-foreground uppercase tracking-wider flex items-center gap-1.5">
                    <Plus class="size-3.5 text-primary" />
                    Agregar producto al bolsón
                </span>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end">
                    <div class="sm:col-span-8 space-y-1">
                        <Label class="text-[11px] font-semibold text-muted-foreground">Producto</Label>
                        <select
                            bind:value={selectedProductId}
                            class="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary outline-none"
                        >
                            <option value="">Seleccionar del catálogo...</option>
                            {#each filteredProducts as prod}
                                <option value={prod.id}>
                                    {prod.name} ({formatCurrency(Number(mode === 'supplier' ? prod.cost_price || prod.price : prod.price))})
                                    {#if prod.stock !== undefined}- Stock: {prod.stock}u{/if}
                                </option>
                            {/each}
                        </select>
                    </div>

                    <div class="sm:col-span-4 space-y-1">
                        <Label class="text-[11px] font-semibold text-muted-foreground">Cant. en bolsón</Label>
                        <div class="flex items-center gap-1.5">
                            <Input
                                type="number"
                                min="1"
                                bind:value={quantityInPack}
                                class="h-9 text-xs text-center font-medium"
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                class="h-9 px-3 text-xs shrink-0 font-semibold"
                                onclick={addComponent}
                            >
                                +
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Componentes del Bolsón -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        Contenido del Bolsón ({components.length} {components.length === 1 ? 'ítem' : 'ítems'})
                    </span>
                    <span class="text-xs font-mono font-semibold text-foreground">
                        Unitario: {formatCurrency(singlePackageTotal)}
                    </span>
                </div>

                <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                    {#if components.length === 0}
                        <div class="p-6 text-center rounded-xl border border-dashed text-xs text-muted-foreground bg-muted/10">
                            Aún no agregaste productos a este bolsón. Selecciona productos arriba para armar el pack.
                        </div>
                    {:else}
                        {#each components as comp, index (comp.product_id)}
                            <div class="flex items-center justify-between p-2.5 rounded-xl border bg-card hover:bg-muted/10 transition-colors">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="size-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                        <Layers class="size-3.5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-foreground truncate max-w-xs">{comp.product_name}</p>
                                        <p class="text-[11px] text-muted-foreground">
                                            {comp.quantity_in_package} u. × {formatCurrency(comp.unit_price)}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs font-bold text-foreground">
                                        {formatCurrency(comp.quantity_in_package * comp.unit_price)}
                                    </span>
                                    <button
                                        type="button"
                                        onclick={() => removeComponent(index)}
                                        class="p-1 text-muted-foreground hover:text-destructive rounded transition-colors"
                                        aria-label="Eliminar del bolsón"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </div>
                        {/each}
                    {/if}
                </div>
            </div>

            <!-- Cantidad de Bolsones a Solicitar / Vender -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 bg-secondary/10 dark:bg-secondary/20 rounded-xl border border-secondary/20">
                <div>
                    <span class="text-xs font-bold text-foreground block">Cantidad de Bolsones a Encargar:</span>
                    <span class="text-[11px] text-muted-foreground">Multiplica los productos del pack por esta cantidad.</span>
                </div>
                <div class="flex items-center gap-2">
                    <Input
                        type="number"
                        min="1"
                        bind:value={packageQuantity}
                        class="h-9 w-20 text-xs text-center font-bold"
                    />
                    <span class="text-xs font-semibold text-muted-foreground">bolsón(es)</span>
                </div>
            </div>

            <!-- Previsualización de la Descripción Formateada -->
            {#if components.length > 0}
                <div class="p-2.5 rounded-lg bg-muted/40 border border-border/50 text-[11px] text-muted-foreground flex items-center justify-between gap-2">
                    <span class="font-semibold text-foreground shrink-0">Formato en pedido:</span>
                    <span class="font-mono truncate max-w-sm text-foreground/80">{formatDescription(components)}</span>
                </div>
            {/if}

            <!-- Footer con Total y Confirmar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t">
                <div>
                    <span class="text-xs text-muted-foreground block">Total del Bolsón / Combo:</span>
                    <span class="text-lg font-bold font-mono text-primary">{formatCurrency(overallTotal)}</span>
                </div>

                <div class="flex items-center gap-2 justify-end">
                    <Button type="button" variant="outline" size="sm" onclick={handleClose}>
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="font-semibold gap-1.5"
                        onclick={handleConfirm}
                        disabled={components.length === 0}
                    >
                        <Package class="size-3.5" />
                        Agregar Bolsón al Pedido
                    </Button>
                </div>
            </div>
        </div>
    </div>
{/if}
