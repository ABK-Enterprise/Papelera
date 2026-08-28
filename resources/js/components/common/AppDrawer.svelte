<script lang="ts">
    import { type Snippet } from 'svelte';
    import { fly, fade } from 'svelte/transition';
    import X from 'lucide-svelte/icons/x';
    import Button from '@/components/ui/button/Button.svelte';

    let {
        open = $bindable(false),
        title = '',
        description = '',
        children,
        footer,
    }: {
        open: boolean;
        title?: string;
        description?: string;
        children?: Snippet;
        footer?: Snippet;
    } = $props();

    function close() {
        open = false;
    }

    function onKeydown(e: KeyboardEvent) {
        if (e.key === 'Escape' && open) {
            close();
        }
    }
</script>

<svelte:window onkeydown={onKeydown} />

{#if open}
    <!-- Backdrop oscuro con fade -->
    <div
        role="presentation"
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs transition-opacity"
        transition:fade={{ duration: 200 }}
        onclick={close}
    ></div>

    <!-- Drawer / Bottom Sheet contenedor con animación fly desde abajo -->
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="drawer-title"
        class="fixed inset-x-0 bottom-0 z-50 max-h-[90vh] flex flex-col rounded-t-[20px] border-t border-border/80 bg-background shadow-2xl overflow-hidden focus:outline-none"
        transition:fly={{ y: '100%', duration: 280 }}
    >
        <!-- Tirador / Drag handle visual característico de Google / iOS -->
        <div class="flex items-center justify-center pt-3 pb-1">
            <div class="h-1.5 w-12 rounded-full bg-muted-foreground/30"></div>
        </div>

        <!-- Cabecera del Drawer -->
        {#if title || description}
            <div class="flex items-start justify-between px-5 pt-2 pb-3 border-b border-border/50">
                <div class="space-y-1 pr-4">
                    {#if title}
                        <h2 id="drawer-title" class="text-lg font-bold text-foreground tracking-tight leading-tight">
                            {title}
                        </h2>
                    {/if}
                    {#if description}
                        <p class="text-xs text-muted-foreground leading-normal">
                            {description}
                        </p>
                    {/if}
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8 min-h-[32px] rounded-full text-muted-foreground hover:text-foreground shrink-0"
                    onclick={close}
                    aria-label="Cerrar panel"
                >
                    <X class="size-4" />
                </Button>
            </div>
        {/if}

        <!-- Contenido scrolleable vertical sin overflow horizontal -->
        <div class="flex-1 overflow-y-auto px-5 py-4 overscroll-contain">
            {@render children?.()}
        </div>

        <!-- Footer con acciones -->
        {#if footer}
            <div class="px-5 py-3.5 border-t border-border/60 bg-muted/20 flex items-center justify-end gap-2.5">
                {@render footer()}
            </div>
        {/if}
    </div>
{/if}
