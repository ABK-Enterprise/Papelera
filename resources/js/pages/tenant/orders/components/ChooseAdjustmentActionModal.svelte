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
    import Plus from 'lucide-svelte/icons/plus';
    import Sliders from 'lucide-svelte/icons/sliders';
    import Tag from 'lucide-svelte/icons/tag';

    interface Props {
        open?: boolean;
        onCreate?: () => void;
        onSelectExisting?: () => void;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        onCreate = undefined,
        onSelectExisting = undefined,
        onclose = undefined,
    }: Props = $props();

    function handleChooseCreate() {
        open = false;
        setTimeout(() => {
            onCreate?.();
        }, 180);
    }

    function handleChooseExisting() {
        open = false;
        setTimeout(() => {
            onSelectExisting?.();
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
                    <Tag class="size-5 sm:size-6 text-primary" />
                </div>
                <div class="min-w-0 flex-1 flex items-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                        Elegir ajuste
                    </DialogTitle>
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="py-2">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button
                    type="button"
                    onclick={handleChooseCreate}
                    class="flex flex-col items-center justify-center text-center p-4 rounded-xl border border-border/80 bg-card hover:bg-primary/5 hover:border-primary/50 transition-all cursor-pointer min-h-[96px] group"
                >
                    <div class="size-9 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-2 group-hover:scale-105 transition-transform">
                        <Plus class="size-5" />
                    </div>
                    <span class="text-sm font-semibold text-foreground">
                        Crear
                    </span>
                    <span class="text-xs text-muted-foreground mt-0.5">
                        Al vuelo
                    </span>
                </button>

                <button
                    type="button"
                    onclick={handleChooseExisting}
                    class="flex flex-col items-center justify-center text-center p-4 rounded-xl border border-border/80 bg-card hover:bg-primary/5 hover:border-primary/50 transition-all cursor-pointer min-h-[96px] group"
                >
                    <div class="size-9 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-2 group-hover:scale-105 transition-transform">
                        <Sliders class="size-5" />
                    </div>
                    <span class="text-sm font-semibold text-foreground">
                        Seleccionar existente
                    </span>
                    <span class="text-xs text-muted-foreground mt-0.5">
                        Del catálogo
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
