<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router } from '@inertiajs/svelte';
    import Layers from 'lucide-svelte/icons/layers';
    import Package from 'lucide-svelte/icons/package';
    import Plus from 'lucide-svelte/icons/plus';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import X from 'lucide-svelte/icons/x';

    interface ProductOption {
        id: number;
        name: string;
        sku: string | null;
        price: string | number;
        stock: number;
        units_per_package: number;
    }

    interface OrderItemInput {
        product_id: number | '';
        packaging_type: 'paquete' | 'bolson';
        quantity: number;
    }

    let {
        open = $bindable(false),
        availableProducts = [],
    }: {
        open: boolean;
        availableProducts: ProductOption[];
    } = $props();

    let customerName = $state('');
    let customerPhone = $state('');
    let deliveryAddress = $state('');
    let notes = $state('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    let items = $state<OrderItemInput[]>([
        { product_id: '', packaging_type: 'paquete', quantity: 1 },
    ]);

    function addItem() {
        items = [...items, { product_id: '', packaging_type: 'paquete', quantity: 1 }];
    }

    function removeItem(index: number) {
        if (items.length > 1) {
            items = items.filter((_, i) => i !== index);
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
            if (!it.product_id) continue;
            const prod = availableProducts.find((p) => p.id === it.product_id);
            if (!prod) continue;
            const unitPrice = Number(prod.price);
            const ratio = it.packaging_type === 'bolson' ? (prod.units_per_package || 1) : 1;
            total += it.quantity * ratio * unitPrice;
        }
        return total;
    });

    function resetForm() {
        customerName = '';
        customerPhone = '';
        deliveryAddress = '';
        notes = '';
        errorMessage = null;
        items = [{ product_id: '', packaging_type: 'paquete', quantity: 1 }];
    }

    function closeModal() {
        open = false;
        resetForm();
    }

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errorMessage = null;

        const validItems = items.filter((it) => it.product_id !== '');
        if (validItems.length === 0) {
            errorMessage = 'Debes seleccionar al menos un producto para el pedido.';
            return;
        }

        // Validate stock
        for (const it of validItems) {
            const prod = availableProducts.find((p) => p.id === it.product_id);
            if (prod) {
                const ratio = it.packaging_type === 'bolson' ? (prod.units_per_package || 1) : 1;
                const needed = it.quantity * ratio;
                if (needed > prod.stock) {
                    errorMessage = `Stock insuficiente para "${prod.name}". Solicitado: ${needed} unid., disponible: ${prod.stock}.`;
                    return;
                }
            }
        }

        isSubmitting = true;

        router.post(
            '/tenant/orders',
            {
                customer_name: customerName,
                customer_phone: customerPhone || null,
                delivery_address: deliveryAddress || null,
                notes: notes || null,
                items: validItems,
            },
            {
                onSuccess: () => {
                    isSubmitting = false;
                    closeModal();
                },
                onError: (errors) => {
                    isSubmitting = false;
                    errorMessage = Object.values(errors).flat().join(' ') || 'Ocurrió un error al registrar el pedido.';
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
                        <ShoppingBag class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-foreground">Nuevo Pedido de Cliente</h2>
                        <p class="text-xs text-muted-foreground">Registra un pedido con paquetes o bolsones y descuento de stock.</p>
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
                <!-- Datos del Cliente -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="cust-name" class="text-xs font-semibold">Nombre del Cliente / Destinatario *</Label>
                        <Input
                            id="cust-name"
                            bind:value={customerName}
                            placeholder="ej. Distribuidora San Martín"
                            class="h-9 text-xs"
                            required
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="cust-phone" class="text-xs font-semibold">Teléfono / WhatsApp</Label>
                        <Input
                            id="cust-phone"
                            bind:value={customerPhone}
                            placeholder="ej. 11 4455-6677"
                            class="h-9 text-xs"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="cust-address" class="text-xs font-semibold">Dirección de Entrega</Label>
                        <Input
                            id="cust-address"
                            bind:value={deliveryAddress}
                            placeholder="ej. Av. Corrientes 1234"
                            class="h-9 text-xs"
                        />
                    </div>
                </div>

                <!-- Líneas de Productos -->
                <div class="space-y-2.5 pt-2 border-t">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Productos del Pedido</span>
                        <Button type="button" size="sm" variant="outline" class="h-7 text-xs gap-1" onclick={addItem}>
                            <Plus class="size-3" />
                            Añadir producto
                        </Button>
                    </div>

                    <div class="space-y-2.5 max-h-60 overflow-y-auto pr-1">
                        {#each items as item, index (index)}
                            {@const selectedProduct = availableProducts.find((p) => p.id === item.product_id)}
                            {@const ratio = item.packaging_type === 'bolson' ? (selectedProduct?.units_per_package || 1) : 1}
                            {@const totalUnits = item.quantity * ratio}
                            {@const subtotal = selectedProduct ? totalUnits * Number(selectedProduct.price) : 0}

                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 p-2.5 rounded-xl border bg-muted/20">
                                <div class="flex-1 min-w-[180px] w-full">
                                    <select
                                        bind:value={item.product_id}
                                        class="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary"
                                        required
                                    >
                                        <option value="">Seleccionar producto...</option>
                                        {#each availableProducts as prod}
                                            <option value={prod.id}>
                                                {prod.name} ({formatPrice(Number(prod.price))}) - Stock: {prod.stock}u
                                            </option>
                                        {/each}
                                    </select>
                                </div>

                                <div class="flex items-center gap-2 w-full sm:w-auto">
                                    <!-- Selector de empaque -->
                                    <select
                                        bind:value={item.packaging_type}
                                        class="h-9 rounded-lg border border-input bg-background px-2 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary"
                                    >
                                        <option value="paquete">Paquete (Unidad)</option>
                                        <option value="bolson">
                                            Bolsón {selectedProduct?.units_per_package ? `(x${selectedProduct.units_per_package}u)` : ''}
                                        </option>
                                    </select>

                                    <!-- Cantidad -->
                                    <Input
                                        type="number"
                                        min="1"
                                        bind:value={item.quantity}
                                        class="h-9 w-16 text-xs text-center"
                                        required
                                    />

                                    <!-- Subtotal -->
                                    <div class="w-24 text-right font-mono font-bold text-xs text-foreground shrink-0">
                                        {formatPrice(subtotal)}
                                    </div>

                                    <!-- Eliminar -->
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
                        {/each}
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="space-y-1.5 pt-2 border-t">
                    <Label for="order-notes" class="text-xs font-semibold">Notas / Observaciones</Label>
                    <textarea
                        id="order-notes"
                        bind:value={notes}
                        rows="2"
                        placeholder="ej. Entregar en horario de la tarde..."
                        class="w-full rounded-lg border border-input bg-background p-2 text-xs placeholder:text-muted-foreground focus:ring-1 focus:ring-primary focus:outline-none"
                    ></textarea>
                </div>

                <!-- Footer y Total -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t">
                    <div>
                        <span class="text-xs text-muted-foreground block">Total Estimado del Pedido:</span>
                        <span class="text-xl font-bold font-mono text-primary">{formatPrice(computedTotal)}</span>
                    </div>

                    <div class="flex items-center gap-2 justify-end">
                        <Button type="button" variant="outline" size="sm" onclick={closeModal}>
                            Cancelar
                        </Button>
                        <Button type="submit" size="sm" disabled={isSubmitting} class="font-semibold">
                            {isSubmitting ? 'Guardando...' : 'Confirmar Pedido'}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </div>
{/if}
