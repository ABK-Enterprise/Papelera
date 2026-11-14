<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogTitle,
    } from '@/components/ui/dialog';
    import BookOpen from 'lucide-svelte/icons/book-open';
    import Eye from 'lucide-svelte/icons/eye';
    import Download from 'lucide-svelte/icons/download';
    import X from 'lucide-svelte/icons/x';
    import { toast } from 'svelte-sonner';

    let {
        isOpen = $bindable(false),
        onclose = () => {},
    }: {
        isOpen?: boolean;
        onclose?: () => void;
    } = $props();

    function handleOpenChange(open: boolean) {
        isOpen = open;
        if (!open) {
            onclose();
        }
    }

    function handleActionClick(action: 'ver' | 'descargar') {
        toast.info('La guía digital en PDF se encuentra en preparación y estará disponible próximamente.');
    }
</script>

<Dialog open={isOpen} onOpenChange={handleOpenChange}>
    <DialogContent class="max-w-md sm:max-w-lg p-5 sm:p-6 space-y-5">
        <!-- Cabecera del Dialog: Título e Icono X de cierre -->
        <div class="flex items-center justify-between gap-3 shrink-0 pb-3 border-b border-border/60">
            <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground">
                Manual de usuario
            </DialogTitle>
            <button
                type="button"
                onclick={() => handleOpenChange(false)}
                class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted dark:hover:bg-secondary/60 transition-colors shrink-0 cursor-pointer"
                aria-label="Cerrar modal"
            >
                <X class="size-4.5" />
            </button>
        </div>

        <!-- Cuerpo del Dialog: Icono centrado y oración descriptiva -->
        <div class="flex flex-col items-center justify-center text-center py-4 sm:py-6 space-y-4">
            <div class="size-16 sm:size-20 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0 shadow-2xs">
                <BookOpen class="size-8 sm:size-10" />
            </div>
            <p class="text-sm sm:text-base text-muted-foreground max-w-xs sm:max-w-sm leading-relaxed">
                Aqui podras ver o descargar el manual de usuario proveido por ABK Solutions.
            </p>
        </div>

        <!-- Nivel Inferior: Botones Ver y Descargar adaptados a Mobile y Desktop -->
        <div class="pt-4 border-t border-border/60 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2.5">
            <div class="grid grid-cols-2 gap-2.5 w-full sm:flex sm:w-auto">
                <Button
                    type="button"
                    variant="secondary"
                    class="min-h-[44px] px-5 gap-2 font-medium w-full sm:w-auto justify-center"
                    onclick={() => handleActionClick('ver')}
                >
                    <Eye class="size-4 shrink-0" />
                    <span>Ver</span>
                </Button>

                <Button
                    type="button"
                    variant="default"
                    class="min-h-[44px] px-5 gap-2 font-medium shadow-xs w-full sm:w-auto justify-center"
                    onclick={() => handleActionClick('descargar')}
                >
                    <Download class="size-4 shrink-0" />
                    <span>Descargar</span>
                </Button>
            </div>
        </div>
    </DialogContent>
</Dialog>
