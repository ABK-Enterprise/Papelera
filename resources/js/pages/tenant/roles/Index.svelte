<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import Shield from 'lucide-svelte/icons/shield';
    import Users from 'lucide-svelte/icons/users';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Edit2 from 'lucide-svelte/icons/edit-2';

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
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Roles y Permisos</h1>
            <p class="text-sm text-muted-foreground">
                Define perfiles de acceso para los empleados basados en los módulos contratados.
            </p>
        </div>
        <div class="flex gap-2">
            <Link href="/tenant/users">
                <Button variant="outline" class="gap-2">
                    <Users class="h-4 w-4" />
                    Ver Empleados
                </Button>
            </Link>
            <Link href="/tenant/roles/create">
                <Button class="gap-2">
                    <Shield class="h-4 w-4" />
                    + Nuevo Rol
                </Button>
            </Link>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        {#each roles as role (role.id)}
            <Card class="flex flex-col justify-between transition-all hover:shadow-md">
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <CardTitle class="text-lg flex items-center gap-2">
                                {role.name}
                            </CardTitle>
                            <CardDescription class="text-xs">
                                {role.users_count} {role.users_count === 1 ? 'usuario con este rol' : 'usuarios con este rol'}
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4 pt-0">
                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        <span class="font-medium">Permisos asignados:</span>
                        <Badge variant="outline">{role.permissions_count} permisos</Badge>
                    </div>

                    <div class="flex items-center justify-between border-t pt-3 text-xs">
                        <Link href={`/tenant/roles/${role.id}/edit`}>
                            <Button size="sm" variant="outline" class="gap-1.5">
                                <Edit2 class="h-3.5 w-3.5" />
                                Editar Permisos
                            </Button>
                        </Link>

                        <Button
                            size="sm"
                            variant="destructive"
                            class="gap-1.5"
                            onclick={() => promptDelete(role)}
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Eliminar
                        </Button>
                    </div>
                </CardContent>
            </Card>
        {/each}

        {#if roles.length === 0}
            <div class="col-span-full rounded-xl border border-dashed p-12 text-center">
                <h3 class="text-lg font-medium">No hay roles personalizados configurados</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Crea perfiles como "Cajero", "Ventas" o "Depósito" para asignar permisos selectivos a tu personal.
                </p>
                <Link href="/tenant/roles/create" class="mt-4 inline-block">
                    <Button>+ Crear Primer Rol</Button>
                </Link>
            </div>
        {/if}
    </div>
</div>

<!-- Modal de Confirmación de Eliminación de Rol -->
<ConfirmActionModal
    bind:open={isDeleteModalOpen}
    title="¿Eliminar rol de permisos?"
    description={`¿Estás seguro de eliminar el rol "${roleToDelete?.name}"? Los empleados que lo tengan asignado perderán los permisos correspondientes.`}
    confirmText="Eliminar Rol"
    cancelText="Cancelar"
    variant="danger"
    loading={isDeleting}
    onconfirm={executeDelete}
/>
