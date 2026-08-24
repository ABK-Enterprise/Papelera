<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router } from '@inertiajs/svelte';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Check from 'lucide-svelte/icons/check';
    import Edit3 from 'lucide-svelte/icons/edit-3';
    import Layers from 'lucide-svelte/icons/layers';
    import Minus from 'lucide-svelte/icons/minus';
    import Package from 'lucide-svelte/icons/package';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Truck from 'lucide-svelte/icons/truck';
    import X from 'lucide-svelte/icons/x';
    import CreatePackageModal, {
        type CreatedPackage,
        type PackageComponent,
    } from '@/pages/tenant/orders/CreatePackageModal.svelte';

    interface SupplierOption {
        id: number;
        name: string;
        contact_number?: string | null;
    }

    interface ProductOption {
        id: number;
        name: string;
        sku?: string | null;
        cost_price?: string | number | null;
        price?: string | number;
        stock?: number;
        units_per_package?: number;
    }

    interface SupplierOrderItemLine {
        id: string;
        is_package: boolean;
        product_id: number | null;
        product_name: string;
        quantity: number;
        unit_cost: number | '';
        search_query: string;
        is_dropdown_open: boolean;
        description?: string;
        components?: PackageComponent[];
    }

    let {
        open = $bindable(false),
        suppliers = [],
        availableProducts = [],
    }: {
        open: boolean;
        suppliers: SupplierOption[];
        availableProducts: ProductOption[];
    } = $props();

    let selectedSupplierId = $state<number | ''>('');
    let notes = $state('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    let isPackageModalOpen = $state(false);

    let items = $state<SupplierOrderItemLine[]>([
        {
            id: 'item_' + Date.now(),
            is_package: false,
            product_id: null,
            product_name: '',
            quantity: 1,
            unit_cost: '',
            search_query: '',
            is_dropdown_open: false,
        },
    ]);

    function addItem() {
        items = [
            ...items,
            {
                id: 'item_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                is_package: false,
                product_id: null,
                product_name: '',
                quantity: 1,
                unit_cost: '',
                search_query: '',
                is_dropdown_open: false,
            },
        ];
    }

    function handleAddPackage(pkg: CreatedPackage) {
        items = [
            ...items.filter((it) => it.product_name.trim() !== ''),
            {
                id: pkg.id,
                is_package: true,
                product_id: null,
                product_name: pkg.name,
                quantity: pkg.package_quantity,
                unit_cost: pkg.unit_price,
                search_query: pkg.name,
                is_dropdown_open: false,
                description: pkg.short_description,
                components: pkg.components,
            },
        ];
    }

    function removeItem(index: number) {
        if (items.length > 1) {
            items = items.filter((_, i) => i !== index);
        } else {
            items = [
                {
                    id: 'item_' + Date.now(),
                    is_package: false,
                    product_id: null,
                    product_name: '',
                    quantity: 1,
                    unit_cost: '',
                    search_query: '',
                    is_dropdown_open: false,
                },
            ];
        }
    }

    function incrementQuantity(index: number) {
        items[index].quantity += 1;
    }

    function decrementQuantity(index: number) {
        if (items[index].quantity > 1) {
            items[index].quantity -= 1;
        }
    }

    function getFilteredProducts(query: string): ProductOption[] {
        const q = query.trim().toLowerCase();
        if (!q) return availableProducts.slice(0, 5);
        return availableProducts.filter((p) => p.name.toLowerCase().includes(q));
    }

    function selectProduct(index: number, product: ProductOption) {
        items[index].product_id = product.id;
        items[index].product_name = product.name;
        items[index].search_query = product.name;
        if (product.cost_price && !items[index].unit_cost) {
            items[index].unit_cost = Number(product.cost_price);
        }
        items[index].is_dropdown_open = false;
    }

    function handleSearchInput(index: number, value: string) {
        items[index].search_query = value;
        items[index].product_name = value;
        items[index].product_id = null;
        items[index].is_dropdown_open = true;

        const match = availableProducts.find(
            (p) => p.name.toLowerCase().trim() === value.toLowerCase().trim()
        );
        if (match) {
            items[index].product_id = match.id;
            if (match.cost_price && !items[index].unit_cost) {
                items[index].unit_cost = Number(match.cost_price);
            }
        }
    }

    function closeAllDropdowns() {
        for (const it of items) {
            it.is_dropdown_open = false;
        }
    }

    function formatPrice(amount: number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(amount);
    }

    let computedTotal = $derived.by(() => {
        let total = 0;
        for (const it of items) {
            if (it.is_package && it.components) {
                const pkgCost = it.components.reduce(
                    (sum, c) => sum + c.quantity_in_package * c.unit_price,
                    0
                );
                total += it.quantity * pkgCost;
            } else if (it.product_name) {
                const cost = Number(it.unit_cost) || 0;
                total += it.quantity * cost;
            }
        }
        return total;
    });

    function resetForm() {
        selectedSupplierId = '';
        notes = '';
        errorMessage = null;
        items = [
            {
                id: 'item_' + Date.now(),
                is_package: false,
                product_id: null,
                product_name: '',
                quantity: 1,
                unit_cost: '',
                search_query: '',
                is_dropdown_open: false,
            },
        ];
    }

    function closeModal() {
        open = false;
        resetForm();
    }

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errorMessage = null;

        if (!selectedSupplierId) {
            errorMessage = 'Debes seleccionar un proveedor.';
            return;
        }

        const payloadItems: Array<{
            product_id: number | null;
            product_name: string;
            quantity: number;
            unit_cost: number;
        }> = [];

        // Flatten packages if any
        for (const it of items) {
            if (it.is_package && it.components) {
                for (const comp of it.components) {
                    payloadItems.push({
                        product_id: comp.product_id,
                        product_name: comp.product_name,
                        quantity: comp.quantity_in_package * it.quantity,
                        unit_cost: comp.unit_price,
                    });
                }
            } else if (it.product_name.trim() !== '') {
                payloadItems.push({
                    product_id: it.product_id,
                    product_name: it.product_name.trim(),
                    quantity: it.quantity,
                    unit_cost: it.unit_cost ? Number(it.unit_cost) : 0.0,
                });
            }
        }

        if (payloadItems.length === 0) {
            errorMessage = 'Debes ingresar al menos un producto para la orden de compra.';
            return;
        }

        isSubmitting = true;

        router.post(
            '/tenant/suppliers/orders',
            {
                supplier_id: selectedSupplierId,
                notes: notes || null,
                items: payloadItems,
            },
            {
                onSuccess: () => {
                    isSubmitting = false;
                    closeModal();
                },
                onError: (errors) => {
                    isSubmitting = false;
                    errorMessage = Object.values(errors).flat().join(' ') || 'Ocurrió un error al registrar la orden.';
                },
            }
        );
    }
