<script lang="ts" generics="T">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    let {
        items = [],
        class: className = '',
        tableClass = '',
        headerClass = '',
        bodyClass = '',
        rowClass,
        header,
        headers,
        row,
        children,
        empty,
        emptyText = 'No hay datos disponibles.',
    }: {
        items?: T[];
        class?: string;
        tableClass?: string;
        headerClass?: string;
        bodyClass?: string;
        rowClass?: ((item: T, index: number) => string) | string;
        header?: Snippet;
        headers?: Snippet;
        row?: Snippet<[T, number]>;
        children?: Snippet<[T, number]>;
        empty?: Snippet;
        emptyText?: string;
    } = $props();

    function getItemKey(item: T, index: number): string | number {
        if (item && typeof item === 'object' && 'id' in item) {
            return (item as { id: string | number }).id;
        }
        return index;
    }

    function getRowClass(item: T, index: number): string {
        if (typeof rowClass === 'function') {
            return rowClass(item, index);
        }
        return rowClass ?? '';
    }
</script>

{#if items.length > 0}
    <div class={cn('rounded-xl overflow-hidden border border-border/60 bg-card shadow-xs', className)}>
        <div class="overflow-x-auto">
            <table class={cn('w-full text-left border-collapse', tableClass)}>
                {#if header || headers}
                    <thead class="bg-muted/50 dark:bg-secondary/35">
                        <tr class={cn('border-b border-border/80 text-xs font-semibold text-muted-foreground uppercase tracking-wider', headerClass)}>
                            {@render (header ?? headers)?.()}
                        </tr>
                    </thead>
                {/if}
                <tbody class={cn('divide-y divide-border/40 text-sm', bodyClass)}>
                    {#each items as item, index (getItemKey(item, index))}
                        <tr class={cn('transition-colors hover:bg-muted/40 dark:hover:bg-secondary/35', getRowClass(item, index))}>
                            {@render (row ?? children)?.(item, index)}
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </div>
{:else}
    {#if empty}
        {@render empty()}
    {:else}
        <div class={cn('rounded-xl border border-dashed border-border/80 p-8 text-center bg-card/80 dark:bg-card flex flex-col items-center justify-center shadow-xs', className)}>
            <p class="text-sm text-muted-foreground">{emptyText}</p>
        </div>
    {/if}
{/if}
