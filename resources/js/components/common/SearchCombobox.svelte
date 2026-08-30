<script lang="ts" generics="T">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';
    import Search from 'lucide-svelte/icons/search';
    import X from 'lucide-svelte/icons/x';
    import Loader2 from 'lucide-svelte/icons/loader-2';

    let {
        value = $bindable(''),
        items = [],
        placeholder = 'Buscar...',
        maxResults = 6,
        debounceMs = 0,
        searchKey,
        filterFn,
        getItemKey,
        getItemLabel,
        loading = false,
        disabled = false,
        autofocus = false,
        class: className = '',
        inputClass = '',
        dropdownClass = '',
        item: itemSnippet,
        children: childrenSnippet,
        empty: emptySnippet,
        onsearch,
        onselect,
        oninput,
        onclear,
    }: {
        value?: string;
        items?: T[];
        placeholder?: string;
        maxResults?: number;
        debounceMs?: number;
        searchKey?: keyof T | ((item: T) => string);
        filterFn?: (item: T, query: string) => boolean;
        getItemKey?: (item: T, index: number) => string | number;
        getItemLabel?: (item: T) => string;
        loading?: boolean;
        disabled?: boolean;
        autofocus?: boolean;
        class?: string;
        inputClass?: string;
        dropdownClass?: string;
        item?: Snippet<[T, number]>;
        children?: Snippet<[T, number]>;
        empty?: Snippet<[string]>;
        onsearch?: (query: string) => void;
        onselect?: (item: T) => void;
        oninput?: (value: string) => void;
        onclear?: () => void;
    } = $props();

    let isOpen = $state(false);
    let highlightedIndex = $state(-1);
    let containerRef: HTMLDivElement | null = $state(null);
    let inputRef: HTMLInputElement | null = $state(null);
    let debounceTimer: ReturnType<typeof setTimeout> | undefined;

    function defaultItemKey(item: T, index: number): string | number {
        if (getItemKey) return getItemKey(item, index);
        if (item && typeof item === 'object' && 'id' in item) {
            return (item as { id: string | number }).id;
        }
        return index;
    }

    function defaultItemLabel(item: T): string {
        if (getItemLabel) return getItemLabel(item);
        if (item && typeof item === 'object') {
            const obj = item as Record<string, any>;
            return obj.name || obj.title || obj.order_number || obj.label || '';
        }
        return String(item ?? '');
    }

    function matchItem(item: T, query: string): boolean {
        if (!query) return false;
        const q = query.toLowerCase().trim();

        if (filterFn) {
            return filterFn(item, q);
        }

        if (typeof searchKey === 'function') {
            return searchKey(item).toLowerCase().includes(q);
        }

        if (searchKey && typeof item === 'object' && item !== null) {
            const val = (item as Record<string, any>)[searchKey as string];
            return String(val ?? '').toLowerCase().includes(q);
        }

        if (item && typeof item === 'object') {
            const obj = item as Record<string, any>;
            const candidates = [
                obj.name,
                obj.sku,
                obj.barcode,
                obj.cuit,
                obj.dni,
                obj.email,
                obj.phone,
                obj.order_number,
                obj.customer_name,
                obj.delivery_address,
                obj.title,
                obj.description,
            ];

            return candidates.some((field) => {
                if (field !== null && field !== undefined) {
                    return String(field).toLowerCase().includes(q);
                }
                return false;
            });
        }

        return String(item ?? '').toLowerCase().includes(q);
    }

    let matchingItems = $derived.by(() => {
        if (!value || value.trim().length === 0 || !items || items.length === 0) {
            return [];
        }
        return items.filter((it) => matchItem(it, value)).slice(0, maxResults);
    });

    function handleInput(e: Event) {
        const target = e.target as HTMLInputElement;
        value = target.value;
        highlightedIndex = -1;
        isOpen = true;

        oninput?.(value);

        if (debounceMs > 0) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                onsearch?.(value);
            }, debounceMs);
        }
    }

    function handleFocus() {
        if (value.trim().length > 0 && matchingItems.length > 0) {
            isOpen = true;
        }
    }

    function handleKeyDown(e: KeyboardEvent) {
        if (disabled) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!isOpen) {
                isOpen = true;
                highlightedIndex = 0;
            } else if (matchingItems.length > 0) {
                highlightedIndex = (highlightedIndex + 1) % matchingItems.length;
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (!isOpen) {
                isOpen = true;
                highlightedIndex = matchingItems.length - 1;
            } else if (matchingItems.length > 0) {
                highlightedIndex = (highlightedIndex - 1 + matchingItems.length) % matchingItems.length;
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (isOpen && highlightedIndex >= 0 && matchingItems[highlightedIndex]) {
                selectItem(matchingItems[highlightedIndex]);
            } else {
                isOpen = false;
                onsearch?.(value);
            }
        } else if (e.key === 'Escape') {
            isOpen = false;
            highlightedIndex = -1;
        } else if (e.key === 'Tab') {
            isOpen = false;
            highlightedIndex = -1;
        }
    }

    function selectItem(item: T) {
        isOpen = false;
        highlightedIndex = -1;
        onselect?.(item);
    }

    function clear() {
        value = '';
        isOpen = false;
        highlightedIndex = -1;
        onclear?.();
        onsearch?.('');
        inputRef?.focus();
    }

    function handleDocumentClick(e: MouseEvent) {
        if (containerRef && !containerRef.contains(e.target as Node)) {
            isOpen = false;
            highlightedIndex = -1;
        }
    }

    $effect(() => {
        if (typeof window !== 'undefined') {
            document.addEventListener('click', handleDocumentClick);
            return () => {
                document.removeEventListener('click', handleDocumentClick);
            };
        }
    });
