<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Badge } from '@/components/ui/badge';
    import { Link, useForm } from '@inertiajs/svelte';

    interface PermissionItem {
        id: number;
        name: string;
        module_id: number | null;
    }

    interface ModuleItem {
        id: number;
        name: string;
        code: string;
        description: string | null;
        permissions: PermissionItem[];
    }

    interface RoleItem {
        id: number;
        name: string;
        permissions: PermissionItem[];
    }

    let { role, modules = [] }: { role: RoleItem; modules: ModuleItem[] } = $props();

    const form = useForm({
        name: role.name,
        permissions: role.permissions ? role.permissions.map((p) => p.name) : [] as string[],
    });

    function togglePermission(name: string, checked: boolean) {
        if (checked) {
            if (!form.permissions.includes(name)) {
                form.permissions = [...form.permissions, name];
            }
        } else {
            form.permissions = form.permissions.filter((p) => p !== name);
        }
    }

    function toggleAllModulePermissions(mod: ModuleItem, checked: boolean) {
        const modPermNames = mod.permissions.map((p) => p.name);
        if (checked) {
            const newPerms = new Set([...form.permissions, ...modPermNames]);
            form.permissions = Array.from(newPerms);
        } else {
            form.permissions = form.permissions.filter((p) => !modPermNames.includes(p));
        }
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.put(`/tenant/roles/${role.id}`);
    }
</script>

<AppHead title="Editar Rol - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-4xl mx-auto w-full">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Editar Rol y Permisos</h1>
                <p class="text-sm text-muted-foreground">
                    Modifica el nombre y permisos asociados al perfil seleccionado.
                </p>
            </div>
            <Link href="/tenant/roles">
                <Button variant="outline">Volver</Button>
            </Link>
        </div>

        <form onsubmit={submit} class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Información del Rol</CardTitle>
                    <CardDescription>Nombre identificador del rol.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="role-name">Nombre del Rol *</Label>
                        <Input
                            id="role-name"
                            bind:value={form.name}
                            required
                        />
                        {#if form.errors.name}
                            <p class="text-xs text-destructive">{form.errors.name}</p>
                        {/if}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Permisos por Módulo Contratado</CardTitle>
                    <CardDescription>
                        Selecciona qué acciones específicas podrán ejecutar los usuarios con este rol.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    {#each modules as mod (mod.id)}
                        <div class="rounded-lg border p-4 space-y-3 bg-muted/20">
                            <div class="flex items-center justify-between border-b pb-2">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-semibold text-sm">{mod.name}</h4>
                                    <Badge variant="outline" class="font-mono text-xs">{mod.code}</Badge>
                                </div>
                                {#if mod.permissions.length > 0}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 text-xs"
                                        onclick={() => {
                                            const allSelected = mod.permissions.every((p) => form.permissions.includes(p.name));
                                            toggleAllModulePermissions(mod, !allSelected);
                                        }}
                                    >
                                        {mod.permissions.every((p) => form.permissions.includes(p.name)) ? 'Deseleccionar todos' : 'Seleccionar todos'}
                                    </Button>
                                {/if}
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5 pt-1">
                                {#each mod.permissions as perm (perm.id)}
                                    <label class="flex items-center gap-2 rounded-md border bg-background p-2.5 text-xs cursor-pointer hover:bg-accent/40">
                                        <Checkbox
                                            checked={form.permissions.includes(perm.name)}
                                            onCheckedChange={(checked) => togglePermission(perm.name, !!checked)}
                                        />
                                        <span class="font-mono">{perm.name}</span>
                                    </label>
                                {/each}
                                {#if mod.permissions.length === 0}
                                    <p class="text-xs italic text-muted-foreground col-span-full">
                                        Este módulo no define permisos granulares.
                                    </p>
                                {/if}
                            </div>
                        </div>
                    {/each}

                    {#if modules.length === 0}
                        <div class="py-6 text-center text-sm text-muted-foreground">
                            Tu comercio no tiene módulos activos contratados en este momento.
                        </div>
                    {/if}

                    {#if form.errors.permissions}
                        <p class="text-xs text-destructive">{form.errors.permissions}</p>
                    {/if}
                </CardContent>
            </Card>

            <div class="flex justify-end gap-3 pt-2">
                <Link href="/tenant/roles">
                    <Button type="button" variant="outline">Cancelar</Button>
                </Link>
                <Button type="submit" disabled={form.processing} class="min-w-36">
                    {form.processing ? 'Guardando...' : 'Guardar Cambios'}
                </Button>
            </div>
        </form>
    </div>
