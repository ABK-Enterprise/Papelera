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
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { useForm } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import ArrowLeftRight from 'lucide-svelte/icons/arrow-left-right';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import X from 'lucide-svelte/icons/x';

    export interface WarehouseItem {
        id: number;
        name: string;
        code?: string | null;
        is_default?: boolean;
    }

    export interface ProductItem {
        id: number;
        name: string;
        sku?: string | null;
        stock?: number;
    }

    let {
        open = $bindable(false),
        products = [],
        warehouses = [],
        preselectedProduct = null,
        preselectedOriginWarehouseId = null,
        preselectedStock = null,
    }: {
        open?: boolean;
        products: ProductItem[];
        warehouses?: WarehouseItem[];
        preselectedProduct?: ProductItem | null;
        preselectedOriginWarehouseId?: number | null;
        preselectedStock?: number | null;
    } = $props();

    const form = useForm({
        product_id: '',
        origin_warehouse_id: '',
        destination_warehouse_id: '',
        quantity: 1,
        notes: '',
        allow_negative_origin: true,
    });

    let productSearchQuery = $state('');
    let showConfirmNegativeStock = $state(false);

    $effect(() => {
        if (open) {
            if (preselectedProduct) {
                form.product_id = String(preselectedProduct.id);
                productSearchQuery = preselectedProduct.name;
            }
            if (preselectedOriginWarehouseId) {
                form.origin_warehouse_id = String(preselectedOriginWarehouseId);
            } else if (!form.origin_warehouse_id && warehouses.length > 0) {
                const def = warehouses.find((w) => w.is_default) || warehouses[0];
                form.origin_warehouse_id = String(def.id);
            }

            // Set destination to first warehouse that is not origin
            if (!form.destination_warehouse_id && warehouses.length > 1) {
                const diff = warehouses.find((w) => String(w.id) !== form.origin_warehouse_id);
                if (diff) {
                    form.destination_warehouse_id = String(diff.id);
                }
            }
        }
    });

    let selectedProduct = $derived(
        products.find((p) => String(p.id) === String(form.product_id)) || preselectedProduct
    );

    let comboboxProducts = $derived.by(() => {
        const q = productSearchQuery.toLowerCase().trim();
        if (!q) return products.slice(0, 10);
        return products.filter(
            (p) => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q))
        );
    });

    let availableDestinations = $derived(
        warehouses.filter((w) => String(w.id) !== form.origin_warehouse_id)
    );

    let originWarehouse = $derived(
        warehouses.find((w) => String(w.id) === form.origin_warehouse_id)
    );

    let destinationWarehouse = $derived(
        warehouses.find((w) => String(w.id) === form.destination_warehouse_id)
    );

    let isNegativeOriginWarning = $derived.by(() => {
        if (preselectedStock !== null && preselectedStock !== undefined) {
            return Number(form.quantity) > Number(preselectedStock);
        }
        return false;
    });

    function handleSelectProduct(product: ProductItem) {
        form.product_id = String(product.id);
        productSearchQuery = product.name;
    }

    function handleClearProduct() {
        form.product_id = '';
        productSearchQuery = '';
    }

    function onOriginChange(e: Event) {
        const newOrigin = (e.target as HTMLSelectElement).value;
        form.origin_warehouse_id = newOrigin;
        if (form.destination_warehouse_id === newOrigin) {
            const nextDest = warehouses.find((w) => String(w.id) !== newOrigin);
            form.destination_warehouse_id = nextDest ? String(nextDest.id) : '';
        }
    }

    function submitTransfer() {
        form.post('/tenant/stock/transferencias', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                productSearchQuery = '';
                showConfirmNegativeStock = false;
                open = false;
            },
        });
    }

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();

        if (isNegativeOriginWarning) {
            showConfirmNegativeStock = true;
            return;
        }

        submitTransfer();
    }

    function closeModal() {
        if (!form.processing) {
            form.reset();
            form.clearErrors();
            productSearchQuery = '';
            showConfirmNegativeStock = false;
            open = false;
        }
    }
