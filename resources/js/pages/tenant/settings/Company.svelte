<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { useForm } from '@inertiajs/svelte';
    import Store from 'lucide-svelte/icons/store';
    import Building2 from 'lucide-svelte/icons/building-2';
    import FileText from 'lucide-svelte/icons/file-text';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Save from 'lucide-svelte/icons/save';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import Pencil from 'lucide-svelte/icons/pencil';
    import ImageOff from 'lucide-svelte/icons/image-off';
    import TenantLogoModal from '@/components/tenant/settings/TenantLogoModal.svelte';
    import { sanitizeIdentificationInput } from '@/lib/numericSanitizer';

    interface CompanyData {
        name: string;
        legal_name: string | null;
        cuit_tax_id: string | null;
        tax_condition: string;
        point_of_sale?: number | null;
        logo_url?: string | null;
        address: string | null;
        city: string | null;
        state: string | null;
        postal_code: string | null;
        phone: string | null;
        email: string | null;
        gross_income_number: string | null;
        start_activity_date: string | null;
    }

    interface Props {
        company: CompanyData;
    }

    let { company }: Props = $props();

    let showLogoModal = $state(false);
    let imageFailed = $state(false);
    let logoUrl = $state(company.logo_url ?? null);

    $effect(() => {
        logoUrl = company.logo_url ?? null;
        imageFailed = false;
    });

    $effect(() => {
        if (typeof window !== 'undefined') {
            const params = new URLSearchParams(window.location.search);
            if (params.get('editar_logo') === '1' || params.get('editar_logo') === 'true') {
                const timer = setTimeout(() => {
                    showLogoModal = true;
                    params.delete('editar_logo');
                    const newUrl = window.location.pathname + (params.toString() ? `?${params.toString()}` : '');
                    window.history.replaceState({}, '', newUrl);
                }, 300);
                return () => clearTimeout(timer);
            }
        }
    });

    $effect(() => {
        if (typeof window !== 'undefined') {
            const handleCustomOpen = () => {
                showLogoModal = true;
            };
            window.addEventListener('open-tenant-logo-modal', handleCustomOpen);
            return () => window.removeEventListener('open-tenant-logo-modal', handleCustomOpen);
        }
    });

    const form = useForm({
        name: company.name ?? '',
        legal_name: company.legal_name ?? '',
        cuit_tax_id: company.cuit_tax_id ?? '',
        tax_condition: company.tax_condition ?? 'responsable_inscripto',
        point_of_sale: company.point_of_sale ?? 1,
        address: company.address ?? '',
        city: company.city ?? '',
        state: company.state ?? '',
        postal_code: company.postal_code ?? '',
        phone: company.phone ?? '',
        email: company.email ?? '',
        gross_income_number: company.gross_income_number ?? '',
        start_activity_date: company.start_activity_date ?? '',
    });

    function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        form.put('/tenant/configuracion/comercio', {
            preserveScroll: true,
        });
    }
</script>

<AppHead title="Datos del Comercio" description="Configuración de identidad fiscal y domicilio comercial" />

