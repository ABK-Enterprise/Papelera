<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import UserListDesktop from './desktop/UserListDesktop.svelte';
    import UserListMobile from './mobile/UserListMobile.svelte';
    import { Link, router, page as inertiaPage } from '@inertiajs/svelte';
    import UserPlus from 'lucide-svelte/icons/user-plus';
    import Shield from 'lucide-svelte/icons/shield';
    import Users from 'lucide-svelte/icons/users';

    interface RoleItem {
        id: number;
        name: string;
    }

    interface UserItem {
        id: number;
        name: string;
        email: string;
        roles: RoleItem[];
        created_at: string;
    }

    let { users = [] }: { users: UserItem[] } = $props();

    let page = $state(1);
    const perPage = 10;
    let paginatedUsers = $derived(users.slice((page - 1) * perPage, page * perPage));

    let currentUser = $derived(inertiaPage.props.auth?.user);
    let userToDelete = $state<UserItem | null>(null);
    let isDeleteModalOpen = $state(false);
    let isDeleting = $state(false);

    function promptDelete(user: UserItem) {
        userToDelete = user;
        isDeleteModalOpen = true;
    }

    function executeDelete() {
        if (!userToDelete) return;
        isDeleting = true;
        router.delete(`/tenant/users/${userToDelete.id}`, {
            onFinish: () => {
                isDeleting = false;
                isDeleteModalOpen = false;
                userToDelete = null;
            },
        });
    }
</script>

<AppHead title="Personal y Empleados - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Encabezado y Acciones Principales -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">Personal y Empleados</h1>
                <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium tracking-wide text-primary tabular-nums">
                    {users.length} {users.length === 1 ? 'miembro' : 'miembros'}
                </span>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Gestiona los accesos y roles del equipo de trabajo en tu comercio.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <Link href="/tenant/roles">
                <Button variant="outline" class="min-h-[44px] gap-2 rounded-xl border-border/70 text-sm font-medium">
                    <Shield class="h-4 w-4" />
                    <span>Roles y Permisos</span>
                </Button>
            </Link>
            <Link href="/tenant/users/create">
                <Button class="min-h-[44px] gap-2 rounded-xl text-sm font-medium shadow-xs">
                    <UserPlus class="h-4 w-4" />
                    <span>Añadir empleado</span>
                </Button>
            </Link>
        </div>
    </div>

    <!-- Contenido Modular: Desktop vs Mobile -->
    {#if paginatedUsers.length > 0}
        <div class="hidden md:block">
            <UserListDesktop
                users={paginatedUsers}
                currentUserId={currentUser?.id}
                ondelete={promptDelete}
            />
        </div>
        <div class="block md:hidden">
            <UserListMobile
                users={paginatedUsers}
                currentUserId={currentUser?.id}
                ondelete={promptDelete}
            />
        </div>
    {:else}
        <EmptyState
            icon={Users}
            title="No hay empleados registrados"
            description="Comienza agregando los miembros de tu equipo para otorgarles acceso operativo al sistema."
            actionLabel="Añadir primer empleado"
            actionHref="/tenant/users/create"
        />
    {/if}

    <!-- Paginación -->
    {#if users.length > perPage}
        <AppPagination count={users.length} {perPage} bind:page />
    {/if}
</div>

<!-- Modal de Confirmación de Eliminación -->
<ConfirmActionModal
    bind:open={isDeleteModalOpen}
    title="¿Eliminar empleado del comercio?"
    description={`Esta acción revoca de inmediato el acceso al sistema para "${userToDelete?.name}" (${userToDelete?.email}). Esta operación no se puede deshacer.`}
    confirmText="Eliminar empleado"
    cancelText="Cancelar"
    variant="danger"
    loading={isDeleting}
    onconfirm={executeDelete}
/>
