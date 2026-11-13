<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import { router } from '@inertiajs/svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import Tag from 'lucide-svelte/icons/tag';
    import Plus from 'lucide-svelte/icons/plus';
    import Box from 'lucide-svelte/icons/box';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Layers from 'lucide-svelte/icons/layers';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import ChevronUp from 'lucide-svelte/icons/chevron-up';

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

    const initialLimit = 11;
    const batchSize = 6;
    let visibleLimit = $state(11);
    let visibleCategories = $derived(categories.slice(0, visibleLimit));

    $effect(() => {
        if (selectedCategoryId !== null) {
            const idx = categories.findIndex((c) => c.id === selectedCategoryId);
            if (idx >= visibleLimit) {
                visibleLimit = Math.ceil((idx + 1 - initialLimit) / batchSize) * batchSize + initialLimit;
            }
        }
    });

    let isCategoryModalOpen = $state(false);
    let isConfirmAlertOpen = $state(false);
    let newCategoryName = $state('');
    let categoryError = $state('');
    let isSaving = $state(false);

    function openCreateCategoryModal() {
        newCategoryName = '';
        categoryError = '';
        isCategoryModalOpen = true;
    }

    function handleCategorySubmit(e: SubmitEvent) {
        e.preventDefault();
        if (!newCategoryName.trim()) {
            categoryError = 'El nombre de la categoría es obligatorio.';
            return;
        }
        categoryError = '';
        isConfirmAlertOpen = true;
    }

    function handleConfirmedSubmit() {
        isSaving = true;
        router.post(
            '/tenant/categorias',
            {
                name: newCategoryName.trim(),
                icon: 'box',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isSaving = false;
                    isConfirmAlertOpen = false;
                    isCategoryModalOpen = false;
                    newCategoryName = '';
                },
                onError: (errs) => {
                    isSaving = false;
                    isConfirmAlertOpen = false;
                    categoryError = (errs.name as string) || (errs.slug as string) || 'Error al registrar la categoría.';
                },
            }
        );
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
            size="icon"
            class="size-8 rounded-xl shadow-xs cursor-pointer"
            onclick={openCreateCategoryModal}
            title="Nueva categoría"
            aria-label="Nueva categoría"
        >
            <Plus class="size-4" />
        </Button>
    </div>

    <!-- Búsqueda rápida de categorías -->
    {#if categories.length > 0}
        <div class="mb-3">
            <SearchCombobox
                items={categories}
                searchKeys={['name']}
                placeholder="Buscar categoría..."
                onselect={(cat) => onSelectCategory(cat.id)}
                onclear={() => onSelectCategory(null)}
                inputClass="h-9 text-xs rounded-xl border-input bg-field focus:border-primary focus:ring-1 focus:ring-primary"
            />
        </div>
    {/if}

    <ul class="flex flex-col gap-1.5">
        <li>
            <button
                type="button"
                class="w-full flex items-center justify-between p-2.5 rounded-xl text-left text-sm transition-colors cursor-pointer select-none border {selectedCategoryId === null ? 'bg-primary/10 dark:bg-primary/15 border-primary/25 text-primary font-bold shadow-2xs' : 'border-transparent text-muted-foreground hover:bg-muted/70 dark:hover:bg-secondary/50 hover:text-foreground'}"
                onclick={() => onSelectCategory(null)}
            >
                <div class="flex items-center gap-2.5">
                    <Tag class="h-4 w-4 {selectedCategoryId === null ? 'text-primary' : 'text-muted-foreground'}" />
                    <span>Todas las Categorías</span>
                </div>
            </button>
        </li>

        {#each visibleCategories as cat (cat.id)}
            {@const IconComponent = getCategoryIcon(cat.icon)}
            <li>
                <button
                    type="button"
                    class="w-full flex items-center justify-between p-2.5 rounded-xl text-left text-sm transition-colors cursor-pointer select-none border {selectedCategoryId === cat.id ? 'bg-primary/10 dark:bg-primary/15 border-primary/25 text-primary font-bold shadow-2xs' : 'border-transparent text-muted-foreground hover:bg-muted/70 dark:hover:bg-secondary/50 hover:text-foreground'}"
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

    {#if categories.length > initialLimit}
        <div class="flex items-center justify-between pt-3 px-1 text-xs border-t border-border/40 mt-2">
            {#if categories.length > visibleLimit}
                <button
                    type="button"
                    class="inline-flex items-center gap-1 font-semibold text-primary hover:underline cursor-pointer transition-colors"
                    onclick={() => (visibleLimit += batchSize)}
                >
                    <ChevronDown class="size-3.5" />
                    <span>Ver más ({Math.min(batchSize, categories.length - visibleLimit)})</span>
                </button>
            {:else}
                <span class="text-xs text-muted-foreground/80">Todas mostradas</span>
            {/if}

            {#if visibleLimit > initialLimit}
                <button
                    type="button"
                    class="inline-flex items-center gap-1 font-semibold text-muted-foreground hover:text-foreground hover:underline cursor-pointer transition-colors ml-auto"
                    onclick={() => (visibleLimit = initialLimit)}
                >
                    <ChevronUp class="size-3.5" />
                    <span>Ver menos</span>
                </button>
            {/if}
        </div>
    {/if}

    {#if categories.length === 0}
        <p class="py-4 text-center text-xs text-muted-foreground">
            No hay categorías registradas.
        </p>
    {/if}
</Card>

<!-- Dialog Pequeño para Crear Categoría -->
<Dialog bind:open={isCategoryModalOpen}>
    <DialogContent class="sm:max-w-[440px] p-4 sm:p-5 space-y-4">
        <DialogHeader class="p-0 pb-3 border-b border-border/60">
            <div class="flex items-center gap-2.5">
                <IconContainer size="md" variant="primary" icon="solar:tag-bold-duotone" />
                <DialogTitle class="text-lg font-bold tracking-tight text-foreground leading-tight">
                    Nueva Categoría
                </DialogTitle>
            </div>
        </DialogHeader>

        <form onsubmit={handleCategorySubmit} class="space-y-4">
            <div class="space-y-1.5">
                <Label for="modal-cat-name" class="text-xs font-semibold text-foreground">
                    Nombre de la Categoría <span class="text-destructive">*</span>
                </Label>
                <Input
                    id="modal-cat-name"
                    bind:value={newCategoryName}
                    placeholder="ej. Papelería, Embalaje, Librería..."
                    class="h-10 text-sm rounded-xl border-input bg-field text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                    required
                    disabled={isSaving}
                />
                {#if categoryError}
                    <p class="text-xs text-destructive font-medium mt-1">{categoryError}</p>
                {/if}
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-border/50">
                <Button
                    type="button"
                    variant="secondary"
                    class="min-h-[44px] px-4 rounded-xl text-xs font-semibold"
                    onclick={() => (isCategoryModalOpen = false)}
                    disabled={isSaving}
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    class="min-h-[44px] px-5 rounded-xl text-xs font-semibold shadow-xs"
                    disabled={isSaving || !newCategoryName.trim()}
                >
                    <span>Guardar categoría</span>
                </Button>
            </div>
        </form>
    </DialogContent>
</Dialog>

<!-- Alert Dialog de Confirmación para Guardar Categoría -->
<ConfirmActionModal
    bind:open={isConfirmAlertOpen}
    title="¿Confirmar creación de categoría?"
    description={`¿Deseas registrar la categoría "${newCategoryName.trim()}" en el catálogo del comercio?`}
    confirmText="Sí, guardar categoría"
    cancelText="Volver"
    variant="primary"
    loading={isSaving}
    onconfirm={handleConfirmedSubmit}
/>
