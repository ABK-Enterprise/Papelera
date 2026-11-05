<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { useForm } from '@inertiajs/svelte';
    import UserPlus from 'lucide-svelte/icons/user-plus';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import FileText from 'lucide-svelte/icons/file-text';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import Percent from 'lucide-svelte/icons/percent';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import X from 'lucide-svelte/icons/x';
    import Icon from '@iconify/svelte';
    import {
        fetchProvinces,
        fetchDepartments,
        fetchLocalities,
        fetchLocalitiesByProvince,
        type GeoProvince,
        type GeoDepartment,
        type GeoLocality,
        ARGENTINE_PROVINCES_FALLBACK,
    } from '@/lib/geodata';
    import { preventInvalidNumericKeys, sanitizeNumericInput, sanitizeIdentificationInput } from '@/lib/numericSanitizer';

    let {
        open = $bindable(false),
    }: {
        open?: boolean;
    } = $props();

    const form = useForm({
        name: '',
        tax_id: '',
        tax_condition: 'consumidor_final',
        email: '',
        phone: '',
        address: '',
        province: '',
        city: '',
        credit_limit: 0,
        discount_percentage: 0,
        is_defaulter: false,
        is_active: true,
        notes: '',
    });

    let provinces = $state<GeoProvince[]>([]);
    let departments = $state<GeoDepartment[]>([]);
    let localities = $state<GeoLocality[]>([]);
    let selectedDepartmentId = $state<string>('');
    let isLoadingDepartments = $state<boolean>(false);
    let isLoadingLocalities = $state<boolean>(false);
    let isManualCity = $state<boolean>(false);

    $effect(() => {
        if (open) {
            fetchProvinces().then(data => {
                provinces = data;
            });
        }
    });

    async function onProvinceChange() {
        form.city = '';
        selectedDepartmentId = '';
        departments = [];
        localities = [];

        if (!form.province) return;

        let provObj = provinces.find(p => p.name === form.province);
        if (!provObj) {
            provinces = await fetchProvinces();
            provObj = provinces.find(p => p.name === form.province);
        }
        if (!provObj) {
            provObj = ARGENTINE_PROVINCES_FALLBACK.find(p => p.name === form.province);
        }
        if (!provObj) return;

        isLoadingDepartments = true;
        try {
            departments = await fetchDepartments(provObj.id);
            if (departments.length === 1) {
                selectedDepartmentId = departments[0].id;
                await onDepartmentChange();
            } else if (departments.length === 0) {
                // Si la provincia no tiene departamentos desglosados, cargar localidades directas
                isLoadingLocalities = true;
                try {
                    localities = await fetchLocalitiesByProvince(provObj.id);
                } finally {
                    isLoadingLocalities = false;
                }
            }
        } finally {
            isLoadingDepartments = false;
        }
    }

    async function onDepartmentChange() {
        form.city = '';
        localities = [];

        if (!selectedDepartmentId) return;

        isLoadingLocalities = true;
        try {
            localities = await fetchLocalities(selectedDepartmentId);
        } finally {
            isLoadingLocalities = false;
        }
    }

    function submit(e: SubmitEvent) {
        e.preventDefault();

        form.post('/tenant/clientes', {
            onSuccess: () => {
                open = false;
                form.reset();
            },
        });
    }


    function handleClose() {
        open = false;
        form.reset();
        form.clearErrors();
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto p-6 sm:p-8">
        <div class="flex items-center justify-between gap-2.5 sm:gap-3.5 pb-4 border-b border-border w-full">
            <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 flex-1">
                <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                    <Icon class="size-5 sm:size-6 text-primary" icon="solar:user-plus-bold-duotone" />
                </div>
                <div class="min-w-0 flex-1 flex items-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                        Añadir Nuevo Cliente
                    </DialogTitle>
                </div>
            </div>
            <button
                type="button"
                onclick={handleClose}
                class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer shrink-0"
                aria-label="Cerrar"
            >
                <X class="size-4" />
            </button>
        </div>

        <form onsubmit={submit} class="space-y-4 pt-4">
            <!-- Nombre o Razón Social -->
            <div class="space-y-1.5">
                <Label for="create-customer-name" class="text-sm font-medium text-foreground">
                    Nombre / Razón Social <span class="text-destructive">*</span>
                </Label>
                <Input
                    id="create-customer-name"
                    bind:value={form.name}
                    placeholder="ej. Papelera San Martín S.R.L."
                    class="h-9 text-sm"
                    required
                />
                {#if form.errors.name}
                    <p class="text-xs text-destructive">{form.errors.name}</p>
                {/if}
            </div>

            <!-- CUIT / DNI y Condición Fiscal -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div class="space-y-1.5">
                    <Label for="create-customer-tax-id" class="text-sm font-medium text-foreground">CUIT / DNI</Label>
                    <Input
                        id="create-customer-tax-id"
                        bind:value={form.tax_id}
                        placeholder="ej. 30-71234567-9"
                        maxlength="13"
                        oninput={(e) => sanitizeIdentificationInput(e, (val) => { form.tax_id = val; })}
                        class="h-9 text-sm tabular-nums"
                    />
                    {#if form.errors.tax_id}
                        <p class="text-xs text-destructive">{form.errors.tax_id}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="create-customer-tax-condition" class="text-sm font-medium text-foreground">
                        Condición Fiscal (IVA) <span class="text-destructive">*</span>
                    </Label>
                    <select
                        id="create-customer-tax-condition"
                        bind:value={form.tax_condition}
                        class="h-10 w-full rounded-xl border border-border/80 bg-select-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-transparent disabled:border-dashed"
                    >

                        <option value="consumidor_final">Consumidor Final</option>
                        <option value="responsable_inscripto">Responsable Inscripto</option>
                        <option value="monotributo">Monotributo</option>
                        <option value="exento">Exento de IVA</option>
                    </select>

                    {#if form.errors.tax_condition}
                        <p class="text-xs text-destructive">{form.errors.tax_condition}</p>
                    {/if}
                </div>
            </div>

            <!-- Teléfono y Email -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div class="space-y-1.5">
                    <Label for="create-customer-phone" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                        Teléfono / WhatsApp
                    </Label>
                    <Input
                        id="create-customer-phone"
                        bind:value={form.phone}
                        placeholder="ej. +54 9 11 4455-6677"
                        class="h-9 text-sm"
                    />
                    {#if form.errors.phone}
                        <p class="text-xs text-destructive">{form.errors.phone}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="create-customer-email" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <Mail class="h-3.5 w-3.5 text-muted-foreground" />
                        Correo Electrónico
                    </Label>
                    <Input
                        id="create-customer-email"
                        type="email"
                        bind:value={form.email}
                        placeholder="contacto@cliente.com"
                        class="h-9 text-sm"
                    />
                    {#if form.errors.email}
                        <p class="text-xs text-destructive">{form.errors.email}</p>
                    {/if}
                </div>
            </div>

            <!-- Dirección Comercial / Entrega -->
            <div class="space-y-1.5">
                <Label for="create-customer-address" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                    <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                    Dirección Comercial / Entrega
                </Label>
                <Input
                    id="create-customer-address"
                    bind:value={form.address}
                    maxlength="255"
                    placeholder="ej. Av. Rivadavia 4520, Piso 3"
                    class="h-9 text-sm"
                />
                {#if form.errors.address}
                    <p class="text-xs text-destructive">{form.errors.address}</p>
                {/if}
            </div>

            <!-- Provincia, Departamento y Localidad / Barrio -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div class="space-y-1.5">
                    <Label for="create-customer-province" class="text-sm font-medium text-foreground">Provincia</Label>
                    <select
                        id="create-customer-province"
                        bind:value={form.province}
                        onchange={onProvinceChange}
                        class="h-10 w-full rounded-xl border border-border/80 bg-select-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary cursor-pointer"
                    >
                        <option value="">Selecciona provincia...</option>
                        {#each provinces.length > 0 ? provinces : ARGENTINE_PROVINCES_FALLBACK.map(p => ({ id: p, name: p })) as prov}
                            <option value={prov.name}>{prov.name}</option>
                        {/each}
                    </select>
                    {#if form.errors.province}
                        <p class="text-xs text-destructive">{form.errors.province}</p>
                    {/if}
                </div>

                <div class="space-y-1.5">
                    <Label for="create-customer-department" class="text-sm font-medium text-foreground">Departamento / Partido</Label>
                    <select
                        id="create-customer-department"
                        bind:value={selectedDepartmentId}
                        onchange={onDepartmentChange}
                        disabled={!form.province || isLoadingDepartments}
                        class="h-10 w-full rounded-xl border border-border/80 bg-select-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                    >
                        <option value="">
                            {#if isLoadingDepartments}
                                Cargando...
                            {:else if !form.province}
                                Primero provincia
                            {:else}
                                Selecciona...
                            {/if}
                        </option>
                        {#each departments as dept}
                            <option value={dept.id}>{dept.name}</option>
                        {/each}
                    </select>
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <Label for="create-customer-city" class="text-sm font-medium text-foreground">Localidad / Barrio</Label>
                        <button
                            type="button"
                            onclick={() => (isManualCity = !isManualCity)}
                            class="text-xs text-primary hover:underline cursor-pointer"
                        >
                            {isManualCity ? 'Elegir de lista' : 'Ingreso manual'}
                        </button>
                    </div>
                    {#if isManualCity}
                        <Input
                            id="create-customer-city"
                            bind:value={form.city}
                            placeholder="Nombre de localidad o barrio"
                            class="h-10 text-sm font-medium"
                        />
                    {:else}
                        <select
                            id="create-customer-city"
                            bind:value={form.city}
                            disabled={(!selectedDepartmentId && localities.length === 0) || isLoadingLocalities}
                            class="h-10 w-full rounded-xl border border-border/80 bg-select-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                        >
                            <option value="">
                                {#if isLoadingLocalities}
                                    Cargando...
                                {:else if !selectedDepartmentId && localities.length === 0}
                                    Primero departamento
                                {:else}
                                    Selecciona localidad...
                                {/if}
                            </option>
                            {#each localities as loc}
                                <option value={loc.name}>{loc.name}</option>
                            {/each}
                        </select>
                    {/if}
                    {#if form.errors.city}
                        <p class="text-xs text-destructive">{form.errors.city}</p>
                    {/if}
                </div>
            </div>

            <!-- Límite de Crédito & Descuento Habitual & Flags -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 p-3.5 rounded-xl border bg-muted/15">
                <div class="space-y-1.5">
                    <Label for="create-customer-credit" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <CreditCard class="h-3.5 w-3.5 text-muted-foreground" />
                        Límite de crédito
                    </Label>
                    <Input
                        id="create-customer-credit"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100000000"
                        value={form.credit_limit}
                        onkeydown={(e) => preventInvalidNumericKeys(e)}
                        oninput={(e) => {
                            sanitizeNumericInput(e, 0, 100_000_000, (val) => {
                                form.credit_limit = typeof val === 'number' ? val : (Number(val) || 0);
                            });
                        }}
                        placeholder="0.00"
                        class="h-9 text-sm tabular-nums"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="create-customer-discount" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <Percent class="h-3.5 w-3.5 text-primary" />
                        Descuento habitual
                    </Label>
                    <Input
                        id="create-customer-discount"
                        type="number"
                        step="0.5"
                        min="0"
                        max="100"
                        value={form.discount_percentage}
                        onkeydown={(e) => preventInvalidNumericKeys(e)}
                        oninput={(e) => {
                            sanitizeNumericInput(e, 0, 100, (val) => {
                                form.discount_percentage = typeof val === 'number' ? val : (Number(val) || 0);
                            });
                        }}
                        placeholder="0.00"
                        class="h-9 text-sm tabular-nums font-semibold text-primary"
                    />
                </div>

                <div class="flex flex-col justify-center gap-2 pt-1">
                    <label class="flex items-center gap-2 text-sm font-medium text-foreground cursor-pointer">
                        <input
                            type="checkbox"
                            bind:checked={form.is_defaulter}
                            class="size-4 rounded border-border text-rose-600 focus:ring-rose-500"
                        />
                        <span class="flex items-center gap-1 text-rose-600 dark:text-rose-400">
                            <AlertTriangle class="size-3.5" />
                            Moroso / Riesgo
                        </span>
                    </label>


                    <label class="flex items-center gap-2 text-sm font-medium text-foreground cursor-pointer">
                        <input
                            type="checkbox"
                            bind:checked={form.is_active}
                            class="size-4 rounded border-border text-primary focus:ring-primary"
                        />
                        <span>Cliente Activo</span>
                    </label>
                </div>
            </div>

            <!-- Observaciones / Notas -->
            <div class="space-y-1.5">
                <Label for="create-customer-notes" class="text-sm font-medium text-foreground">
                    Notas Internas / Preferencias de Entrega
                </Label>
                <textarea
                    id="create-customer-notes"
                    bind:value={form.notes}
                    rows="2"
                    maxlength="255"
                    placeholder="ej. Horarios de recepción, condiciones de pago convenidas..."
                    class="w-full rounded-xl border border-border/80 bg-background p-3 text-sm placeholder:text-muted-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:bg-transparent disabled:border-dashed resize-none"
                ></textarea>

                {#if form.errors.notes}
                    <p class="text-xs text-destructive">{form.errors.notes}</p>
                {/if}
            </div>

            <!-- Footer: Botones -->
            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border mt-6">
                <Button type="button" variant="outline" onclick={handleClose} disabled={form.processing} class="min-h-[44px] px-4 rounded-xl text-sm font-medium">
                    Cancelar
                </Button>
                <Button type="submit" disabled={form.processing} class="min-h-[44px] px-4 rounded-xl text-sm font-medium min-w-32">
                    {form.processing ? 'Registrando...' : 'Registrar Cliente'}
                </Button>
            </div>

        </form>
    </DialogContent>
</Dialog>
