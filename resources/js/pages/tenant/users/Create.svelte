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

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: roles.length > 0 ? roles[0].name : '',
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        $form.post('/tenant/users');
    }
</script>

<AppHead title="Nuevo Empleado - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-2xl mx-auto w-full">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">Añadir empleado</h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Crea una cuenta de acceso para un miembro del equipo en tu comercio.
            </p>
        </div>
        <Link href="/tenant/users">
            <Button variant="outline" class="min-h-[44px] gap-2 rounded-xl border-border/70 font-medium">
                <ArrowLeft class="h-4 w-4" />
                <span>Volver</span>
            </Button>
        </Link>
    </div>

    <form onsubmit={submit} class="space-y-6">
        <Card class="rounded-2xl border-border/70 bg-card shadow-xs">
            <CardHeader class="pb-4">
                <CardTitle class="text-base font-semibold text-foreground">Información del Empleado</CardTitle>
                <CardDescription class="text-xs text-muted-foreground">
                    Completa los datos de acceso e identidad del usuario.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4 pt-0">
                <div class="space-y-2">
                    <Label for="user-name" class="text-sm font-medium text-foreground">Nombre completo <span class="text-destructive">*</span></Label>
                    <Input
                        id="user-name"
                        bind:value={$form.name}
                        placeholder="ej. María González"
                        required
                        class="h-11 rounded-xl border-border/70 bg-muted/20 px-3.5 text-sm font-normal transition-colors focus:bg-background"
                    />
                    {#if $form.errors.name}
                        <p class="text-xs font-medium text-destructive">{$form.errors.name}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="user-email" class="text-sm font-medium text-foreground">Correo electrónico (Login) <span class="text-destructive">*</span></Label>
                    <Input
                        id="user-email"
                        type="email"
                        bind:value={$form.email}
                        placeholder="maria@comercio.com"
                        required
                        class="h-11 rounded-xl border-border/70 bg-muted/20 px-3.5 text-sm font-normal transition-colors focus:bg-background"
                    />
                    {#if $form.errors.email}
                        <p class="text-xs font-medium text-destructive">{$form.errors.email}</p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="user-role" class="text-sm font-medium text-foreground">Rol asignado <span class="text-destructive">*</span></Label>
                    <select
                        id="user-role"
                        bind:value={$form.role}
                        class="flex h-11 w-full rounded-xl border border-border/70 bg-muted/20 px-3.5 py-2 text-sm font-normal text-foreground shadow-2xs transition-colors focus:bg-background focus:outline-hidden focus:ring-2 focus:ring-ring"
                        required
                    >
                        {#each roles as role (role.id)}
                            <option value={role.name}>{role.name}</option>
                        {/each}
                    </select>
                    {#if $form.errors.role}
                        <p class="text-xs font-medium text-destructive">{$form.errors.role}</p>
                    {/if}
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 pt-1">
                    <div class="space-y-2">
                        <Label for="user-password" class="text-sm font-medium text-foreground">Contraseña <span class="text-destructive">*</span></Label>
                        <PasswordInput
                            id="user-password"
                            bind:value={$form.password}
                            placeholder="Mínimo 8 caracteres"
                            required
                            class="h-11 rounded-xl border-border/70 bg-muted/20 px-3.5 text-sm transition-colors focus:bg-background"
                        />
                        {#if $form.errors.password}
                            <p class="text-xs font-medium text-destructive">{$form.errors.password}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <Label for="user-password-confirmation" class="text-sm font-medium text-foreground">Confirmar contraseña <span class="text-destructive">*</span></Label>
                        <PasswordInput
                            id="user-password-confirmation"
                            bind:value={$form.password_confirmation}
                            placeholder="Repite la contraseña"
                            required
                            class="h-11 rounded-xl border-border/70 bg-muted/20 px-3.5 text-sm transition-colors focus:bg-background"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-3">
            <Link href="/tenant/users">
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
                    <UserPlus class="h-4 w-4" />
                    <span>Guardar empleado</span>
                {/if}
            </Button>
        </div>
    </form>
</div>
