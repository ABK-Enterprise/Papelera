<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import ManageAdjustmentModal, { type PriceAdjustmentItem } from '@/components/adjustments/ManageAdjustmentModal.svelte';
    import { router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import Percent from 'lucide-svelte/icons/percent';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import Tag from 'lucide-svelte/icons/tag';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import XCircle from 'lucide-svelte/icons/x-circle';

    let {
        adjustments = [],
    }: {
        adjustments?: PriceAdjustmentItem[];
    } = $props();

    let searchQuery = $state('');
    let selectedTypeFilter = $state<'all' | 'discount' | 'surcharge'>('all');
    let onlyActive = $state(false);

    let isManageModalOpen = $state(false);
    let selectedAdjustment = $state<PriceAdjustmentItem | null>(null);

    let isDeleteModalOpen = $state(false);
    let adjustmentToDelete = $state<PriceAdjustmentItem | null>(null);
    let isDeleting = $state(false);

    const DAY_NAMES: Record<number, string> = {
        1: 'Lun',
        2: 'Mar',
        3: 'Mié',
        4: 'Jue',
        5: 'Vie',
        6: 'Sáb',
        7: 'Dom',
    };

    const filteredAdjustments = $derived.by(() => {
        return adjustments.filter(adj => {
            if (onlyActive && !adj.is_active) return false;
            if (selectedTypeFilter !== 'all' && adj.type !== selectedTypeFilter) return false;

            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                const nameMatches = (adj.name || '').toLowerCase().includes(q);
                const reasonMatches = (adj.reason || '').toLowerCase().includes(q);
                if (!nameMatches && !reasonMatches) return false;
            }

            return true;
        });
    });

    function openCreateModal() {
        selectedAdjustment = null;
        isManageModalOpen = true;
    }

    function openEditModal(adj: PriceAdjustmentItem) {
        selectedAdjustment = adj;
        isManageModalOpen = true;
    }

    function openDeleteModal(adj: PriceAdjustmentItem) {
        adjustmentToDelete = adj;
        isDeleteModalOpen = true;
    }

    function toggleActive(adj: PriceAdjustmentItem) {
        router.patch(`/tenant/configuracion/recargos-descuentos/${adj.id}/alternar`, {}, {
            preserveScroll: true,
        });
    }

    function confirmDelete() {
        if (!adjustmentToDelete) return;
        isDeleting = true;

        router.delete(`/tenant/configuracion/recargos-descuentos/${adjustmentToDelete.id}`, {
            preserveScroll: true,
            onFinish: () => {
                isDeleting = false;
                isDeleteModalOpen = false;
                adjustmentToDelete = null;
            },
        });
    }

    function formatValue(adj: PriceAdjustmentItem): string {
        const val = Number(adj.value) || 0;
        if (adj.calculation_type === 'percentage') {
            return `${val.toFixed(2)}%`;
        }
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(val);
    }

    function formatDateRange(adj: PriceAdjustmentItem): string {
        if (!adj.starts_at && !adj.ends_at) {
            return 'Vigencia permanente';
        }
        if (adj.starts_at && !adj.ends_at) {
            return `Desde ${adj.starts_at.slice(0, 10)}`;
        }
        if (!adj.starts_at && adj.ends_at) {
            return `Hasta ${adj.ends_at.slice(0, 10)}`;
        }
        return `${adj.starts_at?.slice(0, 10)} al ${adj.ends_at?.slice(0, 10)}`;
    }
</script>

<AppHead title="Recargos y Descuentos" />

