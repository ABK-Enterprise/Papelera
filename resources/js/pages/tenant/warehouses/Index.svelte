<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import WarehouseListDesktop, { type WarehouseItem, type BranchOption } from './desktop/WarehouseListDesktop.svelte';
    import WarehouseListMobile from './mobile/WarehouseListMobile.svelte';
    import ManageWarehouseModal from './ManageWarehouseModal.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import WarehouseIcon from 'lucide-svelte/icons/warehouse';

    interface Props {
        warehouses: WarehouseItem[];
        branches: BranchOption[];
    }

    let { warehouses = [], branches = [] }: Props = $props();

    let searchTerm = $state('');

    let filteredWarehouses = $derived.by(() => {
        const query = searchTerm.toLowerCase().trim();
        if (!query) return warehouses;
        return warehouses.filter(
            (w) =>
                w.name.toLowerCase().includes(query) ||
                w.code.toLowerCase().includes(query) ||
                (w.branch?.name && w.branch.name.toLowerCase().includes(query)) ||
                (w.address && w.address.toLowerCase().includes(query))
        );
    });

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

        router.delete(`/tenant/configuracion/depositos/${warehouseToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isDeleteConfirmOpen = false;
                warehouseToDelete = null;
            },
        });
    }
</script>

<AppHead title="Gestión de Depósitos - Configuración" />

<div class="flex flex-col gap-6 w-full">
    <!-- Encabezado y Acciones Principales con Botones Equilibrados -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">Gestión de Depósitos</h1>
                <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium tracking-wide text-primary tabular-nums">
                    {warehouses.length} {warehouses.length === 1 ? 'depósito' : 'depósitos'}
                </span>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Controla los espacios físicos de almacenamiento, inventario y asignación por sucursal.
            </p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2.5">
            <Link href="/tenant/productos/buscar-deposito">
                <Button variant="outline" class="min-h-[44px] gap-2 rounded-xl border-border/70 text-sm font-medium">
                    <WarehouseIcon class="h-4 w-4 text-muted-foreground" />
                    <span>Buscar en depósitos</span>
                </Button>
            </Link>

            <Button onclick={openCreateModal} class="min-h-[44px] gap-2 rounded-xl text-sm font-medium shadow-xs">
                <Plus class="h-4 w-4" />
                <span>Nuevo depósito</span>
            </Button>
        </div>
    </div>

    <!-- Barra de Búsqueda de Depósitos -->
    {#if warehouses.length > 0}
        <Card class="rounded-xl p-3 sm:p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.02)] border border-border/60">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="w-full sm:w-80">
                    <SearchCombobox
                        bind:value={searchTerm}
                        items={warehouses}
                        placeholder="Buscar por nombre, código o sucursal..."
                        onselect={(w) => {
                            searchTerm = w.name;
                        }}
                    >
                        {#snippet item(w)}
                            <div class="flex items-center justify-between gap-3 w-full min-w-0">
                                <div class="flex flex-col min-w-0">
                                    <span class="truncate font-medium text-foreground">{w.name}</span>
                                    <span class="text-[11px] font-mono text-muted-foreground truncate">{w.code} {w.branch?.name ? `· ${w.branch.name}` : ''}</span>
                                </div>
                                {#if w.is_default}
                                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary shrink-0">
                                        Principal
                                    </span>
                                {/if}
                            </div>
                        {/snippet}
                    </SearchCombobox>
                </div>
                <div class="text-xs text-muted-foreground font-medium self-end sm:self-center">
                    Mostrando <strong class="text-foreground tabular-nums font-semibold">{filteredWarehouses.length}</strong> de <span class="tabular-nums">{warehouses.length}</span> {warehouses.length === 1 ? 'depósito' : 'depósitos'}
                </div>
            </div>
        </Card>
    {/if}

    <!-- Contenido Modular: Desktop vs Mobile -->
    {#if filteredWarehouses.length > 0}
        <div class="hidden md:block">
            <WarehouseListDesktop
                warehouses={filteredWarehouses}
                onedit={openEditModal}
                ondelete={openDeleteConfirm}
            />
        </div>

        <div class="block md:hidden">
            <WarehouseListMobile
                warehouses={filteredWarehouses}
                onedit={openEditModal}
                ondelete={openDeleteConfirm}
            />
        </div>
    {:else}
        <EmptyState
            title={searchTerm ? 'No se encontraron depósitos coincidentes' : 'No hay depósitos registrados'}
            description={searchTerm
                ? `No existen depósitos que coincidan con "${searchTerm}". Intenta con otros términos.`
                : 'Comienza creando tu primer depósito para gestionar el stock físico de tus productos.'}
            actionLabel={searchTerm ? 'Limpiar búsqueda' : 'Nuevo depósito'}
            onaction={() => {
                if (searchTerm) {
                    searchTerm = '';
                } else {
                    openCreateModal();
                }
            }}
        />
    {/if}

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
        title="¿Eliminar este depósito?"
        description="Esta acción eliminará el registro del depósito de forma permanente. No se pueden eliminar depósitos marcados como principales."
        confirmText="Eliminar depósito"
        cancelText="Cancelar"
        variant="destructive"
        onconfirm={handleDeleteConfirm}
        oncancel={() => {
            isDeleteConfirmOpen = false;
            warehouseToDelete = null;
        }}
    />
</div>

