<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { router } from '@inertiajs/svelte';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import QrCode from 'lucide-svelte/icons/qr-code';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Building2 from 'lucide-svelte/icons/building-2';
    import Mail from 'lucide-svelte/icons/mail';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Unlink from 'lucide-svelte/icons/unlink';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Save from 'lucide-svelte/icons/save';
    import Store from 'lucide-svelte/icons/store';

    interface MpAccount {
        user_id: string | null;
        nickname: string;
        email: string | null;
        live_mode: boolean;
        pos_id: string;
        linked_at: string | null;
        cuit: string | null;
    }

    interface Props {
        isLinked: boolean;
        mpAccount: MpAccount | null;
        clientId?: string | null;
    }

    let { isLinked = false, mpAccount = null, clientId = null }: Props = $props();

    let isDisconnectModalOpen = $state(false);
    let isDisconnecting = $state(false);

    let currentPosId = $state(mpAccount?.pos_id ?? 'SUC01POS01');
    let isUpdatingPos = $state(false);

    function handleConnect() {
        window.location.href = '/tenant/configuracion/mercadopago/vincular';
    }

    function handleDisconnect() {
        isDisconnecting = true;
        router.post(
            '/tenant/configuracion/mercadopago/desvincular',
            {},
            {
                onFinish: () => {
                    isDisconnecting = false;
                    isDisconnectModalOpen = false;
                },
            }
        );
    }

    function handleUpdatePos(e: SubmitEvent) {
        e.preventDefault();
        isUpdatingPos = true;
        router.put(
            '/tenant/configuracion/mercadopago/pos',
            { pos_id: currentPosId },
            {
                preserveScroll: true,
                onFinish: () => {
                    isUpdatingPos = false;
                },
            }
        );
    }

    function formatDate(dateStr: string | null | undefined): string {
        if (!dateStr) return 'Reciente';
        try {
            return new Intl.DateTimeFormat('es-AR', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(new Date(dateStr));
        } catch {
            return dateStr;
        }
    }
</script>

<AppHead title="Mercado Pago" description="Configuración de cobros con Mercado Pago y QR dinámico" />

<div class="flex flex-col gap-6 w-full">
    <!-- Header del Módulo -->
    <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2.5">
            <h2 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <div class="flex size-9 items-center justify-center rounded-xl bg-sky-500/10 text-sky-700 dark:text-sky-400">
                    <CreditCard class="size-5" />
                </div>
                <span>Mercado Pago</span>
            </h2>
            {#if isLinked}
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                    <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Conectado
                </span>
            {:else}
                <span class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                    No vinculado
                </span>
            {/if}
        </div>
        <p class="text-sm text-muted-foreground mt-0.5 max-w-prose">
            Vinculá la cuenta de Mercado Pago de tu comercio para habilitar cobros presenciales con QR dinámico en el mostrador y en repartos.
        </p>
    </div>

    {#if !isLinked}
        <!-- ESTADO A: Cuenta No Vinculada (Empty State) -->
        <Card class="rounded-2xl border border-border/80 bg-card p-6 sm:p-8 shadow-xs">
            <div class="max-w-2xl mx-auto text-center flex flex-col items-center gap-5">
                <div class="flex size-16 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-700 dark:text-sky-400 shadow-inner">
                    <QrCode class="size-8" />
                </div>

                <div class="space-y-2">
                    <h3 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                        Vinculá tu cuenta de Mercado Pago
                    </h3>
                    <p class="text-sm text-muted-foreground max-w-lg mx-auto leading-relaxed">
                        Permite cobrar pedidos en el mostrador y en repartos mediante código QR dinámico.
                    </p>
                </div>

                <!-- Alerta de Requisito: Datos Comerciales y Geográficos -->
                <div class="w-full text-left p-4 sm:p-5 rounded-2xl border border-amber-500/25 bg-amber-500/10 text-amber-950 dark:text-amber-200 flex items-start gap-3.5 text-xs leading-relaxed">
                    <Building2 class="size-4 shrink-0 text-amber-700 dark:text-amber-400 mt-0.5" />
                    <div class="space-y-1.5 w-full">
                        <strong class="font-semibold text-foreground text-xs sm:text-sm block">
                            Requisito: Correspondencia geográfica estricta de tu comercio
                        </strong>
                        <p class="text-muted-foreground">
                            Para dar de alta la sucursal y la caja de cobro, la API de Mercado Pago valida de forma estricta la relación entre la provincia y la ciudad configuradas en <a href="/tenant/configuracion/comercio" class="text-primary font-semibold underline hover:opacity-80">Configuración > Comercio</a>. Si los datos son incompatibles, Mercado Pago rechazará la creación de las órdenes de cobro con código QR.
                        </p>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="pt-2 flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                    <Button
                        variant="default"
                        size="lg"
                        class="min-h-[46px] w-full sm:w-auto py-3.5 sm:py-2.5 px-6 rounded-xl font-semibold gap-2 shadow-xs bg-sky-700 hover:bg-sky-800 text-white cursor-pointer"
                        onclick={handleConnect}
                    >
                        <span class="inline sm:hidden">Vincular Mercado Pago</span>
                        <span class="hidden sm:inline">Vincular cuenta de Mercado Pago</span>
                        <ArrowRight class="size-4" />
                    </Button>
                </div>
            </div>
        </Card>
    {:else}
        <!-- ESTADO B: Cuenta Vinculada (Ficha Oficial) -->
        <div class="space-y-6">
            <!-- Recordatorio Geográfico en Estado Vinculado -->
            <div class="p-4 rounded-xl border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 flex items-start gap-3 text-xs leading-relaxed">
                <Building2 class="size-4 shrink-0 text-primary mt-0.5" />
                <div class="space-y-1">
                    <span class="font-semibold text-foreground block">Datos Geográficos del Comercio</span>
                    <p class="text-muted-foreground">
                        Mercado Pago exige estricta correspondencia entre la provincia y la ciudad configuradas en <a href="/tenant/configuracion/comercio" class="text-primary font-medium underline hover:opacity-80">Configuración > Comercio</a>. Para la <strong>Provincia de Buenos Aires</strong> la ciudad debe ser un partido (ej. <em>San Isidro, Vicente López</em>), mientras que para <strong>Capital Federal</strong> la ciudad debe ser un barrio porteño (ej. <em>Monserrat, Palermo</em>).
                    </p>
                </div>
            </div>
            <!-- Hero Card de Conexión Activa -->
            <Card class="rounded-2xl border border-border/80 bg-card p-5 sm:p-6 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-border/60">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                            <CheckCircle2 class="size-6" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-base sm:text-lg font-bold text-foreground truncate">
                                    {mpAccount?.nickname || 'Cuenta de Mercado Pago'}
                                </h3>
                            </div>
                            <p class="text-xs text-muted-foreground flex items-center gap-1.5 mt-0.5">
                                <Calendar class="size-3.5 shrink-0" />
                                <span>Vinculada el <strong class="tabular-nums font-semibold text-foreground">{formatDate(mpAccount?.linked_at)}</strong></span>
                            </p>
                        </div>
                    </div>

                    <Button
                        variant="outline"
                        class="min-h-[44px] px-4 rounded-xl text-sm font-medium text-destructive border-destructive/30 hover:bg-destructive/10 hover:border-destructive/50 gap-2 shrink-0 cursor-pointer"
                        onclick={() => (isDisconnectModalOpen = true)}
                    >
                        <Unlink class="size-4" />
                        <span>Desvincular cuenta</span>
                    </Button>
                </div>

                <!-- Detalle de Datos Oficiales -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-5">
                    <div class="p-4 rounded-xl bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 flex flex-col gap-1">
                        <span class="text-xs text-muted-foreground flex items-center gap-1.5 font-medium">
                            <Building2 class="size-3.5" />
                            Titular / Razón Social
                        </span>
                        <span class="text-sm font-semibold text-foreground truncate">
                            {mpAccount?.nickname || '—'}
                        </span>
                        {#if mpAccount?.cuit}
                            <span class="text-xs text-muted-foreground tabular-nums">
                                CUIT: {mpAccount.cuit}
                            </span>
                        {/if}
                    </div>

                    <div class="p-4 rounded-xl bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 flex flex-col gap-1">
                        <span class="text-xs text-muted-foreground flex items-center gap-1.5 font-medium">
                            <Mail class="size-3.5" />
                            Email Registrado
                        </span>
                        <span class="text-sm font-semibold text-foreground truncate" title={mpAccount?.email ?? ''}>
                            {mpAccount?.email || '—'}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            Cuenta receptora de cobros
                        </span>
                    </div>
                </div>
            </Card>

            <!-- Configuración de Punto de Venta / Caja POS -->
            <Card class="rounded-2xl border border-border/80 bg-card p-5 sm:p-6 shadow-xs">
                <form onsubmit={handleUpdatePos} class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div class="space-y-1.5 flex-1 max-w-md">
                        <Label for="pos_id" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                            <Store class="size-4 text-primary" />
                            Código de Caja / Punto de Venta (POS)
                        </Label>
                        <p class="text-xs text-muted-foreground">
                            Identificador asignado en Mercado Pago para la emisión del QR presencial dinámico.
                        </p>
                        <Input
                            id="pos_id"
                            bind:value={currentPosId}
                            placeholder="Ej: SUC01POS01"
                            class="h-10 rounded-xl font-sans text-sm uppercase bg-field border-input focus:border-primary focus:ring-1 focus:ring-primary"
                            required
                        />
                    </div>

                    <Button
                        type="submit"
                        variant="secondary"
                        disabled={isUpdatingPos || currentPosId === mpAccount?.pos_id}
                        class="min-h-[44px] px-4 rounded-xl text-sm font-medium gap-2 shadow-xs shrink-0 cursor-pointer"
                    >
                        <Save class="size-4" />
                        <span>{isUpdatingPos ? 'Guardando...' : 'Guardar Caja'}</span>
                    </Button>
                </form>
            </Card>
        </div>
    {/if}
</div>

<!-- Modal de Confirmación para Desvincular Cuenta -->
<ConfirmActionModal
    bind:open={isDisconnectModalOpen}
    variant="destructive"
    title="¿Desvincular cuenta de Mercado Pago?"
    description="Se revocarán los tokens de integración y ya no se podrán generar códigos QR dinámicos para cobrar pedidos hasta que vuelvas a vincular una cuenta."
    confirmText="Desvincular Cuenta"
    loading={isDisconnecting}
    onconfirm={handleDisconnect}
/>
