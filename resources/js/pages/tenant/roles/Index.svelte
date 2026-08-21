<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
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

    function deleteRole(role: RoleItem) {
        if (confirm(`¿Estás seguro de eliminar el rol "${role.name}"?`)) {
            router.delete(`/tenant/roles/${role.id}`);
        }
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
                                    {#if role.name === 'admin'}
                                        <Badge variant="default" class="text-xs">Sistema</Badge>
                                    {/if}
                                </CardTitle>
                                <CardDescription class="text-xs">
                                    {role.users_count} usuarios con este rol
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

                            {#if role.name !== 'admin'}
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    class="gap-1.5"
                                    onclick={() => deleteRole(role)}
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    Eliminar
                                </Button>
                            {/if}
                        </div>
                    </CardContent>
                </Card>
            {/each}

            {#if roles.length === 0}
                <div class="col-span-full rounded-xl border border-dashed p-12 text-center">
                    <h3 class="text-lg font-medium">No hay roles configurados</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Crea perfiles como "Cajero", "Ventas" o "Depósito" para asignar permisos selectivos.
                    </p>
                    <Link href="/tenant/roles/create" class="mt-4 inline-block">
                        <Button>+ Crear Primer Rol</Button>
                    </Link>
                </div>
            {/if}
        </div>
    </div>
