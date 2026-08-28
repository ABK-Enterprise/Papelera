<script lang="ts">
    import type { Snippet } from 'svelte';
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
    <DialogContent size="sm" class="max-w-md">
        <DialogHeader>
            <div class="flex items-center gap-3 min-w-0">
                <!-- Icono dinámico según variante -->
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl {variant === 'danger'
                        ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'
                        : variant === 'warning'
                          ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                          : variant === 'success'
                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                            : 'bg-primary/10 text-primary border border-primary/20'}"
                >
                    {#if variant === 'danger'}
                        <TriangleAlert class="size-4.5" />
                    {:else if variant === 'warning'}
                        <ShieldAlert class="size-4.5" />
                    {:else if variant === 'success'}
                        <CheckCircle2 class="size-4.5" />
                    {:else}
                        <HelpCircle class="size-4.5" />
                    {/if}
                </div>
                <DialogTitle class="text-base font-semibold leading-snug truncate">
                    {title}
                </DialogTitle>
            </div>
            <button
                type="button"
                onclick={handleCancel}
                class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors shrink-0 -mr-1"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </DialogHeader>

        <DialogBody>
            <DialogDescription class="text-xs sm:text-sm text-muted-foreground leading-relaxed">
                {description}
            </DialogDescription>
            {#if children}
                <div class="pt-2">
                    {@render children()}
                </div>
            {/if}
        </DialogBody>

        <DialogFooter>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onclick={handleCancel}
                disabled={loading}
                class="min-h-[40px] px-4 rounded-xl text-xs font-medium border-border/70"
            >
                {cancelText}
            </Button>
            <Button
                type="button"
                size="sm"
                onclick={handleConfirm}
                disabled={loading}
                class="min-h-[40px] px-4 rounded-xl text-xs font-medium min-w-28 shadow-xs {variant === 'danger'
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
