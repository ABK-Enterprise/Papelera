<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogDescription,
        DialogFooter,
    } from '@/components/ui/dialog';
    import BookOpen from 'lucide-svelte/icons/book-open';
    import Eye from 'lucide-svelte/icons/eye';
    import Download from 'lucide-svelte/icons/download';
    import Layers from 'lucide-svelte/icons/layers';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
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
    <DialogContent class="max-w-xl p-5 sm:p-6 space-y-4">
        <DialogHeader class="space-y-1">
            <div class="flex items-center gap-2">
                <div class="size-8 rounded-lg bg-primary-500/10 border border-primary-500/20 flex items-center justify-center text-primary shrink-0">
                    <BookOpen class="size-4" />
                </div>
                <Badge variant="outline" class="border-primary-500/30 bg-primary-500/10 text-primary-700 dark:text-primary-300 font-semibold">
                    Documentación Operativa
                </Badge>
            </div>
            <DialogTitle class="text-xl font-bold tracking-tight text-foreground pt-1">
                Manual de Usuario
            </DialogTitle>
            <DialogDescription class="text-xs sm:text-sm text-muted-foreground">
                Guía completa con capturas y explicaciones paso a paso de cada funcionalidad del sistema.
            </DialogDescription>
        </DialogHeader>

        <!-- Preview de Secciones del Manual -->
        <div class="space-y-2.5 py-1">
            <div class="flex items-start gap-3 rounded-lg border border-border/70 bg-card p-3 shadow-2xs">
                <div class="size-8 rounded-md bg-muted/60 flex items-center justify-center text-foreground shrink-0 mt-0.5">
                    <Layers class="size-4" />
                </div>
                <div class="space-y-0.5">
                    <span class="text-xs font-semibold text-foreground block">Gestión de Catálogo y Depósitos</span>
                    <p class="text-[11px] text-muted-foreground">
                        Carga rápida de productos, control de stock por depósito, transferencias e historial kardex.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-3 rounded-lg border border-border/70 bg-card p-3 shadow-2xs">
                <div class="size-8 rounded-md bg-muted/60 flex items-center justify-center text-foreground shrink-0 mt-0.5">
                    <ShoppingBag class="size-4" />
                </div>
                <div class="space-y-0.5">
                    <span class="text-xs font-semibold text-foreground block">Pedidos y Cuentas Corrientes</span>
                    <p class="text-[11px] text-muted-foreground">
                        Flujo de estados de pedidos, repartos en calle, imputación de pagos y balances de clientes.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-3 rounded-lg border border-border/70 bg-card p-3 shadow-2xs">
                <div class="size-8 rounded-md bg-muted/60 flex items-center justify-center text-foreground shrink-0 mt-0.5">
                    <ReceiptText class="size-4" />
                </div>
                <div class="space-y-0.5">
                    <span class="text-xs font-semibold text-foreground block">Facturación Electrónica ARCA</span>
                    <p class="text-[11px] text-muted-foreground">
                        Emisión automática de facturas y notas de crédito con CAE y código QR oficial.
                    </p>
                </div>
            </div>
        </div>

        <DialogFooter class="pt-3 border-t border-border/50 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2.5">
            <Button
                type="button"
                variant="ghost"
                class="min-h-[44px] px-4 font-medium text-xs text-muted-foreground hover:text-foreground"
                onclick={() => handleOpenChange(false)}
            >
                Cerrar
            </Button>

            <div class="grid grid-cols-2 gap-2.5 sm:flex sm:items-center">
                <!-- Botón Ver -->
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-[44px] px-5 gap-2 font-medium"
                    onclick={() => handleActionClick('ver')}
                >
                    <Eye class="size-4" />
                    <span>Ver</span>
                </Button>

                <!-- Botón Descargar -->
                <Button
                    type="button"
                    variant="default"
                    class="min-h-[44px] px-5 gap-2 font-medium shadow-xs"
                    onclick={() => handleActionClick('descargar')}
                >
                    <Download class="size-4" />
                    <span>Descargar</span>
                </Button>
            </div>
        </DialogFooter>
    </DialogContent>
</Dialog>
