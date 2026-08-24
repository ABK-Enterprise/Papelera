<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import Edit3 from 'lucide-svelte/icons/edit-3';
    import Layers from 'lucide-svelte/icons/layers';
    import Minus from 'lucide-svelte/icons/minus';
    import Package from 'lucide-svelte/icons/package';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';
    import CreatePackageModal, {
        type CreatedPackage,
        type PackageComponent,
    } from './CreatePackageModal.svelte';

    export interface ProductOption {
        id: number;
        name: string;
        sku: string | null;
        price: string | number;
        stock: number;
        units_per_package: number;
    }

    export interface OrderItemLine {
        id: string;
        is_package: boolean;
        product_id?: number | '';
        product_name?: string;
        packaging_type: 'paquete' | 'bolson';
        quantity: number;
        unit_price: number;
        units_per_package: number;
        description?: string;
        components?: PackageComponent[];
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

    let isPackageModalOpen = $state(false);
    let selectedProductIdToAdd = $state<number | ''>('');

    let items = $state<OrderItemLine[]>([
        {
            id: 'item_' + Date.now(),
            is_package: false,
            product_id: '',
            packaging_type: 'paquete',
            quantity: 1,
            unit_price: 0,
            units_per_package: 1,
        },
    ]);

    function addSingleProduct() {
        if (!selectedProductIdToAdd) {
            items = [
                ...items,
                {
                    id: 'item_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                    is_package: false,
                    product_id: '',
                    packaging_type: 'paquete',
                    quantity: 1,
                    unit_price: 0,
                    units_per_package: 1,
                },
            ];
            return;
        }

        const product = availableProducts.find((p) => p.id === selectedProductIdToAdd);
        if (!product) return;

        items = [
            ...items.filter((it) => it.product_id !== ''),
            {
                id: 'item_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
                is_package: false,
                product_id: product.id,
                product_name: product.name,
                packaging_type: 'paquete',
                quantity: 1,
                unit_price: Number(product.price),
                units_per_package: product.units_per_package || 1,
            },
        ];

        selectedProductIdToAdd = '';
    }

    function handleAddPackage(pkg: CreatedPackage) {
        items = [
            ...items.filter((it) => it.product_id !== ''),
            {
                id: pkg.id,
                is_package: true,
                packaging_type: 'bolson',
                quantity: pkg.package_quantity,
                unit_price: pkg.unit_price,
                units_per_package: 1,
                description: pkg.short_description,
                components: pkg.components,
            },
        ];
    }

    function removeItem(index: number) {
        if (items.length > 1) {
            items = items.filter((_, i) => i !== index);
        } else {
            items = [
                {
                    id: 'item_' + Date.now(),
                    is_package: false,
                    product_id: '',
                    packaging_type: 'paquete',
                    quantity: 1,
                    unit_price: 0,
                    units_per_package: 1,
                },
            ];
        }
    }

    function incrementQuantity(index: number) {
        items[index].quantity += 1;
    }

    function decrementQuantity(index: number) {
        if (items[index].quantity > 1) {
            items[index].quantity -= 1;
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
            if (it.is_package && it.components) {
                const pkgUnitCost = it.components.reduce(
                    (sum, c) => sum + c.quantity_in_package * c.unit_price,
                    0
                );
                total += it.quantity * pkgUnitCost;
            } else if (it.product_id) {
                const prod = availableProducts.find((p) => p.id === it.product_id);
                if (prod) {
                    const unitPrice = Number(prod.price);
                    const ratio = it.packaging_type === 'bolson' ? (prod.units_per_package || 1) : 1;
                    total += it.quantity * ratio * unitPrice;
                }
            }
        }
        return total;
    });

    function resetForm() {
        customerName = '';
        customerPhone = '';
        deliveryAddress = '';
        notes = '';
        errorMessage = null;
        selectedProductIdToAdd = '';
        items = [
            {
                id: 'item_' + Date.now(),
                is_package: false,
                product_id: '',
                packaging_type: 'paquete',
                quantity: 1,
                unit_price: 0,
                units_per_package: 1,
            },
        ];
    }

    function closeModal() {
        open = false;
        resetForm();
    }

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        errorMessage = null;

        const payloadItems: Array<{
            product_id: number;
            packaging_type: 'paquete' | 'bolson';
            quantity: number;
        }> = [];

        // Flatten packages into individual product items for backend processing & stock validation
        for (const it of items) {
            if (it.is_package && it.components) {
                for (const comp of it.components) {
                    payloadItems.push({
                        product_id: comp.product_id,
                        packaging_type: 'bolson',
                        quantity: comp.quantity_in_package * it.quantity,
                    });
                }
            } else if (it.product_id) {
                payloadItems.push({
                    product_id: Number(it.product_id),
                    packaging_type: it.packaging_type,
                    quantity: it.quantity,
                });
            }
        }

        if (payloadItems.length === 0) {
            errorMessage = 'Debes seleccionar al menos un producto o bolsón para el pedido.';
            return;
        }

        // Validate stock
        const totalNeededPerProduct: Record<number, number> = {};
        for (const pItem of payloadItems) {
            const prod = availableProducts.find((p) => p.id === pItem.product_id);
            if (prod) {
                const ratio = pItem.packaging_type === 'bolson' ? (prod.units_per_package || 1) : 1;
                const needed = pItem.quantity * ratio;
                totalNeededPerProduct[pItem.product_id] = (totalNeededPerProduct[pItem.product_id] || 0) + needed;
            }
        }

        for (const [prodIdStr, neededQty] of Object.entries(totalNeededPerProduct)) {
            const prod = availableProducts.find((p) => p.id === Number(prodIdStr));
            if (prod && neededQty > prod.stock) {
                errorMessage = `Stock insuficiente para "${prod.name}". Solicitado: ${neededQty} unid., disponible: ${prod.stock}.`;
                return;
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
                items: payloadItems,
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
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
        onclick={(e) => e.target === e.currentTarget && closeModal()}
        onkeydown={(e) => e.key === 'Escape' && closeModal()}
        role="button"
        tabindex="0"
        aria-label="Cerrar modal"
    >
        <div class="bg-card text-card-foreground rounded-2xl border border-border shadow-2xl max-w-4xl w-full p-4 sm:p-6 space-y-4 my-6 overflow-hidden flex flex-col max-h-[92vh]">
            <!-- Header Bento -->
            <div class="flex items-center justify-between border-b pb-3.5 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
                        <ShoppingBag class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-foreground tracking-tight">Nuevo Pedido</h2>
                        <p class="text-xs text-muted-foreground">Ingresa los datos del cliente y los productos para registrar la orden.</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={closeModal}
                    class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                    aria-label="Cerrar"
                >
                    <X class="size-5" />
                </button>
            </div>

            {#if errorMessage}
                <div class="p-3 rounded-xl bg-destructive/10 text-destructive text-xs font-medium border border-destructive/20 shrink-0">
                    {errorMessage}
                </div>
            {/if}

            <form onsubmit={handleSubmit} class="space-y-4 overflow-y-auto pr-1 flex-1">
                <!-- Bento Card 1: Datos del Cliente -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <User class="size-3.5 text-primary" />
                        Datos del Cliente
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="space-y-1">
                            <Label for="cust-name" class="text-xs font-semibold text-foreground">
                                Nombre / Razón Social <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="cust-name"
                                bind:value={customerName}
                                placeholder="ej. Ana García / Kiosco San Juan"
                                class="h-9 text-xs"
                                required
                            />
                        </div>
                        <div class="space-y-1">
                            <Label for="cust-phone" class="text-xs font-semibold text-foreground">Teléfono / Contacto</Label>
                            <Input
                                id="cust-phone"
                                bind:value={customerPhone}
                                placeholder="+54 9 11 4455-6677"
                                class="h-9 text-xs"
                            />
                        </div>
                        <div class="space-y-1">
                            <Label for="cust-address" class="text-xs font-semibold text-foreground">Dirección de Entrega</Label>
                            <Input
                                id="cust-address"
                                bind:value={deliveryAddress}
                                placeholder="Av. Corrientes 1234, CABA"
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>
                </div>

                <!-- Bento Card 2: Productos & Bolsones -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-3.5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Layers class="size-3.5 text-primary" />
                            Productos del Pedido
                        </h3>

                        <!-- Action buttons: Add product / Create Package -->
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative w-48 sm:w-56">
                                <select
                                    bind:value={selectedProductIdToAdd}
                                    class="h-8 w-full rounded-lg border border-input bg-background pl-2 pr-6 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary outline-none"
                                >
                                    <option value="">Buscar producto...</option>
                                    {#each availableProducts as prod}
                                        <option value={prod.id}>
                                            {prod.name} ({formatPrice(Number(prod.price))})
                                        </option>
                                    {/each}
                                </select>
                            </div>

                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                class="h-8 text-xs gap-1 font-semibold"
                                onclick={addSingleProduct}
                            >
                                <Plus class="size-3.5" />
                                Agregar
                            </Button>

                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="h-8 text-xs gap-1.5 font-semibold text-primary border-primary/30 bg-primary/5 hover:bg-primary/10"
                                onclick={() => (isPackageModalOpen = true)}
                            >
                                <Package class="size-3.5" />
                                Crear Bolsón
                            </Button>
                        </div>
                    </div>

                    <!-- Lista de Ítems del Pedido -->
                    <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                        {#each items as item, index (item.id || index)}
                            {#if item.is_package}
                                <!-- Card de Bolsón / Combo -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="size-9 rounded-lg bg-secondary/15 text-secondary flex items-center justify-center shrink-0 border border-secondary/20">
                                            <Package class="size-4.5" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-secondary/15 text-secondary border border-secondary/30">
                                                    BOLSÓN COMBO
                                                </span>
                                                <p class="text-xs font-bold text-foreground truncate max-w-xs sm:max-w-md">
                                                    {item.description || 'Bolsón de productos'}
                                                </p>
                                            </div>
                                            <p class="text-[11px] text-muted-foreground mt-0.5 font-mono">
                                                {formatPrice(item.unit_price)} por bolsón
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between sm:justify-end gap-3 w-full sm:w-auto pt-2 sm:pt-0 border-t sm:border-t-0">
                                        <!-- Stepper -->
                                        <div class="flex items-center border rounded-lg bg-background shadow-2xs">
                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-l-lg transition-colors"
                                                onclick={() => decrementQuantity(index)}
                                                aria-label="Disminuir cantidad"
                                            >
                                                <Minus class="size-3" />
                                            </button>
                                            <span class="px-3 text-xs font-bold font-mono min-w-8 text-center">
                                                {item.quantity}
                                            </span>
                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-r-lg transition-colors"
                                                onclick={() => incrementQuantity(index)}
                                                aria-label="Aumentar cantidad"
                                            >
                                                <Plus class="size-3" />
                                            </button>
                                        </div>

                                        <span class="font-mono text-xs font-bold text-foreground w-24 text-right">
                                            {formatPrice(item.quantity * item.unit_price)}
                                        </span>

                                        <button
                                            type="button"
                                            class="p-1.5 text-muted-foreground hover:text-destructive rounded transition-colors"
                                            onclick={() => removeItem(index)}
                                            aria-label="Eliminar bolsón"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            {:else}
                                <!-- Card de Producto Individual -->
                                {@const selectedProd = availableProducts.find((p) => p.id === item.product_id)}
                                {@const ratio = item.packaging_type === 'bolson' ? (selectedProd?.units_per_package || 1) : 1}
                                {@const lineSubtotal = selectedProd ? item.quantity * ratio * Number(selectedProd.price) : 0}

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl border bg-card hover:bg-muted/10 transition-colors shadow-2xs">
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <div class="size-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 border border-primary/20">
                                            <ShoppingBag class="size-4.5" />
                                        </div>

                                        <div class="flex-1 min-w-[160px]">
                                            <select
                                                bind:value={item.product_id}
                                                class="h-8 w-full rounded-lg border border-input bg-background px-2 text-xs font-semibold text-foreground focus:ring-1 focus:ring-primary outline-none"
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
                                    </div>

                                    <div class="flex items-center justify-between sm:justify-end gap-2.5 w-full sm:w-auto pt-2 sm:pt-0 border-t sm:border-t-0">
                                        <!-- Packaging type -->
                                        <select
                                            bind:value={item.packaging_type}
                                            class="h-8 rounded-lg border border-input bg-background px-2 text-xs font-medium text-foreground focus:ring-1 focus:ring-primary outline-none"
                                        >
                                            <option value="paquete">Unidad</option>
                                            <option value="bolson">
                                                Bolsón {selectedProd?.units_per_package ? `(x${selectedProd.units_per_package}u)` : ''}
                                            </option>
                                        </select>

                                        <!-- Stepper -->
                                        <div class="flex items-center border rounded-lg bg-background shadow-2xs">
                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-l-lg transition-colors"
                                                onclick={() => decrementQuantity(index)}
                                                aria-label="Disminuir cantidad"
                                            >
                                                <Minus class="size-3" />
                                            </button>
                                            <span class="px-2.5 text-xs font-bold font-mono min-w-7 text-center">
                                                {item.quantity}
                                            </span>
                                            <button
                                                type="button"
                                                class="p-1.5 text-muted-foreground hover:text-foreground hover:bg-muted rounded-r-lg transition-colors"
                                                onclick={() => incrementQuantity(index)}
                                                aria-label="Aumentar cantidad"
                                            >
                                                <Plus class="size-3" />
                                            </button>
                                        </div>

                                        <span class="font-mono text-xs font-bold text-foreground w-20 text-right">
                                            {formatPrice(lineSubtotal)}
                                        </span>

                                        <button
                                            type="button"
                                            class="p-1.5 text-muted-foreground hover:text-destructive rounded transition-colors"
                                            onclick={() => removeItem(index)}
                                            aria-label="Eliminar producto"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            {/if}
                        {/each}
                    </div>

                    <!-- Total Card Footer -->
                    <div class="flex justify-end items-center gap-2 pt-2 border-t border-border/50">
                        <span class="text-xs text-muted-foreground font-medium">Total Pedido:</span>
                        <span class="text-lg font-bold font-mono text-primary">{formatPrice(computedTotal)}</span>
                    </div>
                </div>

                <!-- Bento Card 3: Notas u Observaciones -->
                <div class="bg-muted/20 p-4 rounded-xl border border-border/70 space-y-2">
                    <Label for="order-notes" class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <Edit3 class="size-3.5 text-primary" />
                        Notas u Observaciones
                    </Label>
                    <textarea
                        id="order-notes"
                        bind:value={notes}
                        rows="2"
                        placeholder="Instrucciones especiales de entrega, preferencias del cliente, etc."
                        class="w-full bg-background text-foreground text-xs rounded-lg px-3 py-2 outline-none border border-input focus:ring-1 focus:ring-primary transition-colors resize-none"
                    ></textarea>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t shrink-0">
                    <Button type="button" variant="outline" size="sm" onclick={closeModal} disabled={isSubmitting}>
                        Cancelar
                    </Button>
                    <Button type="submit" size="sm" disabled={isSubmitting} class="font-semibold gap-1.5 min-w-36">
                        {#if isSubmitting}
                            <span>Guardando...</span>
                        {:else}
                            <span>Guardar Pedido</span>
                            <Check class="size-3.5" />
                        {/if}
                    </Button>
                </div>
            </form>
        </div>
    </div>
{/if}

<!-- Modal Secundario Crear Bolsón -->
<CreatePackageModal
    bind:open={isPackageModalOpen}
    {availableProducts}
    mode="customer"
    onaddpackage={handleAddPackage}
/>
