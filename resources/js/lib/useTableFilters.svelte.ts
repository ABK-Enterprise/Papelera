import { router } from '@inertiajs/svelte';
import { untrack } from 'svelte';

export interface UseTableFiltersOptions<T extends Record<string, any>> {
    basePath: string;
    defaultFilters: T;
    serverFilters?: () => Partial<T> | undefined;
}

export function useTableFilters<T extends Record<string, any>>(options: UseTableFiltersOptions<T>) {
    function parseValue(key: string, rawVal: string, defaultVal: any): any {
        if (typeof defaultVal === 'number' || (defaultVal === null && !isNaN(Number(rawVal)) && rawVal !== '')) {
            const num = Number(rawVal);
            return isNaN(num) ? defaultVal : num;
        }
        if (typeof defaultVal === 'boolean') {
            return rawVal === 'true' || rawVal === '1';
        }
        return rawVal;
    }

    function getInitialValues(): T {
        const initial = { ...options.defaultFilters };

        // 1. Priority: URL query parameters (stateless deep-link & page refresh retention)
        if (typeof window !== 'undefined') {
            const searchParams = new URLSearchParams(window.location.search);
            for (const key of Object.keys(options.defaultFilters)) {
                if (searchParams.has(key)) {
                    const rawVal = searchParams.get(key);
                    if (rawVal !== null && rawVal !== '') {
                        initial[key as keyof T] = parseValue(key, rawVal, options.defaultFilters[key]);
                    }
                }
            }
        }

        // 2. Server-side props hydration
        const server = options.serverFilters?.();
        if (server) {
            for (const key of Object.keys(options.defaultFilters)) {
                if (key in server && server[key] !== undefined && server[key] !== null) {
                    initial[key as keyof T] = server[key] as any;
                }
            }
        }

        return initial;
    }

    let filters = $state<T>(getInitialValues());

    // Reactive sync when server-side Inertia props change
    if (options.serverFilters) {
        $effect(() => {
            const server = options.serverFilters?.();
            if (server) {
                untrack(() => {
                    for (const key of Object.keys(options.defaultFilters)) {
                        if (key in server && server[key] !== undefined) {
                            filters[key as keyof T] = (server[key] ?? options.defaultFilters[key]) as any;
                        }
                    }
                });
            }
        });
    }

    function apply(overrides?: Partial<T>) {
        if (overrides) {
            Object.assign(filters, overrides);
        }

        const queryParams: Record<string, any> = {};
        for (const [key, value] of Object.entries(filters)) {
            const defVal = options.defaultFilters[key];
            if (value !== undefined && value !== null && value !== '' && value !== defVal) {
                queryParams[key] = value;
            }
        }

        router.get(options.basePath, queryParams, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function reset() {
        for (const [key, value] of Object.entries(options.defaultFilters)) {
            filters[key as keyof T] = value;
        }

        router.get(options.basePath, {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    const hasActiveFilters = $derived.by(() => {
        for (const [key, value] of Object.entries(filters)) {
            const defVal = options.defaultFilters[key];
            if (value !== undefined && value !== null && value !== '' && value !== defVal) {
                return true;
            }
        }
        return false;
    });

    const activeFiltersCount = $derived.by(() => {
        let count = 0;
        for (const [key, value] of Object.entries(filters)) {
            if (key === 'search' || key === 'sort_by') continue;
            const defVal = options.defaultFilters[key];
            if (value !== undefined && value !== null && value !== '' && value !== defVal) {
                count++;
            }
        }
        return count;
    });

    return {
        filters,
        apply,
        reset,
        get hasActiveFilters() {
            return hasActiveFilters;
        },
        get activeFiltersCount() {
            return activeFiltersCount;
        },
    };
}
