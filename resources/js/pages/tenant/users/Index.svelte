<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
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

    let searchTerm = $state('');
    let page = $state(1);
    const perPage = 10;

    let filteredUsers = $derived.by(() => {
        const query = searchTerm.toLowerCase().trim();
        if (!query) return users;
        return users.filter(
            (u) =>
                u.name.toLowerCase().includes(query) ||
                u.email.toLowerCase().includes(query) ||
                u.roles?.some((r) => r.name.toLowerCase().includes(query))
        );
    });

    let paginatedUsers = $derived(filteredUsers.slice((page - 1) * perPage, page * perPage));

    $effect(() => {
        if (searchTerm) {
            page = 1;
        }
    });

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
        router.delete(`/tenant/usuarios/${userToDelete.id}`, {
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
        <div class="flex flex-wrap items-center justify-end gap-2.5">
            <Link href="/tenant/roles">
                <Button variant="secondary" class="min-h-[44px] gap-2 rounded-xl text-sm font-medium shadow-2xs">
                    <Shield class="h-4 w-4" />
                    <span>Roles y Permisos</span>
                </Button>
            </Link>

            <Link href="/tenant/usuarios/create">
                <Button class="min-h-[44px] gap-2 rounded-xl text-sm font-medium shadow-xs">
                    <UserPlus class="h-4 w-4" />
                    <span>Añadir empleado</span>
                </Button>
            </Link>
        </div>
    </div>

    <!-- Barra de Búsqueda de Empleados -->
    {#if users.length > 0}
        <Card class="rounded-xl p-3 sm:p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.02)] border border-border/60">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="w-full sm:w-80">
                    <SearchCombobox
                        bind:value={searchTerm}
                        items={users}
                        placeholder="Buscar por nombre, email o rol..."
                        onselect={(u) => {
                            searchTerm = u.name;
                            page = 1;
                        }}
                    >
                        {#snippet item(u)}
                            <div class="flex items-center justify-between gap-3 w-full min-w-0">
                                <div class="flex flex-col min-w-0">
                                    <span class="truncate font-medium text-foreground">{u.name}</span>
                                    <span class="text-[11px] text-muted-foreground truncate">{u.email}</span>
                                </div>
                                {#if u.roles && u.roles.length > 0}
                                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary shrink-0">
                                        {u.roles[0].name}
                                    </span>
                                {/if}
                            </div>
                        {/snippet}
                    </SearchCombobox>
                </div>
                <div class="text-xs text-muted-foreground font-medium self-end sm:self-center">
                    Mostrando <strong class="text-foreground tabular-nums font-semibold">{filteredUsers.length}</strong> de <span class="tabular-nums">{users.length}</span> {users.length === 1 ? 'empleado' : 'empleados'}
                </div>
            </div>
        </Card>
    {/if}

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
    {:else if searchTerm}
        <div class="rounded-xl border border-dashed border-border/80 p-8 text-center bg-card/40 flex flex-col items-center justify-center shadow-xs">
            <Users class="size-8 text-muted-foreground/50 mb-2 stroke-[1.5]" />
            <h3 class="text-sm font-semibold text-foreground">No se encontraron empleados coincidentes</h3>
            <p class="text-xs text-muted-foreground mt-1">Intenta con otro término o borra el filtro de búsqueda.</p>
            <Button variant="outline" size="sm" class="mt-4 text-xs" onclick={() => (searchTerm = '')}>
                Limpiar búsqueda
            </Button>
        </div>
    {:else}
        <EmptyState
            icon={Users}
            title="No hay empleados registrados"
            description="Comienza agregando los miembros de tu equipo para otorgarles acceso operativo al sistema."
            actionLabel="Añadir primer empleado"
            actionHref="/tenant/usuarios/create"
        />

    {/if}

    <!-- Paginación -->
    {#if filteredUsers.length > perPage}
        <AppPagination count={filteredUsers.length} {perPage} bind:page />
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
