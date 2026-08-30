<!-- resources/js/components/common/SearchCombobox.svelte -->
<script lang="ts" generics="T extends Record<string, any>">
    import type { Snippet } from 'svelte';
    import { clickOutside } from '@/lib/actions/clickOutside';
    import Search from 'lucide-svelte/icons/search';
    import X from 'lucide-svelte/icons/x';
    import Loader2 from 'lucide-svelte/icons/loader-2';

    let {
        value = $bindable(''),
        placeholder = 'Buscar...',
        items = [],
        loading = false,
        debounceMs = 300,
        showDropdown = true,
        itemSnippet,
        emptySnippet,
        onsearch,
        onselect,
        onclear,
        class: className = '',
        inputClass = '',
    }: {
        value?: string;
        placeholder?: string;
        items?: T[];
        loading?: boolean;
        debounceMs?: number;
        showDropdown?: boolean;
        itemSnippet?: Snippet<[T, number]>;
        emptySnippet?: Snippet;
        onsearch?: (query: string) => void;
        onselect?: (item: T) => void;
        onclear?: () => void;
        class?: string;
        inputClass?: string;
    } = $props();

    let isOpen = $state(false);
    let activeIndex = $state(-1);
    let timer: ReturnType<typeof setTimeout> | null = null;
    let inputElement = $state<HTMLInputElement | null>(null);

    function handleInput(e: Event) {
        const val = (e.target as HTMLInputElement).value;
        value = val;
        activeIndex = -1;
        isOpen = showDropdown && val.trim().length > 0 && items.length > 0;

        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            onsearch?.(val);
            if (showDropdown && val.trim().length > 0) {
                isOpen = true;
            }
        }, debounceMs);
    }

    function handleKeyDown(e: KeyboardEvent) {
        if (!isOpen || items.length === 0) {
            if (e.key === 'Enter') {
                if (timer) clearTimeout(timer);
                onsearch?.(value);
            }
            return;
        }

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                break;
            case 'ArrowUp':
                e.preventDefault();
                activeIndex = activeIndex <= 0 ? items.length - 1 : activeIndex - 1;
                break;
            case 'Enter':
                e.preventDefault();
                if (activeIndex >= 0 && items[activeIndex]) {
                    selectItem(items[activeIndex]);
                } else {
                    if (timer) clearTimeout(timer);
                    onsearch?.(value);
                    isOpen = false;
                }
                break;
            case 'Escape':
                isOpen = false;
                break;
        }
    }

    function selectItem(item: T) {
        onselect?.(item);
        isOpen = false;
        activeIndex = -1;
    }

    function clear() {
        value = '';
        isOpen = false;
        activeIndex = -1;
        onclear?.();
        inputElement?.focus();
    }
</script>

<div
    class="relative w-full {className}"
    use:clickOutside={{ callback: () => (isOpen = false), enabled: isOpen }}
>
    <div class="relative flex items-center">
        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
        <input
            bind:this={inputElement}
            type="text"
            {value}
            oninput={handleInput}
            onkeydown={handleKeyDown}
            onfocus={() => {
                if (showDropdown && value.trim().length > 0 && items.length > 0) {
                    isOpen = true;
                }
            }}
            {placeholder}
            role="combobox"
            aria-expanded={isOpen}
            aria-autocomplete="list"
            class="h-10 w-full rounded-xl border border-input bg-card pl-9 pr-9 text-xs sm:text-sm text-foreground placeholder:text-muted-foreground shadow-2xs focus:ring-1 focus:ring-primary outline-none transition-all {inputClass}"
        />

        {#if loading}
            <Loader2 class="absolute right-3 top-1/2 -translate-y-1/2 size-4 animate-spin text-muted-foreground" />
        {:else if value}
            <button
                type="button"
                onclick={clear}
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-0.5 rounded cursor-pointer transition-colors"
                aria-label="Limpiar búsqueda"
            >
                <X class="size-3.5" />
            </button>
        {/if}
    </div>

    {#if isOpen && showDropdown}
        <div class="absolute left-0 right-0 top-full mt-1.5 bg-popover text-popover-foreground rounded-2xl border border-border shadow-xl z-50 overflow-hidden animate-in fade-in duration-150">
            <div class="max-h-64 overflow-y-auto divide-y divide-border/40 custom-scrollbar" role="listbox">
                {#each items as item, i (item.id || i)}
                    <button
                        type="button"
                        role="option"
                        aria-selected={activeIndex === i}
                        class="w-full px-3.5 py-2.5 text-left text-xs transition-colors flex items-center justify-between gap-2 cursor-pointer {activeIndex === i ? 'bg-muted/80 text-foreground font-medium' : 'hover:bg-muted/50 text-foreground'}"
                        onclick={() => selectItem(item)}
                    >
                        {#if itemSnippet}
                            {@render itemSnippet(item, i)}
                        {:else}
                            <span class="truncate">{item.name || item.title || item.code || JSON.stringify(item)}</span>
                        {/if}
                    </button>
                {/each}

                {#if items.length === 0 && !loading}
                    <div class="p-4 text-center text-xs text-muted-foreground italic">
                        {#if emptySnippet}
                            {@render emptySnippet()}
                        {:else}
                            No se encontraron resultados coincidentes.
                        {/if}
                    </div>
                {/if}
            </div>
        </div>
    {/if}
</div>
