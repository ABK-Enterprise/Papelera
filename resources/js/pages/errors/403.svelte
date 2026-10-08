<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
    import { Link } from '@inertiajs/svelte';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Lock from 'lucide-svelte/icons/lock';

    let {
        status = 403,
        message = 'Acción no permitida',
    }: {
        status?: number;
        message?: string;
    } = $props();
</script>

<AppHead title="403 - Acción no permitida" />

<div class="relative flex min-h-screen flex-col justify-between overflow-hidden bg-background text-foreground antialiased selection:bg-primary/20">
    <!-- Ambient Background Blobs -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden">
        <div class="absolute -left-20 top-1/4 h-96 w-96 rounded-full bg-destructive/10 opacity-30 blur-3xl mix-blend-multiply transition-all duration-1000 dark:bg-destructive/5 dark:mix-blend-screen"></div>
        <div class="absolute -right-20 top-1/3 h-80 w-80 rounded-full bg-amber-500/10 opacity-30 blur-3xl mix-blend-multiply transition-all duration-1000 dark:bg-amber-500/5 dark:mix-blend-screen"></div>
    </div>

    <main class="relative z-10 mx-auto flex w-full max-w-7xl flex-1 items-center justify-center px-4 py-12 md:px-12">
        <div class="w-full max-w-md">
            <!-- Brand Logo -->
            <div class="flex items-center justify-center gap-2.5 mb-6">
                <div class="flex size-10 items-center justify-center rounded-xl bg-destructive/10 text-destructive border border-destructive/20">
                    <Lock class="size-5" />
                </div>
                <span class="text-xl font-bold tracking-tight text-foreground">Papelera Cloud</span>
            </div>

            <Card class="border-border/80 bg-card/90 shadow-2xl backdrop-blur-2xl text-center overflow-hidden rounded-3xl">
                <!-- Top Accent Line -->
                <div class="h-1.5 bg-gradient-to-r from-destructive via-amber-500 to-destructive"></div>

                <CardHeader class="pt-8 pb-4">
                    <div class="flex justify-center mb-3">
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-destructive/10 text-destructive border border-destructive/20 shadow-xs">
                            <ShieldAlert class="size-7" />
                        </div>
                    </div>

                    <div class="inline-flex items-center justify-center gap-1.5 self-center px-3 py-1 rounded-full text-xs font-semibold bg-destructive/10 text-destructive border border-destructive/20 mb-2">
                        <span class="tabular-nums font-bold">Error {status}</span>
                        <span>•</span>
                        <span>Acceso restringido</span>
                    </div>

                    <CardTitle class="text-xl sm:text-2xl font-bold text-foreground leading-snug px-2">
                        Acción no permitida
                    </CardTitle>

                    <CardDescription class="text-xs sm:text-sm text-muted-foreground mt-2 leading-relaxed px-4">
                        {message || 'No tienes los permisos necesarios para realizar esta acción o interactuar con este recurso protegido.'}
                    </CardDescription>
                </CardHeader>

                <CardContent class="pb-2">
                    <div class="rounded-xl bg-muted/40 p-3.5 text-xs text-muted-foreground border border-border/40 text-left leading-relaxed">
                        Esta operación se encuentra bloqueada por políticas de seguridad del sistema. Si consideras que deberías tener acceso, comunícate con un administrador de la plataforma.
                    </div>
                </CardContent>

                <CardFooter class="pt-4 pb-8 flex justify-center border-t border-border/40 mt-3">
                    <Link href="/dashboard">
                        <Button
                            variant="default"
                            class="min-h-[44px] px-6 rounded-xl text-sm font-semibold gap-2 shadow-sm"
                        >
                            <ArrowLeft class="size-4" />
                            <span>Volver al dashboard</span>
                        </Button>
                    </Link>
                </CardFooter>
            </Card>
        </div>
    </main>

    <footer class="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-center px-6 py-6 text-xs text-muted-foreground">
        <span class="tabular-nums">&copy; {new Date().getFullYear()} Papelera del Sol. Todos los derechos reservados.</span>
    </footer>
</div>
