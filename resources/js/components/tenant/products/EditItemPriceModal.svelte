<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { router } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import Package from 'lucide-svelte/icons/package';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Store from 'lucide-svelte/icons/store';
    import Tag from 'lucide-svelte/icons/tag';
    import { preventInvalidNumericKeys, sanitizeNumericInput } from '@/lib/numericSanitizer';

    export interface ProductPriceTarget {
        id?: number | null;
        name: string;
        price: number | string;
        sku?: string | null;
    }

    interface Props {
        open?: boolean;
        product: ProductPriceTarget | null;
        isOrderContext?: boolean;
        customUpdateHandler?: (newPrice: number, mode: 'order_only' | 'catalog') => Promise<void> | void;
        onPriceUpdated?: (newPrice: number, mode: 'order_only' | 'catalog') => void;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        product = null,
        isOrderContext = false,
        customUpdateHandler = undefined,
        onPriceUpdated = undefined,
        onclose = undefined,
    }: Props = $props();

    let newPriceInput = $state<string | number>('');
    let updateMode = $state<'order_only' | 'catalog'>('order_only');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    $effect(() => {
        if (open && product) {
            newPriceInput = product.price !== undefined && product.price !== null ? Number(product.price) : '';
            updateMode = isOrderContext ? 'order_only' : 'catalog';
            errorMessage = null;
        }
    });

    function formatCurrency(amount: string | number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount) || 0);
    }

    function handleClose() {
        open = false;
        errorMessage = null;
        onclose?.();
    }

    function handleSubmit() {
        errorMessage = null;
        const parsedPrice = Number(newPriceInput);

        if (!newPriceInput || isNaN(parsedPrice) || parsedPrice <= 0) {
            errorMessage = 'El precio debe ser un número positivo mayor a 0.';
            return;
        }

        if (parsedPrice > 100_000_000) {
            errorMessage = 'El precio no puede exceder los $100.000.000.';
            return;
        }

        if (customUpdateHandler) {
            isSubmitting = true;
            Promise.resolve(customUpdateHandler(parsedPrice, updateMode))
                .then(() => {
                    onPriceUpdated?.(parsedPrice, updateMode);
                    handleClose();
                })
                .catch((err: any) => {
                    errorMessage = err?.message || 'Error al actualizar el precio.';
                })
                .finally(() => {
                    isSubmitting = false;
                });
            return;
        }

        if (isOrderContext && updateMode === 'order_only') {
            onPriceUpdated?.(parsedPrice, 'order_only');
            toast.success(`Precio actualizado para este pedido: ${formatCurrency(parsedPrice)}`);
            handleClose();
            return;
        }

        // Modo catálogo: actualizar en base de datos vía PATCH /tenant/productos/{product}/precio
        if (!product?.id) {
            errorMessage = 'El producto no posee un identificador de catálogo válido para persistir.';
            return;
        }

        isSubmitting = true;
        router.patch(
            `/tenant/productos/${product.id}/precio`,
            { price: parsedPrice },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success(`Precio del catálogo actualizado a ${formatCurrency(parsedPrice)}`);
                    onPriceUpdated?.(parsedPrice, 'catalog');
                    handleClose();
                },
                onError: (errors) => {
                    errorMessage = errors.price || Object.values(errors)[0] || 'Error al actualizar el precio en el catálogo.';
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-lg p-5 sm:p-6 space-y-4 rounded-2xl">
        <DialogHeader class="shrink-0 pb-3 border-b border-border/60">
            <div class="flex items-center gap-2.5 sm:gap-3.5">
                <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                    <Tag class="size-5 sm:size-6 text-primary" />
                </div>
                <div class="min-w-0 flex-1 flex items-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-none truncate">
                        {isOrderContext ? 'Modificar precio en el pedido' : 'Editar precio en catálogo'}
                    </DialogTitle>
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="space-y-4 py-1">
            {#if product}
                <div class="p-3 rounded-xl bg-muted/40 border border-border/60 text-xs">
                    <span class="text-xs text-muted-foreground block">Producto:</span>
                    <strong class="text-foreground font-semibold text-sm">{product.name}</strong>
                    {#if product.sku}
                        <span class="ml-1 text-xs tabular-nums text-muted-foreground">({product.sku})</span>
                    {/if}
                </div>
            {/if}
            <!-- Precio Actual vs Nuevo Precio -->
            <div class="space-y-2">
                <Label for="item-new-price" class="text-sm font-medium text-foreground">
                    Nuevo precio unitario
                </Label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-muted-foreground text-sm font-semibold">
                        $
                    </span>
                    <Input
                        id="item-new-price"
                        type="number"
                        step="0.01"
                        min="0.01"
                        max="100000000"
                        placeholder="0.00"
                        value={newPriceInput}
                        onkeydown={(e) => preventInvalidNumericKeys(e)}
                        oninput={(e) => {
                            sanitizeNumericInput(e, 0.01, 100_000_000, (val) => {
                                newPriceInput = String(val);
                            });
                        }}
                        class="pl-8 text-sm font-semibold tabular-nums min-h-[44px] rounded-xl"
                        autofocus
                    />
                </div>
                {#if product?.price !== undefined && product?.price !== null}
                    <div class="flex items-center justify-between text-xs text-muted-foreground pt-0.5">
                        <span>Precio actual registrado:</span>
                        <span class="font-semibold text-foreground tabular-nums">
                            {formatCurrency(product.price)}
                        </span>
                    </div>
                {/if}
            </div>

            <!-- Selector de Alcance (Solo visible en contexto de Pedido con ID de catálogo) -->
            {#if isOrderContext && product?.id}
                <div class="space-y-2 pt-2 border-t border-border/70">
                    <Label class="text-sm font-medium text-foreground block">
                        Alcance del cambio de precio
                    </Label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <button
                            type="button"
                            onclick={() => (updateMode = 'order_only')}
                            class="flex flex-col p-3 rounded-xl border text-left transition-all cursor-pointer {updateMode === 'order_only' ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-border/70 bg-card hover:bg-muted/40'}"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <ShoppingBag class="size-4 {updateMode === 'order_only' ? 'text-primary' : 'text-muted-foreground'}" />
                                <span class="text-xs font-bold text-foreground">Solo en este pedido</span>
                            </div>
                            <p class="text-xs text-muted-foreground leading-relaxed">
                                Modifica el precio exclusivamente para esta orden. El catálogo base no sufre cambios.
                            </p>
                        </button>

                        <button
                            type="button"
                            onclick={() => (updateMode = 'catalog')}
                            class="flex flex-col p-3 rounded-xl border text-left transition-all cursor-pointer {updateMode === 'catalog' ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-border/70 bg-card hover:bg-muted/40'}"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <Store class="size-4 {updateMode === 'catalog' ? 'text-primary' : 'text-muted-foreground'}" />
                                <span class="text-xs font-bold text-foreground">Actualizar catálogo</span>
                            </div>
                            <p class="text-xs text-muted-foreground leading-relaxed">
                                Actualiza el precio oficial en el catálogo y alerta a pedidos en curso con este ítem.
                            </p>
                        </button>
                    </div>
                </div>
            {/if}

            <!-- Banner Informativo para el modo Catálogo -->
            {#if updateMode === 'catalog'}
                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/25 flex items-start gap-2.5 text-xs text-amber-900 dark:text-amber-300">
                    <AlertTriangle class="size-4 text-amber-600 shrink-0 mt-0.5" />
                    <p class="leading-relaxed">
                        Este cambio se aplicará a todo el catálogo. Aquellos pedidos en preparación no facturados que incluyan este producto serán marcados para revisión de precios.
                    </p>
                </div>
            {/if}

            <!-- Mensaje de Error -->
            {#if errorMessage}
                <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-xs text-destructive font-medium">
                    {errorMessage}
                </div>
            {/if}
        </DialogBody>

        <DialogFooter class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:justify-end sm:w-auto pt-2 border-t border-border/70">
            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] rounded-xl text-sm font-medium cursor-pointer"
                disabled={isSubmitting}
                onclick={handleClose}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                class="min-h-[44px] rounded-xl text-sm font-medium gap-1.5 cursor-pointer"
                disabled={isSubmitting}
                onclick={handleSubmit}
            >
                {#if isSubmitting}
                    <Loader2 class="size-3.5 animate-spin" />
                    <span>Guardando...</span>
                {:else}
                    <span>{updateMode === 'catalog' ? 'Actualizar precio' : 'Aplicar al pedido'}</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
