<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
    import { router } from '@inertiajs/svelte';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import LogOut from 'lucide-svelte/icons/log-out';
    import Lock from 'lucide-svelte/icons/lock';

    let {
        reason = 'unauthorized',
    }: {
        reason?: string;
    } = $props();

    let isDeactivated = $derived(reason === 'account_deactivated');

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
            <div class="h-2 bg-gradient-to-r {isDeactivated ? 'from-amber-500 to-rose-500' : 'from-rose-500 to-destructive'}"></div>

            <CardHeader class="pt-8 pb-4">
                <div class="flex justify-center mb-3">
                    <div class="flex size-14 items-center justify-center rounded-2xl {isDeactivated ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'}">
                        <ShieldAlert class="size-7" />
                    </div>
                </div>

                <CardTitle class="text-xl font-bold text-foreground leading-snug px-2">
                    No tienes acceso al sistema, contacta con un administrador
                </CardTitle>

                {#if isDeactivated}
                    <div class="pt-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                            Te desactivaron la cuenta
                        </span>
                    </div>
                {/if}

                <CardDescription class="text-xs text-muted-foreground mt-3 leading-relaxed px-4">
                    {#if isDeactivated}
                        El comercio al que pertenece tu usuario ha sido desactivado o suspendido por el superadministrador. Comunícate con el soporte de la plataforma para más información.
                    {:else}
                        Tu usuario se encuentra autenticado pero no está asignado a ningún comercio activo en la plataforma. Solicita una invitación a tu administrador.
                    {/if}
                </CardDescription>
            </CardHeader>

            <CardContent class="pb-2">
                <div class="rounded-lg bg-muted/40 p-3 text-[11px] text-muted-foreground border border-border/40">
                    Si consideras que esto es un error, por favor contacta al administrador del sistema indicando tu correo electrónico registrado.
                </div>
            </CardContent>

            <CardFooter class="pt-4 pb-6 flex justify-center border-t border-border/40 mt-2">
                <Button
                    variant="outline"
                    size="sm"
                    onclick={logout}
                    class="text-xs font-semibold gap-2"
                >
                    <LogOut class="size-3.5" />
                    Cerrar sesión
                </Button>
            </CardFooter>
        </Card>
    </div>
</div>
