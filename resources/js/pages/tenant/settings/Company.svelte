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

    interface CompanyData {
        name: string;
        legal_name: string | null;
        cuit_tax_id: string | null;
        tax_condition: string;
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

    const form = useForm({
        name: company.name ?? '',
        legal_name: company.legal_name ?? '',
        cuit_tax_id: company.cuit_tax_id ?? '',
        tax_condition: company.tax_condition ?? 'responsable_inscripto',
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
        <p class="text-sm text-muted-foreground mt-0.5 max-w-prose">
            Configurá la identidad fiscal, domicilio comercial y datos de contacto de tu comercio para comprobantes, recibos oficiales y Mercado Pago.
        </p>
    </div>

    <!-- Formulario de Configuración -->
    <form onsubmit={handleSubmit} class="space-y-6">
        <!-- Tarjeta 1: Identidad Comercial y Fiscal -->
        <Card class="rounded-2xl border border-border/80 bg-card p-5 sm:p-6 shadow-xs space-y-5">
            <div class="flex items-center gap-2 pb-2 border-b border-border/60">
                <Building2 class="size-4 text-primary" />
                <h3 class="text-base font-semibold tracking-normal text-foreground">Identidad Comercial & Fiscal</h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <Label for="company-name" class="text-xs font-semibold">
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
                    <Label for="company-legal-name" class="text-xs font-semibold">
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
                    <Label for="company-cuit" class="text-xs font-semibold flex items-center gap-1.5">
                        <FileText class="size-3.5 text-muted-foreground" />
                        CUIT del Comercio
                    </Label>
                    <Input
                        id="company-cuit"
                        bind:value={form.cuit_tax_id}
                        placeholder="ej. 30-71234567-8"
                        maxlength={13}
                        class="h-10 text-sm font-sans tabular-nums"
                    />
                    {#if form.errors.cuit_tax_id}
                        <p class="text-xs text-destructive">{form.errors.cuit_tax_id}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-tax-condition" class="text-xs font-semibold">
                        Condición frente al IVA <span class="text-destructive">*</span>
                    </Label>
                    <select
                        id="company-tax-condition"
                        bind:value={form.tax_condition}
                        class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
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
                    <Label for="company-address" class="text-xs font-semibold">
                        Dirección (Calle, Número, Piso/Depto)
                    </Label>
                    <Input
                        id="company-address"
                        bind:value={form.address}
                        placeholder="ej. Av. Corrientes 1234, Piso 2"
                        class="h-10 text-sm font-medium"
                    />
                    {#if form.errors.address}
                        <p class="text-xs text-destructive">{form.errors.address}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="company-city" class="text-xs font-semibold">
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
                    <Label for="company-state" class="text-xs font-semibold">
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
                    <Label for="company-postal-code" class="text-xs font-semibold">
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
                    <Label for="company-phone" class="text-xs font-semibold flex items-center gap-1.5">
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
                    <Label for="company-email" class="text-xs font-semibold flex items-center gap-1.5">
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
                    <Label for="company-iibb" class="text-xs font-semibold">
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
                    <Label for="company-start-date" class="text-xs font-semibold flex items-center gap-1.5">
                        <Calendar class="size-3.5 text-muted-foreground" />
                        Inicio de Actividades
                    </Label>
                    <Input
                        id="company-start-date"
                        type="date"
                        bind:value={form.start_activity_date}
                        class="h-10 text-sm font-sans tabular-nums"
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
                class="min-h-[40px] px-5 rounded-xl font-medium flex items-center gap-2 shadow-xs cursor-pointer"
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
</div>
