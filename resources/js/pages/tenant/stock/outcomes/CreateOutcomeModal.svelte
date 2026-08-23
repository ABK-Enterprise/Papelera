<script lang="ts">
    import { Button } from '@/components/ui/button';
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
        open: boolean;
        products: ProductItem[];
    } = $props();

    const form = useForm({
        product_id: '',
        quantity: 1,
        reason: 'devolucion_cliente',
        notes: '',
    });

    let selectedProduct = $derived(
        products.find((p) => p.id.toString() === $form.product_id.toString())
    );

    let isQuantityExceeded = $derived(
        selectedProduct ? $form.quantity > selectedProduct.stock : false
    );

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (isQuantityExceeded) return;

        $form.post('/tenant/stock/egresos', {
            preserveScroll: true,
            onSuccess: () => {
                $form.reset();
                open = false;
            },
        });
    }

    function closeModal() {
        if (!$form.processing) {
            $form.reset();
            $form.clearErrors();
            open = false;
        }
    }
</script>

{#if open}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm animate-in fade-in-0"
        role="dialog"
        aria-modal="true"
    >
        <div
            class="relative w-full max-w-lg rounded-2xl border border-border/80 bg-card p-6 shadow-2xl transition-all"
        >
            <div class="flex items-center justify-between pb-4 border-b border-border/60">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                        <ArrowUpRight class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-foreground">Registrar Egreso / Devolución</h2>
                        <p class="text-xs text-muted-foreground">Registra salida de mercadería por devolución de producto.</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={closeModal}
                    class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                >
                    <X class="size-4" />
                </button>
            </div>

            <form onsubmit={handleSubmit} class="mt-4 space-y-4">
                <!-- Selección de Producto -->
                <div>
                    <label for="outcome-product" class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                        Producto *
                    </label>
                    <select
                        id="outcome-product"
                        bind:value={$form.product_id}
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                        required
                    >
                        <option value="" disabled>Selecciona un producto...</option>
                        {#each products as product}
                            <option value={product.id.toString()}>
                                {product.name} {product.sku ? `(${product.sku})` : ''} — Stock disponible: {product.stock}
                            </option>
                        {/each}
                    </select>
                    {#if $form.errors.product_id}
                        <p class="mt-1 text-xs text-rose-500 font-medium">{$form.errors.product_id}</p>
                    {/if}
                </div>

                {#if selectedProduct}
                    <div class="p-3 rounded-lg bg-muted/40 border border-border/60 flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Stock actual disponible:</span>
                        <span class="font-bold {selectedProduct.stock === 0 ? 'text-rose-500' : 'text-foreground'}">
                            {selectedProduct.stock} unidades
                        </span>
                    </div>
                {/if}

                <!-- Cantidad a Egresar -->
                <div>
                    <label for="outcome-qty" class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                        Cantidad a Egresar *
                    </label>
                    <input
                        id="outcome-qty"
                        type="number"
                        min="1"
                        max={selectedProduct ? selectedProduct.stock : undefined}
                        bind:value={$form.quantity}
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs {isQuantityExceeded ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500' : ''}"
                        placeholder="Ej. 1"
                        required
                    />
                    {#if isQuantityExceeded}
                        <div class="mt-1.5 flex items-center gap-1 text-xs text-rose-500 font-medium">
                            <ShieldAlert class="size-3.5" />
                            <span>La cantidad supera el stock disponible ({selectedProduct?.stock} unid.). No se permite stock negativo.</span>
                        </div>
                    {/if}
                    {#if $form.errors.quantity}
                        <p class="mt-1 text-xs text-rose-500 font-medium">{$form.errors.quantity}</p>
                    {/if}
                </div>

                <!-- Motivo (fijo de momento: Devolución de productos) -->
                <div>
                    <label for="outcome-reason" class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                        Motivo del Egreso
                    </label>
                    <input
                        id="outcome-reason"
                        type="text"
                        value="Devolución de productos"
                        disabled
                        class="w-full rounded-lg border border-border bg-muted/50 px-3.5 py-2.5 text-sm text-foreground font-medium cursor-not-allowed shadow-2xs"
                    />
                    <input type="hidden" bind:value={$form.reason} />
                </div>

                <!-- Notas / Observaciones -->
                <div>
                    <label for="outcome-notes" class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                        Notas u Observaciones
                    </label>
                    <textarea
                        id="outcome-notes"
                        rows="3"
                        bind:value={$form.notes}
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs resize-none"
                        placeholder="Motivo de la devolución del cliente, estado del producto..."
                    ></textarea>
                    {#if $form.errors.notes}
                        <p class="mt-1 text-xs text-rose-500 font-medium">{$form.errors.notes}</p>
                    {/if}
                </div>

                <!-- Botones de Acción -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-border/60">
                    <Button
                        type="button"
                        variant="outline"
                        onclick={closeModal}
                        disabled={$form.processing}
                        class="text-xs"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        disabled={$form.processing || !$form.product_id || isQuantityExceeded}
                        class="text-xs font-semibold bg-rose-600 hover:bg-rose-700 text-white"
                    >
                        {#if $form.processing}
                            <Loader2 class="size-3.5 animate-spin mr-1.5" />
                            <span>Procesando...</span>
                        {:else}
                            <ArrowUpRight class="size-3.5 mr-1.5" />
                            <span>Registrar Egreso</span>
                        {/if}
                    </Button>
                </div>
            </form>
        </div>
    </div>
{/if}
