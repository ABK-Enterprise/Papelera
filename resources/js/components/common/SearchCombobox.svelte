<!-- resources/js/components/common/SearchCombobox.svelte -->
<script lang="ts" generics="T extends Record<string, any>">
    import type { Snippet } from 'svelte';
    import { clickOutside } from '@/lib/actions/clickOutside';
    import Search from 'lucide-svelte/icons/search';
    import X from 'lucide-svelte/icons/x';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import HighlightMatch from '@/components/common/HighlightMatch.svelte';


    let {
        id = '',
        value = $bindable(''),
        placeholder = 'Buscar...',
        items = [],
        loading = false,
        debounceMs = 300,
        showDropdown = true,
        searchKeys,
        itemSnippet,
        emptySnippet,
        onsearch,
        onselect,
        onclear,
        class: className = '',
        inputClass = '',
    }: {
        id?: string;
        value?: string;
        placeholder?: string;
        items?: T[];
        loading?: boolean;
        debounceMs?: number;
        showDropdown?: boolean;
        searchKeys?: (keyof T | string)[];
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

    let displayItems = $derived.by(() => {
        if (!items || items.length === 0) return [];
        if (!value || !value.trim()) {
            return items.slice(0, 5);
        }
        const q = value.toLowerCase().trim();
        return items
            .filter((item) => {
                if (searchKeys && searchKeys.length > 0) {
                    return searchKeys.some((k) => {
                        const val = item[k as keyof T];
                        return val !== undefined && val !== null && String(val).toLowerCase().includes(q);
                    });
                }
                const str = Object.entries(item)
                    .filter(([key, v]) => !['id', 'tenant_id', 'module_id', 'branch_id', 'warehouse_id', 'customer_id', 'supplier_id', 'created_at', 'updated_at', 'deleted_at'].includes(key) && (typeof v === 'string' || typeof v === 'number'))
                    .map(([, v]) => String(v))
                    .join(' ')
                    .toLowerCase();
                return str.includes(q);
            })
            .slice(0, 5);
    });


    let lastSearchedVal: string | null = null;

    function handleInput(e: Event) {
        const val = (e.target as HTMLInputElement).value;
        value = val;
        activeIndex = -1;

        if (debounceMs <= 0) {
            lastSearchedVal = val;
            onsearch?.(val);
            if (showDropdown) {
                isOpen = true;
            }
        } else {
            if (timer) clearTimeout(timer);
            timer = setTimeout(() => {
                lastSearchedVal = val;
                onsearch?.(val);
                if (showDropdown) {
                    isOpen = true;
                }
            }, debounceMs);
        }
    }

    function handleKeyDown(e: KeyboardEvent) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (isOpen && activeIndex >= 0 && displayItems[activeIndex]) {
                selectItem(displayItems[activeIndex]);
            } else {
                if (timer) {
                    clearTimeout(timer);
                    timer = null;
                }
                const currentVal = (e.target as HTMLInputElement)?.value ?? inputElement?.value ?? value;
                value = currentVal;
                lastSearchedVal = currentVal;
                onsearch?.(currentVal);
                isOpen = false;
            }
            return;
        }




        if (!isOpen || displayItems.length === 0) {
            return;
        }

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                activeIndex = (activeIndex + 1) % displayItems.length;
                break;
            case 'ArrowUp':
                e.preventDefault();
                activeIndex = activeIndex <= 0 ? displayItems.length - 1 : activeIndex - 1;
                break;
            case 'Escape':
                isOpen = false;
                break;
        }
    }

    function selectItem(item: T) {
        if (searchKeys && searchKeys.length > 0) {
            const firstKey = searchKeys[0] as keyof T;
            if (item[firstKey] !== undefined && item[firstKey] !== null) {
                value = String(item[firstKey]);
            }
        } else if (item.name || item.title || item.product_name) {
            value = String(item.name || item.title || item.product_name);
        }
        onselect?.(item);
        isOpen = false;
        activeIndex = -1;
    }


    function clear() {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
        value = '';
        lastSearchedVal = '';
        isOpen = false;
        activeIndex = -1;
        onclear?.();
        inputElement?.focus();
    }


    function handleFocusOrClick() {
        if (showDropdown && (displayItems.length > 0 || emptySnippet)) {
            isOpen = true;
        }
    }

    const listboxId = 'combobox-listbox-' + Math.random().toString(36).substring(2, 9);
</script>

<div
    class="relative w-full {className}"
    use:clickOutside={{ callback: () => (isOpen = false), enabled: isOpen }}
>
    <div class="relative flex items-center">
        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
        <input
            bind:this={inputElement}
            id={id || undefined}
            type="text"
            bind:value={value}
            oninput={handleInput}
            onkeydown={handleKeyDown}
            onfocus={handleFocusOrClick}
            onclick={handleFocusOrClick}
            {placeholder}
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            role="combobox"
            aria-expanded={isOpen}
            aria-controls={isOpen ? listboxId : undefined}
            class="h-10 w-full rounded-xl border border-border/80 bg-background pl-9 pr-9 text-xs sm:text-sm text-foreground placeholder:text-muted-foreground shadow-2xs focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary transition-all disabled:cursor-not-allowed disabled:bg-transparent disabled:border-dashed disabled:text-muted-foreground/60 {inputClass}"
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
        {#if displayItems.length > 0}
            <div class="absolute left-0 right-0 top-full mt-1.5 bg-popover text-popover-foreground rounded-2xl border border-border/90 shadow-2xl shadow-accent/15 z-50 overflow-hidden animate-in fade-in duration-150 p-1.5">
                <div id={listboxId} class="max-h-64 overflow-y-auto space-y-0.5 custom-scrollbar" role="listbox">
                    {#each displayItems as item, i (item.id || i)}
                        <button
                            type="button"
                            role="option"
                            aria-selected={activeIndex === i}
                            class="w-full px-3 py-2 rounded-xl text-left text-xs transition-all flex items-center justify-between gap-2 cursor-pointer {activeIndex === i ? 'bg-accent text-accent-foreground font-semibold shadow-2xs' : 'text-foreground hover:bg-accent/40 hover:text-accent-foreground'}"
                            onclick={() => selectItem(item)}
                        >
                            {#if itemSnippet}
                                {@render itemSnippet(item, i)}
                            {:else}
                                <div class="flex flex-col min-w-0 flex-1 text-left">
                                    <span class="truncate font-medium text-xs">
                                        <HighlightMatch text={item.name ?? item.title ?? item.product_name ?? item.code ?? JSON.stringify(item)} query={value} />
                                    </span>
                                    {#if item.sku ?? item.product_sku}
                                        <span class="text-[10px] text-muted-foreground font-mono">
                                            SKU: <HighlightMatch text={item.sku ?? item.product_sku} query={value} />
                                        </span>
                                    {/if}
                                </div>
                            {/if}

                        </button>
                    {/each}
                </div>
            </div>
        {:else if emptySnippet}
            <div class="absolute left-0 right-0 top-full mt-1.5 bg-popover text-popover-foreground rounded-2xl border border-border/90 shadow-2xl shadow-accent/15 z-50 overflow-hidden animate-in fade-in duration-150 p-3">
                {@render emptySnippet()}
            </div>
        {/if}
    {/if}
</div>

