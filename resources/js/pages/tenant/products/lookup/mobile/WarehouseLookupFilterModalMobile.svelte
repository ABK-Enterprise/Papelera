<script lang="ts">
    import FilterModalLayoutMobile, { type FilterTabItem } from '@/components/common/FilterModalLayoutMobile.svelte';
    import WarehouseIcon from 'lucide-svelte/icons/warehouse';
    import Tag from 'lucide-svelte/icons/tag';
    import Boxes from 'lucide-svelte/icons/boxes';
    import Check from 'lucide-svelte/icons/check';

    interface WarehouseOption {
        id: number;
        name: string;
        code: string;
        is_default: boolean;
    }

    interface CategoryOption {
        id: number;
        name: string;
    }

    let {
        open = $bindable(false),
        warehouses = [],
        categories = [],
        selectedWarehouseId = '',
        selectedCategoryId = '',
        selectedStockStatus = '',
        onapply,
        onreset,
    }: {
        open?: boolean;
        warehouses?: WarehouseOption[];
        categories?: CategoryOption[];
        selectedWarehouseId?: string | number | null;
        selectedCategoryId?: string | number | null;
        selectedStockStatus?: string | null;
        onapply?: (filters: { warehouse_id: string | number; category_id: string | number; stock_status: string }) => void;
        onreset?: () => void;
    } = $props();

    let localWarehouseId = $state<string | number>('');
    let localCategoryId = $state<string | number>('');
    let localStockStatus = $state<string>('');
    let activeTab = $state<'warehouse' | 'category' | 'stock'>('warehouse');

    $effect(() => {
        if (open) {
            localWarehouseId = selectedWarehouseId ?? '';
            localCategoryId = selectedCategoryId ?? '';
            localStockStatus = selectedStockStatus ?? '';
        }
    });

    const activeCount = $derived(
        (localWarehouseId ? 1 : 0) +
        (localCategoryId ? 1 : 0) +
        (localStockStatus ? 1 : 0)
    );

    const tabs = $derived<FilterTabItem[]>([
        {
            id: 'warehouse',
            label: 'Depósitos',
            icon: WarehouseIcon,
            isActive: Boolean(localWarehouseId),
            badge: localWarehouseId ? '1' : null,
        },
        {
            id: 'category',
            label: 'Categorías',
            icon: Tag,
            isActive: Boolean(localCategoryId),
            badge: localCategoryId ? '1' : null,
        },
        {
            id: 'stock',
            label: 'Estado de Stock',
            icon: Boxes,
            isActive: Boolean(localStockStatus),
            badge: localStockStatus ? '1' : null,
        },
    ]);

    function handleApply() {
        onapply?.({
            warehouse_id: localWarehouseId,
            category_id: localCategoryId,
            stock_status: localStockStatus,
        });
        open = false;
    }

    function handleReset() {
        localWarehouseId = '';
        localCategoryId = '';
        localStockStatus = '';
        onreset?.();
        open = false;
    }
</script>

<FilterModalLayoutMobile
    bind:open
    title="Filtros de Depósito"
    description="Filtra las existencias por depósito físico, categoría o nivel de stock."
    {tabs}
    bind:activeTab
    {activeCount}
    onapply={handleApply}
    onreset={handleReset}
>
    <!-- Contenido según Pestaña Activa -->
    {#if activeTab === 'warehouse'}
        <div class="space-y-1">
            <button
                type="button"
                onclick={() => (localWarehouseId = '')}
                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-left text-xs sm:text-sm font-medium transition-colors {localWarehouseId === '' ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'}"
            >
                <span>Todos los Depósitos</span>
                {#if localWarehouseId === ''}
                    <Check class="size-4 text-primary" />
                {/if}
            </button>
            {#each warehouses as wh (wh.id)}
                <button
                    type="button"
                    onclick={() => (localWarehouseId = wh.id)}
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-left text-xs sm:text-sm font-medium transition-colors {String(localWarehouseId) === String(wh.id) ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'}"
                >
                    <div class="flex flex-col min-w-0 pr-2">
                        <span class="truncate">{wh.name}</span>
                        <span class="text-[11px] text-muted-foreground font-normal">{wh.code} {wh.is_default ? '• Principal' : ''}</span>
                    </div>
                    {#if String(localWarehouseId) === String(wh.id)}
                        <Check class="size-4 text-primary shrink-0" />
                    {/if}
                </button>
            {/each}
        </div>
    {:else if activeTab === 'category'}
        <div class="space-y-1">
            <button
                type="button"
                onclick={() => (localCategoryId = '')}
                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-left text-xs sm:text-sm font-medium transition-colors {localCategoryId === '' ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'}"
            >
                <span>Todas las Categorías</span>
                {#if localCategoryId === ''}
                    <Check class="size-4 text-primary" />
                {/if}
            </button>
            {#each categories as cat (cat.id)}
                <button
                    type="button"
                    onclick={() => (localCategoryId = cat.id)}
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-left text-xs sm:text-sm font-medium transition-colors {String(localCategoryId) === String(cat.id) ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'}"
                >
                    <span class="truncate pr-2">{cat.name}</span>
                    {#if String(localCategoryId) === String(cat.id)}
                        <Check class="size-4 text-primary shrink-0" />
                    {/if}
                </button>
            {/each}
        </div>
    {:else if activeTab === 'stock'}
        <div class="space-y-1">
            <button
                type="button"
                onclick={() => (localStockStatus = '')}
                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-left text-xs sm:text-sm font-medium transition-colors {localStockStatus === '' ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'}"
            >
                <span>Todos los Estados</span>
                {#if localStockStatus === ''}
                    <Check class="size-4 text-primary" />
                {/if}
            </button>
            <button
                type="button"
                onclick={() => (localStockStatus = 'in_stock')}
                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-left text-xs sm:text-sm font-medium transition-colors {localStockStatus === 'in_stock' ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'}"
            >
                <div class="flex flex-col">
                    <span>Con stock</span>
                    <span class="text-[11px] text-muted-foreground font-normal">Existencias mayores a 0</span>
                </div>
                {#if localStockStatus === 'in_stock'}
                    <Check class="size-4 text-primary shrink-0" />
                {/if}
            </button>
            <button
                type="button"
                onclick={() => (localStockStatus = 'low_stock')}
                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-left text-xs sm:text-sm font-medium transition-colors {localStockStatus === 'low_stock' ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'}"
            >
                <div class="flex flex-col">
                    <span>Stock bajo</span>
                    <span class="text-[11px] text-muted-foreground font-normal">Existencias menores o iguales al mínimo</span>
                </div>
                {#if localStockStatus === 'low_stock'}
                    <Check class="size-4 text-primary shrink-0" />
                {/if}
            </button>
            <button
                type="button"
                onclick={() => (localStockStatus = 'out_of_stock')}
                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-left text-xs sm:text-sm font-medium transition-colors {localStockStatus === 'out_of_stock' ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'}"
            >
                <div class="flex flex-col">
                    <span>Sin stock</span>
                    <span class="text-[11px] text-muted-foreground font-normal">Existencias en 0</span>
                </div>
                {#if localStockStatus === 'out_of_stock'}
                    <Check class="size-4 text-primary shrink-0" />
                {/if}
            </button>
        </div>
    {/if}
</FilterModalLayoutMobile>
