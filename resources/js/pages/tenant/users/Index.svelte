<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { Link, router, page as inertiaPage } from '@inertiajs/svelte';
    import UserPlus from 'lucide-svelte/icons/user-plus';
    import Shield from 'lucide-svelte/icons/shield';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Edit2 from 'lucide-svelte/icons/edit-2';

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
    const perPage = 9;
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
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Personal y Empleados</h1>
            <p class="text-sm text-muted-foreground">
                Gestiona los usuarios de tu comercio y sus roles asignados.
            </p>
        </div>
        <div class="flex gap-2">
            <Link href="/tenant/roles">
                <Button variant="outline" class="gap-2">
                    <Shield class="h-4 w-4" />
                    Roles y Permisos
                </Button>
            </Link>
            <Link href="/tenant/users/create">
                <Button class="gap-2">
                    <UserPlus class="h-4 w-4" />
                    + Nuevo Empleado
                </Button>
            </Link>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        {#each paginatedUsers as user (user.id)}
            <Card class="flex flex-col justify-between transition-all hover:shadow-md">
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <CardTitle class="text-lg">{user.name}</CardTitle>
                            <span class="text-xs text-muted-foreground">{user.email}</span>
                        </div>
                        {#if currentUser?.id === user.id}
                            <Badge variant="secondary" class="text-xs">Tú</Badge>
                        {/if}
                    </div>
                </CardHeader>

                <CardContent class="space-y-4 pt-0">
                    <div class="space-y-1.5">
                        <span class="text-xs font-semibold text-muted-foreground">Rol Asignado:</span>
                        <div class="flex flex-wrap gap-1.5">
                            {#each user.roles as role}
                                <Badge variant={role.name === 'admin' ? 'default' : 'outline'} class="text-xs">
                                    {role.name}
                                </Badge>
                            {/each}
                            {#if user.roles.length === 0}
                                <span class="text-xs italic text-muted-foreground">Sin rol</span>
                            {/if}
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t pt-3 text-xs">
                        <Link href={`/tenant/users/${user.id}/edit`}>
                            <Button size="sm" variant="outline" class="gap-1.5">
                                <Edit2 class="h-3.5 w-3.5" />
                                Editar
                            </Button>
                        </Link>

                        {#if currentUser?.id !== user.id}
                            <Button
                                size="sm"
                                variant="destructive"
                                class="gap-1.5"
                                onclick={() => promptDelete(user)}
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                Eliminar
                            </Button>
                        {/if}
                    </div>
                </CardContent>
            </Card>
        {/each}

        {#if users.length === 0}
            <div class="col-span-full rounded-xl border border-dashed p-12 text-center">
                <h3 class="text-lg font-medium">No hay empleados registrados</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Comienza agregando miembros a tu equipo de trabajo.
                </p>
                <Link href="/tenant/users/create" class="mt-4 inline-block">
                    <Button>+ Agregar Primer Empleado</Button>
                </Link>
            </div>
        {/if}
    </div>

    {#if users.length > perPage}
        <AppPagination count={users.length} {perPage} bind:page />
    {/if}
</div>

<!-- Modal de Confirmación de Eliminación de Empleado -->
<ConfirmActionModal
    bind:open={isDeleteModalOpen}
    title="¿Eliminar empleado del comercio?"
    description={`¿Estás seguro de eliminar la cuenta de "${userToDelete?.name}" (${userToDelete?.email})? Perderá acceso inmediato al sistema.`}
    confirmText="Eliminar Empleado"
    cancelText="Cancelar"
    variant="danger"
    loading={isDeleting}
    onconfirm={executeDelete}
/>
