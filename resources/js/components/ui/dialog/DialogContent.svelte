<script lang="ts">
    import type { Snippet } from 'svelte';
    import { getContext } from 'svelte';
    import { fade, scale } from 'svelte/transition';
    import { portal } from '@/lib/portal';
    import { cn } from '@/lib/utils';
    import { DIALOG_CONTEXT, type DialogContext } from './context';

    let {
        class: className = '',
        children,
        ...restProps
    }: {
        class?: string;
        children?: Snippet;
        [key: string]: any;
    } = $props();

    const { open, setOpen } = getContext<DialogContext>(DIALOG_CONTEXT);

    const close = () => setOpen(false);
</script>

{#if open()}
    <div use:portal class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
        <button
            type="button"
            transition:fade={{ duration: 150 }}
            class="fixed inset-0 bg-black/60 backdrop-blur-xs"
            aria-label="Cerrar modal"
            onclick={close}
        ></button>
        <div
            transition:scale={{ start: 0.97, duration: 150 }}
            class={cn(
                'relative z-10 w-full max-w-lg rounded-2xl sm:rounded-3xl border border-border bg-card text-card-foreground shadow-2xl flex flex-col gap-[1px] max-h-[85vh] overflow-hidden',
                className,
            )}
            role="dialog"

            aria-modal="true"
            {...restProps}
        >
            {@render children?.()}
        </div>
    </div>
{/if}
