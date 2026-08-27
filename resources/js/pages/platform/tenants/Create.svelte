<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Link, useForm } from '@inertiajs/svelte';

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
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight leading-tight text-foreground">Alta de nuevo comercio</h1>
            <p class="text-sm text-muted-foreground mt-0.5 leading-relaxed">
                Crea una nueva empresa en el sistema, su cuenta de administrador inicial y asígnale los módulos contratados.
            </p>
        </div>
        <Link href="/platform/tenants">
            <Button variant="outline" class="min-h-[40px] rounded-xl border-border/70 font-medium">Volver</Button>
        </Link>
    </div>

    <form onsubmit={submit} class="space-y-6">
        <!-- Sección 1: Datos de la Empresa -->
        <Card class="rounded-2xl border-border/70 bg-card shadow-xs">
            <CardHeader class="pb-4">
                <CardTitle class="text-base sm:text-lg font-semibold leading-snug text-foreground">1. Datos del comercio</CardTitle>
                <CardDescription class="text-xs text-muted-foreground leading-relaxed">Información fiscal y comercial de la entidad.</CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2 pt-0">
                <div class="space-y-2">
                    <Label for="tenant-name" class="text-sm font-medium text-foreground">Razón social o nombre fantasía *</Label>
                    <Input
                        id="tenant-name"
                        bind:value={form.tenant.name}
                        placeholder="ej. Papelera del Sol S.R.L."
                        required
                        class="h-11 rounded-xl border-border/70 text-sm"
                    />
                    {#if form.errors['tenant.name']}
                        <p class="text-xs font-medium text-destructive">{form.errors['tenant.name']}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="tenant-cuit" class="text-sm font-medium text-foreground">CUIT / Identificación fiscal</Label>
                    <Input
                        id="tenant-cuit"
                        bind:value={form.tenant.cuit_tax_id}
                        placeholder="ej. 30712345678"
                        class="h-11 rounded-xl border-border/70 font-mono tabular-nums text-sm"
                    />
                    {#if form.errors['tenant.cuit_tax_id']}
                        <p class="text-xs font-medium text-destructive">{form.errors['tenant.cuit_tax_id']}</p>
                    {/if}
                </div>
            </CardContent>
        </Card>

        <!-- Sección 2: Administrador del Comercio -->
        <Card class="rounded-2xl border-border/70 bg-card shadow-xs">
            <CardHeader class="pb-4">
                <CardTitle class="text-base sm:text-lg font-semibold leading-snug text-foreground">2. Administrador principal del comercio</CardTitle>
                <CardDescription class="text-xs text-muted-foreground leading-relaxed">
                    Esta persona recibirá acceso para administrar los empleados y módulos de su comercio.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2 pt-0">
                <div class="space-y-2">
                    <Label for="admin-name" class="text-sm font-medium text-foreground">Nombre y apellido *</Label>
                    <Input
                        id="admin-name"
                        bind:value={form.admin.name}
                        placeholder="ej. Juan Pérez"
                        required
                        class="h-11 rounded-xl border-border/70 text-sm"
                    />
                    {#if form.errors['admin.name']}
                        <p class="text-xs font-medium text-destructive">{form.errors['admin.name']}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="admin-email" class="text-sm font-medium text-foreground">Correo electrónico (Login) *</Label>
                    <Input
                        id="admin-email"
                        type="email"
                        bind:value={form.admin.email}
                        placeholder="admin@comercio.com"
                        required
                        class="h-11 rounded-xl border-border/70 text-sm"
                    />
                    {#if form.errors['admin.email']}
                        <p class="text-xs font-medium text-destructive">{form.errors['admin.email']}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="admin-password" class="text-sm font-medium text-foreground">Contraseña provisoria *</Label>
                    <Input
                        id="admin-password"
                        type="password"
                        bind:value={form.admin.password}
                        placeholder="Mínimo 8 caracteres"
                        required
                        class="h-11 rounded-xl border-border/70 text-sm"
                    />
                    {#if form.errors['admin.password']}
                        <p class="text-xs font-medium text-destructive">{form.errors['admin.password']}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="admin-password-confirm" class="text-sm font-medium text-foreground">Confirmar contraseña *</Label>
                    <Input
                        id="admin-password-confirm"
                        type="password"
                        bind:value={form.admin.password_confirmation}
                        placeholder="Repite la contraseña"
                        required
                        class="h-11 rounded-xl border-border/70 text-sm"
                    />
                </div>
            </CardContent>
        </Card>

        <!-- Sección 3: Módulos Contratados -->
        <Card class="rounded-2xl border-border/70 bg-card shadow-xs">
            <CardHeader class="pb-4">
                <CardTitle class="text-base sm:text-lg font-semibold leading-snug text-foreground">3. Módulos habilitados para este comercio</CardTitle>
                <CardDescription class="text-xs text-muted-foreground leading-relaxed">
                    Selecciona las funcionalidades que este comercio tendrá activas en su suscripción.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-3 md:grid-cols-2 pt-0">
                {#each modules as mod (mod.id)}
                    <label
                        class="flex items-start gap-3 rounded-xl border p-3.5 transition-colors cursor-pointer hover:bg-accent/50 {form.module_ids.includes(mod.id) ? 'border-primary bg-primary/5' : 'border-border/60'}"
                    >
                        <Checkbox
                            checked={form.module_ids.includes(mod.id)}
                            onCheckedChange={(checked) => toggleModule(mod.id, !!checked)}
                            class="mt-0.5"
                        />
                        <div class="space-y-1">
                            <span class="text-sm font-semibold leading-none">{mod.name}</span>
                            <span class="font-mono text-xs text-muted-foreground tabular-nums block">({mod.code})</span>
                            {#if mod.description}
                                <p class="text-xs text-muted-foreground leading-relaxed">{mod.description}</p>
                            {/if}
                        </div>
                    </label>
                {/each}

                {#if modules.length === 0}
                    <div class="col-span-full py-4 text-center text-xs italic text-muted-foreground">
                        No hay módulos activos configurados en el catálogo. Puedes agregarlos más tarde.
                    </div>
                {/if}
            </CardContent>
        </Card>

        <div class="flex justify-end gap-3 pt-2">
            <Link href="/platform/tenants">
                <Button type="button" variant="outline" class="min-h-[44px] px-5 rounded-xl font-medium border-border/70">Cancelar</Button>
            </Link>
            <Button type="submit" disabled={form.processing} class="min-h-[44px] min-w-36 rounded-xl font-medium shadow-sm">
                {form.processing ? 'Creando comercio...' : 'Crear comercio'}
            </Button>
        </div>
    </form>
</div>
