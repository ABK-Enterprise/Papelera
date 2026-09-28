<script module lang="ts">
    export const layout = {
        title: 'Recuperar contraseña',
        description: 'Ingresá tu correo electrónico para recibir el enlace de recuperación',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { login } from '@/routes';
    import { email } from '@/routes/password';

    let {
        status = '',
    }: {
        status?: string;
    } = $props();

    let formattedStatus = $derived(
        status === 'passwords.sent' || status === 'password.sent' || status === 'sent'
            ? 'Correo enviado a la cuenta.'
            : status
    );

    function formatError(error?: string): string {
        if (!error) return '';
        if (
            error === 'passwords.user' ||
            error === 'password.user' ||
            error === 'password.error' ||
            error === 'password.auth' ||
            error === 'auth.failed'
        ) {
            return 'Cuenta de correo incorrecta. Intente denuevo.';
        }
        if (error === 'passwords.throttled' || error === 'password.throttled') {
            return 'Demasiados intentos. Por favor espere antes de reintentar.';
        }
        return error;
    }
</script>

<AppHead title="Recuperar contraseña" />

{#if formattedStatus}
    <div class="mb-5 flex items-center justify-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3.5 text-center text-sm font-medium text-emerald-700 dark:text-emerald-400 shadow-2xs">
        <CheckCircle2 class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
        <span>{formattedStatus}</span>
    </div>
{/if}

<div class="space-y-6">
    <Form {...email.form()} class="space-y-4">
        {#snippet children({ errors, processing })}
            <div class="grid gap-2">
                <Label for="email" class="text-sm font-medium text-foreground">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="nombre@empresa.com"
                    class="h-11 rounded-lg border-input/60 bg-muted/40 px-3.5 transition-colors focus:bg-background"
                />
                <InputError message={formatError(errors.email)} />
            </div>

            <div class="pt-2">
                <Button
                    type="submit"
                    class="h-11 w-full gap-2 rounded-lg font-medium shadow-sm transition-all hover:shadow"
                    disabled={processing}
                    data-test="email-password-reset-link-button"
                >
                    {#if processing}
                        <Spinner class="size-4 text-current" />
                        <span>Enviando enlace...</span>
                    {:else}
                        <span>Enviar enlace de recuperación</span>
                        <ArrowRight class="size-4" />
                    {/if}
                </Button>
            </div>
        {/snippet}
    </Form>

    <div class="space-x-1 text-center text-sm text-muted-foreground pt-1">
        <span>¿Te acordás de tu contraseña?</span>
        <TextLink href={login()} class="text-primary hover:underline">Iniciar sesión</TextLink>
    </div>
</div>
