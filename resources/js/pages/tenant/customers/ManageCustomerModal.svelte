<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { router, useForm } from '@inertiajs/svelte';
    import { hasPermission } from '@/lib/permissions';
    import UserCheck from 'lucide-svelte/icons/user-check';
    import Phone from 'lucide-svelte/icons/phone';
    import Mail from 'lucide-svelte/icons/mail';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import FileText from 'lucide-svelte/icons/file-text';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import Percent from 'lucide-svelte/icons/percent';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import MoreHorizontal from 'lucide-svelte/icons/more-horizontal';
    import X from 'lucide-svelte/icons/x';
    import Calendar from 'lucide-svelte/icons/calendar';
    import IconContainer from '@/components/common/IconContainer.svelte';
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
    import type { CustomerItem } from './desktop/CustomerListDesktop.svelte';

    let {
        open = $bindable(false),
        customer = null,
    }: {
        open?: boolean;
        customer?: CustomerItem | null;
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

    let isDeleting = $state(false);
    let lastLoadedCustomerId = $state<number | null>(null);

    let canEdit = $derived(hasPermission('clientes.editar'));
    let canDelete = $derived(hasPermission('clientes.eliminar'));

    // Modales de Confirmación
    let showDeactivateConfirm = $state(false);
    let showActivateConfirm = $state(false);
    let showDeleteConfirm = $state(false);

    $effect(() => {
        if (customer && customer.id !== lastLoadedCustomerId) {
            lastLoadedCustomerId = customer.id;
            form.name = customer.name ?? '';
            form.tax_id = customer.tax_id ?? '';
            form.tax_condition = customer.tax_condition ?? 'consumidor_final';
            form.email = customer.email ?? '';
            form.phone = customer.phone ?? '';
            form.address = customer.address ?? '';
            form.province = customer.province ?? '';
            form.city = customer.city ?? '';
            form.credit_limit = Number(customer.credit_limit) || 0;
            form.discount_percentage = Number((customer as any).discount_percentage) || 0;
            form.is_defaulter = Boolean(customer.is_defaulter);
            form.is_active = Boolean(customer.is_active);
            form.notes = customer.notes ?? '';
            form.clearErrors();
        } else if (!customer) {
            lastLoadedCustomerId = null;
        }
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
                if (form.province) {
                    const provObj = data.find(p => p.name === form.province) || ARGENTINE_PROVINCES_FALLBACK.find(p => p.name === form.province);
                    if (provObj) {
                        isLoadingDepartments = true;
                        fetchDepartments(provObj.id).then(depts => {
                            departments = depts;
                            isLoadingDepartments = false;
                            if (depts.length === 1) {
                                selectedDepartmentId = depts[0].id;
                                onDepartmentChange();
                            } else if (depts.length === 0) {
                                isLoadingLocalities = true;
                                fetchLocalitiesByProvince(provObj.id).then(locs => {
                                    localities = locs;
                                    isLoadingLocalities = false;
                                });
                            }
                        });
                    }
                }
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
        if (!customer) return;

        form.put(`/tenant/clientes/${customer.id}`, {
            onSuccess: () => {
                open = false;
            },
        });
    }

    function confirmToggleDeactivate() {
        form.is_active = false;
        showDeactivateConfirm = false;
    }

    function confirmToggleActivate() {
        form.is_active = true;
        showActivateConfirm = false;
    }

    function executeDelete() {
        if (!customer) return;

        isDeleting = true;
        router.delete(`/tenant/clientes/${customer.id}`, {
            onFinish: () => {
                isDeleting = false;
                showDeleteConfirm = false;
                open = false;
            },
        });
    }


    function handleClose() {
        open = false;
        form.clearErrors();
    }

    function formatDate(dateStr?: string) {
        if (!dateStr) return '';
        try {
            const d = new Date(dateStr);
            return new Intl.DateTimeFormat('es-AR', {
                dateStyle: 'medium',
            }).format(d);
        } catch {
            return dateStr;
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-2xl max-h-[85vh]">
        <DialogHeader>
            <div class="flex items-center gap-3 min-w-0">
                <IconContainer
                    size="md"
                    variant={form.is_defaulter ? 'warning' : 'primary'}
                    icon={form.is_defaulter ? 'solar:danger-triangle-bold-duotone' : 'solar:shop-bold-duotone'}
                />
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <DialogTitle class="text-xl font-bold text-foreground">Gestión de Cliente</DialogTitle>
                        {#if form.is_defaulter}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                Moroso
                            </span>
                        {/if}
                        {#if form.is_active}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                Activo
                            </span>
                        {:else}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-muted text-muted-foreground border border-border">
                                Inactivo
                            </span>
                        {/if}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-1 shrink-0 -mr-1">
                {#if canEdit || canDelete}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            {#snippet children(props)}
                                <button
                                    {...props}
                                    type="button"
                                    class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer"
                                    aria-label="Más opciones"
                                >
                                    <MoreHorizontal class="size-4" />
                                </button>
                            {/snippet}
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48 shadow-lg">
                            {#if canEdit}
                                {#if form.is_active}
                                    <DropdownMenuItem asChild>
                                        {#snippet children(props)}
                                            <button
                                                type="button"
                                                class="{props.class} w-full flex items-center text-rose-600 dark:text-rose-400 focus:text-rose-600 focus:bg-rose-50 dark:focus:bg-rose-950/30 gap-2 text-xs font-medium cursor-pointer"
                                                onclick={() => {
                                                    props.onClick?.();
                                                    showDeactivateConfirm = true;
                                                }}
                                            >
                                                <ShieldAlert class="size-3.5 shrink-0" />
                                                <span>Desactivar cliente</span>
                                            </button>
                                        {/snippet}
                                    </DropdownMenuItem>
                                {:else}
                                    <DropdownMenuItem asChild>
                                        {#snippet children(props)}
                                            <button
                                                type="button"
                                                class="{props.class} w-full flex items-center text-emerald-600 dark:text-emerald-400 focus:text-emerald-600 focus:bg-emerald-50 dark:focus:bg-emerald-950/30 gap-2 text-xs font-medium cursor-pointer"
                                                onclick={() => {
                                                    props.onClick?.();
                                                    showActivateConfirm = true;
                                                }}
                                            >
                                                <ShieldCheck class="size-3.5 shrink-0" />
                                                <span>Activar cliente</span>
                                            </button>
                                        {/snippet}
                                    </DropdownMenuItem>
                                {/if}
                            {/if}
                            {#if canEdit && canDelete}
                                <DropdownMenuSeparator />
                            {/if}
                            {#if canDelete}
                                <DropdownMenuItem asChild>
                                    {#snippet children(props)}
                                        <button
                                            type="button"
                                            class="{props.class} w-full flex items-center text-destructive focus:text-destructive focus:bg-destructive/10 gap-2 text-xs font-medium cursor-pointer"
                                            onclick={() => {
                                                props.onClick?.();
                                                showDeleteConfirm = true;
                                            }}
                                        >
                                            <Trash2 class="size-3.5 shrink-0" />
                                            <span>Eliminar cliente</span>
                                        </button>
                                    {/snippet}
                                </DropdownMenuItem>
                            {/if}
                        </DropdownMenuContent>
                    </DropdownMenu>
                {/if}

                <button
                    type="button"
                    onclick={handleClose}
                    class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer shrink-0"
                    aria-label="Cerrar"
                >
                    <X class="size-4" />
                </button>
            </div>
        </DialogHeader>


        <DialogBody>
            <form id="manage-customer-form" onsubmit={submit} class="space-y-4">
                <!-- Nombre o Razón Social -->
                <div class="space-y-1.5">
                    <Label for="manage-customer-name" class="text-sm font-medium text-foreground">
                        Nombre / Razón Social <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="manage-customer-name"
                        bind:value={form.name}
                        placeholder="Nombre del cliente"
                        required
                        class="h-9 text-sm font-medium"
                    />
                    {#if form.errors.name}
                        <p class="text-xs text-destructive">{form.errors.name}</p>
                    {/if}
                </div>

                <!-- CUIT / DNI y Condición Fiscal -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="space-y-1.5">
                        <Label for="manage-customer-tax-id" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                            <FileText class="h-3.5 w-3.5 text-muted-foreground" />
                            CUIT / CUIL / DNI
                        </Label>
                        <Input
                            id="manage-customer-tax-id"
                            bind:value={form.tax_id}
                            placeholder="ej. 20-33445566-9"
                            maxlength="13"
                            oninput={(e) => sanitizeIdentificationInput(e, (val) => { form.tax_id = val; })}
                            class="h-9 text-sm tabular-nums"
                        />
                        {#if form.errors.tax_id}
                            <p class="text-xs text-destructive">{form.errors.tax_id}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="manage-customer-tax-condition" class="text-sm font-medium text-foreground">
                            Condición Fiscal (IVA) <span class="text-destructive">*</span>
                        </Label>
                        <select
                            id="manage-customer-tax-condition"
                            bind:value={form.tax_condition}
                            class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:bg-transparent disabled:border-dashed"
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
                        <Label for="manage-customer-phone" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                            <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                            Teléfono / WhatsApp
                        </Label>
                        <Input
                            id="manage-customer-phone"
                            bind:value={form.phone}
                            placeholder="ej. +54 9 11 4455-6677"
                            class="h-9 text-sm tabular-nums"
                        />
                        {#if form.errors.phone}
                            <p class="text-xs text-destructive">{form.errors.phone}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="manage-customer-email" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                            <Mail class="h-3.5 w-3.5 text-muted-foreground" />
                            Correo Electrónico
                        </Label>
                        <Input
                            id="manage-customer-email"
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

                <!-- Dirección -->
                <div class="space-y-1.5">
                    <Label for="manage-customer-address" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                        <MapPin class="h-3.5 w-3.5 text-muted-foreground" />
                        Dirección Comercial / Entrega
                    </Label>
                    <Input
                        id="manage-customer-address"
                        bind:value={form.address}
                        placeholder="ej. Av. Rivadavia 4520, CABA"
                        class="h-9 text-sm"
                    />
                    {#if form.errors.address}
                        <p class="text-xs text-destructive">{form.errors.address}</p>
                    {/if}
                </div>

                <!-- Provincia, Departamento y Localidad / Barrio -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div class="space-y-1.5">
                        <Label for="manage-customer-province" class="text-sm font-medium text-foreground">Provincia</Label>
                        <select
                            id="manage-customer-province"
                            bind:value={form.province}
                            onchange={onProvinceChange}
                            class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary cursor-pointer"
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
                        <Label for="manage-customer-department" class="text-sm font-medium text-foreground">Departamento / Partido</Label>
                        <select
                            id="manage-customer-department"
                            bind:value={selectedDepartmentId}
                            onchange={onDepartmentChange}
                            disabled={!form.province || isLoadingDepartments}
                            class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
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
                            <Label for="manage-customer-city" class="text-sm font-medium text-foreground">Localidad / Barrio</Label>
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
                                id="manage-customer-city"
                                bind:value={form.city}
                                placeholder="Nombre de localidad o barrio"
                                class="h-10 text-sm font-medium"
                            />
                        {:else}
                            <select
                                id="manage-customer-city"
                                bind:value={form.city}
                                disabled={(!selectedDepartmentId && localities.length === 0) || isLoadingLocalities}
                                class="h-10 w-full rounded-xl border border-border/80 bg-background px-3 py-1.5 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                            >
                                <option value="">
                                    {#if isLoadingLocalities}
                                        Cargando...
                                    {:else if !selectedDepartmentId && !form.city}
                                        Primero departamento
                                    {:else}
                                        {form.city ? form.city : 'Selecciona localidad...'}
                                    {/if}
                                </option>
                                {#if form.city && !localities.some(l => l.name === form.city)}
                                    <option value={form.city}>{form.city} (actual)</option>
                                {/if}
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
                        <Label for="manage-customer-credit" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                            <CreditCard class="h-3.5 w-3.5 text-muted-foreground" />
                            Límite de crédito
                        </Label>
                        <Input
                            id="manage-customer-credit"
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
                        <Label for="manage-customer-discount" class="text-sm font-medium text-foreground flex items-center gap-1.5">
                            <Percent class="h-3.5 w-3.5 text-primary" />
                            Descuento habitual
                        </Label>
                        <Input
                            id="manage-customer-discount"
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

                <!-- Notas -->
                <div class="space-y-1.5">
                    <Label for="manage-customer-notes" class="text-sm font-medium text-foreground">
                        Notas Internas / Preferencias de Entrega
                    </Label>
                    <textarea
                        id="manage-customer-notes"
                        bind:value={form.notes}
                        rows="2"
                        placeholder="ej. Preferencias de entrega o historial crediticio..."
                        class="w-full rounded-xl border border-border/80 bg-background p-3 text-sm placeholder:text-muted-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary disabled:bg-transparent disabled:border-dashed resize-none"
                    ></textarea>

                    {#if form.errors.notes}
                        <p class="text-xs text-destructive">{form.errors.notes}</p>
                    {/if}
                </div>
            </form>
        </DialogBody>

        <DialogFooter class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2">
            <Button type="button" variant="outline" onclick={handleClose} disabled={form.processing || isDeleting} class="min-h-[44px] px-4 rounded-xl text-sm font-medium">
                Cancelar
            </Button>
            {#if canEdit}
                <Button type="submit" form="manage-customer-form" disabled={form.processing || isDeleting} class="min-h-[44px] px-4 rounded-xl text-sm font-medium min-w-32">
                    {form.processing ? 'Guardando...' : 'Guardar cambios'}
                </Button>
            {/if}
        </DialogFooter>

    </DialogContent>
</Dialog>

<!-- Modal Precaución: Desactivar Cliente -->
<ConfirmActionModal
    bind:open={showDeactivateConfirm}
    title="Desactivar Cliente"
    description={`¿Estás seguro de que deseas desactivar al cliente "${form.name || customer?.name}"? No aparecerá para nuevos pedidos ni operaciones hasta que sea reactivado.`}
    confirmText="Sí, desactivar"
    variant="warning"
    onConfirm={confirmToggleDeactivate}
/>

<!-- Modal: Activar Cliente -->
<ConfirmActionModal
    bind:open={showActivateConfirm}
    title="Activar Cliente"
    description={`¿Deseas activar nuevamente al cliente "${form.name || customer?.name}" para habilitar nuevos pedidos y ventas?`}
    confirmText="Sí, activar"
    variant="primary"
    onConfirm={confirmToggleActivate}
/>

<!-- Modal Precaución: Eliminar Cliente Permanentemente -->
<ConfirmActionModal
    bind:open={showDeleteConfirm}
    title="Eliminar Cliente Permanentemente"
    description={`¿Estás seguro de que deseas eliminar permanentemente al cliente "${form.name || customer?.name}"? Se desvinculará de pedidos anteriores.`}
    confirmText="Sí, eliminar definitivamente"
    variant="destructive"
    isLoading={isDeleting}
    onConfirm={executeDelete}
/>
