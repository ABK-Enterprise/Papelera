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
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Loader2 from 'lucide-svelte/icons/loader-2';
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
        origin_warehouse_id: '',
        quantity: 1,
        reason: 'ingreso_manual',
        notes: '',
        allow_negative_origin: true,
    });

    $effect(() => {
        if (!form.warehouse_id && warehouses.length > 0) {
            const def = warehouses.find((w) => w.is_default) || warehouses[0];
            form.warehouse_id = String(def.id);
        }
        if (form.reason === 'transferencia_deposito' && (!form.origin_warehouse_id || form.origin_warehouse_id === form.warehouse_id) && warehouses.length > 1) {
            const diff = warehouses.find((w) => String(w.id) !== form.warehouse_id);
            if (diff) form.origin_warehouse_id = String(diff.id);
        }
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

    let availableOrigins = $derived(
        warehouses.filter((w) => String(w.id) !== form.warehouse_id)
    );

    let originWarehouse = $derived(
        warehouses.find((w) => String(w.id) === form.origin_warehouse_id)
    );

    let destWarehouse = $derived(
        warehouses.find((w) => String(w.id) === form.warehouse_id)
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
        const url = form.reason === 'transferencia_deposito'
            ? '/tenant/stock/transferencias'
            : '/tenant/stock/ingresos';

        const payload: Record<string, any> = {
            product_id: form.product_id,
            quantity: form.quantity,
            notes: form.notes,
        };

        if (form.reason === 'transferencia_deposito') {
            payload.origin_warehouse_id = form.origin_warehouse_id;
            payload.destination_warehouse_id = form.warehouse_id;
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
                class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer shrink-0"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </DialogHeader>


        <DialogBody>
            <form id="create-income-form" onsubmit={handleSubmit} class="space-y-4">
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

                <!-- Depósito Destino -->
                {#if warehouses.length > 0}
                    <div class="space-y-1.5">
                        <Label for="income-warehouse" class="text-sm font-medium text-foreground">
                            Depósito Destino *
                        </Label>
                        <select
                            id="income-warehouse"
                            bind:value={form.warehouse_id}
                            class="w-full rounded-xl border border-border/80 bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary shadow-2xs cursor-pointer min-h-[42px]"
                        >
                            {#each warehouses as wh (wh.id)}
                                <option value={String(wh.id)}>
                                    {wh.name} {wh.is_default ? '(Principal)' : ''}
                                </option>
                            {/each}
                        </select>
                        {#if form.errors.warehouse_id}
                            <p class="text-xs text-rose-500 font-medium">{form.errors.warehouse_id}</p>
                        {/if}
                    </div>
                {/if}

                <!-- Cantidad a Ingresar -->
                <div class="space-y-1.5">
                    <Label for="income-qty" class="text-sm font-medium text-foreground">
                        Cantidad a Ingresar *
                    </Label>
                    <Input
                        id="income-qty"
                        type="number"
                        min="1"
                        max="10000"
                        maxlength="5"
                        oninput={(e) => {
                            const val = (e.target as HTMLInputElement).value;
                            if (val.length > 5) (e.target as HTMLInputElement).value = val.slice(0, 5);
                            if (Number(val) > 10000) form.quantity = 10000;
                        }}
                        bind:value={form.quantity}
                        placeholder="Ej. 10"
                        required
                        class="text-sm shadow-2xs tabular-nums"
                    />
                    {#if form.errors.quantity}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.quantity}</p>
                    {/if}
                </div>

                <!-- Motivo del Ingreso -->
                <div class="space-y-1.5">
                    <Label for="income-reason" class="text-sm font-medium text-foreground">
                        Motivo / Concepto *
                    </Label>
                    <select
                        id="income-reason"
                        bind:value={form.reason}
                        class="w-full rounded-xl border border-border/80 bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary shadow-2xs cursor-pointer min-h-[42px]"
                    >
                        <option value="ingreso_manual">Ingreso manual / Ajuste inicial</option>
                        <option value="compra_proveedor">Compra de mercadería / Proveedor</option>
                        <option value="devolucion_cliente">Devolución de cliente</option>
                        <option value="transferencia_deposito">Transferencia desde otro depósito</option>
                    </select>
                    {#if form.errors.reason}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.reason}</p>
                    {/if}
                </div>

                <!-- Campos específicos si es Transferencia de Depósito -->
                {#if form.reason === 'transferencia_deposito'}
                    <!-- Depósito de Origen -->
                    <div class="space-y-1.5">
                        <Label for="income-origin-warehouse" class="text-sm font-medium text-foreground">
                            Depósito de Origen *
                        </Label>
                        <select
                            id="income-origin-warehouse"
                            bind:value={form.origin_warehouse_id}
                            disabled={availableOrigins.length === 0}
                            class="w-full rounded-xl border border-border/80 bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary shadow-2xs cursor-pointer min-h-[42px] disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            {#if availableOrigins.length === 0}
                                <option value="" disabled selected>No hay depósito origen</option>
                            {:else}
                                {#each availableOrigins as wh (wh.id)}
                                    <option value={String(wh.id)}>
                                        {wh.name} {wh.is_default ? '(Principal)' : ''}
                                    </option>
                                {/each}
                            {/if}
                        </select>
                        {#if form.errors.origin_warehouse_id}
                            <p class="text-xs text-rose-500 font-medium">{form.errors.origin_warehouse_id}</p>
                        {/if}
                    </div>

                    <!-- Indicador visual con flechas de dirección -->
                    {#if originWarehouse && destWarehouse}
                        <div class="p-3.5 rounded-xl bg-primary-500/5 border border-primary-500/20 flex items-center justify-between gap-2.5">
                            <div class="min-w-0 flex-1">
                                <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider block">Sale de</span>
                                <span class="text-xs sm:text-sm font-semibold text-foreground truncate block">{originWarehouse.name}</span>
                            </div>
                            <div class="flex flex-col items-center justify-center shrink-0 px-2">
                                <ArrowRight class="size-4 text-primary-500" />
                                <span class="text-xs font-bold text-primary-600 dark:text-primary-400 tabular-nums">
                                    {form.quantity || 0} u.
                                </span>
                            </div>
                            <div class="min-w-0 flex-1 text-right">
                                <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider block">Ingresa en</span>
                                <span class="text-xs sm:text-sm font-semibold text-foreground truncate block">{destWarehouse.name}</span>
                            </div>
                        </div>
                    {/if}
                {/if}

                <!-- Notas / Observaciones -->
                <div class="space-y-1.5">
                    <Label for="income-notes" class="text-sm font-medium text-foreground">
                        Notas u Observaciones
                    </Label>
                    <textarea
                        id="income-notes"
                        rows="3"
                        maxlength="255"
                        bind:value={form.notes}
                        class="w-full rounded-xl border border-border/80 bg-background px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary shadow-2xs resize-none"
                        placeholder="Detalles adicionales, número de remito o factura..."
                    ></textarea>

                    {#if form.errors.notes}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.notes}</p>
                    {/if}
                </div>
            </form>
        </DialogBody>

        <DialogFooter class="p-4 sm:p-6">
            <div class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:w-auto sm:justify-end sm:items-center">
                <Button
                    type="button"
                    variant="outline"
                    onclick={closeModal}
                    disabled={form.processing}
                    class="w-full sm:w-auto min-h-[44px] px-4 rounded-xl text-sm font-medium border-border/70 whitespace-nowrap cursor-pointer"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    form="create-income-form"
                    disabled={form.processing || !form.product_id}
                    class="w-full sm:w-auto min-h-[44px] px-4 rounded-xl text-sm font-medium bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs whitespace-nowrap cursor-pointer"
                >
                    {#if form.processing}
                        <Loader2 class="size-3.5 animate-spin mr-1.5 shrink-0" />
                        <span>Registrando...</span>
                    {:else}
                        <ArrowDownLeft class="size-3.5 mr-1.5 shrink-0" />
                        <span>Registrar ingreso</span>
                    {/if}
                </Button>
            </div>
        </DialogFooter>

    </DialogContent>
</Dialog>
