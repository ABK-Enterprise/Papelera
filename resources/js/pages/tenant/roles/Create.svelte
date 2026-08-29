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
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import { Spinner } from '@/components/ui/spinner';

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

    let { modules = [] }: { modules: ModuleItem[] } = $props();

    const form = useForm({
        name: '',
        permissions: [] as string[],
    });

    function togglePermission(name: string) {
        if ($form.permissions.includes(name)) {
            $form.permissions = $form.permissions.filter((p) => p !== name);
        } else {
            $form.permissions = [...$form.permissions, name];
        }
    }

    function toggleAllModulePermissions(mod: ModuleItem, selectAll: boolean) {
        const modPermNames = mod.permissions.map((p) => p.name);
        if (selectAll) {
            const newPerms = new Set([...$form.permissions, ...modPermNames]);
            $form.permissions = Array.from(newPerms);
        } else {
            $form.permissions = $form.permissions.filter((p) => !modPermNames.includes(p));
        }
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();
        $form.post('/tenant/roles');
    }
</script>

<AppHead title="Nuevo Rol - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-4xl mx-auto w-full">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Shield class="h-6 w-6 stroke-[1.75]" />
            </div>
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">Crear rol</h1>
                <p class="text-sm text-muted-foreground mt-0.5">
                    Define un perfil operativo y selecciona los permisos según tus módulos contratados.
                </p>
            </div>
        </div>
        <Link href="/tenant/roles">
            <Button variant="outline" class="min-h-[44px] gap-2 rounded-xl border-border/70 font-medium">
                <ArrowLeft class="h-4 w-4" />
                <span>Volver</span>
            </Button>
        </Link>
    </div>

    <form onsubmit={submit} class="space-y-6">
        <Card class="rounded-2xl border-border/70 bg-card shadow-xs">
            <CardHeader class="pb-4">
                <CardTitle class="text-base font-semibold text-foreground">Información del Rol</CardTitle>
                <CardDescription class="text-xs text-muted-foreground">Nombre identificador del puesto o nivel de acceso.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4 pt-0">
                <div class="space-y-2">
                    <Label for="role-name" class="text-sm font-medium text-foreground">Nombre del Rol <span class="text-destructive">*</span></Label>
                    <Input
                        id="role-name"
                        bind:value={$form.name}
                        placeholder="ej. Cajero, Repositor, Encargado de Ventas"
                        required
                        class="h-11 rounded-xl border-border/70 bg-muted/20 px-3.5 text-sm font-normal transition-colors focus:bg-background"
                    />
                    {#if $form.errors.name}
                        <p class="text-xs font-medium text-destructive">{$form.errors.name}</p>
                    {/if}
                </div>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70 bg-card shadow-xs">
            <CardHeader class="pb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="text-base font-semibold text-foreground">Permisos por Módulo</CardTitle>
                        <CardDescription class="text-xs text-muted-foreground mt-0.5">
                            Selecciona qué acciones específicas podrán ejecutar los empleados asignados a este rol.
                        </CardDescription>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium tracking-wide text-primary tabular-nums">
                        {$form.permissions.length} seleccionados
                    </span>
                </div>
            </CardHeader>
            <CardContent class="space-y-6 pt-0">
                {#each modules as mod (mod.id)}
                    {@const modPermNames = mod.permissions.map((p) => p.name)}
                    {@const allSelected = mod.permissions.length > 0 && mod.permissions.every((p) => $form.permissions.includes(p.name))}
                    <div class="rounded-2xl border border-border/60 p-4 space-y-3.5 bg-card shadow-2xs">
                        <div class="flex items-center justify-between border-b border-border/40 pb-3">
                            <div class="flex items-center gap-2">
                                <h4 class="font-semibold text-sm text-foreground">{mod.name}</h4>
                                <Badge variant="outline" class=" text-xs bg-muted/40 px-2">{mod.code}</Badge>
                            </div>
                            {#if mod.permissions.length > 0}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="min-h-[36px] text-xs font-medium rounded-lg"
                                    onclick={() => toggleAllModulePermissions(mod, !allSelected)}
                                >
                                    {allSelected ? 'Deseleccionar todos' : 'Seleccionar todos'}
                                </Button>
                            {/if}
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5 pt-1">
                            {#each mod.permissions as perm (perm.id)}
                                {@const isChecked = $form.permissions.includes(perm.name)}
                                <button
                                    type="button"
                                    onclick={() => togglePermission(perm.name)}
                                    class="flex min-h-[44px] items-center gap-3 rounded-xl border p-2.5 text-left text-xs cursor-pointer select-none transition-all {isChecked
                                        ? 'border-primary bg-primary/5 text-foreground shadow-2xs font-semibold'
                                       : 'border-border/60 bg-muted/10 hover:bg-muted/30 text-muted-foreground'}"
                                >
                                    <div class="size-4.5 rounded-md border flex items-center justify-center transition-colors shrink-0 {isChecked ? 'bg-primary text-primary-foreground border-primary' : 'border-border bg-background'}">
                                        {#if isChecked}
                                            <Check class="size-3 stroke-[3]" />
                                        {/if}
                                    </div>
                                    <span class=" text-xs truncate flex-1">{perm.name}</span>
                                </button>
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
                    <div class="py-8 text-center text-xs italic text-muted-foreground">
                        Tu comercio no tiene módulos activos contratados en este momento.
                    </div>
                {/if}

                {#if $form.errors.permissions}
                    <p class="text-xs font-medium text-destructive">{$form.errors.permissions}</p>
                {/if}
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-3">
            <Link href="/tenant/roles">
                <Button type="button" variant="outline" class="min-h-[44px] px-5 rounded-xl font-medium border-border/70 text-xs">
                    Cancelar
                </Button>
            </Link>
            <Button
                type="submit"
                disabled={$form.processing}
                class="min-h-[44px] min-w-36 gap-2 rounded-xl font-medium text-xs shadow-sm"
            >
                {#if $form.processing}
                    <Spinner class="mr-2" />
                    <span>Guardando...</span>
                {:else}
                    <Shield class="h-4 w-4" />
                    <span>Crear rol</span>
                {/if}
            </Button>
        </div>
    </form>
</div>
