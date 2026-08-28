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

    let selectedProduct = $derived(
        products.find((p) => String(p.id) === String(form.product_id))
    );

    let isQuantityExceeded = $derived(
        selectedProduct ? form.quantity > selectedProduct.stock : false
    );

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (isQuantityExceeded) return;

        form.post('/tenant/stock/egresos', {
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
    <DialogContent size="default" class="max-w-lg">
        <DialogHeader>
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                    <ArrowUpRight class="size-5" />
                </div>
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
                class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors shrink-0 -mr-1"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </DialogHeader>

        <DialogBody>
            <form id="create-outcome-form" onsubmit={handleSubmit} class="space-y-4">
                <!-- Selección de Producto -->
                <div class="space-y-1.5">
                    <Label for="outcome-product" class="text-xs font-semibold text-foreground">
                        Producto *
                    </Label>
                    <select
                        id="outcome-product"
                        bind:value={form.product_id}
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                        required
                    >
                        <option value="" disabled>Selecciona un producto...</option>
                        {#each products as product}
                            <option value={String(product.id)}>
                                {product.name} {product.sku ? `(${product.sku})` : ''} — Stock disponible: {product.stock}
                            </option>
                        {/each}
                    </select>
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
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs font-medium"
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
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs resize-none"
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
                size="sm"
                onclick={closeModal}
                disabled={form.processing}
                class="min-h-[40px] px-4 rounded-xl text-xs font-medium border-border/70"
            >
                Cancelar
            </Button>
            <Button
                type="submit"
                form="create-outcome-form"
                disabled={form.processing || !form.product_id || isQuantityExceeded}
                class="min-h-[40px] px-4 rounded-xl text-xs font-semibold bg-rose-600 hover:bg-rose-700 text-white min-w-32 shadow-xs"
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
