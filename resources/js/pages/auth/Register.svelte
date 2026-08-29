<script module lang="ts">
    export const layout = {
        title: 'Crear Cuenta',
        description: 'Ingresa tus datos para registrarte',
    };
</script>

<script lang="ts">
    import { useForm, Link } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { toUrl } from '@/lib/utils';
    import { login } from '@/routes';

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: Event) {
        e.preventDefault();
        form.post('/register', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }
</script>

<AppHead title="Crear Cuenta" />

<form onsubmit={submit} class="flex flex-col gap-5">
    <div class="grid gap-4">
        <div class="grid gap-2">
            <Label for="name" class="text-sm font-medium text-foreground">Nombre completo</Label>
            <Input
                id="name"
                type="text"
                bind:value={form.name}
                required
                autofocus
                autocomplete="name"
                placeholder="Tu nombre completo"
                class="h-11 rounded-lg border-input/60 bg-muted/40 px-3.5 transition-colors focus:bg-background"
            />
            <InputError message={form.errors.name} />
        </div>

        <div class="grid gap-2">
            <Label for="email" class="text-sm font-medium text-foreground">Correo electrónico</Label>
            <Input
                id="email"
                type="email"
                bind:value={form.email}
                required
                autocomplete="username"
                placeholder="nombre@ejemplo.com"
                class="h-11 rounded-lg border-input/60 bg-muted/40 px-3.5 transition-colors focus:bg-background"
            />
            <InputError message={form.errors.email} />
        </div>

        <div class="grid gap-2">
            <Label for="password" class="text-sm font-medium text-foreground">Contraseña</Label>
            <PasswordInput
                id="password"
                bind:value={form.password}
                required
                autocomplete="new-password"
                placeholder="Crea una contraseña segura"
                class="h-11 rounded-lg border-input/60 bg-muted/40 px-3.5 transition-colors focus:bg-background"
            />
            <InputError message={form.errors.password} />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation" class="text-sm font-medium text-foreground">
                Confirmar contraseña
            </Label>
            <PasswordInput
                id="password_confirmation"
                bind:value={form.password_confirmation}
                required
                autocomplete="new-password"
                placeholder="Repite tu contraseña"
                class="h-11 rounded-lg border-input/60 bg-muted/40 px-3.5 transition-colors focus:bg-background"
            />
            <InputError message={form.errors.password_confirmation} />
        </div>

        <Button
            type="submit"
            class="h-11 w-full rounded-lg font-medium shadow-sm transition-all"
            disabled={form.processing}
        >
            {#if form.processing}
                <Spinner class="mr-2" />
                <span>Registrando...</span>
            {:else}
                <span>Crear cuenta</span>
                <ArrowRight class="ml-2 h-4 w-4" />
            {/if}
        </Button>

        <div class="mt-2 text-center text-sm text-muted-foreground">
            ¿Ya tienes una cuenta?
            <Link
                href={toUrl(login())}
                class="font-medium text-foreground underline underline-offset-4 hover:text-primary transition-colors"
            >
                Inicia sesión
            </Link>
        </div>
    </div>
</form>
