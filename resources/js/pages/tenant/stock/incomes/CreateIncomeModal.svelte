<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
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

    let selectedProduct = $derived(
        products.find((p) => String(p.id) === String(form.product_id))
    );

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        form.post('/tenant/stock/ingresos', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                open = false;
            },
        });
    }

    function closeModal() {
        if (!form.processing) {
            form.reset();
            form.clearErrors();
            open = false;
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-lg p-6">
        <div class="flex items-center justify-between pb-4 border-b border-border">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <ArrowDownLeft class="size-5" />
                </div>
                <div>
                    <DialogTitle class="text-lg font-bold text-foreground">Registrar Ingreso de Stock</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Añade unidades al inventario de un producto de tu comercio.
                    </DialogDescription>
                </div>
            </div>
            <button
                type="button"
                onclick={closeModal}
                class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </div>

        <form onsubmit={handleSubmit} class="space-y-4 pt-3">
            <!-- Selección de Producto -->
            <div class="space-y-1.5">
                <Label for="income-product" class="text-xs font-semibold text-foreground">
                    Producto *
                </Label>
                <select
                    id="income-product"
                    bind:value={form.product_id}
                    class="w-full rounded-lg border border-border bg-background px-3.5 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                    required
                >
                    <option value="" disabled>Selecciona un producto...</option>
                    {#each products as product}
                        <option value={String(product.id)}>
                            {product.name} {product.sku ? `(${product.sku})` : ''} — Stock actual: {product.stock}
                        </option>
                    {/each}
                </select>
                {#if form.errors.product_id}
                    <p class="text-xs text-rose-500 font-medium">{form.errors.product_id}</p>
                {/if}
            </div>

            {#if selectedProduct}
                <div class="p-3 rounded-lg bg-muted/40 border border-border/60 flex items-center justify-between text-xs">
                    <span class="text-muted-foreground">Stock actual registrado:</span>
                    <span class="font-bold text-foreground">{selectedProduct.stock} unidades</span>
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
                    class="text-sm shadow-2xs"
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

            <DialogFooter class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-4 border-t border-border">
                <Button
                    type="button"
                    variant="outline"
                    onclick={closeModal}
                    disabled={form.processing}
                    class="text-xs"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    disabled={form.processing || !form.product_id}
                    class="text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white min-w-32"
                >
                    {#if form.processing}
                        <Loader2 class="size-3.5 animate-spin mr-1.5" />
                        <span>Registrando...</span>
                    {:else}
                        <ArrowDownLeft class="size-3.5 mr-1.5" />
                        <span>Registrar Ingreso</span>
                    {/if}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>
