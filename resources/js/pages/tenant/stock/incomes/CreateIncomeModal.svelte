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
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import Loader2 from 'lucide-svelte/icons/loader-2';
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
        reason: 'ingreso_manual',
        notes: '',
    });

    let productSearchQuery = $state('');

    let selectedProduct = $derived(
        products.find((p) => String(p.id) === String(form.product_id))
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
        form.post('/tenant/stock/ingresos', {
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
                <IconContainer size="md" variant="success" icon="solar:archive-down-minimalistic-bold-duotone" />
                <div class="min-w-0">
                    <DialogTitle class="text-base sm:text-lg font-semibold text-foreground leading-snug">Registrar ingreso de stock</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Añade unidades al inventario de un producto de tu comercio.
                    </DialogDescription>
                </div>
            </div>
            <button
                type="button"
                onclick={closeModal}
                class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors shrink-0 -mr-1"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </DialogHeader>

        <DialogBody>
            <form id="create-income-form" onsubmit={handleSubmit} class="space-y-4">
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
                                <span class="text-xs text-muted-foreground tabular-nums shrink-0">
                                    Stock: <strong class="font-bold text-foreground tabular-nums">{product.stock}</strong>
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
                        <span class="text-muted-foreground">Stock actual registrado:</span>
                        <span class="font-semibold text-foreground tabular-nums">{selectedProduct.stock} unidades</span>
                    </div>
                {/if}

                <!-- Cantidad a Ingresar -->
                <div class="space-y-1.5">
                    <Label for="income-qty" class="text-xs font-semibold text-foreground">
                        Cantidad a Ingresar *
                    </Label>
                    <Input
                        id="income-qty"
                        type="number"
                        min="1"
                        bind:value={form.quantity}
                        placeholder="Ej. 10"
                        required
                        class="text-sm shadow-2xs tabular-nums"
                    />
                    {#if form.errors.quantity}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.quantity}</p>
                    {/if}
                </div>

                <!-- Motivo -->
                <div class="space-y-1.5">
                    <Label for="income-reason" class="text-xs font-semibold text-foreground">
                        Motivo / Concepto
                    </Label>
                    <Input
                        id="income-reason"
                        type="text"
                        bind:value={form.reason}
                        placeholder="Ej. Compra a proveedor, ajuste inicial..."
                        class="text-sm shadow-2xs"
                    />
                    {#if form.errors.reason}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.reason}</p>
                    {/if}
                </div>

                <!-- Notas / Observaciones -->
                <div class="space-y-1.5">
                    <Label for="income-notes" class="text-xs font-semibold text-foreground">
                        Notas u Observaciones
                    </Label>
                    <textarea
                        id="income-notes"
                        rows="3"
                        bind:value={form.notes}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs resize-none"
                        placeholder="Detalles adicionales, número de remito o factura..."
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
                size="sm"
                onclick={closeModal}
                disabled={form.processing}
                class="min-h-[40px] px-4 rounded-xl text-xs font-medium border-border/70"
            >
                Cancelar
            </Button>
            <Button
                type="submit"
                form="create-income-form"
                disabled={form.processing || !form.product_id}
                class="min-h-[40px] px-4 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white min-w-32 shadow-xs"
            >
                {#if form.processing}
                    <Loader2 class="size-3.5 animate-spin mr-1.5" />
                    <span>Registrando...</span>
                {:else}
                    <ArrowDownLeft class="size-3.5 mr-1.5" />
                    <span>Registrar ingreso</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