<div class="flex flex-col gap-6 w-full">
    <!-- Header del Módulo -->
    <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2.5">
            <h2 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Store class="size-5" />
                </div>
                <span>Datos del Comercio</span>
            </h2>
        </div>
    </div>

    <!-- Formulario de Configuración -->
    <form onsubmit={handleSubmit} class="space-y-6">
        <!-- Tarjeta 1: Identidad Comercial y Fiscal -->
        <Card class="rounded-2xl border border-border/80 bg-card p-5 sm:p-6 shadow-xs space-y-5">
            <div class="flex items-center gap-2 pb-2 border-b border-border/60">
                <Building2 class="size-4 text-primary" />
                <h3 class="text-base font-semibold tracking-normal text-foreground">Identidad Comercial & Fiscal</h3>
            </div>

            <!-- Avatar / Logo del Comercio con Indicador de Edición -->
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 p-4 rounded-xl bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90">
                <div
                    class="relative size-24 sm:size-28 rounded-full border-2 border-border/80 dark:border-border/90 bg-card shadow-xs cursor-pointer group shrink-0 flex items-center justify-center"
                    onclick={() => (showLogoModal = true)}
                    onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') showLogoModal = true; }}
                    role="button"
                    tabindex="0"
                    aria-label="Editar logo del comercio"
                    title="Editar logo del comercio"
                >
                    <div class="size-full rounded-full overflow-hidden flex items-center justify-center">
                        {#if logoUrl && !imageFailed}
                            <img
                                src={logoUrl}
                                alt="Logo del comercio"
                                onerror={() => (imageFailed = true)}
                                class="size-full object-cover group-hover:scale-105 transition-transform duration-200"
                            />
                        {:else}
                            <div class="flex flex-col items-center justify-center gap-1 text-center select-none px-2">
                                <ImageOff class="size-5 text-muted-foreground/80 group-hover:text-primary transition-colors" />
                                <span class="text-xs font-medium text-muted-foreground tracking-tight leading-tight">
                                    Sin imagen
                                </span>
                            </div>
                        {/if}
                    </div>

                    <!-- Indicador visual de lápiz sobre el borde circular -->
                    <div
                        class="absolute bottom-0 right-0 size-8 rounded-full bg-primary text-primary-foreground flex items-center justify-center shadow-xs border-2 border-background group-hover:scale-110 transition-transform"
                    >
                        <Pencil class="size-3.5" />
                    </div>
                </div>

                <div class="flex flex-col items-center sm:items-start text-center sm:text-left gap-1 pt-1">
                    <span class="text-sm font-semibold text-foreground">Logo del comercio</span>
                    <p class="text-xs text-muted-foreground max-w-sm">
                        Se muestra en la plataforma y en todos los comprobantes PDF oficiales (facturas, remitos, órdenes y recibos).
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        onclick={() => (showLogoModal = true)}
                        class="mt-2 min-h-[44px] text-sm font-medium gap-1.5 rounded-xl"
                    >
                        <Pencil class="size-3.5 text-primary" />
                        <span>{logoUrl ? 'Cambiar logo' : 'Subir logo'}</span>
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <Label for="company-name" class="text-sm font-medium text-foreground">
                        Nombre de Fantasía <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="company-name"
                        bind:value={form.name}
                        placeholder="ej. Papelera Central"
                        required
                        class="h-10 text-sm font-medium"
                    />
                    {#if form.errors.name}
                        <p class="text-xs text-destructive">{form.errors.name}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-legal-name" class="text-sm font-medium text-foreground">
                        Razón Social
                    </Label>
                    <Input
                        id="company-legal-name"
                        bind:value={form.legal_name}
                        placeholder="ej. Distribuidora Papelera S.R.L."
                        class="h-10 text-sm font-medium"
                    />
                    {#if form.errors.legal_name}
                        <p class="text-xs text-destructive">{form.errors.legal_name}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-cuit" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <FileText class="size-3.5 text-muted-foreground" />
                        CUIT del Comercio
                    </Label>
                    <Input
                        id="company-cuit"
                        bind:value={form.cuit_tax_id}
                        placeholder="ej. 30-71234567-8"
                        maxlength={13}
                        oninput={(e) => sanitizeIdentificationInput(e, (val) => { form.cuit_tax_id = val; })}
                        class="h-10 text-sm font-sans tabular-nums"
                    />
                    {#if form.errors.cuit_tax_id}
                        <p class="text-xs text-destructive">{form.errors.cuit_tax_id}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-tax-condition" class="text-sm font-medium text-foreground">
                        Condición frente al IVA <span class="text-destructive">*</span>
                    </Label>
                    <select
                        id="company-tax-condition"
                        bind:value={form.tax_condition}
                        class="h-10 w-full rounded-xl border border-input bg-field px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                    >
                        <option value="responsable_inscripto">Responsable Inscripto</option>
                        <option value="monotributo">Monotributo</option>
                        <option value="exento">Exento de IVA</option>
                        <option value="consumidor_final">Consumidor Final</option>
                    </select>
                    {#if form.errors.tax_condition}
                        <p class="text-xs text-destructive">{form.errors.tax_condition}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-point-of-sale" class="text-sm font-medium text-foreground">
                        Punto de Venta de ARCA
                    </Label>
                    <Input
                        id="company-point-of-sale"
                        type="number"
                        min="1"
                        max="99999"
                        bind:value={form.point_of_sale}
                        placeholder="ej. 1"
                        class="h-10 text-sm font-sans tabular-nums"
                    />
                    <p class="text-xs text-muted-foreground">
                        debe coincidir con el punto fiscal declarado en ARCA
                    </p>
                    {#if form.errors.point_of_sale}
                        <p class="text-xs text-destructive">{form.errors.point_of_sale}</p>
                    {/if}
                </div>
            </div>
        </Card>

        <!-- Tarjeta 2: Domicilio Comercial -->
        <Card class="rounded-2xl border border-border/80 bg-card p-5 sm:p-6 shadow-xs space-y-5">
            <div class="flex items-center gap-2 pb-2 border-b border-border/60">
                <MapPin class="size-4 text-primary" />
                <h3 class="text-base font-semibold tracking-normal text-foreground">Domicilio Comercial</h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2 space-y-1.5">
                    <Label for="company-address" class="text-sm font-medium text-foreground">
                        Dirección (Calle, Número, Piso/Depto)
                    </Label>
                    <Input
                        id="company-address"
                        bind:value={form.address}
                        placeholder="ej. Av. Corrientes 1234, Piso 2"
                        maxlength="255"
                        class="h-10 text-sm font-medium"
                    />
                    {#if form.errors.address}
                        <p class="text-xs text-destructive">{form.errors.address}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-city" class="text-sm font-medium text-foreground">
                        Ciudad / Localidad
                    </Label>
                    <Input
                        id="company-city"
                        bind:value={form.city}
                        placeholder="ej. Rosario"
                        class="h-10 text-sm font-medium"
                    />
                    {#if form.errors.city}
                        <p class="text-xs text-destructive">{form.errors.city}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-state" class="text-sm font-medium text-foreground">
                        Provincia
                    </Label>
                    <Input
                        id="company-state"
                        bind:value={form.state}
                        placeholder="ej. Entre Ríos"
                        list="argentine-provinces"
                        class="h-10 text-sm font-medium"
                    />
                    <datalist id="argentine-provinces">
                        <option value="Buenos Aires"></option>
                        <option value="Capital Federal"></option>
                        <option value="Catamarca"></option>
                        <option value="Chaco"></option>
                        <option value="Chubut"></option>
                        <option value="Corrientes"></option>
                        <option value="Córdoba"></option>
                        <option value="Entre Ríos"></option>
                        <option value="Formosa"></option>
                        <option value="Jujuy"></option>
                        <option value="La Pampa"></option>
                        <option value="La Rioja"></option>
                        <option value="Mendoza"></option>
                        <option value="Misiones"></option>
                        <option value="Neuquén"></option>
                        <option value="Río Negro"></option>
                        <option value="Salta"></option>
                        <option value="San Juan"></option>
                        <option value="San Luis"></option>
                        <option value="Santa Cruz"></option>
                        <option value="Santa Fe"></option>
                        <option value="Santiago del Estero"></option>
                        <option value="Tierra del Fuego"></option>
                        <option value="Tucumán"></option>
                    </datalist>
                    {#if form.errors.state}
                        <p class="text-xs text-destructive">{form.errors.state}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-postal-code" class="text-sm font-medium text-foreground">
                        Código Postal
                    </Label>
                    <Input
                        id="company-postal-code"
                        bind:value={form.postal_code}
                        placeholder="ej. 2000"
                        class="h-10 text-sm font-sans tabular-nums"
                    />
                    {#if form.errors.postal_code}
                        <p class="text-xs text-destructive">{form.errors.postal_code}</p>
                    {/if}
                </div>
            </div>
        </Card>

        <!-- Tarjeta 3: Contacto y Registración -->
        <Card class="rounded-2xl border border-border/80 bg-card p-5 sm:p-6 shadow-xs space-y-5">
            <div class="flex items-center gap-2 pb-2 border-b border-border/60">
                <FileText class="size-4 text-primary" />
                <h3 class="text-base font-semibold tracking-normal text-foreground">Contacto & Registración</h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <Label for="company-phone" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <Phone class="size-3.5 text-muted-foreground" />
                        Teléfono Comercial
                    </Label>
                    <Input
                        id="company-phone"
                        bind:value={form.phone}
                        placeholder="ej. +54 341 4123456"
                        class="h-10 text-sm font-sans tabular-nums"
                    />
                    {#if form.errors.phone}
                        <p class="text-xs text-destructive">{form.errors.phone}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-email" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <Mail class="size-3.5 text-muted-foreground" />
                        Email Comercial
                    </Label>
                    <Input
                        id="company-email"
                        type="email"
                        bind:value={form.email}
                        placeholder="ej. administracion@micomercio.com"
                        class="h-10 text-sm font-medium"
                    />
                    {#if form.errors.email}
                        <p class="text-xs text-destructive">{form.errors.email}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-iibb" class="text-sm font-medium text-foreground">
                        N° de Ingresos Brutos (IIBB)
                    </Label>
                    <Input
                        id="company-iibb"
                        bind:value={form.gross_income_number}
                        placeholder="ej. 901-123456-7"
                        class="h-10 text-sm font-sans tabular-nums"
                    />
                    {#if form.errors.gross_income_number}
                        <p class="text-xs text-destructive">{form.errors.gross_income_number}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-start-date" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <Calendar class="size-3.5 text-muted-foreground" />
                        Inicio de Actividades
                    </Label>
                    <Input
                        id="company-start-date"
                        type="date"
                        bind:value={form.start_activity_date}
                        class="h-10 text-sm font-sans tabular-nums dark:[color-scheme:dark]"
                    />
                    {#if form.errors.start_activity_date}
                        <p class="text-xs text-destructive">{form.errors.start_activity_date}</p>
                    {/if}
                </div>
            </div>
        </Card>

        <!-- Barra de Acciones -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <Button
                type="submit"
                disabled={form.processing}
                class="min-h-[44px] px-5 rounded-xl text-sm font-medium flex items-center gap-2 shadow-xs cursor-pointer"
            >
                {#if form.processing}
                    <Loader2 class="size-4 animate-spin" />
                    <span>Guardando...</span>
                {:else}
                    <Save class="size-4" />
                    <span>Guardar cambios</span>
                {/if}
            </Button>
        </div>
    </form>

    <TenantLogoModal
        bind:open={showLogoModal}
        currentLogoUrl={logoUrl}
        onSuccess={(newLogo) => {
            logoUrl = newLogo;
        }}
    />
</div>
