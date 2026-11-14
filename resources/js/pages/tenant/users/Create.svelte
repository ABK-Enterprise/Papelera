<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Link, useForm } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import UserPlus from 'lucide-svelte/icons/user-plus';
    import { Spinner } from '@/components/ui/spinner';

    interface RoleOption {
        id: number;
        name: string;
    }

    let { roles = [] }: { roles: RoleOption[] } = $props();

    const form = useForm(() => ({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: roles.length > 0 ? roles[0].name : '',
    }));

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.post('/tenant/usuarios');
    }
</script>

<AppHead title="Nuevo Empleado - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-2xl mx-auto w-full">
    <div class="flex flex-col gap-3">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">Añadir empleado</h1>
        </div>
        <div class="flex justify-end w-full">
            <Link
                href="/tenant/usuarios"
                class="inline-flex items-center gap-2 min-h-[44px] px-4 rounded-xl border border-border/70 bg-secondary hover:bg-secondary/80 dark:hover:bg-[#3a3a3a] text-secondary-foreground text-sm font-medium shadow-2xs transition-colors"
            >
                <ArrowLeft class="h-4 w-4" />
                <span>Volver</span>
            </Link>
        </div>
    </div>

    <form onsubmit={submit} class="space-y-6">
        <Card class="rounded-2xl border-border/70 bg-card shadow-xs">
            <CardHeader class="pb-4">
                <CardTitle class="text-base font-semibold text-foreground">Información del Empleado</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 pt-0">
                <div class="space-y-2">
                    <Label for="user-name" class="text-sm font-medium text-foreground">Nombre completo <span class="text-destructive">*</span></Label>
                    <Input
                        id="user-name"
                        bind:value={form.name}
                        placeholder="ej. María González"
                        required
                        class="h-11 rounded-xl border border-input bg-field px-3.5 text-sm font-normal text-foreground transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    {#if form.errors.name}
                        <p class="text-xs font-medium text-destructive">{form.errors.name}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="user-email" class="text-sm font-medium text-foreground">Correo electrónico (Login) <span class="text-destructive">*</span></Label>
                    <Input
                        id="user-email"
                        type="email"
                        bind:value={form.email}
                        placeholder="maria@comercio.com"
                        required
                        class="h-11 rounded-xl border border-input bg-field px-3.5 text-sm font-normal text-foreground transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                    {#if form.errors.email}
                        <p class="text-xs font-medium text-destructive">{form.errors.email}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="user-role" class="text-sm font-medium text-foreground">Rol asignado <span class="text-destructive">*</span></Label>
                    <select
                        id="user-role"
                        bind:value={form.role}
                        class="flex h-11 w-full rounded-xl border border-input bg-field px-3.5 py-2 text-sm font-normal text-foreground shadow-2xs transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer"
                        required
                    >
                        {#each roles as role (role.id)}
                            <option value={role.name}>{role.name}</option>
                        {/each}
                    </select>
                    {#if form.errors.role}
                        <p class="text-xs font-medium text-destructive">{form.errors.role}</p>
                    {/if}
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 pt-1">
                    <div class="space-y-2">
                        <Label for="user-password" class="text-sm font-medium text-foreground">Contraseña <span class="text-destructive">*</span></Label>
                        <PasswordInput
                            id="user-password"
                            bind:value={form.password}
                            placeholder="Mínimo 8 caracteres"
                            required
                            class="h-11 rounded-xl border border-input bg-field px-3.5 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                        {#if form.errors.password}
                            <p class="text-xs font-medium text-destructive">{form.errors.password}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="user-password-confirmation" class="text-sm font-medium text-foreground">Confirmar contraseña <span class="text-destructive">*</span></Label>
                        <PasswordInput
                            id="user-password-confirmation"
                            bind:value={form.password_confirmation}
                            placeholder="Repite la contraseña"
                            required
                            class="h-11 rounded-xl border border-input bg-field px-3.5 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-3">
            <Link
                href="/tenant/usuarios"
                class="inline-flex items-center justify-center min-h-[44px] min-w-36 px-5 rounded-xl font-medium border border-border/70 bg-secondary hover:bg-secondary/80 dark:hover:bg-[#3a3a3a] text-secondary-foreground text-sm transition-colors shadow-2xs"
            >
                Cancelar
            </Link>

            <Button
                type="submit"
                disabled={form.processing}
                class="min-h-[44px] min-w-36 px-5 gap-2 rounded-xl font-medium text-sm shadow-xs"
            >
                {#if form.processing}
                    <Spinner class="mr-2" />
                    <span>Guardando...</span>
                {:else}
                    <UserPlus class="h-4 w-4" />
                    <span>Guardar empleado</span>
                {/if}
            </Button>
        </div>
    </form>
</div>
