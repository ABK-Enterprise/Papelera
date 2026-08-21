<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router, useForm } from '@inertiajs/svelte';

    interface PermissionItem {
        id: number;
        name: string;
        module_id: number;
    }

    interface ModuleItem {
        id: number;
        name: string;
        code: string;
        description: string | null;
        is_active: boolean;
        tenants_count?: number;
        permissions: PermissionItem[];
    }

    let { modules = [] }: { modules: ModuleItem[] } = $props();

    let isCreating = $state(false);
    let editingModule = $state<ModuleItem | null>(null);
    let newPermissionInput = $state('');

    const form = useForm({
        name: '',
        code: '',
        description: '',
        is_active: true,
        permissions: [] as string[],
    });

    function openCreate() {
        editingModule = null;
        form.reset();
        form.permissions = [];
        newPermissionInput = '';
        isCreating = true;
    }

    function openEdit(mod: ModuleItem) {
        editingModule = mod;
        form.name = mod.name;
        form.code = mod.code;
        form.description = mod.description || '';
        form.is_active = mod.is_active;
        form.permissions = mod.permissions.map((p) => p.name);
        newPermissionInput = '';
        isCreating = true;
    }

    function closeForm() {
        isCreating = false;
        editingModule = null;
    }

    function addPermission() {
        const val = newPermissionInput.trim().toLowerCase();
        if (val && !form.permissions.includes(val)) {
            form.permissions = [...form.permissions, val];
            newPermissionInput = '';
        }
    }

    function removePermission(perm: string) {
        form.permissions = form.permissions.filter((p) => p !== perm);
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();
        if (editingModule) {
            form.put(`/platform/modules/${editingModule.id}`, {
                onSuccess: () => closeForm(),
            });
        } else {
            form.post('/platform/modules', {
                onSuccess: () => closeForm(),
            });
        }
    }

    function toggleActive(mod: ModuleItem) {
        router.patch(`/platform/modules/${mod.id}/toggle-active`);
    }
</script>

<AppHead title="Catálogo de Módulos - Plataforma" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Catálogo de Módulos del Sistema</h1>
                <p class="text-sm text-muted-foreground">
                    Define los módulos de suscripción disponibles y los permisos granulares asociados a cada uno.
                </p>
            </div>
            <Button onclick={openCreate}>+ Nuevo Módulo</Button>
        </div>

        {#if isCreating}
            <Card class="border-primary/50 shadow-md">
                <CardHeader>
                    <CardTitle>{editingModule ? 'Editar Módulo' : 'Nuevo Módulo de Negocio'}</CardTitle>
                    <CardDescription>
                        {editingModule
                            ? 'Actualiza los datos del módulo y su conjunto de permisos.'
                            : 'Crea un nuevo módulo que podrá ser contratado por los comercios.'}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onsubmit={submit} class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="mod-name">Nombre del Módulo</Label>
                                <Input
                                    id="mod-name"
                                    bind:value={form.name}
                                    placeholder="ej. Facturación ARCA"
                                    required
                                />
                                {#if form.errors.name}
                                    <p class="text-xs text-destructive">{form.errors.name}</p>
                                {/if}
                            </div>
                            <div class="space-y-2">
                                <Label for="mod-code">Código Único (Slug)</Label>
                                <Input
                                    id="mod-code"
                                    bind:value={form.code}
                                    placeholder="ej. facturacion_arca"
                                    required
                                />
                                {#if form.errors.code}
                                    <p class="text-xs text-destructive">{form.errors.code}</p>
                                {/if}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="mod-desc">Descripción</Label>
                            <Input
                                id="mod-desc"
                                bind:value={form.description}
                                placeholder="Breve descripción del alcance del módulo..."
                            />
                        </div>

                        <div class="space-y-2">
                            <Label>Permisos Asociados a este Módulo</Label>
                            <div class="flex gap-2">
                                <Input
                                    bind:value={newPermissionInput}
                                    placeholder="ej. facturas.emitir, facturas.anular"
                                    onkeydown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            addPermission();
                                        }
                                    }}
                                />
                                <Button type="button" variant="secondary" onclick={addPermission}>
                                    Agregar
                                </Button>
                            </div>
                            <div class="flex flex-wrap gap-2 pt-2">
                                {#each form.permissions as perm}
                                    <Badge variant="outline" class="flex items-center gap-1 py-1 pl-2.5 pr-1.5 text-xs">
                                        <code>{perm}</code>
                                        <button
                                            type="button"
                                            class="ml-1 rounded-full p-0.5 hover:bg-destructive/20 hover:text-destructive"
                                            onclick={() => removePermission(perm)}
                                        >
                                            ✕
                                        </button>
                                    </Badge>
                                {/each}
                                {#if form.permissions.length === 0}
                                    <p class="text-xs italic text-muted-foreground">
                                        No hay permisos agregados aún. Escribe un permiso y presiona Enter o Agregar.
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t">
                            <Button type="button" variant="outline" onclick={closeForm}>Cancelar</Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Guardando...' : editingModule ? 'Guardar Cambios' : 'Crear Módulo'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        {/if}

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            {#each modules as mod (mod.id)}
                <Card class="flex flex-col justify-between transition-all hover:shadow-md {mod.is_active ? '' : 'opacity-60 bg-muted/30'}">
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <CardTitle class="text-lg">{mod.name}</CardTitle>
                                <span class="font-mono text-xs text-muted-foreground">{mod.code}</span>
                            </div>
                            <Badge variant={mod.is_active ? 'default' : 'secondary'}>
                                {mod.is_active ? 'Activo' : 'Inactivo'}
                            </Badge>
                        </div>
                        {#if mod.description}
                            <CardDescription class="pt-2 text-xs">{mod.description}</CardDescription>
                        {/if}
                    </CardHeader>
                    <CardContent class="space-y-4 pt-0">
                        <div class="space-y-1.5">
                            <span class="text-xs font-semibold text-muted-foreground">Permisos ({mod.permissions.length}):</span>
                            <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto">
                                {#each mod.permissions as perm}
                                    <Badge variant="outline" class="text-[10px] font-mono">
                                        {perm.name}
                                    </Badge>
                                {/each}
                                {#if mod.permissions.length === 0}
                                    <span class="text-xs italic text-muted-foreground">Sin permisos definidos</span>
                                {/if}
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t pt-3 text-xs text-muted-foreground">
                            <span>{mod.tenants_count ?? 0} comercios activos</span>
                            <div class="flex gap-2">
                                <Button size="sm" variant="ghost" onclick={() => openEdit(mod)}>
                                    Editar
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onclick={() => toggleActive(mod)}
                                >
                                    {mod.is_active ? 'Desactivar' : 'Activar'}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            {/each}

            {#if modules.length === 0 && !isCreating}
                <div class="col-span-full rounded-xl border border-dashed p-12 text-center">
                    <h3 class="text-lg font-medium">No hay módulos configurados</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Crea el primer módulo para comenzar a empaquetar funcionalidades para tus clientes.
                    </p>
                    <Button class="mt-4" onclick={openCreate}>Crear Módulo</Button>
                </div>
            {/if}
        </div>
    </div>
