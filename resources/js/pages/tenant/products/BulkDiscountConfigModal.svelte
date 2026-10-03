<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import { router } from '@inertiajs/svelte';
    import Percent from 'lucide-svelte/icons/percent';
    import Tag from 'lucide-svelte/icons/tag';
    import Check from 'lucide-svelte/icons/check';
    import X from 'lucide-svelte/icons/x';

    let {
        open = $bindable(false),
        currentDiscount = 0,
    }: {
        open?: boolean;
        currentDiscount?: number;
    } = $props();

    let discountValue = $state(0);
    let isSubmitting = $state(false);

    $effect(() => {
        if (open) {
            discountValue = currentDiscount;
        }
    });

    function handleSubmit(e: Event) {
        e.preventDefault();
        isSubmitting = true;

        router.patch(
            '/tenant/productos/descuento-bulto/global',
            {
                default_bulk_discount_percentage: discountValue,
            },

            {
                preserveScroll: true,
                onSuccess: () => {
                    isSubmitting = false;
                    open = false;
                },
                onError: () => {
                    isSubmitting = false;
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="sm:max-w-[480px] p-6 sm:p-7">
        <form onsubmit={handleSubmit}>
            <div class="flex items-start justify-between gap-4 pb-4 border-b border-border">
                <div class="flex items-center gap-3">
                    <IconContainer size="md" variant="primary" icon="solar:tag-price-bold-duotone" />
                    <div>
                        <DialogTitle class="text-lg font-bold tracking-tight text-foreground">Descuento Global por Bulto</DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            Porcentaje predeterminado aplicado al vender por bulto cerrado.
                        </DialogDescription>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={() => (open = false)}
                    class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer shrink-0"
                    aria-label="Cerrar"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div class="grid gap-4 py-5">
                <div class="space-y-2">
                    <Label for="discount-percentage" class="text-sm font-medium text-foreground">
                        Descuento global por bulto
                    </Label>
                    <div class="relative">
                        <Percent class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                        <Input
                            id="discount-percentage"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            bind:value={discountValue}
                            class="pl-9 text-base tabular-nums font-semibold"
                            placeholder="0.00"
                            required
                        />
                    </div>
                </div>

                <div class="rounded-xl border border-border/60 bg-muted/30 p-3.5 text-xs text-muted-foreground space-y-1.5">
                    <p class="font-medium text-foreground flex items-center gap-1.5">
                        <Tag class="size-3.5 text-primary" />
                        ¿Cómo funciona este ajuste?
                    </p>
                    <p>
                        Todos los productos con la regla <span class="font-semibold text-foreground">"Heredar global"</span> aplicarán automáticamente este <span class="font-semibold text-foreground tabular-nums">{discountValue}%</span> al venderse por bulto. Puedes sobrescribir este valor individualmente en cada producto.
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                <Button type="button" variant="outline" class="min-h-[44px] px-4 rounded-xl text-sm font-semibold" onclick={() => (open = false)} disabled={isSubmitting}>
                    Cancelar
                </Button>
                <Button type="submit" disabled={isSubmitting} class="min-h-[44px] px-4 rounded-xl text-sm font-semibold gap-1.5">
                    <Check class="size-4" />
                    <span>Guardar configuración</span>
                </Button>
            </div>

        </form>
    </DialogContent>
</Dialog>