</script>

<svelte:window onclick={closeAllDropdowns} />

{#if open}
    <div
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
        onclick={(e) => e.target === e.currentTarget && closeModal()}
        onkeydown={(e) => e.key === 'Escape' && closeModal()}
        role="button"
        tabindex="0"
        aria-label="Cerrar modal"
    >
        <div class="bg-card text-card-foreground rounded-2xl border border-border shadow-2xl max-w-4xl w-full p-4 sm:p-6 space-y-4 my-6 overflow-hidden flex flex-col max-h-[92vh] relative">
            <!-- Header Bento -->
            <div class="flex items-center justify-between border-b pb-3.5 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
                        <Truck class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-foreground tracking-tight">Hacer Pedido a Proveedor</h2>
                        <p class="text-xs text-muted-foreground">Genera una orden de compra con productos existentes o nuevos.</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={closeModal}
                    class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                    aria-label="Cerrar"
                >
                    <X class="size-5" />
                </button>
            </div>

            {#if errorMessage}
                <div class="p-3 rounded-xl bg-destructive/10 text-destructive text-xs font-medium border border-destructive/20 shrink-0">
                    {errorMessage}
                </div>
            {/if}

            <form onsubmit={handleSubmit} class="space-y-4 overflow-y-auto pr-1 flex-1">
                <!-- Bento Card 1: Proveedor Destinatario -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <Truck class="size-3.5 text-primary" />
                        Proveedor Destinatario
                    </h3>

                    <div class="space-y-1">
                        <Label for="order-supplier" class="text-xs font-semibold text-foreground">
                            Seleccionar Proveedor <span class="text-destructive">*</span>
                        </Label>
                        <select
                            id="order-supplier"
                            bind:value={selectedSupplierId}
                            class="h-9 w-full rounded-lg border border-input bg-background px-3 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary outline-none"
                            required
                        >
                            <option value="">Seleccionar proveedor de la lista...</option>
                            {#each suppliers as sup}
                                <option value={sup.id}>{sup.name} {sup.contact_number ? `(${sup.contact_number})` : ''}</option>
                            {/each}
                        </select>
                    </div>
                </div>

                <!-- Bento Card 2: Productos & Bolsones -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3.5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary" />
                            Productos a Solicitar
                        </h3>

                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                class="h-8 text-xs gap-1 font-semibold"
                                onclick={addItem}
                            >
                                <Plus class="size-3.5" />
                                Añadir producto
                            </Button>

                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="h-8 text-xs gap-1.5 font-semibold text-primary border-primary/30 bg-primary/5 hover:bg-primary/10"
                                onclick={() => (isPackageModalOpen = true)}
                            >
                                <Package class="size-3.5" />
                                Crear Bolsón
                            </Button>
                        </div>
                    </div>

                    <!-- Lista de Productos -->
                    <div class="space-y-2.5 max-h-64 overflow-y-auto pr-1">
                        {#each items as item, index (item.id || index)}
                            {#if item.is_package}
                                <!-- Card de Bolsón / Combo -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="size-9 rounded-lg bg-secondary/15 text-secondary flex items-center justify-center shrink-0 border border-secondary/20">
                                            <Package class="size-4.5" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-secondary/15 text-secondary border border-secondary/30">
                                                    BOLSÓN COMBO
                                                </span>
                                                <p class="text-xs font-bold text-foreground truncate max-w-xs sm:max-w-md">
                                                    {item.description || item.product_name}
                                                </p>
                                            </div>
                                            <p class="text-[11px] text-muted-foreground mt-0.5 font-mono">
                                                {formatPrice(Number(item.unit_cost) || 0)} costo unitario
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between sm:justify-end gap-3 w-full sm:w-auto pt-2 sm:pt-0 border-t sm:border-t-0">
                                        <!-- Stepper -->
                                        <div class="flex items-center border rounded-lg bg-background shadow-2xs">
                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-l-lg transition-colors"
                                                onclick={() => decrementQuantity(index)}
                                                aria-label="Disminuir cantidad"
                                            >
                                                <Minus class="size-3" />
                                            </button>
                                            <span class="px-3 text-xs font-bold font-mono min-w-8 text-center">
                                                {item.quantity}
                                            </span>
                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-r-lg transition-colors"
                                                onclick={() => incrementQuantity(index)}
                                                aria-label="Aumentar cantidad"
                                            >
                                                <Plus class="size-3" />
                                            </button>
                                        </div>

                                        <span class="font-mono text-xs font-bold text-foreground w-24 text-right">
                                            {formatPrice(item.quantity * (Number(item.unit_cost) || 0))}
                                        </span>

                                        <button
                                            type="button"
                                            class="p-1.5 text-muted-foreground hover:text-destructive rounded transition-colors"
                                            onclick={() => removeItem(index)}
                                            aria-label="Eliminar bolsón"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            {:else}
                                <!-- Card de Producto Individual con Autocomplete inteligente -->
                                {@const filtered = getFilteredProducts(item.search_query)}
                                {@const hasExactMatch = Boolean(item.product_id)}

                                <div
                                    class="flex flex-col gap-2 p-3 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs relative"
                                    onclick={(e) => e.stopPropagation()}
                                >
                                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                                        <!-- Input Buscador on-the-fly -->
                                        <div class="relative flex-1 w-full">
                                            <Input
                                                type="text"
                                                value={item.search_query}
                                                oninput={(e) => handleSearchInput(index, (e.target as HTMLInputElement).value)}
                                                onfocus={() => {
                                                    closeAllDropdowns();
                                                    item.is_dropdown_open = true;
                                                }}
                                                placeholder="Escribe el nombre del producto..."
                                                class="h-9 text-xs pr-20 font-medium"
                                                required
                                            />
                                            {#if hasExactMatch}
                                                <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-600 bg-emerald-500/15 px-1.5 py-0.2 rounded pointer-events-none">
                                                    En catálogo
                                                </span>
                                            {/if}

                                            <!-- Dropdown flotante z-50 -->
                                            {#if item.is_dropdown_open && item.search_query.trim() !== ''}
                                                <div class="absolute left-0 right-0 top-full mt-1.5 z-50 rounded-xl border border-border/80 bg-popover text-popover-foreground shadow-2xl overflow-y-auto max-h-[185px] [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                                    {#each filtered as prod}
                                                        <button
                                                            type="button"
                                                            class="w-full px-3 py-2 text-left text-xs hover:bg-muted transition-colors flex items-center justify-between border-b last:border-b-0"
                                                            onclick={() => selectProduct(index, prod)}
                                                        >
                                                            <span class="font-medium text-foreground">{prod.name}</span>
                                                            <span class="text-[10px] text-muted-foreground font-mono">
                                                                {prod.cost_price ? `Costo: ${formatPrice(Number(prod.cost_price))}` : 'Sin costo'}
                                                            </span>
                                                        </button>
                                                    {/each}

                                                    {#if filtered.length === 0}
                                                        <div class="p-2.5 text-xs text-amber-600 dark:text-amber-400 bg-amber-500/10 flex items-center gap-2">
                                                            <AlertCircle class="size-3.5 shrink-0" />
                                                            <span>No hay coincidencias, se creará al confirmar la orden.</span>
                                                        </div>
                                                    {/if}
                                                </div>
                                            {/if}
                                        </div>

                                        <!-- Cantidad y Costo -->
                                        <div class="flex items-center justify-between sm:justify-end gap-2 w-full sm:w-auto">
                                            <div class="flex items-center gap-1">
                                                <span class="text-[11px] text-muted-foreground sm:hidden">Cant:</span>
                                                <Input
                                                    type="number"
                                                    min="1"
                                                    bind:value={item.quantity}
                                                    placeholder="Cant"
                                                    class="h-9 w-18 text-xs text-center font-bold"
                                                    required
                                                />
                                            </div>

                                            <div class="flex items-center gap-1">
                                                <span class="text-[11px] text-muted-foreground sm:hidden">Costo:</span>
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    bind:value={item.unit_cost}
                                                    placeholder="Costo ($)"
                                                    class="h-9 w-24 text-xs text-right font-mono"
                                                />
                                            </div>

                                            <span class="font-mono text-xs font-bold text-foreground w-20 text-right">
                                                {formatPrice(item.quantity * (Number(item.unit_cost) || 0))}
                                            </span>

                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-destructive rounded transition-colors"
                                                onclick={() => removeItem(index)}
                                                aria-label="Eliminar producto"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            {/if}
                        {/each}
                    </div>

                    <!-- Total Card Footer -->
                    <div class="flex justify-end items-center gap-2 pt-2 border-t border-border/50">
                        <span class="text-xs text-muted-foreground font-medium">Costo Total:</span>
                        <span class="text-lg font-bold font-mono text-primary">{formatPrice(computedTotal)}</span>
                    </div>
                </div>

                <!-- Bento Card 3: Notas u Observaciones -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-2">
                    <Label for="order-notes" class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <Edit3 class="size-3.5 text-primary" />
                        Notas u Observaciones para el Proveedor
                    </Label>
                    <textarea
                        id="order-notes"
                        bind:value={notes}
                        rows="2"
                        placeholder="ej. Solicitar factura tipo A, entrega en depósito..."
                        class="w-full bg-background text-foreground text-xs rounded-lg px-3 py-2 outline-none border border-input focus:ring-1 focus:ring-primary transition-colors resize-none"
                    ></textarea>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t shrink-0">
                    <Button type="button" variant="outline" size="sm" onclick={closeModal} disabled={isSubmitting}>
                        Cancelar
                    </Button>
                    <Button type="submit" size="sm" disabled={isSubmitting} class="font-semibold gap-1.5 min-w-36">
                        {#if isSubmitting}
                            <span>Guardando...</span>
                        {:else}
                            <span>Hacer Pedido</span>
                            <Check class="size-3.5" />
                        {/if}
                    </Button>
                </div>
            </form>
        </div>
    </div>
{/if}

<!-- Modal Secundario Crear Bolsón -->
<CreatePackageModal
    bind:open={isPackageModalOpen}
    {availableProducts}
    mode="supplier"
    onaddpackage={handleAddPackage}
/>