</script>

<Dialog bind:open>
    <DialogContent size="default" class="max-w-xl">
        <DialogHeader>
            <div class="flex items-center gap-2.5 min-w-0">
                <IconContainer size="md" variant="primary" icon="solar:transfer-horizontal-bold-duotone" />
                <div class="min-w-0">
                    <DialogTitle class="text-base sm:text-lg font-semibold text-foreground leading-snug">
                        Transferir stock entre depósitos
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Mueve existencias físicas de un depósito a otro de forma atómica y trazable.
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
            <form id="transfer-stock-form" onsubmit={handleSubmit} class="space-y-4">
                <!-- Selección de Producto con SearchCombobox -->
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold text-foreground">
                        Producto a transferir *
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
                                {#if product.stock !== undefined}
                                    <span class="text-xs text-muted-foreground tabular-nums shrink-0">
                                        Total: <strong class="font-bold text-foreground tabular-nums">{product.stock}</strong>
                                    </span>
                                {/if}
                            </div>
                        {/snippet}
                    </SearchCombobox>
                    {#if form.errors.product_id}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.product_id}</p>
                    {/if}
                </div>

                <!-- Resumen visual del Producto -->
                {#if selectedProduct}
                    <div class="p-3 rounded-xl bg-muted/40 border border-border/60 flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Stock total en catálogo:</span>
                        <span class="font-semibold text-foreground tabular-nums">
                            {selectedProduct.stock ?? preselectedStock ?? 0} unidades
                        </span>
                    </div>
                {/if}

                <!-- Grilla de Depósitos: Origen y Destino -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 items-center">
                    <!-- Depósito Origen -->
                    <div class="space-y-1.5">
                        <Label for="transfer-origin" class="text-xs font-semibold text-foreground">
                            Depósito Origen *
                        </Label>
                        <select
                            id="transfer-origin"
                            value={form.origin_warehouse_id}
                            onchange={onOriginChange}
                            class="w-full rounded-xl border border-border/80 bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary shadow-2xs cursor-pointer min-h-[42px]"
                        >
                            {#each warehouses as wh (wh.id)}
                                <option value={String(wh.id)}>
                                    {wh.name} {wh.is_default ? '(Principal)' : ''}
                                </option>
                            {/each}
                        </select>
                        {#if form.errors.origin_warehouse_id}
                            <p class="text-xs text-rose-500 font-medium">{form.errors.origin_warehouse_id}</p>
                        {/if}
                    </div>

                    <!-- Depósito Destino -->
                    <div class="space-y-1.5">
                        <Label for="transfer-destination" class="text-xs font-semibold text-foreground">
                            Depósito Destino *
                        </Label>
                        <select
                            id="transfer-destination"
                            bind:value={form.destination_warehouse_id}
                            class="w-full rounded-xl border border-border/80 bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary shadow-2xs cursor-pointer min-h-[42px]"
                        >
                            {#each availableDestinations as wh (wh.id)}
                                <option value={String(wh.id)}>
                                    {wh.name} {wh.is_default ? '(Principal)' : ''}
                                </option>
                            {/each}
                        </select>
                        {#if form.errors.destination_warehouse_id}
                            <p class="text-xs text-rose-500 font-medium">{form.errors.destination_warehouse_id}</p>
                        {/if}
                    </div>
                </div>

                <!-- Tarjeta Visual de Trayecto -->
                {#if originWarehouse && destinationWarehouse}
                    <div class="p-3.5 rounded-xl bg-primary-500/5 border border-primary-500/20 flex items-center justify-between gap-2.5">
                        <div class="min-w-0 flex-1">
                            <span class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider block">Sale de</span>
                            <span class="text-xs sm:text-sm font-semibold text-foreground truncate block">{originWarehouse.name}</span>
                        </div>
                        <div class="flex flex-col items-center justify-center shrink-0 px-2">
                            <ArrowRight class="size-4 text-primary-500" />
                            <span class="text-[10px] font-bold text-primary-600 dark:text-primary-400 tabular-nums">
                                {form.quantity || 0} u.
                            </span>
                        </div>
                        <div class="min-w-0 flex-1 text-right">
                            <span class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider block">Ingresa en</span>
                            <span class="text-xs sm:text-sm font-semibold text-foreground truncate block">{destinationWarehouse.name}</span>
                        </div>
                    </div>
                {/if}

                <!-- Cantidad a Transferir -->
                <div class="space-y-1.5">
                    <Label for="transfer-qty" class="text-xs font-semibold text-foreground">
                        Cantidad a Transferir *
                    </Label>
                    <Input
                        id="transfer-qty"
                        type="number"
                        min="0.001"
                        max="10000"
                        step="any"
                        bind:value={form.quantity}
                        placeholder="Ej. 10"
                        required
                        class="text-sm shadow-2xs tabular-nums"
                    />
                    {#if form.errors.quantity}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.quantity}</p>
                    {/if}
                </div>

                <!-- Alerta de Stock Negativo en Origen -->
                {#if isNegativeOriginWarning}
                    <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-2.5 text-xs text-amber-700 dark:text-amber-400">
                        <AlertTriangle class="size-4 shrink-0 mt-0.5 text-amber-500" />
                        <div>
                            <span class="font-semibold block">Advertencia de saldo insuficiente:</span>
                            <span>
                                La cantidad a transferir ({form.quantity}) supera el stock registrado en {originWarehouse?.name} ({preselectedStock}). El depósito quedará en saldo negativo.
                            </span>
                        </div>
                    </div>
                {/if}

                <!-- Notas / Observaciones -->
                <div class="space-y-1.5">
                    <Label for="transfer-notes" class="text-xs font-semibold text-foreground">
                        Motivo / Observaciones
                    </Label>
                    <textarea
                        id="transfer-notes"
                        rows="2"
                        bind:value={form.notes}
                        class="w-full rounded-xl border border-border/80 bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary shadow-2xs resize-none"
                        placeholder="Ej. Reabastecimiento a sucursal, redistribución de mercadería..."
                    ></textarea>
                    {#if form.errors.notes}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.notes}</p>
                    {/if}
                </div>
            </form>
        </DialogBody>

        <DialogFooter class="flex items-center justify-between sm:justify-end gap-2.5">
            <Button
                type="button"
                variant="outline"
                onclick={closeModal}
                disabled={form.processing}
                class="min-h-[44px] rounded-xl font-medium"
            >
                Cancelar
            </Button>
            <Button
                type="submit"
                form="transfer-stock-form"
                disabled={form.processing || !form.product_id || !form.origin_warehouse_id || !form.destination_warehouse_id || !form.quantity}
                class="min-h-[44px] gap-2 rounded-xl font-semibold shadow-2xs"
            >
                {#if form.processing}
                    <Loader2 class="size-4 animate-spin" />
                    <span>Procesando...</span>
                {:else}
                    <ArrowLeftRight class="size-4" />
                    <span>Confirmar transferencia</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Modal de Confirmación para Saldo Negativo -->
<ConfirmActionModal
    bind:open={showConfirmNegativeStock}
    title="¿Transferir con saldo insuficiente en origen?"
    description="El depósito de origen '{originWarehouse?.name}' quedará con saldo negativo. La operación quedará registrada en el Kardex y requerirá un ajuste o reposición posterior. ¿Deseas continuar?"
    confirmText="Sí, transferir igualmente"
    cancelText="Volver y revisar"
    variant="warning"
    loading={form.processing}
    onconfirm={submitTransfer}
    oncancel={() => (showConfirmNegativeStock = false)}
/>
