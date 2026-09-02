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
    import IconContainer from '@/components/common/IconContainer.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import { useForm } from '@inertiajs/svelte';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import X from 'lucide-svelte/icons/x';

    interface ProductItem {
        id: number;
        name: string;
        sku: string | null;
        stock: number;
    }

    let {
        open = $bindable(false),
        products = [],
    }: {
        open?: boolean;
        products: ProductItem[];
    } = $props();

    const form = useForm({
        product_id: '',
        quantity: 1,
        reason: 'devolucion',
        notes: '',
    });

    let productSearchQuery = $state('');

    let selectedProduct = $derived(
        products.find((p) => String(p.id) === String(form.product_id))
    );

    let isQuantityExceeded = $derived(
        selectedProduct ? form.quantity > selectedProduct.stock : false
    );

    let comboboxProducts = $derived.by(() => {
        const q = productSearchQuery.toLowerCase().trim();
        if (!q) return products.slice(0, 10);
        return products.filter(
            (p) => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q))
        );
    });

    function handleSelectProduct(product: ProductItem) {
        form.product_id = String(product.id);
        productSearchQuery = product.name;
    }

    function handleClearProduct() {
        form.product_id = '';
        productSearchQuery = '';
    }

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (isQuantityExceeded) return;

        form.post('/tenant/stock/egresos', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                productSearchQuery = '';
                open = false;
            },
        });
    }

    function closeModal() {
        if (!form.processing) {
            form.reset();
            form.clearErrors();
            productSearchQuery = '';
            open = false;
        }
    }
</script>

<Dialog bind:open>
    <DialogContent size="default" class="max-w-lg">
        <DialogHeader>
            <div class="flex items-center gap-2.5 min-w-0">
                <IconContainer size="md" variant="destructive" icon="solar:archive-up-minimalistic-bold-duotone" />
                <div class="min-w-0">
                    <DialogTitle class="text-base sm:text-lg font-semibold text-foreground leading-snug">Registrar egreso de stock</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Registra salida o devolución de mercadería de tu inventario.
                    </DialogDescription>
                </div>
            </div>
            <button
                type="button"
                onclick={closeModal}
                class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer shrink-0"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </DialogHeader>


        <DialogBody>
            <form id="create-outcome-form" onsubmit={handleSubmit} class="space-y-4">
                <!-- Selección de Producto con SearchCombobox -->
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold text-foreground">
                        Producto *
                    </Label>
                    <SearchCombobox
                        bind:value={productSearchQuery}
                        items={comboboxProducts}
                        placeholder="Buscar producto por nombre o SKU..."
                        showDropdown={true}
                        onsearch={(q) => (productSearchQuery = q)}
                        onselect={handleSelectProduct}
                        onclear={handleClearProduct}
                    >
                        {#snippet itemSnippet(product)}
                            <div class="flex items-center justify-between gap-3 w-full">
                                <div class="min-w-0">
                                    <span class="font-medium text-foreground truncate block">{product.name}</span>
                                    {#if product.sku}
                                        <span class="text-[11px] text-muted-foreground tabular-nums">SKU: {product.sku}</span>
                                    {/if}
                                </div>
                                <span class="text-xs tabular-nums shrink-0 {product.stock === 0 ? 'text-rose-500 font-semibold' : 'text-muted-foreground'}">
                                    Stock: <strong class="font-bold {product.stock === 0 ? 'text-rose-500' : 'text-foreground'} tabular-nums">{product.stock}</strong>
                                </span>
                            </div>
                        {/snippet}
                    </SearchCombobox>
                    {#if form.errors.product_id}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.product_id}</p>
                    {/if}
                </div>

                {#if selectedProduct}
                    <div class="p-3 rounded-xl bg-muted/40 border border-border/60 flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Stock disponible:</span>
                        <span class="font-semibold tabular-nums {selectedProduct.stock === 0 ? 'text-rose-500' : 'text-foreground'}">
                            {selectedProduct.stock} unidades
                        </span>
                    </div>
                {/if}

                <!-- Cantidad a Egresar -->
                <div class="space-y-1.5">
                    <Label for="outcome-qty" class="text-xs font-semibold text-foreground">
                        Cantidad a Egresar *
                    </Label>
                    <Input
                        id="outcome-qty"
                        type="number"
                        min="1"
                        max={selectedProduct ? selectedProduct.stock : undefined}
                        bind:value={form.quantity}
                        placeholder="Ej. 1"
                        required
                        class="text-sm shadow-2xs tabular-nums {isQuantityExceeded ? 'border-rose-500 focus-visible:ring-rose-500' : ''}"
                    />
                    {#if isQuantityExceeded}
                        <div class="mt-1.5 flex items-center gap-1 text-xs text-rose-500 font-medium">
                            <ShieldAlert class="size-3.5 shrink-0" />
                            <span>La cantidad supera el stock disponible (<span class="tabular-nums font-semibold">{selectedProduct?.stock}</span> unid.).</span>
                        </div>
                    {/if}
                    {#if form.errors.quantity}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.quantity}</p>
                    {/if}
                </div>

                <!-- Motivo de Egreso -->
                <div class="space-y-1.5">
                    <Label for="outcome-reason" class="text-xs font-semibold text-foreground">
                        Motivo del Egreso *
                    </Label>
                    <select
                        id="outcome-reason"
                        bind:value={form.reason}
                        class="w-full h-10 rounded-xl border border-border/80 bg-background px-3.5 py-2 text-sm text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary shadow-2xs font-medium"
                    >
                        <option value="devolucion">Devolución de productos</option>
                        <option value="merma">Merma / Producto Dañado</option>
                        <option value="ajuste_inventario">Ajuste de inventario</option>
                        <option value="vencimiento">Vencimiento</option>
                    </select>
                    {#if form.errors.reason}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.reason}</p>
                    {/if}
                </div>

                <!-- Notas / Observaciones -->
                <div class="space-y-1.5">
                    <Label for="outcome-notes" class="text-xs font-semibold text-foreground">
                        Notas u Observaciones
                    </Label>
                    <textarea
                        id="outcome-notes"
                        rows="3"
                        bind:value={form.notes}
                        class="w-full rounded-xl border border-border/80 bg-background px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary shadow-2xs resize-none"
                        placeholder="Motivo de la devolución del cliente, estado del producto..."
                    ></textarea>

                    {#if form.errors.notes}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.notes}</p>
                    {/if}
                </div>
            </form>
        </DialogBody>

        <DialogFooter>
            <Button
                type="button"
                variant="outline"
                onclick={closeModal}
                disabled={form.processing}
                class="min-h-[44px] px-4 rounded-xl text-sm font-semibold border-border/70"
            >
                Cancelar
            </Button>
            <Button
                type="submit"
                form="create-outcome-form"
                disabled={form.processing || !form.product_id || isQuantityExceeded}
                class="min-h-[44px] px-4 rounded-xl text-sm font-semibold bg-rose-600 hover:bg-rose-700 text-white min-w-32 shadow-xs"
            >
                {#if form.processing}
                    <Loader2 class="size-3.5 animate-spin mr-1.5" />
                    <span>Procesando...</span>
                {:else}
                    <ArrowUpRight class="size-3.5 mr-1.5" />
                    <span>Registrar egreso</span>
                {/if}
            </Button>
        </DialogFooter>

    </DialogContent>
</Dialog>
