<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router } from '@inertiajs/svelte';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Package from 'lucide-svelte/icons/package';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Truck from 'lucide-svelte/icons/truck';
    import X from 'lucide-svelte/icons/x';

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
        stock?: number;
    }

    interface SupplierOrderItemInput {
        product_id: number | null;
        product_name: string;
        quantity: number;
        unit_cost: number | '';
        search_query: string;
        is_dropdown_open: boolean;
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

    let items = $state<SupplierOrderItemInput[]>([
        { product_id: null, product_name: '', quantity: 1, unit_cost: '', search_query: '', is_dropdown_open: false },
    ]);

    function addItem() {
        items = [
            ...items,
            { product_id: null, product_name: '', quantity: 1, unit_cost: '', search_query: '', is_dropdown_open: false },
        ];
    }

    function removeItem(index: number) {
        if (items.length > 1) {
            items = items.filter((_, i) => i !== index);
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

        // Auto-match exact name
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
            if (!it.product_name) continue;
            const cost = Number(it.unit_cost) || 0;
            total += it.quantity * cost;
        }
        return total;
    });

    function resetForm() {
        selectedSupplierId = '';
        notes = '';
        errorMessage = null;
        items = [{ product_id: null, product_name: '', quantity: 1, unit_cost: '', search_query: '', is_dropdown_open: false }];
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

        const validItems = items.filter((it) => it.product_name.trim() !== '');
        if (validItems.length === 0) {
            errorMessage = 'Debes ingresar al menos un producto para la orden de compra.';
            return;
        }

        isSubmitting = true;

        router.post(
            '/tenant/suppliers/orders',
            {
                supplier_id: selectedSupplierId,
                notes: notes || null,
                items: validItems.map((it) => ({
                    product_id: it.product_id,
                    product_name: it.product_name.trim(),
                    quantity: it.quantity,
                    unit_cost: it.unit_cost ? Number(it.unit_cost) : 0.00,
                })),
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

{#if open}
    <div
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto"
        onclick={(e) => e.target === e.currentTarget && closeModal()}
        onkeydown={(e) => e.key === 'Escape' && closeModal()}
        role="button"
        tabindex="0"
        aria-label="Cerrar modal"
    >
        <div class="bg-card text-card-foreground rounded-2xl border border-border shadow-2xl max-w-2xl w-full p-6 space-y-5 my-8">
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Truck class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-foreground">Hacer Pedido a Proveedor</h2>
                        <p class="text-xs text-muted-foreground">Genera una orden de compra con productos existentes o nuevos.</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={closeModal}
                    class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Cerrar"
                >
                    <X class="size-5" />
                </button>
            </div>

            {#if errorMessage}
                <div class="p-3 rounded-lg bg-destructive/10 text-destructive text-xs font-medium border border-destructive/20">
                    {errorMessage}
                </div>
            {/if}

            <form onsubmit={handleSubmit} class="space-y-4">
                <!-- Selector de Proveedor -->
                <div class="space-y-1.5">
                    <Label for="order-supplier" class="text-xs font-semibold">Proveedor Destinatario *</Label>
                    <select
                        id="order-supplier"
                        bind:value={selectedSupplierId}
                        class="h-9 w-full rounded-lg border border-input bg-background px-3 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary"
                        required
                    >
                        <option value="">Seleccionar proveedor...</option>
                        {#each suppliers as sup}
                            <option value={sup.id}>{sup.name}</option>
                        {/each}
                    </select>
                </div>

                <!-- Líneas de Productos con Autocomplete inteligente -->
                <div class="space-y-2.5 pt-2 border-t">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Productos a Solicitar</span>
                        <Button type="button" size="sm" variant="outline" class="h-7 text-xs gap-1" onclick={addItem}>
                            <Plus class="size-3" />
                            Añadir producto
                        </Button>
                    </div>

                    <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                        {#each items as item, index (index)}
                            {@const filtered = getFilteredProducts(item.search_query)}
                            {@const hasExactMatch = Boolean(item.product_id)}

                            <div class="flex flex-col gap-1.5 p-3 rounded-xl border bg-muted/20 relative">
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                                    <!-- Input de Buscador / Creación on-the-fly -->
                                    <div class="relative flex-1 w-full">
                                        <Input
                                            type="text"
                                            value={item.search_query}
                                            oninput={(e) => handleSearchInput(index, (e.target as HTMLInputElement).value)}
                                            onfocus={() => (item.is_dropdown_open = true)}
                                            placeholder="Escribe el nombre del producto..."
                                            class="h-9 text-xs pr-7 font-medium"
                                            required
                                        />
                                        {#if hasExactMatch}
                                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-bold text-emerald-600 bg-emerald-500/15 px-1.5 py-0.2 rounded">
                                                En catálogo
                                            </span>
                                        {/if}

                                        <!-- Menú desplegable de sugerencias -->
                                        {#if item.is_dropdown_open && item.search_query.trim() !== ''}
                                            <div class="absolute left-0 right-0 top-full mt-1 z-50 rounded-lg border bg-popover text-popover-foreground shadow-lg overflow-hidden max-h-44 overflow-y-auto">
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
                                                        <span>No hay coincidencias, si agregas ese producto se guardará en el sistema.</span>
                                                    </div>
                                                {/if}
                                            </div>
                                        {/if}
                                    </div>

                                    <!-- Cantidad y Costo -->
                                    <div class="flex items-center gap-2 w-full sm:w-auto">
                                        <div class="flex items-center gap-1">
                                            <span class="text-[11px] text-muted-foreground sm:hidden">Cant:</span>
                                            <Input
                                                type="number"
                                                min="1"
                                                bind:value={item.quantity}
                                                placeholder="Cant"
                                                class="h-9 w-20 text-xs text-center font-bold"
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

                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            class="size-8 text-muted-foreground hover:text-destructive shrink-0"
                                            disabled={items.length === 1}
                                            onclick={() => removeItem(index)}
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="space-y-1.5 pt-2 border-t">
                    <Label for="order-notes" class="text-xs font-semibold">Notas / Observaciones para el Proveedor</Label>
                    <textarea
                        id="order-notes"
                        bind:value={notes}
                        rows="2"
                        placeholder="ej. Solicitar factura tipo A, entrega en depósito..."
                        class="w-full rounded-lg border border-input bg-background p-2 text-xs placeholder:text-muted-foreground focus:ring-1 focus:ring-primary focus:outline-none"
                    ></textarea>
                </div>

                <!-- Footer y Total -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t">
                    <div>
                        <span class="text-xs text-muted-foreground block">Costo Total Estimado:</span>
                        <span class="text-xl font-bold font-mono text-primary">{formatPrice(computedTotal)}</span>
                    </div>

                    <div class="flex items-center gap-2 justify-end">
                        <Button type="button" variant="outline" size="sm" onclick={closeModal}>
                            Cancelar
                        </Button>
                        <Button type="submit" size="sm" disabled={isSubmitting} class="font-semibold">
                            {isSubmitting ? 'Guardando...' : 'Hacer Pedido'}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </div>
{/if}
