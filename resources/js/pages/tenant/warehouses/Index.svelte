<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Link, router } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import WarehouseIcon from 'lucide-svelte/icons/warehouse';
    import ManageWarehouseModal from './ManageWarehouseModal.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';

    interface BranchOption {
        id: number;
        name: string;
        point_of_sale: number;
    }

    interface WarehouseItem {
        id: number;
        branch_id?: number | null;
        branch?: { id: number; name: string } | null;
        name: string;
        code: string;
        address?: string | null;
        is_active: boolean;
        is_default: boolean;
        products_count?: number;
    }

    interface Props {
        warehouses: WarehouseItem[];
        branches: BranchOption[];
    }

    let { warehouses = [], branches = [] }: Props = $props();

    let isModalOpen = $state(false);
    let modalMode = $state<'create' | 'edit'>('create');
    let selectedWarehouse = $state<WarehouseItem | null>(null);

    let isDeleteConfirmOpen = $state(false);
    let warehouseToDelete = $state<WarehouseItem | null>(null);

    function openCreateModal() {
        modalMode = 'create';
        selectedWarehouse = null;
        isModalOpen = true;
    }

    function openEditModal(wh: WarehouseItem) {
        modalMode = 'edit';
        selectedWarehouse = wh;
        isModalOpen = true;
    }

    function openDeleteConfirm(wh: WarehouseItem) {
        warehouseToDelete = wh;
        isDeleteConfirmOpen = true;
    }

    function handleDeleteConfirm() {
        if (!warehouseToDelete) return;

        router.delete(`/tenant/warehouses/${warehouseToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isDeleteConfirmOpen = false;
                warehouseToDelete = null;
            },
        });
    }
</script>

<AppHead title="Gestión de Depósitos" />

<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Encabezado de Página -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight leading-tight text-foreground flex items-center gap-2.5">
                <WarehouseIcon class="size-7 text-primary" />
                Gestión de Depósitos
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5 max-w-prose">
                Administra los depósitos físicos, centros de almacenamiento y vinculaciones a sucursales de tu comercio.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <Link href="/tenant/products/buscar-deposito">
                <Button variant="outline" class="gap-1.5 font-medium shadow-2xs">
                    <WarehouseIcon class="size-4 text-muted-foreground" />
                    Buscar en Depósitos
                </Button>
            </Link>


            <Button onclick={openCreateModal} class="gap-1.5 font-semibold shadow-sm">
                <Plus class="size-4" />
                Nuevo Depósito
            </Button>
        </div>
    </div>

    <!-- Lista de Depósitos (Grid Bento) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {#each warehouses as wh}
            <Card class="p-5 rounded-xl border border-border/70 hover:border-primary/40 transition-colors shadow-2xs flex flex-col justify-between gap-4 bg-card">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary shrink-0">
                                <WarehouseIcon class="size-6" />
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-foreground leading-snug">
                                    {wh.name}
                                </h3>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="font-mono text-xs text-muted-foreground font-semibold">
                                        {wh.code}
                                    </span>
                                    {#if wh.is_default}
                                        <Badge variant="default" class="text-[10px] font-bold py-0 h-4 bg-primary text-primary-foreground">
                                            Principal
                                        </Badge>
                                    {/if}
                                    {#if !wh.is_active}
                                        <Badge variant="secondary" class="text-[10px] text-muted-foreground py-0 h-4">
                                            Inactivo
                                        </Badge>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-muted/30 p-3 space-y-2 border border-border/40 text-xs">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="flex items-center gap-1.5 font-medium">
                                <Building2 class="size-3.5 text-primary shrink-0" />
                                Sucursal:
                            </span>
                            <span class="font-semibold text-foreground">
                                {wh.branch?.name || 'Depósito Central'}
                            </span>
                        </div>

                        {#if wh.address}
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span class="flex items-center gap-1.5 font-medium">
                                    <MapPin class="size-3.5 text-muted-foreground shrink-0" />
                                    Dirección:
                                </span>
                                <span class="font-medium text-foreground truncate max-w-[180px]" title={wh.address}>
                                    {wh.address}
                                </span>
                            </div>
                        {/if}

                        <div class="flex items-center justify-between pt-1 border-t border-border/40 text-muted-foreground">
                            <span>Artículos asignados:</span>
                            <span class="font-bold text-foreground font-sans tabular-nums">
                                {wh.products_count ?? 0}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="border-t border-border/50 pt-3 flex items-center justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onclick={() => openEditModal(wh)}
                        class="text-xs font-medium gap-1.5 h-8 px-3"
                    >
                        <Edit2 class="size-3.5" />
                        Editar
                    </Button>

                    {#if !wh.is_default}
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={() => openDeleteConfirm(wh)}
                            class="text-xs font-medium text-destructive hover:bg-destructive/10 hover:text-destructive gap-1.5 h-8 px-3"
                        >
                            <Trash2 class="size-3.5" />
                            Eliminar
                        </Button>
                    {/if}
                </div>
            </Card>
        {/each}
    </div>

    <!-- Modal de Creación / Edición -->
    <ManageWarehouseModal
        bind:open={isModalOpen}
        mode={modalMode}
        warehouse={selectedWarehouse}
        {branches}
    />

    <!-- Diálogo de Confirmación para Eliminar -->
    <ConfirmActionModal
        bind:open={isDeleteConfirmOpen}
        title="¿Eliminar depósito?"
        description="Esta acción eliminará el depósito seleccionado. Asegúrese de que no posea existencias de stock activas."
        confirmText="Eliminar depósito"
        confirmVariant="destructive"
        onconfirm={handleDeleteConfirm}
        oncancel={() => {
            isDeleteConfirmOpen = false;
            warehouseToDelete = null;
        }}
    />
</div>
