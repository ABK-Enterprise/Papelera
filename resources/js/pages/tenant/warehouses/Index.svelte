<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { router } from '@inertiajs/svelte';
    import WarehouseListDesktop, { type WarehouseItem, type BranchOption } from './desktop/WarehouseListDesktop.svelte';
    import WarehouseListMobile from './mobile/WarehouseListMobile.svelte';
    import ManageWarehouseModal from './ManageWarehouseModal.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';

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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <!-- Desktop View -->
    <WarehouseListDesktop
        {warehouses}
        oncreate={openCreateModal}
        onedit={openEditModal}
        ondelete={openDeleteConfirm}
    />

    <!-- Mobile View -->
    <WarehouseListMobile
        {warehouses}
        oncreate={openCreateModal}
        onedit={openEditModal}
        ondelete={openDeleteConfirm}
    />

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
