<script module lang="ts">
    export const layout = {
        title: 'Ingresa a ABK Solutions!',
        description: 'Inicia sesión a tu cuenta para continuar',
    };
</script>

<script lang="ts">
    import { Form, Link } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { toUrl } from '@/lib/utils';
    import { store } from '@/routes/login';
    import { request } from '@/routes/password';

    let {
        status = '',
        canResetPassword,
    }: {
        status?: string;
        canResetPassword: boolean;
    } = $props();
</script>

<AppHead title="Ingresa a ABK Solutions!" />

{#if status}
    <div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3.5 text-center text-sm font-medium text-emerald-700 dark:text-emerald-400 shadow-2xs">
        {status}
    </div>
{/if}

<Form
    {...store.form()}
    resetOnSuccess={['password']}
    class="flex flex-col gap-5"
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-4">
            <div class="grid gap-2">
                <Label for="email" class="text-sm font-medium text-foreground">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="nombre@empresa.com"
                    class="h-11 rounded-lg border-input bg-field px-3.5 transition-colors"
                />
                <InputError message={errors.email === 'auth.failed' ? 'El correo electrónico o la contraseña son incorrectos.' : errors.email} />
            </div>



            <div class="grid gap-2">
                <Label for="password" class="text-sm font-medium text-foreground">Contraseña</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="h-11 rounded-lg border-input bg-field px-3.5 transition-colors"
                />
                <InputError message={errors.password} />
            </div>

            <div class="flex flex-col items-start gap-2.5 py-1 sm:flex-row sm:items-center sm:justify-between sm:gap-0">
                <Label for="remember" class="flex items-center space-x-2 text-sm text-muted-foreground cursor-pointer">
                    <Checkbox id="remember" name="remember" />
                    <span>Recordarme</span>
                </Label>
                {#if canResetPassword}
                    <TextLink href={request()} class="text-xs text-muted-foreground hover:text-foreground">
                        ¿Olvidaste tu contraseña?
                    </TextLink>
                {/if}
            </div>

            <Button
                type="submit"
                class="h-11 w-full gap-2 rounded-lg font-medium shadow-sm transition-all hover:shadow"
                disabled={processing}
                data-test="login-button"
            >
                {#if processing}
                    <Spinner />
                {:else}
                    <span>Iniciar Sesión</span>
                    <ArrowRight class="size-4" />
                {/if}
            </Button>

            <div class="relative my-2">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t border-border/70"></span>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="rounded-md border border-border/80 bg-card px-2.5 py-0.5 font-semibold text-muted-foreground shadow-2xs">o</span>
                </div>
            </div>

            <a
                href="/auth/google/redirect"
                aria-label="Continuar con Google"
                class="inline-flex h-11 w-full items-center justify-center gap-2.5 rounded-lg border border-input bg-card dark:bg-secondary/50 px-4 text-sm font-medium text-foreground shadow-xs transition-all hover:bg-muted dark:hover:bg-secondary/75 hover:shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                data-test="google-login-button"
            >
                <svg class="h-4 w-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24">
                    <path
                        fill="#4285F4"
                        d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                    />
                    <path
                        fill="#34A853"
                        d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                    />
                    <path
                        fill="#FBBC05"
                        d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"
                    />
                    <path
                        fill="#EA4335"
                        d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"
                    />
                </svg>
            </a>
        </div>
    {/snippet}
</Form>