<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Header del Módulo (Separación homogénea mb-6 / gap-6 sin métricas decorativas) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-border/70">
        <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
            <div class="h-8 w-8 rounded-lg bg-primary-500/10 text-primary flex items-center justify-center">
                <Sparkles class="h-4 w-4" />
            </div>
            Recargos y Descuentos
        </h1>

        <Button
            onclick={openCreateModal}
            class="min-h-[40px] px-4 font-semibold text-xs flex items-center gap-2 shadow-xs cursor-pointer"
        >
            <Plus class="h-4 w-4" />
            <span>Nuevo Ajuste</span>
        </Button>
    </div>

    <!-- Barra Operativa de Búsqueda y Filtros -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="relative flex-1 max-w-md">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
            <Input
                type="text"
                placeholder="Buscar por nombre o motivo..."
                bind:value={searchQuery}
                class="pl-9 h-10 text-sm font-medium"
            />
        </div>

        <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0">
            <!-- Filtro de Tipo -->
            <div class="flex items-center rounded-xl border border-border/80 bg-muted/30 p-1">
                <button
                    type="button"
                    onclick={() => selectedTypeFilter = 'all'}
                    class={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                        selectedTypeFilter === 'all'
                            ? 'bg-background text-foreground shadow-2xs'
                            : 'text-muted-foreground hover:text-foreground'
                    }`}
                >
                    Todos
                </button>
                <button
                    type="button"
                    onclick={() => selectedTypeFilter = 'discount'}
                    class={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                        selectedTypeFilter === 'discount'
                            ? 'bg-emerald-500 text-white shadow-2xs'
                            : 'text-muted-foreground hover:text-foreground'
                    }`}
                >
                    Descuentos
                </button>
                <button
                    type="button"
                    onclick={() => selectedTypeFilter = 'surcharge'}
                    class={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                        selectedTypeFilter === 'surcharge'
                            ? 'bg-amber-600 text-white shadow-2xs'
                            : 'text-muted-foreground hover:text-foreground'
                    }`}
                >
                    Recargos
                </button>
            </div>

            <!-- Filtro Activos -->
            <button
                type="button"
                onclick={() => onlyActive = !onlyActive}
                class={`min-h-[38px] px-3 rounded-xl border text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer ${
                    onlyActive
                        ? 'bg-primary/10 text-primary border-primary/30'
                        : 'bg-background text-muted-foreground border-border/80 hover:bg-muted'
                }`}
            >
                <CheckCircle2 class="h-3.5 w-3.5" />
                <span>Solo activos</span>
            </button>
        </div>
    </div>

    <!-- Lista de Wide Cards -->
    {#if filteredAdjustments.length > 0}
        <div class="space-y-3">
            {#each filteredAdjustments as adj (adj.id)}
                {@const isDiscount = adj.type === 'discount'}
                <div
                    class={`group rounded-2xl border p-4 sm:p-5 transition-all bg-card ${
                        adj.is_active
                            ? 'border-border/80 shadow-xs hover:border-primary/40'
                            : 'border-border/40 opacity-70 bg-muted/10'
                    }`}
                >
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <!-- Columna Izquierda: Badge de Tipo, Nombre y Motivo -->
                        <div class="space-y-1.5 flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <!-- Badge de Tipo -->
                                <span
                                    class={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider ${
                                        isDiscount
                                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20'
                                            : 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20'
                                    }`}
                                >
                                    {#if isDiscount}
                                        <Percent class="h-3 w-3" />
                                        Descuento
                                    {:else}
                                        <DollarSign class="h-3 w-3" />
                                        Recargo
                                    {/if}
                                </span>

                                <!-- Modalidad de Cálculo -->
                                <span class="text-2xs font-medium text-muted-foreground px-2 py-0.5 rounded-md bg-muted/60 border border-border/60">
                                    {adj.calculation_type === 'percentage' ? 'Porcentual' : 'Monto Fijo'}
                                </span>

                                <!-- Estado Activo / Inactivo -->
                                <span class={`inline-flex items-center gap-1 text-2xs font-semibold ${
                                    adj.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'
                                }`}>
                                    <div class={`h-1.5 w-1.5 rounded-full ${adj.is_active ? 'bg-emerald-500' : 'bg-muted-foreground'}`}></div>
                                    {adj.is_active ? 'Activo' : 'Pausado'}
                                </span>
                            </div>

                            <!-- Nombre -->
                            <h3 class="text-base font-bold text-foreground tracking-tight break-words">
                                {adj.name || 'Sin Nombre'}
                            </h3>

                            <!-- Motivo / Justificación -->
                            {#if adj.reason}
                                <p class="text-xs text-muted-foreground line-clamp-2">
                                    {adj.reason}
                                </p>
                            {/if}
                        </div>

                        <!-- Columna Central: Valor Grande y Reglas de Calendario -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4 lg:gap-8 pt-2 lg:pt-0 border-t lg:border-t-0 border-border/60">
                            <!-- Valor destacado -->
                            <div class="text-left sm:text-right shrink-0">
                                <span class="text-2xs font-bold text-muted-foreground uppercase tracking-wider block">
                                    Valor Aplicable
                                </span>
                                <span class={`text-xl sm:text-2xl font-black tabular-nums tracking-tight ${
                                    isDiscount ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'
                                }`}>
                                    {formatValue(adj)}
                                </span>
                            </div>

                            <!-- Reglas de Calendario y Días -->
                            <div class="space-y-1.5 text-xs text-muted-foreground min-w-[200px]">
                                <!-- Días de la semana -->
                                <div class="flex items-center gap-1 flex-wrap">
                                    {#if !adj.days_of_week || adj.days_of_week.length === 0}
                                        <span class="text-2xs font-medium px-2 py-0.5 rounded bg-muted/60 text-foreground">
                                            Todos los días
                                        </span>
                                    {:else}
                                        <div class="flex items-center gap-1">
                                            {#each [1, 2, 3, 4, 5, 6, 7] as dayId}
                                                {@const hasDay = adj.days_of_week.includes(dayId)}
                                                <span
                                                    class={`h-5 w-5 rounded flex items-center justify-center text-2xs font-bold ${
                                                        hasDay
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'bg-muted/40 text-muted-foreground/40'
                                                    }`}
                                                    title={DAY_NAMES[dayId]}
                                                >
                                                    {DAY_NAMES[dayId]?.slice(0, 1)}
                                                </span>
                                            {/each}
                                        </div>
                                    {/if}
                                </div>

                                <!-- Rango de Vigencia -->
                                <div class="flex items-center gap-1.5 text-2xs">
                                    <Calendar class="h-3 w-3 text-muted-foreground shrink-0" />
                                    <span>{formatDateRange(adj)}</span>
                                </div>
                            </div>

                            <!-- Columna Derecha: Acciones (Botones adyacentes de tamaño simétrico min-h-[36px]) -->
                            <div class="flex items-center gap-2 shrink-0 pt-2 sm:pt-0">
                                <!-- Switch de Activación Rápida -->
                                <button
                                    type="button"
                                    onclick={() => toggleActive(adj)}
                                    class={`min-h-[36px] px-3 rounded-xl border text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer ${
                                        adj.is_active
                                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/20'
                                            : 'bg-muted/40 text-muted-foreground border-border/80 hover:bg-muted'
                                    }`}
                                    title={adj.is_active ? 'Hacer clic para pausar' : 'Hacer clic para activar'}
                                >
                                    {adj.is_active ? 'Pausar' : 'Activar'}
                                </button>

                                <!-- Editar -->
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onclick={() => openEditModal(adj)}
                                    class="min-h-[36px] px-3 text-xs font-semibold flex items-center gap-1.5 cursor-pointer"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                    <span>Editar</span>
                                </Button>

                                <!-- Eliminar -->
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onclick={() => openDeleteModal(adj)}
                                    class="min-h-[36px] px-2.5 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive cursor-pointer"
                                    title="Eliminar ajuste"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            {/each}
        </div>
    {:else}
        <!-- Estado Vacío -->
        <div class="rounded-2xl border border-dashed border-border/80 p-12 text-center bg-card/50">
            <div class="mx-auto h-12 w-12 rounded-2xl bg-muted/60 flex items-center justify-center text-muted-foreground mb-4">
                <Tag class="h-6 w-6" />
            </div>
            <h3 class="text-base font-bold text-foreground">
                {#if searchQuery || selectedTypeFilter !== 'all' || onlyActive}
                    No se encontraron ajustes con los filtros seleccionados
                {:else}
                    Aún no hay recargos ni descuentos configurados
                {/if}
            </h3>
            <p class="text-xs text-muted-foreground mt-1 max-w-sm mx-auto">
                {#if searchQuery || selectedTypeFilter !== 'all' || onlyActive}
                    Prueba modificando o limpiando los criterios de búsqueda.
                {:else}
                    Crea reglas automáticas para aplicar descuentos semanales, promociones por fecha o recargos comerciales en tus pedidos.
                {/if}
            </p>
            {#if !searchQuery && selectedTypeFilter === 'all' && !onlyActive}
                <div class="mt-5">
                    <Button onclick={openCreateModal} class="min-h-[40px] px-4 text-xs font-semibold gap-2 cursor-pointer">
                        <Plus class="h-4 w-4" />
                        <span>Crear primer ajuste</span>
                    </Button>
                </div>
            {/if}
        </div>
    {/if}
</div>

<!-- Modal de Creación / Edición -->
<ManageAdjustmentModal
    bind:open={isManageModalOpen}
    adjustment={selectedAdjustment}
/>

<!-- Modal de Confirmación de Eliminación -->
<ConfirmActionModal
    bind:open={isDeleteModalOpen}
    title="¿Eliminar este recargo / descuento?"
    description={`Estás a punto de eliminar permanentemente "${adjustmentToDelete?.name}". Los pedidos creados previamente conservarán su snapshot histórico intacto, pero el ajuste ya no estará disponible para nuevos pedidos.`}
    confirmLabel="Eliminar Ajuste"
    confirmVariant="destructive"
    isLoading={isDeleting}
    onconfirm={confirmDelete}
/>
