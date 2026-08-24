<script lang="ts">
    import type { Snippet } from 'svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import HelpCircle from 'lucide-svelte/icons/help-circle';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import X from 'lucide-svelte/icons/x';

    let {
        open = $bindable(false),
        title = '¿Estás seguro?',
        description = 'Esta acción no se puede deshacer.',
        confirmText = 'Confirmar',
        cancelText = 'Cancelar',
        variant = 'danger',
        loading = false,
        onconfirm,
        oncancel,
        children,
    }: {
        open?: boolean;
        title?: string;
        description?: string;
        confirmText?: string;
        cancelText?: string;
        variant?: 'danger' | 'warning' | 'primary' | 'success';
        loading?: boolean;
        onconfirm?: () => void;
        oncancel?: () => void;
        children?: Snippet;
    } = $props();

    function handleConfirm() {
        if (loading) return;
        if (onconfirm) {
            onconfirm();
        }
    }

    function handleCancel() {
        open = false;
        if (oncancel) {
            oncancel();
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-md p-6">
        <div class="flex items-start gap-4">
            <!-- Icono dinámico según variante -->
            <div
                class="flex size-11 shrink-0 items-center justify-center rounded-xl {variant === 'danger'
                    ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'
                    : variant === 'warning'
                      ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                      : variant === 'success'
                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                        : 'bg-primary/10 text-primary border border-primary/20'}"
            >
                {#if variant === 'danger'}
                    <TriangleAlert class="size-5.5" />
                {:else if variant === 'warning'}
                    <ShieldAlert class="size-5.5" />
                {:else if variant === 'success'}
                    <CheckCircle2 class="size-5.5" />
                {:else}
                    <HelpCircle class="size-5.5" />
                {/if}
            </div>

            <div class="space-y-1.5 flex-1 min-w-0">
                <DialogTitle class="text-lg font-bold text-foreground leading-snug">
                    {title}
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground leading-relaxed">
                    {description}
                </DialogDescription>
            </div>

            <button
                type="button"
                onclick={handleCancel}
                class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors shrink-0 -mr-1 -mt-1"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </div>

        {#if children}
            <div class="pt-3">
                {@render children()}
            </div>
        {/if}

        <DialogFooter class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 pt-4 mt-2 border-t border-border/50">
            <Button
                type="button"
                variant="outline"
                size="sm"
                onclick={handleCancel}
                disabled={loading}
                class="text-xs"
            >
                {cancelText}
            </Button>
            <Button
                type="button"
                size="sm"
                onclick={handleConfirm}
                disabled={loading}
                class="text-xs font-semibold min-w-28 {variant === 'danger'
                    ? 'bg-rose-600 hover:bg-rose-700 text-white dark:bg-rose-600 dark:hover:bg-rose-700'
                    : variant === 'warning'
                      ? 'bg-amber-600 hover:bg-amber-700 text-white'
                      : variant === 'success'
                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white dark:bg-emerald-600 dark:hover:bg-emerald-700'
                        : ''}"
            >
                {loading ? 'Procesando...' : confirmText}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
