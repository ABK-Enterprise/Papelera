<script lang="ts">
    import { cn } from '@/lib/utils';
    import { ChevronsUpDown, Check } from 'lucide-svelte';
    import { onMount } from 'svelte';

    export type StatScreenId = 'inicial' | 'pedidos' | 'clientes' | 'proveedores' | 'productos';

    export interface StatScreenOption {
        id: StatScreenId;
        title: string;
        subtitle: string;
    }

    const DEFAULT_OPTIONS: StatScreenOption[] = [
        { id: 'inicial', title: 'Pantalla Inicial', subtitle: 'datos, gráficos' },
        { id: 'pedidos', title: 'Pedidos', subtitle: 'datos, gráficos' },
        { id: 'clientes', title: 'Clientes', subtitle: 'listado, datos' },
        { id: 'proveedores', title: 'Proveedores', subtitle: 'listado, datos' },
        { id: 'productos', title: 'Productos', subtitle: 'listado, datos, gráficos' },
    ];

    let {
        value = $bindable('inicial'),
        options = DEFAULT_OPTIONS,
        class: className = '',
        disabled = false,
        onchange = (id: StatScreenId) => {},
    }: {
        value?: StatScreenId;
        options?: StatScreenOption[];
        class?: string;
        disabled?: boolean;
        onchange?: (id: StatScreenId) => void;
    } = $props();

    let isOpen = $state(false);
    let triggerRef = $state<HTMLButtonElement | null>(null);
    let menuRef = $state<HTMLDivElement | null>(null);
    const itemRefs: (HTMLButtonElement | null)[] = [];
    let hoveredIndex = $state<number | null>(null);

    function registerItem(node: HTMLButtonElement, index: number) {
        itemRefs[index] = node;
        return {
            destroy() {
                itemRefs[index] = null;
            },
        };
    }

    const activeOption = $derived(
        options.find((opt) => opt.id === value) ?? options[0]
    );

    const activeIndex = $derived(
        options.findIndex((opt) => opt.id === value)
    );

    let pillTop = $state(0);
    let pillHeight = $state(0);
    let pillOpacity = $state(0);

    function updatePill(index: number | null) {
        if (index === null || index < 0 || !itemRefs[index]) {
            pillOpacity = 0;
            return;
        }

        const el = itemRefs[index];
        if (el) {
            pillTop = el.offsetTop;
            pillHeight = el.offsetHeight;
            pillOpacity = 1;
        }
    }

    $effect(() => {
        if (isOpen) {
            const targetIdx = hoveredIndex !== null ? hoveredIndex : (activeIndex >= 0 ? activeIndex : 0);
            requestAnimationFrame(() => {
                updatePill(targetIdx);
            });
        } else {
            pillOpacity = 0;
        }
    });

    function toggleMenu(e: MouseEvent) {
        e.stopPropagation();
        if (disabled) return;
        isOpen = !isOpen;
        if (isOpen) {
            hoveredIndex = activeIndex >= 0 ? activeIndex : 0;
        }
    }

    function selectOption(opt: StatScreenOption) {
        value = opt.id;
        isOpen = false;
        hoveredIndex = null;
        onchange(opt.id);
    }

    function handleKeyDown(e: KeyboardEvent) {
        if (disabled) return;

        if (!isOpen) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                e.preventDefault();
                isOpen = true;
                hoveredIndex = activeIndex >= 0 ? activeIndex : 0;
            }
            return;
        }

        if (e.key === 'Escape') {
            e.preventDefault();
            isOpen = false;
            triggerRef?.focus();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            const current = hoveredIndex ?? (activeIndex >= 0 ? activeIndex : 0);
            hoveredIndex = (current + 1) % options.length;
            updatePill(hoveredIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const current = hoveredIndex ?? (activeIndex >= 0 ? activeIndex : 0);
            hoveredIndex = (current - 1 + options.length) % options.length;
            updatePill(hoveredIndex);
        } else if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            const targetIdx = hoveredIndex ?? (activeIndex >= 0 ? activeIndex : 0);
            if (options[targetIdx]) {
                selectOption(options[targetIdx]);
            }
        }
    }

    function handleOutsidePointer(e: PointerEvent | MouseEvent | TouchEvent) {
        if (!isOpen) return;
        const target = e.target as Node;
        if (
            triggerRef &&
            !triggerRef.contains(target) &&
            menuRef &&
            !menuRef.contains(target)
        ) {
            isOpen = false;
            hoveredIndex = null;
        }
    }

    onMount(() => {
        window.addEventListener('pointerdown', handleOutsidePointer);
        return () => {
            window.removeEventListener('pointerdown', handleOutsidePointer);
        };
    });
