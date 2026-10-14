<script lang="ts">
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogBody,
        DialogFooter,
    } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import Pencil from 'lucide-svelte/icons/pencil';
    import PackagePlus from 'lucide-svelte/icons/package-plus';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';

    interface OrderSummary {
        id: number;
        order_number: string;
        customer_name?: string;
    }

    interface Props {
        open?: boolean;
        order?: OrderSummary | null;
        onEditPrices?: () => void;
        onAddProducts?: () => void;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        order = null,
        onEditPrices = undefined,
        onAddProducts = undefined,
        onclose = undefined,
    }: Props = $props();

    function handleChooseEditPrices() {
        open = false;
        setTimeout(() => {
            onEditPrices?.();
        }, 180);
    }

    function handleChooseAddProducts() {
        open = false;
        setTimeout(() => {
            onAddProducts?.();
        }, 180);
    }

    function handleClose() {
        open = false;
        onclose?.();
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-md p-5 sm:p-6 space-y-4 rounded-2xl">
        <DialogHeader class="shrink-0 pb-3 border-b border-border/60">
            <div class="flex items-center gap-2.5 sm:gap-3.5">
                <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                    <SlidersHorizontal class="size-5 sm:size-6 text-primary" />
                </div>
                <div class="min-w-0 flex-1 flex flex-col justify-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                        Modificar pedido
                    </DialogTitle>
                    {#if order}
                        <p class="text-xs text-muted-foreground mt-1 tabular-nums">
                            Pedido #{order.order_number}{order.customer_name ? ` • ${order.customer_name}` : ''}
                        </p>
                    {/if}
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="py-2">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button
                    type="button"
                    onclick={handleChooseEditPrices}
                    class="flex flex-col items-center justify-center text-center p-4 rounded-xl border border-border/80 bg-card hover:bg-primary/5 hover:border-primary/50 transition-all cursor-pointer min-h-[110px] group"
                >
                    <div class="size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-2 group-hover:scale-105 transition-transform">
                        <Pencil class="size-5" />
                    </div>
                    <span class="text-sm font-semibold text-foreground">
                        Editar precios
                    </span>
                    <span class="text-xs text-muted-foreground mt-0.5">
                        De productos existentes
                    </span>
                </button>

                <button
                    type="button"
                    onclick={handleChooseAddProducts}
                    class="flex flex-col items-center justify-center text-center p-4 rounded-xl border border-border/80 bg-card hover:bg-primary/5 hover:border-primary/50 transition-all cursor-pointer min-h-[110px] group"
                >
                    <div class="size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-2 group-hover:scale-105 transition-transform">
                        <PackagePlus class="size-5" />
                    </div>
                    <span class="text-sm font-semibold text-foreground">
                        Agregar productos
                    </span>
                    <span class="text-xs text-muted-foreground mt-0.5">
                        Añadir nuevos ítems
                    </span>
                </button>
            </div>
        </DialogBody>

        <DialogFooter class="flex justify-end pt-2 border-t border-border/70">
            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] rounded-xl text-sm font-semibold cursor-pointer w-full sm:w-auto"
                onclick={handleClose}
            >
                Cancelar
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
