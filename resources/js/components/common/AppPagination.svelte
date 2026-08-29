<script lang="ts">
    import { Pagination } from 'bits-ui';
    import { router } from '@inertiajs/svelte';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import MoreHorizontal from 'lucide-svelte/icons/more-horizontal';

    export interface PaginationMeta {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from?: number | null;
        to?: number | null;
    }

    let {
        pagination = null,
        count = 0,
        perPage = 10,
        page = $bindable(1),
        siblingCount = 1,
        onPageChange,
        class: className = '',
    }: {
        pagination?: PaginationMeta | null;
        count?: number;
        perPage?: number;
        page?: number;
        siblingCount?: number;
        onPageChange?: (newPage: number) => void;
        class?: string;
    } = $props();

    let effectiveCount = $derived(pagination ? pagination.total : count);
    let effectivePerPage = $derived(pagination ? pagination.per_page : perPage);
    let effectivePage = $derived(pagination ? pagination.current_page : page);

    function handlePageChange(newPage: number) {
        page = newPage;
        if (onPageChange) {
            onPageChange(newPage);
        } else {
            // Default Inertia router navigation preserving filters
            const url = new URL(window.location.href);
            url.searchParams.set('page', String(newPage));
            router.get(
                url.pathname + url.search,
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                }
            );
        }
    }
</script>

{#if effectiveCount > effectivePerPage}
    <div class="w-full pt-4 pb-2 {className}">
        <Pagination.Root
            count={effectiveCount}
            perPage={effectivePerPage}
            {siblingCount}
            page={effectivePage}
            onPageChange={handlePageChange}
        >
            {#snippet children({ pages, range })}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 w-full">
                    <span class="text-xs text-muted-foreground tabular-nums text-center sm:text-left">
                        Mostrando <strong class="font-semibold text-foreground">{range.start + 1}</strong> a <strong class="font-semibold text-foreground">{range.end}</strong> de <strong class="font-semibold text-foreground">{effectiveCount}</strong> resultados
                    </span>

                    <div class="flex items-center justify-center gap-1.5">
                        <Pagination.PrevButton
                            class="inline-flex size-8 items-center justify-center rounded-xl border border-input bg-background text-foreground hover:bg-muted disabled:opacity-30 disabled:pointer-events-none transition-all shadow-2xs text-xs"
                            aria-label="Página anterior"
                        >
                            <ChevronLeft class="size-4" />
                        </Pagination.PrevButton>

                        {#each pages as pageItem (pageItem.key)}
                            {#if pageItem.type === 'ellipsis'}
                                <div class="flex size-8 items-center justify-center text-muted-foreground">
                                    <MoreHorizontal class="size-4" />
                                </div>
                            {:else}
                                <Pagination.Page
                                    page={pageItem}
                                    class="inline-flex size-8 items-center justify-center rounded-xl border text-xs font-semibold tabular-nums transition-all shadow-2xs {pageItem.value === effectivePage ? 'bg-primary text-primary-foreground border-primary shadow-xs scale-105' : 'border-input bg-background text-foreground hover:bg-muted'}"
                                    aria-label={`Página ${pageItem.value}`}
                                >
                                    {pageItem.value}
                                </Pagination.Page>
                            {/if}
                        {/each}

                        <Pagination.NextButton
                            class="inline-flex size-8 items-center justify-center rounded-xl border border-input bg-background text-foreground hover:bg-muted disabled:opacity-30 disabled:pointer-events-none transition-all shadow-2xs text-xs"
                            aria-label="Página siguiente"
                        >
                            <ChevronRight class="size-4" />
                        </Pagination.NextButton>
                    </div>
                </div>
            {/snippet}
        </Pagination.Root>
    </div>
{/if}
