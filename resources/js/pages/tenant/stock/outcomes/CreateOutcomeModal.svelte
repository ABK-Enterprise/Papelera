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
    import Icon from '@iconify/svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import { useForm } from '@inertiajs/svelte';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import X from 'lucide-svelte/icons/x';

    interface WarehouseItem {
        id: number;
        name: string;
        code?: string | null;
        is_default?: boolean;
    }

    interface ProductItem {
        id: number;
        name: string;
        sku: string | null;
        stock: number;
    }

    let {
        open = $bindable(false),
        products = [],
        warehouses = [],
    }: {
        open?: boolean;
        products: ProductItem[];
        warehouses?: WarehouseItem[];
    } = $props();

    const form = useForm({
        product_id: '',
        warehouse_id: '',
        destination_warehouse_id: '',
        quantity: 1,
        reason: 'devolucion',
        notes: '',
        allow_negative_origin: true,
    });

    $effect(() => {
        if (!form.warehouse_id && warehouses.length > 0) {
            const def = warehouses.find((w) => w.is_default) || warehouses[0];
            form.warehouse_id = String(def.id);
        }
        if (form.reason === 'transferencia_deposito' && (!form.destination_warehouse_id || form.destination_warehouse_id === form.warehouse_id) && warehouses.length > 1) {
            const diff = warehouses.find((w) => String(w.id) !== form.warehouse_id);
            if (diff) form.destination_warehouse_id = String(diff.id);
        }
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

    let availableDestinations = $derived(
        warehouses.filter((w) => String(w.id) !== form.warehouse_id)
    );

    let originWarehouse = $derived(
        warehouses.find((w) => String(w.id) === form.warehouse_id)
    );

    let destinationWarehouse = $derived(
        warehouses.find((w) => String(w.id) === form.destination_warehouse_id)
    );

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
        if (isQuantityExceeded && form.reason !== 'transferencia_deposito') return;

        const url = form.reason === 'transferencia_deposito'
            ? '/tenant/stock/transferencias'
            : '/tenant/stock/egresos';

        const payload: Record<string, any> = {
            product_id: form.product_id,
            quantity: form.quantity,
            notes: form.notes,
        };

        if (form.reason === 'transferencia_deposito') {
            payload.origin_warehouse_id = form.warehouse_id;
            payload.destination_warehouse_id = form.destination_warehouse_id;
            payload.allow_negative_origin = true;
        } else {
            payload.warehouse_id = form.warehouse_id;
            payload.reason = form.reason;
        }

        form.transform(() => payload).post(url, {
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
        <DialogHeader class="pb-3 border-b border-border/60 shrink-0">
            <div class="flex items-center justify-between gap-2.5 sm:gap-3.5 w-full">
                <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 flex-1">
                    <div class="size-10 sm:size-12 rounded-xl bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/25 dark:border-rose-500/30 flex items-center justify-center shrink-0">
                        <Icon class="size-5 sm:size-6 text-rose-700 dark:text-rose-400" icon="solar:archive-up-minimalistic-bold-duotone" />
                    </div>
                    <div class="min-w-0 flex-1 flex items-center">
                        <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                            Registrar egreso de stock
                        </DialogTitle>
                        <DialogDescription class="sr-only">
                            Registra salida o devolución de mercadería de tu inventario.
                        </DialogDescription>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={closeModal}
                    class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted dark:hover:bg-secondary/60 hover:text-foreground transition-colors cursor-pointer shrink-0"
                    aria-label="Cerrar"
                >
                    <X class="size-4" />
                </button>
            </div>
        </DialogHeader>


        <DialogBody>
            <form id="create-outcome-form" onsubmit={handleSubmit} class="space-y-4">
                <!-- Selección de Producto con SearchCombobox -->
                <div class="space-y-1.5">
                    <Label class="text-sm font-medium text-foreground">
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
                                        <span class="text-xs text-muted-foreground tabular-nums">SKU: {product.sku}</span>
                                    {/if}
                                </div>
                                <span class="text-xs tabular-nums shrink-0 {product.stock === 0 ? 'text-destructive font-semibold' : 'text-muted-foreground'}">
                                    Stock: <strong class="font-bold {product.stock === 0 ? 'text-destructive' : 'text-foreground'} tabular-nums">{product.stock}</strong>
                                </span>
                            </div>
                        {/snippet}
                    </SearchCombobox>
                    {#if form.errors.product_id}
                        <p class="text-xs text-destructive font-medium">{form.errors.product_id}</p>
                    {/if}
                </div>

                {#if selectedProduct}
                    <div class="p-3 rounded-xl bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Stock disponible:</span>
                        <span class="font-semibold tabular-nums {selectedProduct.stock === 0 ? 'text-destructive' : 'text-foreground'}">
                            {selectedProduct.stock} unidades
                        </span>
                    </div>
                {/if}

                <!-- Depósito Origen -->
                {#if warehouses.length > 0}
                    <div class="space-y-1.5">
                        <Label for="outcome-warehouse" class="text-sm font-medium text-foreground">
                            Depósito Origen *
                        </Label>
                        <select
                            id="outcome-warehouse"
                            bind:value={form.warehouse_id}
                            class="w-full rounded-xl border border-input bg-field px-3 py-2 text-sm text-foreground focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary shadow-2xs cursor-pointer min-h-[42px]"
                        >
                            {#each warehouses as wh (wh.id)}
                                <option value={String(wh.id)}>
                                    {wh.name} {wh.is_default ? '(Principal)' : ''}
                                </option>
                            {/each}
                        </select>
                        {#if form.errors.warehouse_id}
                            <p class="text-xs text-destructive font-medium">{form.errors.warehouse_id}</p>
                        {/if}
                    </div>
                {/if}

                <!-- Cantidad a Egresar -->
                <div class="space-y-1.5">
                    <Label for="outcome-qty" class="text-sm font-medium text-foreground">
                        Cantidad a Egresar *
                    </Label>
                    <Input
                        id="outcome-qty"
                        type="number"
                        min="1"
                        max={selectedProduct ? Math.min(selectedProduct.stock, 10000) : 10000}
                        maxlength="5"
                        oninput={(e) => {
                            const val = (e.target as HTMLInputElement).value;
                            if (val.length > 5) (e.target as HTMLInputElement).value = val.slice(0, 5);
                            const max = selectedProduct ? Math.min(selectedProduct.stock, 10000) : 10000;
                            if (Number(val) > max) form.quantity = max;
                        }}
                        bind:value={form.quantity}
                        placeholder="Ej. 1"
                        required
                        class="text-sm shadow-2xs tabular-nums {isQuantityExceeded ? 'border-destructive focus-visible:ring-destructive' : ''}"
                    />
                    {#if isQuantityExceeded}
                        <div class="mt-1.5 flex items-center gap-1 text-xs text-destructive font-medium">
                            <ShieldAlert class="size-3.5 shrink-0" />
                            <span>La cantidad supera el stock disponible (<span class="tabular-nums font-semibold">{selectedProduct?.stock}</span> unid.).</span>
                        </div>
                    {/if}
                    {#if form.errors.quantity}
                        <p class="text-xs text-destructive font-medium">{form.errors.quantity}</p>
                    {/if}
                </div>

                <!-- Motivo de Egreso -->
                <div class="space-y-1.5">
                    <Label for="outcome-reason" class="text-sm font-medium text-foreground">
                        Motivo del Egreso *
                    </Label>
                    <select
                        id="outcome-reason"
                        bind:value={form.reason}
                        class="w-full rounded-xl border border-input bg-field px-3 py-2 text-sm text-foreground focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary shadow-2xs cursor-pointer min-h-[42px] font-medium"
                    >
                        <option value="devolucion">Devolución de productos</option>
                        <option value="merma">Merma / Producto Dañado</option>
                        <option value="ajuste_inventario">Ajuste de inventario</option>
                        <option value="vencimiento">Vencimiento</option>
                        <option value="transferencia_deposito">Transferencia hacia otro depósito</option>
                    </select>
                    {#if form.errors.reason}
                        <p class="text-xs text-destructive font-medium">{form.errors.reason}</p>
                    {/if}
                </div>

                <!-- Campos específicos si es Transferencia de Depósito -->
                {#if form.reason === 'transferencia_deposito'}
                    <!-- Depósito de Destino -->
                    <div class="space-y-1.5">
                        <Label for="outcome-dest-warehouse" class="text-sm font-medium text-foreground">
                            Depósito de Destino *
                        </Label>
                        <select
                            id="outcome-dest-warehouse"
                            bind:value={form.destination_warehouse_id}
                            disabled={availableDestinations.length === 0}
                            class="w-full rounded-xl border border-input bg-field px-3 py-2 text-sm text-foreground focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary shadow-2xs cursor-pointer min-h-[42px] disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            {#if availableDestinations.length === 0}
                                <option value="" disabled selected>No hay depósito destino</option>
                            {:else}
                                {#each availableDestinations as wh (wh.id)}
                                    <option value={String(wh.id)}>
                                        {wh.name} {wh.is_default ? '(Principal)' : ''}
                                    </option>
                                {/each}
                            {/if}
                        </select>
                        {#if form.errors.destination_warehouse_id}
                            <p class="text-xs text-destructive font-medium">{form.errors.destination_warehouse_id}</p>
                        {/if}
                    </div>

                    <!-- Indicador visual con flechas de dirección -->
                    {#if originWarehouse && destinationWarehouse}
                        <div class="p-3.5 rounded-xl bg-primary/10 dark:bg-primary/15 border border-primary/25 dark:border-primary/35 flex items-center justify-between gap-2.5">
                            <div class="min-w-0 flex-1">
                                <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider block">Sale de</span>
                                <span class="text-xs sm:text-sm font-semibold text-foreground truncate block">{originWarehouse.name}</span>
                            </div>
                            <div class="flex flex-col items-center justify-center shrink-0 px-2">
                                <ArrowRight class="size-4 text-primary" />
                                <span class="text-xs font-bold text-primary tabular-nums">
                                    {form.quantity || 0} u.
                                </span>
                            </div>
                            <div class="min-w-0 flex-1 text-right">
                                <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider block">Ingresa en</span>
                                <span class="text-xs sm:text-sm font-semibold text-foreground truncate block">{destinationWarehouse.name}</span>
                            </div>
                        </div>
                    {/if}
                {/if}

                <!-- Notas / Observaciones -->
                <div class="space-y-1.5">
                    <Label for="outcome-notes" class="text-sm font-medium text-foreground">
                        Notas u Observaciones
                    </Label>
                    <textarea
                        id="outcome-notes"
                        rows="3"
                        maxlength="255"
                        bind:value={form.notes}
                        class="w-full rounded-xl border border-input bg-field px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary shadow-2xs resize-none"
                        placeholder="Motivo de la devolución del cliente, estado del producto..."
                    ></textarea>

                    {#if form.errors.notes}
                        <p class="text-xs text-destructive font-medium">{form.errors.notes}</p>
                    {/if}
                </div>
            </form>
        </DialogBody>

        <DialogFooter class="p-4 sm:p-6">
            <div class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:w-auto sm:justify-end sm:items-center">
                <Button
                    type="button"
                    variant="secondary"
                    onclick={closeModal}
                    disabled={form.processing}
                    class="w-full sm:w-auto min-h-[44px] px-4 rounded-xl text-sm font-medium whitespace-nowrap cursor-pointer"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    form="create-outcome-form"
                    disabled={form.processing || !form.product_id || isQuantityExceeded}
                    class="w-full sm:w-auto min-h-[44px] px-4 rounded-xl text-sm font-medium bg-rose-700 hover:bg-rose-800 text-white shadow-xs whitespace-nowrap cursor-pointer"
                >
                    {#if form.processing}
                        <Loader2 class="size-3.5 animate-spin mr-1.5 shrink-0" />
                        <span>Procesando...</span>
                    {:else}
                        <ArrowUpRight class="size-3.5 mr-1.5 shrink-0" />
                        <span>Registrar egreso</span>
                    {/if}
                </Button>
            </div>
        </DialogFooter>

    </DialogContent>
</Dialog>