</script>

<div class={cn('relative inline-block text-left w-full sm:w-auto', className)}>
    <!-- Trigger Button (mismo ancho simétrico que el select de depósitos: sm:w-[350px]) -->
    <button
        bind:this={triggerRef}
        type="button"
        {disabled}
        onclick={toggleMenu}
        onkeydown={handleKeyDown}
        aria-haspopup="listbox"
        aria-expanded={isOpen}
        class={cn(
            'flex items-center justify-between gap-3 w-full sm:w-[350px] min-h-[40px] px-3.5 py-1.5',
            'rounded-lg border border-input/80 bg-select-background text-sm font-medium shadow-xs',
            'transition-colors duration-150 cursor-pointer select-none',
            'hover:bg-muted/50 dark:hover:bg-accent/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
            disabled && 'opacity-50 cursor-not-allowed pointer-events-none'
        )}
    >
        <div class="flex items-center gap-2 min-w-0 flex-1 text-left">
            <span class="font-semibold text-foreground truncate">
                {activeOption.title}
            </span>
            <span class="text-xs text-muted-foreground bg-muted/80 dark:bg-muted/50 px-2 py-0.5 rounded-md shrink-0 tabular-nums">
                {activeOption.subtitle}
            </span>
        </div>
        <ChevronsUpDown class="size-4 shrink-0 text-muted-foreground" />
    </button>

    <!-- Dropdown Menu with Gliding Pill Backdrop (+10px ampliado: sm:w-[330px]) -->
    {#if isOpen}
        <div
            bind:this={menuRef}
            class={cn(
                'absolute right-0 z-50 mt-1.5 w-full sm:w-[330px] rounded-xl border border-border/80',
                'bg-popover p-1.5 text-popover-foreground shadow-lg backdrop-blur-md',
                'animate-in fade-in-0 zoom-in-95 duration-100 ease-out origin-top-right'
            )}
            role="listbox"
            tabindex="-1"
            onmouseleave={() => {
                hoveredIndex = activeIndex >= 0 ? activeIndex : 0;
                updatePill(hoveredIndex);
            }}
        >
            <div class="relative flex flex-col gap-0.5">
                <!-- Animated Glide Pill Background (mismo contexto relativo que los botones) -->
                <div
                    class="absolute left-0 right-0 rounded-lg bg-accent/80 dark:bg-accent/40 pointer-events-none transition-all duration-150 ease-out z-0"
                    style="top: {pillTop}px; height: {pillHeight}px; opacity: {pillOpacity};"
                ></div>

                {#each options as opt, i (opt.id)}
                    {@const isSelected = opt.id === value}
                    <button
                        use:registerItem={i}
                        type="button"
                        role="option"
                        aria-selected={isSelected}
                        class={cn(
                            'group flex items-center justify-between w-full px-3 py-2.5 rounded-lg text-sm text-left',
                            'cursor-pointer transition-colors duration-75 select-none relative z-10',
                            isSelected ? 'font-semibold text-foreground' : 'text-foreground/90 font-medium'
                        )}
                        onmouseenter={() => {
                            hoveredIndex = i;
                            updatePill(i);
                        }}
                        onclick={() => selectOption(opt)}
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            {#if isSelected}
                                <Check class="size-4 text-primary shrink-0" />
                            {:else}
                                <span class="size-4 shrink-0"></span>
                            {/if}
                            <span class="truncate">{opt.title}</span>
                        </div>
                        <span class="text-xs text-muted-foreground text-right shrink-0 pl-2 tabular-nums">
                            {opt.subtitle}
                        </span>
                    </button>
                {/each}
            </div>
        </div>
    {/if}
</div>