</script>

<div class={cn('relative w-full', className)} bind:this={containerRef}>
    <!-- Input Container -->
    <div class="relative flex items-center w-full">
        <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground shrink-0" />

        <input
            bind:this={inputRef}
            type="text"
            role="combobox"
            aria-expanded={isOpen && matchingItems.length > 0}
            aria-autocomplete="list"
            aria-controls="search-combobox-results"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            {placeholder}
            {disabled}
            {autofocus}
            value={value}
            oninput={handleInput}
            onfocus={handleFocus}
            onkeydown={handleKeyDown}
            class={cn(

                'h-10 min-h-[40px] w-full rounded-xl border border-border/80 bg-background/90 pl-9 pr-9 text-sm text-foreground placeholder:text-muted-foreground/70 transition-all duration-150',
                'hover:border-border hover:bg-background',
                'focus:bg-background focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs',
                disabled && 'opacity-60 cursor-not-allowed bg-muted/40',
                inputClass
            )}
        />

        {#if loading}
            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground flex items-center justify-center">
                <Loader2 class="size-4 animate-spin text-primary" />
            </div>
        {:else if value}
            <button
                type="button"
                onclick={clear}
                class="absolute right-2.5 top-1/2 -translate-y-1/2 size-6 rounded-lg flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-colors cursor-pointer"
                aria-label="Limpiar búsqueda"
            >
                <X class="size-3.5" />
            </button>
        {/if}
    </div>

    <!-- Dropdown / Results Popover -->
    {#if isOpen && matchingItems.length > 0}
        <div
            id="search-combobox-results"
            role="listbox"
            class={cn(
                'absolute left-0 right-0 top-full mt-1.5 z-50 max-h-80 overflow-y-auto rounded-xl border border-border/80 bg-popover/95 p-1.5 text-popover-foreground shadow-lg backdrop-blur-md transition-all duration-150',
                dropdownClass
            )}
        >
            <div class="px-2.5 py-1 text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                Sugerencias directas ({matchingItems.length})
            </div>

            <div class="space-y-0.5 mt-0.5">
                {#each matchingItems as matchItem, idx (defaultItemKey(matchItem, idx))}
                    <button
                        type="button"
                        role="option"
                        aria-selected={highlightedIndex === idx}
                        onclick={() => selectItem(matchItem)}
                        class={cn(
                            'w-full text-left rounded-lg px-2.5 py-2 text-xs transition-colors flex items-center justify-between gap-3 cursor-pointer min-h-[38px]',
                            highlightedIndex === idx
                                ? 'bg-primary/10 text-primary font-medium'
                                : 'hover:bg-muted/70 text-foreground'
                        )}
                    >
                        {#if itemSnippet || childrenSnippet}
                            {@render (itemSnippet ?? childrenSnippet)?.(matchItem, idx)}
                        {:else}
                            <span class="truncate font-medium">{defaultItemLabel(matchItem)}</span>
                        {/if}
                    </button>
                {/each}
            </div>

            <div class="border-t border-border/50 mt-1.5 pt-1.5 px-2.5 py-1 flex items-center justify-between text-[11px] text-muted-foreground">
                <span>Presiona <kbd class="px-1.5 py-0.5 rounded bg-muted text-[10px] font-mono border border-border/70 text-foreground font-semibold">↵ Enter</kbd> para filtrar todo</span>
            </div>
        </div>
    {/if}
</div>
