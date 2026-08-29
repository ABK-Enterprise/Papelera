<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import RoleListDesktop from './desktop/RoleListDesktop.svelte';
    import RoleListMobile from './mobile/RoleListMobile.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import Shield from 'lucide-svelte/icons/shield';
    import Users from 'lucide-svelte/icons/users';

    interface RoleItem {
        id: number;
        name: string;
        permissions_count: number;
        users_count: number;
        created_at: string;
    }

    let { roles = [] }: { roles: RoleItem[] } = $props();

    let roleToDelete = $state<RoleItem | null>(null);
    let isDeleteModalOpen = $state(false);
    let isDeleting = $state(false);

    function promptDelete(role: RoleItem) {
        roleToDelete = role;
        isDeleteModalOpen = true;
    }

    function executeDelete() {
        if (!roleToDelete) return;
        isDeleting = true;
        router.delete(`/tenant/roles/${roleToDelete.id}`, {
            onFinish: () => {
                isDeleting = false;
                isDeleteModalOpen = false;
                roleToDelete = null;
            },
        });
    }
</script>

<AppHead title="Roles y Permisos - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Encabezado y Acciones -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">Roles y Permisos</h1>
                <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium tracking-wide text-primary tabular-nums">
                    {roles.length} {roles.length === 1 ? 'perfil' : 'perfiles'}
                </span>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Define perfiles de acceso para los empleados basados en los módulos contratados.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <Link href="/tenant/users">
                <Button variant="outline" class="min-h-[44px] gap-2 rounded-xl border-border/70 text-sm font-medium">
                    <Users class="h-4 w-4" />
                    <span>Ver empleados</span>
                </Button>
            </Link>
            <Link href="/tenant/roles/create">
                <Button class="min-h-[44px] gap-2 rounded-xl text-sm font-medium shadow-xs">
                    <Shield class="h-4 w-4" />
                    <span>Crear rol</span>
                </Button>
            </Link>
        </div>
    </div>

    <!-- Contenido Modular: Desktop vs Mobile -->
    {#if roles.length > 0}
        <div class="hidden md:block">
            <RoleListDesktop {roles} ondelete={promptDelete} />
        </div>
        <div class="block md:hidden">
            <RoleListMobile {roles} ondelete={promptDelete} />
        </div>
    {:else}
        <EmptyState
            icon={Shield}
            title="No hay roles personalizados"
            description="Crea perfiles de trabajo como 'Cajero', 'Ventas' o 'Depósito' para asignar permisos selectivos a tu personal."
            actionLabel="Crear primer rol"
            actionHref="/tenant/roles/create"
        />
    {/if}
</div>

<!-- Modal de Confirmación de Eliminación -->
<ConfirmActionModal
    bind:open={isDeleteModalOpen}
    title="¿Eliminar rol de permisos?"
    description={`¿Estás seguro de eliminar el rol "${roleToDelete?.name}"? Los empleados que lo tengan asignado perderán de inmediato los permisos correspondientes.`}
    confirmText="Eliminar rol"
    cancelText="Cancelar"
    variant="danger"
    loading={isDeleting}
    onconfirm={executeDelete}
/>
