<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Link, useForm } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';

    interface AvailableModule {

        id: number;
        name: string;
        code: string;
        description: string | null;
    }

    let { modules = [] }: { modules: AvailableModule[] } = $props();

    const form = useForm({
        tenant: {
            name: '',
            cuit_tax_id: '',
        },
        admin: {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        },
        module_ids: [] as number[],
    });

    function toggleModule(id: number, checked: boolean) {
        if (checked) {
            if (!form.module_ids.includes(id)) {
                form.module_ids = [...form.module_ids, id];
            }
        } else {
            form.module_ids = form.module_ids.filter((mId) => mId !== id);
        }
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.post('/platform/tenants');
    }
</script>

<AppHead title="Nuevo Comercio - Plataforma" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-4xl mx-auto w-full">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Alta de Nuevo Comercio (Tenant)</h1>
            </div>
            <Link href="/platform/tenants">
                <Button variant="outline" class="min-h-[40px] px-4 rounded-xl text-sm font-medium gap-2">
                    <ArrowLeft class="size-4" />
                    <span>Volver</span>
                </Button>
            </Link>
        </div>


        <form onsubmit={submit} class="space-y-6">
            <!-- Sección 1: Datos de la Empresa -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">1. Datos del Comercio / Empresa</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="tenant-name">Razón Social o Nombre Fantasía *</Label>
                        <Input
                            id="tenant-name"
                            bind:value={form.tenant.name}
                            placeholder="ej. Papelera del Sol S.R.L."
                            required
                        />
                        {#if form.errors['tenant.name']}
                            <p class="text-xs text-destructive">{form.errors['tenant.name']}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="tenant-cuit">CUIT / Identificación Fiscal</Label>
                        <Input
                            id="tenant-cuit"
                            bind:value={form.tenant.cuit_tax_id}
                            placeholder="ej. 30712345678"
                        />
                        {#if form.errors['tenant.cuit_tax_id']}
                            <p class="text-xs text-destructive">{form.errors['tenant.cuit_tax_id']}</p>
                        {/if}
                    </div>
                </CardContent>
            </Card>

            <!-- Sección 2: Administrador del Comercio -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">2. Usuario Administrador del Comercio</CardTitle>
                    <CardDescription>
                        Esta persona recibirá acceso para administrar los empleados y módulos de su comercio.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="admin-name">Nombre Completo *</Label>
                        <Input
                            id="admin-name"
                            bind:value={form.admin.name}
                            placeholder="ej. Juan Pérez"
                            required
                        />
                        {#if form.errors['admin.name']}
                            <p class="text-xs text-destructive">{form.errors['admin.name']}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="admin-email">Correo Electrónico (Login) *</Label>
                        <Input
                            id="admin-email"
                            type="email"
                            bind:value={form.admin.email}
                            placeholder="admin@comercio.com"
                            required
                        />
                        {#if form.errors['admin.email']}
                            <p class="text-xs text-destructive">{form.errors['admin.email']}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="admin-password">Contraseña Provisoria *</Label>
                        <Input
                            id="admin-password"
                            type="password"
                            bind:value={form.admin.password}
                            placeholder="Mínimo 8 caracteres"
                            required
                        />
                        {#if form.errors['admin.password']}
                            <p class="text-xs text-destructive">{form.errors['admin.password']}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="admin-password-confirm">Confirmar Contraseña *</Label>
                        <Input
                            id="admin-password-confirm"
                            type="password"
                            bind:value={form.admin.password_confirmation}
                            placeholder="Repite la contraseña"
                            required
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- Sección 3: Módulos Contratados -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">3. Módulos Habilitados para este Comercio</CardTitle>
                    <CardDescription>
                        Selecciona las funcionalidades que este comercio tendrá activas en su suscripción.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    {#each modules as mod (mod.id)}
                        <label
                            class="flex items-start gap-3 rounded-lg border p-3.5 transition-colors cursor-pointer hover:bg-accent/50 {form.module_ids.includes(mod.id) ? 'border-primary bg-primary/5' : ''}"
                        >
                            <Checkbox
                                checked={form.module_ids.includes(mod.id)}
                                onCheckedChange={(checked) => toggleModule(mod.id, !!checked)}
                                class="mt-0.5"
                            />
                            <div class="space-y-1">
                                <span class="text-sm font-semibold leading-none">{mod.name}</span>
                                <span class="font-mono text-xs text-muted-foreground block">({mod.code})</span>
                                {#if mod.description}
                                    <p class="text-xs text-muted-foreground">{mod.description}</p>
                                {/if}
                            </div>
                        </label>
                    {/each}

                    {#if modules.length === 0}
                        <div class="col-span-full py-4 text-center text-sm text-muted-foreground">
                            No hay módulos activos configurados en el catálogo. Puedes agregarlos más tarde.
                        </div>
                    {/if}
                </CardContent>
            </Card>

            <div class="flex justify-end gap-3 pt-2">
                <Link href="/platform/tenants">
                    <Button type="button" variant="outline">Cancelar</Button>
                </Link>
                <Button type="submit" disabled={form.processing} class="min-w-36">
                    {form.processing ? 'Creando...' : 'Crear Comercio'}
                </Button>
            </div>
        </form>
    </div>
