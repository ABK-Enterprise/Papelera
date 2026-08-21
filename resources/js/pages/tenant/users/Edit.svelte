<script lang="ts">
    import AppLayout from '@/layouts/AppLayout.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Link, useForm } from '@inertiajs/svelte';

    interface RoleOption {
        id: number;
        name: string;
    }

    interface UserItem {
        id: number;
        name: string;
        email: string;
        roles: RoleOption[];
    }

    let { user, roles = [] }: { user: UserItem; roles: RoleOption[] } = $props();

    const form = useForm({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        role: user.roles && user.roles.length > 0 ? user.roles[0].name : (roles.length > 0 ? roles[0].name : 'empleado'),
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.put(`/tenant/users/${user.id}`);
    }
</script>

<AppHead title="Editar Empleado - Comercio" />

<AppLayout
    breadcrumbs={[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Empleados', href: '/tenant/users' },
        { title: 'Editar Empleado', href: `/tenant/users/${user.id}/edit` },
    ]}
>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-2xl mx-auto w-full">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Editar Empleado</h1>
                <p class="text-sm text-muted-foreground">
                    Modifica los datos del empleado o su rol asignado.
                </p>
            </div>
            <Link href="/tenant/users">
                <Button variant="outline">Volver</Button>
            </Link>
        </div>

        <form onsubmit={submit} class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Datos del Empleado</CardTitle>
                    <CardDescription>Actualiza el perfil y permisos del colaborador.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="user-name">Nombre Completo *</Label>
                        <Input
                            id="user-name"
                            bind:value={form.name}
                            required
                        />
                        {#if form.errors.name}
                            <p class="text-xs text-destructive">{form.errors.name}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="user-email">Correo Electrónico (Login) *</Label>
                        <Input
                            id="user-email"
                            type="email"
                            bind:value={form.email}
                            required
                        />
                        {#if form.errors.email}
                            <p class="text-xs text-destructive">{form.errors.email}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="user-role">Rol Asignado *</Label>
                        <select
                            id="user-role"
                            bind:value={form.role}
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            required
                        >
                            {#each roles as role (role.id)}
                                <option value={role.name}>{role.name}</option>
                            {/each}
                        </select>
                        {#if form.errors.role}
                            <p class="text-xs text-destructive">{form.errors.role}</p>
                        {/if}
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 pt-2 border-t">
                        <div class="space-y-2">
                            <Label for="user-password">Nueva Contraseña (Opcional)</Label>
                            <Input
                                id="user-password"
                                type="password"
                                bind:value={form.password}
                                placeholder="Dejar en blanco para no cambiar"
                            />
                            {#if form.errors.password}
                                <p class="text-xs text-destructive">{form.errors.password}</p>
                            {/if}
                        </div>

                        <div class="space-y-2">
                            <Label for="user-password-confirmation">Confirmar Nueva Contraseña</Label>
                            <Input
                                id="user-password-confirmation"
                                type="password"
                                bind:value={form.password_confirmation}
                                placeholder="Repite la nueva contraseña"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-3 pt-2">
                <Link href="/tenant/users">
                    <Button type="button" variant="outline">Cancelar</Button>
                </Link>
                <Button type="submit" disabled={form.processing} class="min-w-36">
                    {form.processing ? 'Actualizando...' : 'Guardar Cambios'}
                </Button>
            </div>
        </form>
    </div>
</AppLayout>
