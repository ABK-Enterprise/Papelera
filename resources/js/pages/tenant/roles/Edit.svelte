<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Badge } from '@/components/ui/badge';
    import { Link, useForm } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import Shield from 'lucide-svelte/icons/shield';

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

    function togglePermission(name: string) {
        if (form.permissions.includes(name)) {
            form.permissions = form.permissions.filter((p) => p !== name);
        } else {
            form.permissions = [...form.permissions, name];
        }
    }

    function toggleAllModulePermissions(mod: ModuleItem, selectAll: boolean) {
        const modPermNames = mod.permissions.map((p) => p.name);
        if (selectAll) {
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
        <div class="flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Shield class="size-5" />
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Editar Rol y Permisos</h1>
                <p class="text-xs sm:text-sm text-muted-foreground">
                    Modifica el nombre y permisos asociados al perfil seleccionado.
                </p>
            </div>
        </div>
        <Link href="/tenant/roles">
            <Button variant="outline" size="sm" class="text-xs">Volver</Button>
        </Link>
    </div>

    <form onsubmit={submit} class="space-y-6">
        <Card class="border-border/60 shadow-xs">
            <CardHeader class="pb-4">
                <CardTitle class="text-base font-bold text-foreground">Información del Rol</CardTitle>
                <CardDescription class="text-xs text-muted-foreground">Nombre identificador del rol.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-1.5">
                    <Label for="role-name" class="text-xs font-semibold text-foreground">Nombre del Rol *</Label>
                    <Input
                        id="role-name"
                        bind:value={form.name}
                        required
                        class="text-sm shadow-2xs"
                    />
                    {#if form.errors.name}
                        <p class="text-xs text-rose-500 font-medium">{form.errors.name}</p>
                    {/if}
                </div>
            </CardContent>
        </Card>

        <Card class="border-border/60 shadow-xs">
            <CardHeader class="pb-4">
                <CardTitle class="text-base font-bold text-foreground">Permisos por Módulo Contratado</CardTitle>
                <CardDescription class="text-xs text-muted-foreground">
                    Selecciona qué acciones específicas podrán ejecutar los usuarios con este rol.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                {#each modules as mod (mod.id)}
                    {@const modPermNames = mod.permissions.map((p) => p.name)}
                    {@const allSelected = mod.permissions.length > 0 && mod.permissions.every((p) => form.permissions.includes(p.name))}
                    <div class="rounded-xl border border-border/60 p-4 space-y-3 bg-card/60 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-border/60 pb-3">
                            <div class="flex items-center gap-2">
                                <h4 class="font-bold text-sm text-foreground">{mod.name}</h4>
                                <Badge variant="outline" class="font-mono text-[11px] bg-muted/50">{mod.code}</Badge>
                            </div>
                            {#if mod.permissions.length > 0}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 text-xs font-medium"
                                    onclick={() => toggleAllModulePermissions(mod, !allSelected)}
                                >
                                    {allSelected ? 'Deseleccionar todos' : 'Seleccionar todos'}
                                </Button>
                            {/if}
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5 pt-1">
                            {#each mod.permissions as perm (perm.id)}
                                {@const isChecked = form.permissions.includes(perm.name)}
                                <div
                                    role="button"
                                    tabindex="0"
                                    onclick={() => togglePermission(perm.name)}
                                    onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && togglePermission(perm.name)}
                                    class="flex items-center gap-2.5 rounded-lg border p-2.5 text-xs cursor-pointer select-none transition-all {isChecked
                                        ? 'border-primary/60 bg-primary/5 text-foreground shadow-2xs font-semibold'
                                        : 'border-border/60 bg-background hover:bg-muted/40 text-muted-foreground'}"
                                >
                                    <div class="size-4 rounded-md border flex items-center justify-center transition-colors shrink-0 {isChecked ? 'bg-primary text-primary-foreground border-primary' : 'border-border bg-background'}">
                                        {#if isChecked}
                                            <Check class="size-3 stroke-[3]" />
                                        {/if}
                                    </div>
                                    <span class="font-mono text-xs truncate">{perm.name}</span>
                                </div>
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
                    <div class="py-6 text-center text-xs italic text-muted-foreground">
                        Tu comercio no tiene módulos activos contratados en este momento.
                    </div>
                {/if}

                {#if form.errors.permissions}
                    <p class="text-xs text-rose-500 font-medium">{form.errors.permissions}</p>
                {/if}
            </CardContent>
        </Card>

        <div class="flex justify-end gap-3 pt-2">
            <Link href="/tenant/roles">
                <Button type="button" variant="outline" size="sm" class="text-xs">Cancelar</Button>
            </Link>
            <Button type="submit" disabled={form.processing} size="sm" class="min-w-36 text-xs font-semibold">
                {form.processing ? 'Guardando...' : 'Guardar Cambios'}
            </Button>
        </div>
    </form>
</div>
