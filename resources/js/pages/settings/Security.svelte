<script module lang="ts">
    import { edit } from '@/routes/security';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Configuración de seguridad',
                href: edit(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';

    let {
        passwordRules,
        hasPassword = true,
    }: {
        passwordRules: string;
        hasPassword?: boolean;
    } = $props();

    let newPassword = $state('');

    const criteria = $derived([
        { id: 'length', label: 'Al menos 8 caracteres', met: newPassword.length >= 8 },
        { id: 'lowercase', label: 'Letras minúsculas (a-z)', met: /[a-z]/.test(newPassword) },
        { id: 'uppercase', label: 'Letras mayúsculas (A-Z)', met: /[A-Z]/.test(newPassword) },
        { id: 'number', label: 'Números (0-9)', met: /[0-9]/.test(newPassword) },
        { id: 'special', label: 'Símbolos especiales (!@#$...)', met: /[^A-Za-z0-9]/.test(newPassword) },
    ]);

    const score = $derived(criteria.filter((c) => c.met).length);

    const strength = $derived.by(() => {
        if (score <= 1) {
            return {
                level: 1,
                label: score === 0 ? 'Muy débil' : 'Débil',
                color: 'bg-destructive',
                textColor: 'text-destructive',
            };
        }
        if (score === 2) {
            return {
                level: 2,
                label: 'Regular',
                color: 'bg-amber-500',
                textColor: 'text-amber-600 dark:text-amber-400',
            };
        }
        if (score === 3) {
            return {
                level: 3,
                label: 'Buena',
                color: 'bg-yellow-500',
                textColor: 'text-yellow-600 dark:text-yellow-400',
            };
        }
        return {
            level: 4,
            label: score === 5 ? 'Excelente' : 'Muy segura',
            color: 'bg-emerald-500',
            textColor: 'text-emerald-600 dark:text-emerald-400',
        };
    });
</script>

<AppHead title="Configuración de seguridad" />

<h1 class="sr-only">Configuración de seguridad</h1>

<div class="space-y-6">
    <Heading
        variant="small"
        title={hasPassword ? 'Actualizar contraseña' : 'Establecer contraseña'}
        description={hasPassword
            ? 'Asegúrate de que tu cuenta utilice una contraseña segura para proteger tu acceso'
            : 'Establece una contraseña para poder iniciar sesión con tu correo electrónico'}
    />

    <Form
        {...SecurityController.update.form()}
        class="space-y-6"
        options={{
            preserveScroll: true,
            onSuccess: () => {
                newPassword = '';
            },
        }}
        resetOnSuccess
        resetOnError={['password', 'password_confirmation', 'current_password']}
    >
        {#snippet children({ errors, processing })}
            {#if hasPassword}
                <div class="grid gap-2">
                    <Label for="current_password">Contraseña actual</Label>
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        class="mt-1 block w-full"
                        autocomplete="current-password"
                        placeholder="Contraseña actual"
                    />
                    <InputError message={errors.current_password} />
                </div>
            {/if}

            <div class="grid gap-2">
                <Label for="password">{hasPassword ? 'Nueva contraseña' : 'Contraseña'}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder={hasPassword ? 'Nueva contraseña' : 'Contraseña'}
                    passwordrules={passwordRules}
                    maxlength="64"
                    bind:value={newPassword}
                    oninput={(e: Event) => {
                        newPassword = (e.currentTarget as HTMLInputElement).value;
                    }}
                />
                <InputError message={errors.password} />

                {#if newPassword.length > 0}
                    <div class="mt-2 space-y-2.5 rounded-lg border border-border/60 bg-muted/20 p-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted-foreground">Seguridad de la contraseña:</span>
                            <span class="font-semibold {strength.textColor}">{strength.label}</span>
                        </div>

                        <div
                            class="grid grid-cols-4 gap-1.5"
                            role="progressbar"
                            aria-valuenow={strength.level}
                            aria-valuemin={1}
                            aria-valuemax={4}
                            aria-valuetext={strength.label}
                            aria-label="Nivel de seguridad de la contraseña"
                        >
                            {#each [1, 2, 3, 4] as step}
                                <div
                                    class="h-1.5 rounded-full transition-all duration-300 {step <= strength.level ? strength.color : 'bg-muted dark:bg-neutral-800'}"
                                ></div>
                            {/each}
                        </div>

                        <div class="grid grid-cols-1 gap-1.5 pt-1 sm:grid-cols-2">
                            {#each criteria as item (item.id)}
                                <div class="flex items-center gap-1.5 text-xs transition-colors {item.met ? 'font-medium text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'}">
                                    {#if item.met}
                                        <Check class="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                                    {:else}
                                        <span class="mx-1 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-muted-foreground/40"></span>
                                    {/if}
                                    <span>{item.label}</span>
                                </div>
                            {/each}
                        </div>
                    </div>
                {/if}
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar contraseña</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Confirmar contraseña"
                    passwordrules={passwordRules}
                    maxlength="64"
                />
                <InputError message={errors.password_confirmation} />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    disabled={processing}
                    class="min-h-[40px] px-5 rounded-xl font-semibold"
                    data-test="update-password-button"
                >
                    Guardar cambios
                </Button>
            </div>
        {/snippet}
    </Form>
</div>
