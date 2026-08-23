<script lang="ts">
    import { Button } from '@/components/ui/button';
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
        open: boolean;
        products: ProductItem[];
    } = $props();

    const form = useForm({
        product_id: '',
        quantity: 1,
        reason: 'ingreso_manual',
        notes: '',
    });

    let selectedProduct = $derived(
        products.find((p) => p.id.toString() === $form.product_id.toString())
    );

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        $form.post('/tenant/stock/ingresos', {
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
                    <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <ArrowDownLeft class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-foreground">Registrar Ingreso de Stock</h2>
                        <p class="text-xs text-muted-foreground">Añade unidades al inventario de un producto.</p>
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
                    <label for="income-product" class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                        Producto *
                    </label>
                    <select
                        id="income-product"
                        bind:value={$form.product_id}
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                        required
                    >
                        <option value="" disabled>Selecciona un producto...</option>
                        {#each products as product}
                            <option value={product.id.toString()}>
                                {product.name} {product.sku ? `(${product.sku})` : ''} — Stock actual: {product.stock}
                            </option>
                        {/each}
                    </select>
                    {#if $form.errors.product_id}
                        <p class="mt-1 text-xs text-rose-500 font-medium">{$form.errors.product_id}</p>
                    {/if}
                </div>

                {#if selectedProduct}
                    <div class="p-3 rounded-lg bg-muted/40 border border-border/60 flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Stock actual registrado:</span>
                        <span class="font-bold text-foreground">{selectedProduct.stock} unidades</span>
                    </div>
                {/if}

                <!-- Cantidad a Ingresar -->
                <div>
                    <label for="income-qty" class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                        Cantidad a Ingresar *
                    </label>
                    <input
                        id="income-qty"
                        type="number"
                        min="1"
                        bind:value={$form.quantity}
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                        placeholder="Ej. 10"
                        required
                    />
                    {#if $form.errors.quantity}
                        <p class="mt-1 text-xs text-rose-500 font-medium">{$form.errors.quantity}</p>
                    {/if}
                </div>

                <!-- Motivo -->
                <div>
                    <label for="income-reason" class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                        Motivo / Concepto
                    </label>
                    <input
                        id="income-reason"
                        type="text"
                        bind:value={$form.reason}
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                        placeholder="Ej. Compra a proveedor, ajuste inicial..."
                    />
                    {#if $form.errors.reason}
                        <p class="mt-1 text-xs text-rose-500 font-medium">{$form.errors.reason}</p>
                    {/if}
                </div>

                <!-- Notas / Observaciones -->
                <div>
                    <label for="income-notes" class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                        Notas u Observaciones
                    </label>
                    <textarea
                        id="income-notes"
                        rows="3"
                        bind:value={$form.notes}
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs resize-none"
                        placeholder="Detalles adicionales, número de remito o factura..."
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
                        disabled={$form.processing || !$form.product_id}
                        class="text-xs font-semibold"
                    >
                        {#if $form.processing}
                            <Loader2 class="size-3.5 animate-spin mr-1.5" />
                            <span>Registrando...</span>
                        {:else}
                            <ArrowDownLeft class="size-3.5 mr-1.5" />
                            <span>Registrar Ingreso</span>
                        {/if}
                    </Button>
                </div>
            </form>
        </div>
    </div>
{/if}
