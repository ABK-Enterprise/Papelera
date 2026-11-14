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
        isLoading = false,
        onconfirm,
        onConfirm,
        oncancel,
        onCancel,
        children,
    }: {
        open?: boolean;
        title?: string;
        description?: string;
        confirmText?: string;
        cancelText?: string;
        variant?: 'danger' | 'destructive' | 'warning' | 'primary' | 'success';
        loading?: boolean;
        isLoading?: boolean;
        onconfirm?: () => void;
        onConfirm?: () => void;
        oncancel?: () => void;
        onCancel?: () => void;
        children?: Snippet;
    } = $props();

    const isBusy = $derived(loading || isLoading);
    const isDestructive = $derived(variant === 'danger' || variant === 'destructive');

    function handleConfirm() {
        if (isBusy) return;
        if (onconfirm) {
            onconfirm();
        } else if (onConfirm) {
            onConfirm();
        }
    }

    function handleCancel() {
        open = false;
        if (oncancel) {
            oncancel();
        } else if (onCancel) {
            onCancel();
        }
    }
</script>

<Dialog bind:open>
    <DialogContent size="sm" class="max-w-md">
        <DialogHeader class="pb-3 border-b border-border/60 shrink-0">
            <div class="flex items-center justify-between gap-2.5 sm:gap-3.5 w-full">
                <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 flex-1">
                    <div
                        class="flex size-10 sm:size-12 shrink-0 items-center justify-center rounded-xl {isDestructive
                            ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'
                            : variant === 'warning'
                              ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                              : variant === 'success'
                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                : 'bg-primary/10 text-primary border border-primary/20'}"
                    >
                        {#if isDestructive}
                            <TriangleAlert class="size-5 sm:size-6" />
                        {:else if variant === 'warning'}
                            <ShieldAlert class="size-5 sm:size-6" />
                        {:else if variant === 'success'}
                            <CheckCircle2 class="size-5 sm:size-6" />
                        {:else}
                            <HelpCircle class="size-5 sm:size-6" />
                        {/if}
                    </div>
                    <div class="min-w-0 flex-1 flex items-center">
                        <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                            {title}
                        </DialogTitle>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={handleCancel}
                    class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted dark:hover:bg-secondary/60 hover:text-foreground transition-colors cursor-pointer shrink-0"
                    aria-label="Cerrar"
                >
                    <X class="size-4" />
                </button>
            </div>
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
                variant="secondary"
                onclick={handleCancel}
                disabled={isBusy}
                class="min-h-[44px] px-4 rounded-xl text-sm font-semibold"
            >
                {cancelText}
            </Button>
            <Button
                type="button"
                variant={isDestructive
                    ? 'destructive'
                    : variant === 'warning'
                      ? 'warning'
                      : variant === 'success'
                        ? 'success'
                        : 'default'}
                onclick={handleConfirm}
                disabled={isBusy}
                class="min-h-[44px] px-4 rounded-xl text-sm font-semibold min-w-28 shadow-xs"
            >
                {isBusy ? 'Procesando...' : confirmText}
            </Button>
        </DialogFooter>

    </DialogContent>
</Dialog>
