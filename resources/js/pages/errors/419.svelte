<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
    import Clock from 'lucide-svelte/icons/clock';
    import LogIn from 'lucide-svelte/icons/log-in';
    import KeyRound from 'lucide-svelte/icons/key-round';

    let {
        status = 419,
        message = 'Expiró la sesión en el navegador',
    }: {
        status?: number;
        message?: string;
    } = $props();

    function goToLogin() {
        window.location.href = '/login';
    }
</script>

<AppHead title="419 - Sesión Expirada" />

<div class="relative flex min-h-screen flex-col justify-between overflow-hidden bg-background text-foreground antialiased selection:bg-primary/20">
    <!-- Ambient Background Blobs (coherente con AuthSimpleLayout) -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden">
        <div class="absolute -left-20 top-1/4 h-[480px] w-[480px] rounded-full bg-primary-500/40 blur-[120px] transition-all duration-1000 dark:bg-primary-500/35"></div>
        <div class="absolute -right-20 top-1/3 h-[420px] w-[420px] rounded-full bg-secondary-500/35 blur-[100px] transition-all duration-1000 dark:bg-accent-500/30"></div>
        <div class="absolute -bottom-24 left-1/3 h-[500px] w-[500px] rounded-full bg-accent-500/30 blur-[130px] transition-all duration-1000 dark:bg-primary-600/30"></div>
    </div>

    <main class="relative z-10 mx-auto flex w-full max-w-7xl flex-1 items-center justify-center px-4 py-12 md:px-12">
        <div class="w-full max-w-md">
            <!-- Brand Logo -->
            <div class="flex items-center justify-center gap-2.5 mb-6">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
                    <KeyRound class="size-5" />
                </div>
                <span class="text-xl font-bold tracking-tight text-foreground">Papelera Cloud</span>
            </div>

            <Card class="border-border/80 bg-card/90 shadow-2xl backdrop-blur-2xl text-center overflow-hidden rounded-3xl">
                <!-- Top Accent Line -->
                <div class="h-1.5 bg-gradient-to-r from-primary via-emerald-500 to-primary"></div>

                <CardHeader class="pt-8 pb-4">
                    <div class="flex justify-center mb-3">
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 shadow-xs">
                            <Clock class="size-7" />
                        </div>
                    </div>

                    <div class="inline-flex items-center justify-center gap-1.5 self-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 mb-2">
                        <span class="tabular-nums font-bold">Estado {status}</span>
                        <span>•</span>
                        <span>Autenticación</span>
                    </div>

                    <CardTitle class="text-xl sm:text-2xl font-bold text-foreground leading-snug px-2">
                        Expiró la sesión en el navegador
                    </CardTitle>

                    <CardDescription class="text-xs sm:text-sm text-muted-foreground mt-2 leading-relaxed px-4">
                        {message || 'Por razones de seguridad, la sesión caducó debido a un período prolongado de inactividad o actualización de tokens de seguridad.'}
                    </CardDescription>
                </CardHeader>

                <CardContent class="pb-2">
                    <div class="rounded-xl bg-muted/40 p-3.5 text-xs text-muted-foreground border border-border/40 text-left leading-relaxed">
                        Para volver a interactuar con la plataforma y renovar tus credenciales seguras, por favor ingresa nuevamente tus credenciales de acceso.
                    </div>
                </CardContent>

                <CardFooter class="pt-4 pb-8 flex justify-center border-t border-border/40 mt-3">
                    <Button
                        variant="default"
                        onclick={goToLogin}
                        class="min-h-[44px] px-6 rounded-xl text-sm font-semibold gap-2 shadow-sm cursor-pointer"
                    >
                        <LogIn class="size-4" />
                        <span>Ir al login</span>
                    </Button>
                </CardFooter>
            </Card>
        </div>
    </main>

    <footer class="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-center px-6 py-6 text-xs text-muted-foreground">
        <span class="tabular-nums">&copy; {new Date().getFullYear()} Papelera del Sol. Todos los derechos reservados.</span>
    </footer>
</div>
