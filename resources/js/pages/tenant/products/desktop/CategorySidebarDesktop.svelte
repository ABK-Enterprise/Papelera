<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router, useForm } from '@inertiajs/svelte';
    import Tag from 'lucide-svelte/icons/tag';
    import Plus from 'lucide-svelte/icons/plus';
    import Box from 'lucide-svelte/icons/box';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Layers from 'lucide-svelte/icons/layers';

    interface CategoryItem {
        id: number;
        name: string;
        slug: string;
        icon: string | null;
        products_count?: number;
    }

    let {
        categories = [],
        selectedCategoryId = null,
        onSelectCategory,
    }: {
        categories: CategoryItem[];
        selectedCategoryId: number | null;
        onSelectCategory: (id: number | null) => void;
    } = $props();

    let showNewCategory = $state(false);

    const categoryForm = useForm({
        name: '',
        icon: 'box',
    });

    function submitCategory(e: SubmitEvent) {
        e.preventDefault();
        categoryForm.post('/tenant/categories', {
            onSuccess: () => {
                categoryForm.reset();
                showNewCategory = false;
            },
        });
    }

    function getCategoryIcon(iconName?: string | null) {
        switch (iconName) {
            case 'shopping_bag':
                return ShoppingBag;
            case 'layers':
                return Layers;
            case 'box':
            default:
                return Box;
        }
    }
</script>

<Card class="rounded-xl p-5 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] border border-border/60">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-base text-foreground flex items-center gap-2 leading-snug">
            <Tag class="h-5 w-5 text-primary" />
            Categorías
        </h3>
        <Button
            size="sm"
            variant="ghost"
            class="h-8 px-2 text-xs gap-1"
            onclick={() => (showNewCategory = !showNewCategory)}
        >
            <Plus class="h-3.5 w-3.5" />
            Nueva categoría
        </Button>
    </div>

    {#if showNewCategory}
        <form onsubmit={submitCategory} class="mb-4 p-3 rounded-lg border bg-muted/40 space-y-2.5">
            <div class="space-y-1">
                <Label for="new-cat-name" class="text-xs font-medium">Nombre de Categoría</Label>
                <Input
                    id="new-cat-name"
                    bind:value={categoryForm.name}
                    placeholder="ej. Papelería"
                    class="h-8 text-xs"
                    required
                />
            </div>
            <div class="flex justify-end gap-1.5 pt-1">
                <Button type="button" size="sm" variant="ghost" class="h-7 text-xs" onclick={() => (showNewCategory = false)}>
                    Cancelar
                </Button>
                <Button type="submit" size="sm" class="h-7 text-xs" disabled={categoryForm.processing}>
                    Guardar categoría
                </Button>
            </div>
        </form>
    {/if}

    <ul class="flex flex-col gap-1.5">
        <li>
            <button
                type="button"
                class="w-full flex items-center justify-between p-2.5 rounded-lg text-left text-sm transition-all {selectedCategoryId === null ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'}"
                onclick={() => onSelectCategory(null)}
            >
                <div class="flex items-center gap-2.5">
                    <Tag class="h-4 w-4" />
                    <span>Todas las Categorías</span>
                </div>
            </button>
        </li>

        {#each categories as cat (cat.id)}
            {@const IconComponent = getCategoryIcon(cat.icon)}
            <li>
                <button
                    type="button"
                    class="w-full flex items-center justify-between p-2.5 rounded-lg text-left text-sm transition-all {selectedCategoryId === cat.id ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'}"
                    onclick={() => onSelectCategory(cat.id)}
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <IconComponent class="h-4 w-4 shrink-0 {selectedCategoryId === cat.id ? 'text-primary' : 'text-muted-foreground'}" />
                        <span class="truncate">{cat.name}</span>
                    </div>
                    <Badge variant={selectedCategoryId === cat.id ? 'default' : 'secondary'} class="text-xs px-2 py-0.5 rounded-full tabular-nums font-medium">
                        {cat.products_count ?? 0}
                    </Badge>
                </button>
            </li>
        {/each}
    </ul>

    {#if categories.length === 0}
        <p class="py-4 text-center text-xs text-muted-foreground">
            No hay categorías registradas.
        </p>
    {/if}
</Card>
