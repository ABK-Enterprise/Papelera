<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Badge } from '@/components/ui/badge';
    import { useForm, Link } from '@inertiajs/svelte';
    import Shield from 'lucide-svelte/icons/shield';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Check from 'lucide-svelte/icons/check';

    interface PermissionItem {
        id: number;
        name: string;
        module_id: number;
    }

    interface ModuleItem {
        id: number;
        name: string;
        code: string;
        permissions: PermissionItem[];
    }

    let { modules = [] }: { modules: ModuleItem[] } = $props();

    const form = useForm({
        name: '',
        permissions: [] as string[],
    });

    function togglePermission(name: string) {
        if (form.permissions.includes(name)) {
            form.permissions = form.permissions.filter((p) => p !== name);
        } else {
            form.permissions = [...form.permissions, name];
        }
    }

    function toggleAllModulePermissions(mod: ModuleItem, select: boolean) {
        const modPermNames = mod.permissions.map((p) => p.name);
        if (select) {
            const newPerms = new Set([...form.permissions, ...modPermNames]);
            form.permissions = Array.from(newPerms);
        } else {
            form.permissions = form.permissions.filter((p) => !modPermNames.includes(p));
        }
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.post('/tenant/roles');
    }
</script>

<AppHead title="Crear rol - Roles y Permisos" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-4xl mx-auto w-full">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Shield class="h-6 w-6 stroke-[1.75]" />
            </div>
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">Crear rol</h1>
            </div>
        </div>
        <Link
            href="/tenant/roles"
            class="inline-flex items-center gap-2 min-h-[44px] px-4 rounded-xl border border-border/80 bg-secondary hover:bg-secondary/80 text-secondary-foreground text-sm font-medium shadow-2xs transition-colors"
        >
            <ArrowLeft class="size-4" />
            <span>Volver</span>
        </Link>
    </div>

    <form onsubmit={submit} class="space-y-6">
        <Card class="rounded-2xl border-border/70 bg-card shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-semibold text-foreground">Información del Rol</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 pt-0">
                <div class="space-y-2">
                    <Label for="role-name" class="text-sm font-medium text-foreground">Nombre del Rol <span class="text-destructive">*</span></Label>
                    <Input
                        id="role-name"
                        bind:value={form.name}
                        placeholder="ej. Cajero, Repositor, Encargado de Ventas"
                        required
                        class="h-11 rounded-xl border border-border/80 bg-background px-3.5 text-sm font-normal text-foreground transition-colors focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    {#if form.errors.name}
                        <p class="text-xs font-medium text-destructive">{form.errors.name}</p>
                    {/if}
                </div>
            </CardContent>
        </Card>

        <Card class="rounded-2xl border-border/70 bg-card shadow-xs">
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="text-base font-semibold text-foreground">Permisos por Módulo</CardTitle>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-primary/10 px-3.5 py-1 text-xs font-semibold tracking-wide text-primary tabular-nums border border-primary/20">
                        {form.permissions.length} seleccionados
                    </span>
                </div>
            </CardHeader>
            <CardContent class="space-y-6 pt-0">
                {#each modules as mod (mod.id)}
                    {@const modPermNames = mod.permissions.map((p) => p.name)}
                    {@const selectedCountInMod = mod.permissions.filter((p) => form.permissions.includes(p.name)).length}
                    {@const allSelected = mod.permissions.length > 0 && selectedCountInMod === mod.permissions.length}
                    <div class="space-y-2">
                        <!-- Header externo a la caja del módulo con control de alternancia individual -->
                        <div class="flex items-center justify-between px-1">
                            <span class="text-xs font-medium text-muted-foreground tabular-nums">
                                {selectedCountInMod} de {mod.permissions.length} seleccionados
                            </span>
                            {#if mod.permissions.length > 0}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class={`min-h-[36px] text-xs font-semibold rounded-xl border transition-all ${
                                        allSelected
                                            ? 'bg-primary/15 text-primary border-primary/40 hover:bg-primary/25'
                                            : 'bg-background hover:bg-muted text-foreground border-border/80'
                                    }`}
                                    onclick={() => toggleAllModulePermissions(mod, !allSelected)}
                                >
                                    {allSelected ? 'Deseleccionar todos' : 'Seleccionar todos'}
                                </Button>
                            {/if}
                        </div>

                        <!-- Caja / Contenedor del módulo con distinción de superficie tonal escalonada -->
                        <div class="rounded-2xl border border-border/80 p-4 space-y-3.5 bg-background/80 dark:bg-card/50 shadow-xs">
                            <div class="flex items-center justify-between gap-3 border-b border-border/60 pb-3">
                                <h4 class="font-bold text-sm text-foreground truncate flex-1">{mod.name}</h4>
                                <Badge variant="secondary" class="text-xs font-sans tracking-wide px-2.5 py-0.5 shrink-0 border border-border/60">
                                    {mod.code}
                                </Badge>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5 pt-1">
                                {#each mod.permissions as perm (perm.id)}
                                    {@const isChecked = form.permissions.includes(perm.name)}
                                    <button
                                        type="button"
                                        onclick={() => togglePermission(perm.name)}
                                        class="flex min-h-[44px] items-center gap-3 rounded-xl border p-2.5 text-left text-xs cursor-pointer select-none transition-all {isChecked
                                            ? 'border-primary bg-primary/10 text-foreground shadow-2xs font-semibold'
                                            : 'border-border/80 bg-card hover:bg-muted/60 text-muted-foreground'}"
                                    >
                                        <div class="size-4.5 rounded-md border flex items-center justify-center transition-colors shrink-0 {isChecked ? 'bg-primary text-primary-foreground border-primary' : 'border-border/80 bg-background'}">
                                            {#if isChecked}
                                                <Check class="size-3 stroke-[3]" />
                                            {/if}
                                        </div>
                                        <span class="text-xs truncate flex-1 {isChecked ? 'text-foreground font-semibold' : 'text-muted-foreground'}">{perm.name}</span>
                                    </button>
                                {/each}
                                {#if mod.permissions.length === 0}
                                    <p class="text-xs italic text-muted-foreground col-span-full">
                                        Este módulo no define permisos granulares.
                                    </p>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/each}

                {#if modules.length === 0}
                    <div class="py-8 text-center text-xs italic text-muted-foreground">
                        Tu comercio no tiene módulos activos contratados en este momento.
                    </div>
                {/if}

                {#if form.errors.permissions}
                    <p class="text-xs font-medium text-destructive">{form.errors.permissions}</p>
                {/if}
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-3">
            <Link
                href="/tenant/roles"
                class="inline-flex items-center justify-center min-h-[44px] min-w-36 px-5 rounded-xl font-semibold border border-border/80 bg-secondary hover:bg-secondary/80 text-secondary-foreground text-xs transition-colors shadow-2xs"
            >
                Cancelar
            </Link>
            <Button
                type="submit"
                disabled={form.processing}
                class="min-h-[44px] min-w-36 px-5 gap-2 rounded-xl font-semibold text-xs shadow-xs"
            >
                {form.processing ? 'Guardando...' : 'Crear rol'}
            </Button>
        </div>
    </form>
</div>
