<script lang="ts">
    import type { Snippet } from 'svelte';
    import { getContext } from 'svelte';
    import { cn } from '@/lib/utils';
    import { DIALOG_CONTEXT, type DialogContext } from './context';

    let {
        class: className = '',
        size = 'default',
        children,
    }: {
        class?: string;
        size?: 'sm' | 'default' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl' | '5xl';
        children?: Snippet;
    } = $props();

    const { open, setOpen } = getContext<DialogContext>(DIALOG_CONTEXT);
    const close = () => setOpen(false);

    const sizeClasses: Record<string, string> = {
        sm: 'max-w-sm',
        default: 'max-w-lg',
        lg: 'max-w-xl',
        xl: 'max-w-2xl',
        '2xl': 'max-w-3xl',
        '3xl': 'max-w-4xl',
        '4xl': 'max-w-5xl',
        '5xl': 'max-w-6xl',
    };
</script>

{#if open()}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-hidden">
        <!-- Backdrop oscuro con blur suave -->
        <button
            type="button"
            class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
            aria-label="Cerrar modal"
            onclick={close}
        ></button>

        <!-- Modal Card Flotante con Margen Seguro de 16px, Max Height 85vh y Scroll Interno -->
        <div
            class={cn(
                'relative z-10 w-full rounded-2xl sm:rounded-3xl border border-border bg-card text-card-foreground shadow-2xl flex flex-col max-h-[85vh] sm:max-h-[88vh] overflow-hidden animate-in fade-in zoom-in-95 duration-150',
                sizeClasses[size] || sizeClasses.default,
                className,
            )}
            role="dialog"
            aria-modal="true"
        >
            {@render children?.()}
        </div>
    </div>
{/if}
