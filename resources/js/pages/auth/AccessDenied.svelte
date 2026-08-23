<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
    import { Link, router } from '@inertiajs/svelte';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import LogOut from 'lucide-svelte/icons/log-out';
    import LayoutGrid from 'lucide-svelte/icons/layout-grid';
    import Lock from 'lucide-svelte/icons/lock';

    let {
        reason = 'insufficient_permissions',
    }: {
        reason?: string;
    } = $props();

    let subtitle = $derived.by(() => {
        switch (reason) {
            case 'account_deactivated':
                return 'Te desactivaron la cuenta';
            case 'module_not_assigned':
                return 'Módulo no contratado';
            case 'insufficient_permissions':
            case 'no_permissions':
                return 'Sin permisos asignados';
            case 'unauthorized':
            default:
                return 'Sin permisos de acceso';
        }
    });

    let description = $derived.by(() => {
        switch (reason) {
            case 'account_deactivated':
                return 'El comercio al que pertenece tu usuario ha sido desactivado o suspendido por el superadministrador. Comunícate con el soporte de la plataforma para más información.';
            case 'module_not_assigned':
                return 'Tu comercio no tiene habilitado este módulo en la plataforma. Contacta al administrador para gestionar la activación de los módulos.';
            case 'insufficient_permissions':
            case 'no_permissions':
                return 'Tu usuario no cuenta con los permisos necesarios para acceder a esta sección. Contacta al administrador de tu comercio para que te asigne los permisos correspondientes.';
            case 'unauthorized':
            default:
                return 'Tu usuario se encuentra autenticado pero no tiene los permisos requeridos para ingresar al sistema o módulo solicitado.';
        }
    });

    function logout() {
        router.post('/logout');
    }
</script>

<AppHead title="Acceso Denegado - Papelera" />

<div class="min-h-screen w-full flex flex-col items-center justify-center p-4 bg-background text-foreground">
    <div class="w-full max-w-md">
        <!-- Logo / Marca -->
        <div class="flex items-center justify-center gap-2 mb-6">
            <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Lock class="size-5" />
            </div>
            <span class="text-xl font-bold tracking-tight">Papelera Cloud</span>
        </div>

        <Card class="border-border/60 shadow-lg text-center overflow-hidden">
            <!-- Barra superior en degradé anaranjado -->
            <div class="h-2 bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600"></div>

            <CardHeader class="pt-8 pb-4">
                <div class="flex justify-center mb-3">
                    <div class="flex size-14 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                        <ShieldAlert class="size-7" />
                    </div>
                </div>

                <CardTitle class="text-xl font-bold text-foreground leading-snug px-2">
                    No tienes acceso al sistema, contacta con un administrador
                </CardTitle>

                <!-- Subtítulo anaranjado destacado -->
                <div class="pt-2.5">
                    <span class="inline-flex items-center px-3.5 py-1 rounded-md text-xs font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 tracking-wide uppercase">
                        {subtitle}
                    </span>
                </div>

                <CardDescription class="text-xs text-muted-foreground mt-3 leading-relaxed px-4">
                    {description}
                </CardDescription>
            </CardHeader>

            <CardContent class="pb-2">
                <div class="rounded-lg bg-muted/40 p-3 text-[11px] text-muted-foreground border border-border/40">
                    Si consideras que esto es un error, por favor contacta al administrador del sistema indicando tu correo electrónico registrado.
                </div>
            </CardContent>

            <CardFooter class="pt-4 pb-6 flex flex-wrap justify-center gap-2 border-t border-border/40 mt-2">
                {#if reason !== 'account_deactivated'}
                    <Link href="/dashboard">
                        <Button
                            variant="default"
                            size="sm"
                            class="text-xs font-semibold gap-1.5"
                        >
                            <LayoutGrid class="size-3.5" />
                            Ir al Dashboard
                        </Button>
                    </Link>
                {/if}
                <Button
                    variant="outline"
                    size="sm"
                    onclick={logout}
                    class="text-xs font-semibold gap-1.5"
                >
                    <LogOut class="size-3.5" />
                    Cerrar sesión
                </Button>
            </CardFooter>
        </Card>
    </div>
</div>
