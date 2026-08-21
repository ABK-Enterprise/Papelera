<script module lang="ts">
    export const layout = null;
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import FacturaPdfPreview from './components/FacturaPdfPreview.svelte';
    import PlaygroundSidebar from './components/PlaygroundSidebar.svelte';

    interface Props {
        ticket: {
            token: string;
            sign: string;
            cuit: number;
            generation_time: string;
            expiration_time: string;
            is_valid?: boolean;
        } | null;
        cuitDefault: number;
        serviceDefault: string;
        wsaaUrl: string;
        wsfeUrl: string;
        catalogos: {
            comprobantes: Array<{ id: number; nombre: string; letra: string; discrimina_iva: boolean; requiere_cuit: boolean; es_ajuste?: boolean }>;
            conceptos: Array<{ id: number; nombre: string; requiere_fechas_servicio: boolean }>;
            tiposDoc: Array<{ id: number; nombre: string }>;
            condicionesIvaReceptor: Array<{ id: number; nombre: string; tipico: string }>;
            alicuotasIva: Array<{ id: number; porcentaje: number; nombre: string }>;
        };
    }

    let { ticket: initialTicket = null, cuitDefault, serviceDefault, wsaaUrl, wsfeUrl, catalogos }: Props = $props();

    // Estado de Navegación y Puntos de Venta
    let activeTab = $state<'pos' | 'ptos_venta' | 'wsaa' | 'docs'>('pos');
    let ticket = $state<{
        token: string;
        sign: string;
        cuit: number;
        generation_time: string;
        expiration_time: string;
        is_valid?: boolean;
    } | null>(initialTicket);

    let loadingWsaa = $state(false);
    let loadingUltimoNro = $state(false);
    let loadingEmitir = $state(false);
    let errorGeneral = $state<string | null>(null);
    let copiedField = $state<string | null>(null);
    let timeRemaining = $state<string>('');

    // Historial de actividad
    let logs = $state<Array<{ time: string; msg: string; type: 'info' | 'success' | 'error' }>>([]);

    function addLog(msg: string, type: 'info' | 'success' | 'error' = 'info') {
        const now = new Date().toLocaleTimeString('es-AR');
        logs = [{ time: now, msg, type }, ...logs.slice(0, 40)];
    }

    // Cuenta regresiva del Ticket
    function updateTimeRemaining() {
        if (!ticket?.expiration_time) {
            timeRemaining = '';
            return;
        }
        const exp = new Date(ticket.expiration_time).getTime();
        const now = Date.now();
        const diff = exp - now;
        if (diff <= 0) {
            timeRemaining = 'Expirado';
            if (ticket) ticket.is_valid = false;
        } else {
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            timeRemaining = `${hours}h ${minutes}m ${seconds}s`;
            if (ticket) ticket.is_valid = true;
        }
    }

    $effect(() => {
        updateTimeRemaining();
        const interval = setInterval(updateTimeRemaining, 1000);
        return () => clearInterval(interval);
    });

    // Helper de petición AJAX seguro con CSRF
    async function apiRequest(url: string, body: any): Promise<any> {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
            || (document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ? decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)![1]) : '');

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            const data = await response.json();
            if (!response.ok || data.success === false) {
                throw new Error(data.error || data.message || `Error HTTP ${response.status}`);
            }
            return data;
        } else {
            const text = await response.text();
            if (!response.ok) {
                throw new Error(`Error en servidor (${response.status}): ${text.slice(0, 200)}`);
            }
            throw new Error(`Respuesta no esperada del servidor: ${text.slice(0, 150)}`);
        }
    }

    // --- GESTIÓN DE PUNTOS DE VENTA LOCALES / TESTING ---
    interface PuntoVentaLocal {
        id: number;
        nombre: string;
        descripcion: string;
        tipo: 'Mostrador' | 'Deposito' | 'Web' | 'Movil';
    }

    let puntosVenta = $state<PuntoVentaLocal[]>([
        { id: 1, nombre: 'Punto 0001 - Mostrador Principal', descripcion: 'Atención al público en salón central', tipo: 'Mostrador' },
        { id: 2, nombre: 'Punto 0002 - Sucursal Flores / Depósito', descripcion: 'Ventas mayoristas y despacho', tipo: 'Deposito' },
        { id: 3, nombre: 'Punto 0003 - Tienda E-commerce', descripcion: 'Facturación automática online', tipo: 'Web' },
    ]);

    let ptoVta = $state(1);
    let nuevoPtoNro = $state(4);
    let nuevoPtoNombre = $state('');

    function agregarPuntoVenta() {
        if (!nuevoPtoNro || nuevoPtoNro <= 0) return;
        if (puntosVenta.some((p) => p.id === nuevoPtoNro)) {
            errorGeneral = `El Punto de Venta ${nuevoPtoNro} ya existe en la lista.`;
            return;
        }
        const nombreFinal = nuevoPtoNombre.trim() || `Punto ${String(nuevoPtoNro).padStart(4, '0')} - Sucursal Personalizada`;
        puntosVenta = [...puntosVenta, { id: nuevoPtoNro, nombre: nombreFinal, descripcion: 'Creado para pruebas en Homologación', tipo: 'Mostrador' }];
        ptoVta = nuevoPtoNro;
        nuevoPtoNro += 1;
        nuevoPtoNombre = '';
        addLog(`Punto de Venta ${ptoVta} registrado localmente y seleccionado.`, 'success');
        consultarUltimoNumero();
    }

    function seleccionarPuntoVenta(id: number) {
        ptoVta = id;
        addLog(`Cambiado al Punto de Venta ${ptoVta}`, 'info');
        consultarUltimoNumero();
    }

    // --- CATÁLOGO DE PRODUCTOS Y CARRITO (POV / POS) ---
    interface ItemProducto {
        id: string;
        descripcion: string;
        cantidad: number;
        precio_unitario: number;
        alicuota_iva: number; // 21, 10.5, 27, 0
        tipo: 'producto' | 'servicio' | 'exento';
    }

    const catalogoRapido = [
        { descripcion: 'Resma A4 75g Ledesma Autor (500 hojas)', precio_unitario: 5800.0, alicuota_iva: 21.0, tipo: 'producto' as const },
        { descripcion: 'Rollo Térmico 80mm x 50m (Pack x10)', precio_unitario: 12500.0, alicuota_iva: 21.0, tipo: 'producto' as const },
        { descripcion: 'Cuaderno Universitario A4 Rayado 100 Hojas', precio_unitario: 3400.0, alicuota_iva: 21.0, tipo: 'producto' as const },
        { descripcion: 'Impresora Térmica Fiscal Bien de Capital', precio_unitario: 95000.0, alicuota_iva: 10.5, tipo: 'producto' as const },
        { descripcion: 'Servicio de Impresión y Encuadernación', precio_unitario: 8500.0, alicuota_iva: 21.0, tipo: 'servicio' as const },
        { descripcion: 'Libro / Manual de Formación Educativo', precio_unitario: 4200.0, alicuota_iva: 0.0, tipo: 'exento' as const },
    ];

    let carrito = $state<ItemProducto[]>([
        {
            id: 'item-1',
            descripcion: 'Resma A4 75g Ledesma Autor (500 hojas)',
            cantidad: 2,
            precio_unitario: 5800.0,
            alicuota_iva: 21.0,
            tipo: 'producto',
        },
        {
            id: 'item-2',
            descripcion: 'Rollo Térmico 80mm x 50m (Pack x10)',
            cantidad: 1,
            precio_unitario: 12500.0,
            alicuota_iva: 21.0,
            tipo: 'producto',
        },
    ]);

    // Formulario de nuevo producto manual
    let customDesc = $state('');
    let customCant = $state(1);
    let customPrecio = $state(1000.0);
    let customAlic = $state(21.0);

    function agregarProductoPersonalizado() {
        if (!customDesc.trim()) return;
        const newItem: ItemProducto = {
            id: `item-${Date.now()}`,
            descripcion: customDesc.trim(),
            cantidad: Number(customCant) || 1,
            precio_unitario: Number(customPrecio) || 0,
            alicuota_iva: Number(customAlic),
            tipo: customAlic === 0 ? 'exento' : 'producto',
        };
        carrito = [...carrito, newItem];
        customDesc = '';
        customCant = 1;
        customPrecio = 1000.0;
        addLog(`Producto agregado al carrito: ${newItem.descripcion}`, 'info');
    }

    function agregarDesdeCatalogo(prod: typeof catalogoRapido[0]) {
        const existente = carrito.find((i) => i.descripcion === prod.descripcion);
        if (existente) {
            existente.cantidad += 1;
        } else {
            carrito = [
                ...carrito,
                {
                    id: `item-${Date.now()}`,
                    descripcion: prod.descripcion,
                    cantidad: 1,
                    precio_unitario: prod.precio_unitario,
                    alicuota_iva: prod.alicuota_iva,
                    tipo: prod.tipo,
                },
            ];
        }
        addLog(`+1 ${prod.descripcion} agregado al carrito`, 'info');
    }

    function eliminarItem(id: string) {
        carrito = carrito.filter((i) => i.id !== id);
    }

    function vaciarCarrito() {
        carrito = [];
        addLog('Carrito vaciado', 'info');
    }

    // --- CONFIGURACIÓN DE FACTURACIÓN & RECEPTOR ---
    let cbteTipo = $state(1); // 1: Factura A, 6: Factura B, 11: Factura C
    let docTipo = $state(80); // 80: CUIT, 96: DNI, 99: Consumidor Final
    let docNro = $state(30000000007); // CUIT de prueba ARCA
    let condicionIvaReceptorId = $state(1); // RG 5616: 1 (Resp. Inscripto)
    let cbteNro = $state(1);

    const getTodayString = () => new Date().toISOString().slice(0, 10).replace(/-/g, '');
    let cbteFch = $state(getTodayString());
    let fchServDesde = $state(getTodayString());
    let fchServHasta = $state(getTodayString());
    let fchVtoPago = $state(getTodayString());
    let tributosImporte = $state(0.0);

    // Concepto deducido automáticamente de los ítems
    let concepto = $derived(() => {
        const tieneServicios = carrito.some((i) => i.tipo === 'servicio');
        const tieneProductos = carrito.some((i) => i.tipo === 'producto' || i.tipo === 'exento');
        if (tieneServicios && tieneProductos) return 3; // Mixto
        if (tieneServicios) return 2; // Servicios
        return 1; // Productos
    });

    let requiereFechasServicio = $derived(concepto() === 2 || concepto() === 3);
    let comprobanteSeleccionado = $derived(catalogos.comprobantes.find((c) => c.id === Number(cbteTipo)));
    let discriminaIva = $derived(comprobanteSeleccionado?.discrimina_iva ?? true);

    // --- CÁLCULOS FISCALES AGRUPADOS EN TIEMPO REAL ($derived) ---
    let neto21 = $derived(
        Number(
            carrito
                .filter((i) => i.alicuota_iva === 21.0)
                .reduce((acc, i) => acc + i.cantidad * i.precio_unitario, 0)
                .toFixed(2)
        )
    );

    let neto105 = $derived(
        Number(
            carrito
                .filter((i) => i.alicuota_iva === 10.5)
                .reduce((acc, i) => acc + i.cantidad * i.precio_unitario, 0)
                .toFixed(2)
        )
    );

    let neto27 = $derived(
        Number(
            carrito
                .filter((i) => i.alicuota_iva === 27.0)
                .reduce((acc, i) => acc + i.cantidad * i.precio_unitario, 0)
                .toFixed(2)
        )
    );

    let exento = $derived(
        Number(
            carrito
                .filter((i) => i.alicuota_iva === 0.0)
                .reduce((acc, i) => acc + i.cantidad * i.precio_unitario, 0)
                .toFixed(2)
        )
    );

    let iva21 = $derived(Number((neto21 * 0.21).toFixed(2)));
    let iva105 = $derived(Number((neto105 * 0.105).toFixed(2)));
    let iva27 = $derived(Number((neto27 * 0.27).toFixed(2)));
    let totalIva = $derived(Number((iva21 + iva105 + iva27).toFixed(2)));
    let totalNeto = $derived(Number((neto21 + neto105 + neto27).toFixed(2)));

    let totalFactura = $derived(
        discriminaIva
            ? Number((totalNeto + exento + totalIva + tributosImporte).toFixed(2))
            : Number((totalNeto + exento + tributosImporte).toFixed(2))
    );

    // --- ACCIONES ARCA ---
    interface FacturaResultado {
        resultado: string;
        cuit: number;
        pto_vta: number;
        cbte_tipo: number;
        cbte_desde: number;
        cbte_hasta: number;
        cbte_fch: string;
        cae?: string | null;
        cae_fch_vto?: string | null;
        observaciones: Array<{ code: number; msg: string }>;
        errores: Array<{ code: number; msg: string }>;
        eventos: Array<{ code: number; msg: string }>;
        raw_xml: string;
    }

    let facturaResponse = $state<FacturaResultado | null>(null);
    let mostrarModalPdf = $state(false);

    async function solicitarTicketWsaa(forceRenew = false) {
        loadingWsaa = true;
        errorGeneral = null;
        try {
            const data = await apiRequest('/playground/arca/ticket', {
                cuit: cuitDefault,
                service: serviceDefault,
                force_renew: forceRenew,
            });
            ticket = data.ticket;
            updateTimeRemaining();
            addLog(`Ticket WSAA ${forceRenew ? 'renovado' : 'obtenido'} exitosamente para CUIT ${ticket?.cuit}`, 'success');
        } catch (e: any) {
            errorGeneral = e.message;
            addLog(`Error WSAA: ${e.message}`, 'error');
        } finally {
            loadingWsaa = false;
        }
    }

    async function consultarUltimoNumero() {
        loadingUltimoNro = true;
        errorGeneral = null;
        try {
            const data = await apiRequest('/playground/arca/ultimo-comprobante', {
                pto_vta: ptoVta,
                cbte_tipo: cbteTipo,
            });
            cbteNro = data.siguiente_nro;
            addLog(`Último N° en ARCA para Pto Vta ${ptoVta} / Tipo ${cbteTipo}: ${data.ultimo_nro} (Siguiente a emitir: ${data.siguiente_nro})`, 'success');
        } catch (e: any) {
            errorGeneral = e.message;
            addLog(`Error al consultar último número: ${e.message}`, 'error');
        } finally {
            loadingUltimoNro = false;
        }
    }

    async function emitirFacturaDesdeCarrito() {
        if (carrito.length === 0) {
            errorGeneral = 'El carrito está vacío. Agregue productos antes de emitir la factura.';
            return;
        }

        loadingEmitir = true;
        errorGeneral = null;
        facturaResponse = null;

        // Construir detalle agrupado de IVA para ARCA
        const arrayIva: Array<{ id: number; base_imp: number; importe: number }> = [];
        if (discriminaIva) {
            if (neto21 > 0) arrayIva.push({ id: 5, base_imp: neto21, importe: iva21 });
            if (neto105 > 0) arrayIva.push({ id: 4, base_imp: neto105, importe: iva105 });
            if (neto27 > 0) arrayIva.push({ id: 6, base_imp: neto27, importe: iva27 });
        }

        const payload = {
            pto_vta: ptoVta,
            cbte_tipo: cbteTipo,
            concepto: concepto(),
            doc_tipo: docTipo,
            doc_nro: docNro,
            cbte_desde: cbteNro,
            cbte_hasta: cbteNro,
            cbte_fch: cbteFch,
            imp_total: totalFactura,
            imp_tot_conc: 0.0,
            imp_neto: totalNeto,
            imp_op_ex: exento,
            imp_trib: tributosImporte,
            imp_iva: discriminaIva ? totalIva : 0,
            mon_id: 'PES',
            mon_cotiz: 1.0,
            condicion_iva_receptor_id: condicionIvaReceptorId,
            fch_serv_desde: requiereFechasServicio ? fchServDesde : null,
            fch_serv_hasta: requiereFechasServicio ? fchServHasta : null,
            fch_vto_pago: requiereFechasServicio ? fchVtoPago : null,
            iva: arrayIva,
            tributos: [],
            cbtes_asoc: [],
        };

        try {
            const data = await apiRequest('/playground/arca/emitir', payload);
            facturaResponse = data.factura;
            if (data.factura.resultado === 'A') {
                addLog(`Factura emitida y APROBADA con CAE ${data.factura.cae} (Vto: ${data.factura.cae_fch_vto}) para Pto Vta ${ptoVta}`, 'success');
                // Actualizar correlativo para la próxima venta
                cbteNro = data.factura.cbte_desde + 1;
            } else {
                addLog(`Factura RECHAZADA por ARCA. Verifique los errores en pantalla.`, 'error');
            }
        } catch (e: any) {
            errorGeneral = e.message;
            addLog(`Error en emisión: ${e.message}`, 'error');
        } finally {
            loadingEmitir = false;
        }
    }

    function copiarTexto(texto: string, label: string) {
        navigator.clipboard.writeText(texto);
        copiedField = label;
        setTimeout(() => {
            copiedField = null;
        }, 2000);
    }
</script>

<AppHead title="Papelera POS - Punto de Venta ARCA" />

<div class="flex h-screen w-screen overflow-hidden bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <!-- Sidebar Reutilizable -->
    <PlaygroundSidebar
        {activeTab}
        onSelectTab={(t) => (activeTab = t)}
        {ticket}
        {timeRemaining}
        currentPtoVta={ptoVta}
    />

    <!-- Contenido Principal -->
    <div class="flex flex-1 flex-col overflow-hidden">
        <!-- Top Bar Header con Selector Rápido de Punto de Venta -->
        <header class="flex h-14 items-center justify-between border-b border-zinc-200 bg-white px-6 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-500">Punto de Venta Activo:</span>
                <select
                    bind:value={ptoVta}
                    onchange={() => consultarUltimoNumero()}
                    class="rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-1.5 text-xs font-bold text-zinc-900 shadow-sm focus:border-emerald-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                >
                    {#each puntosVenta as pv}
                        <option value={pv.id}>{pv.nombre} ({pv.tipo})</option>
                    {/each}
                </select>

                <button
                    onclick={consultarUltimoNumero}
                    disabled={loadingUltimoNro}
                    class="flex items-center gap-1 rounded-lg bg-zinc-100 px-2.5 py-1 text-[11px] font-semibold text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300"
                >
                    {loadingUltimoNro ? 'Consultando...' : 'N° Correlativo: ' + cbteNro}
                </button>
            </div>

            <div class="flex items-center gap-3">
                <button
                    onclick={() => solicitarTicketWsaa(true)}
                    disabled={loadingWsaa}
                    class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50 dark:bg-emerald-500 dark:text-zinc-950"
                >
                    {#if loadingWsaa}
                        <span class="h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                        <span>Renovando...</span>
                    {:else}
                        <span>⚡ {ticket?.is_valid ? 'Ticket WSAA Activo' : 'Obtener Ticket WSAA'}</span>
                    {/if}
                </button>
            </div>
        </header>

        <!-- Area de Trabajo -->
        <main class="flex-1 overflow-y-auto p-6">
            {#if errorGeneral}
                <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-200">
                    <div class="flex items-center justify-between">
                        <span class="font-bold">Error detectado:</span>
                        <button onclick={() => (errorGeneral = null)} class="font-bold">✕</button>
                    </div>
                    <p class="mt-1 font-mono">{errorGeneral}</p>
                </div>
            {/if}

            <!-- TAB 1: PUNTO DE VENTA (POS & CHECKOUT DE PRODUCTOS) -->
            {#if activeTab === 'pos'}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <!-- Columna Izquierda: Catálogo Rápido + Carrito de Ítems -->
                    <div class="space-y-6 lg:col-span-7">
                        <!-- Catálogo de Productos Rápidos -->
                        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex items-center justify-between">
                                <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Catálogo de Productos Rápidos</h2>
                                <span class="text-[11px] text-zinc-500">Haga clic en un producto para agregarlo</span>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {#each catalogoRapido as prod}
                                    <button
                                        type="button"
                                        onclick={() => agregarDesdeCatalogo(prod)}
                                        class="flex flex-col justify-between rounded-lg border border-zinc-200 bg-zinc-50/70 p-3 text-left transition-all hover:border-emerald-500 hover:bg-emerald-50/30 dark:border-zinc-800 dark:bg-zinc-800/50 dark:hover:border-emerald-500"
                                    >
                                        <div>
                                            <p class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 line-clamp-2">{prod.descripcion}</p>
                                            <span class="mt-1 inline-block rounded bg-zinc-200 px-1.5 py-0.2 text-[10px] font-mono text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                                IVA {prod.alicuota_iva}%
                                            </span>
                                        </div>
                                        <p class="mt-2 text-xs font-black text-emerald-600 dark:text-emerald-400">${prod.precio_unitario.toLocaleString('es-AR')}</p>
                                    </button>
                                {/each}
                            </div>
                        </div>

                        <!-- Formulario para Agregar Producto Manual -->
                        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Agregar Ítem Personalizado</h2>
                            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-12">
                                <div class="sm:col-span-6">
                                    <label for="customDescInput" class="block text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">Descripción</label>
                                    <input
                                        id="customDescInput"
                                        type="text"
                                        placeholder="Ej: Resma Papel Ilustración 150g"
                                        bind:value={customDesc}
                                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="customCantInput" class="block text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">Cantidad</label>
                                    <input
                                        id="customCantInput"
                                        type="number"
                                        min="1"
                                        bind:value={customCant}
                                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-mono text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="customPrecioInput" class="block text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">Precio Unit.</label>
                                    <input
                                        id="customPrecioInput"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        bind:value={customPrecio}
                                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-mono text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="customAlicSelect" class="block text-[11px] font-semibold text-zinc-600 dark:text-zinc-400">Alícuota IVA</label>
                                    <select
                                        id="customAlicSelect"
                                        bind:value={customAlic}
                                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-xs text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    >
                                        <option value={21}>21%</option>
                                        <option value={10.5}>10.5%</option>
                                        <option value={27}>27%</option>
                                        <option value={0}>0% (Exento)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end">
                                <button
                                    type="button"
                                    onclick={agregarProductoPersonalizado}
                                    class="rounded-lg bg-zinc-900 px-4 py-1.5 text-xs font-semibold text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900"
                                >
                                    + Agregar al Carrito
                                </button>
                            </div>
                        </div>

                        <!-- Grilla / Carrito de Ítems -->
                        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex items-center justify-between border-b border-zinc-200 pb-3 dark:border-zinc-800">
                                <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                                    Ítems del Comprobante ({carrito.length})
                                </h2>
                                {#if carrito.length > 0}
                                    <button
                                        onclick={vaciarCarrito}
                                        class="text-xs font-semibold text-rose-600 hover:underline dark:text-rose-400"
                                    >
                                        Vaciar Carrito
                                    </button>
                                {/if}
                            </div>

                            {#if carrito.length === 0}
                                <div class="py-10 text-center text-xs text-zinc-400 italic">
                                    El carrito está vacío. Seleccione productos del catálogo o agregue un ítem personalizado.
                                </div>
                            {:else}
                                <div class="mt-3 overflow-x-auto">
                                    <table class="w-full border-collapse text-left text-xs">
                                        <thead>
                                            <tr class="border-b border-zinc-200 bg-zinc-50 font-bold dark:border-zinc-800 dark:bg-zinc-800/40">
                                                <th class="py-2 px-3">Descripción</th>
                                                <th class="py-2 px-2 text-center w-20">Cant.</th>
                                                <th class="py-2 px-3 text-right">P. Unit.</th>
                                                <th class="py-2 px-3 text-right">IVA</th>
                                                <th class="py-2 px-3 text-right">Subtotal</th>
                                                <th class="py-2 px-2 text-center w-10"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                            {#each carrito as item}
                                                <tr>
                                                    <td class="py-2.5 px-3 font-medium">{item.descripcion}</td>
                                                    <td class="py-2.5 px-2 text-center">
                                                        <input
                                                            type="number"
                                                            min="1"
                                                            bind:value={item.cantidad}
                                                            class="w-16 rounded border border-zinc-300 bg-white px-1.5 py-0.5 text-center font-mono text-xs dark:border-zinc-700 dark:bg-zinc-800"
                                                        />
                                                    </td>
                                                    <td class="py-2.5 px-3 text-right font-mono">
                                                        ${item.precio_unitario.toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                                                    </td>
                                                    <td class="py-2.5 px-3 text-right font-mono text-zinc-500">
                                                        {item.alicuota_iva}%
                                                    </td>
                                                    <td class="py-2.5 px-3 text-right font-mono font-bold">
                                                        ${(item.cantidad * item.precio_unitario).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                                                    </td>
                                                    <td class="py-2.5 px-2 text-center">
                                                        <button
                                                            type="button"
                                                            onclick={() => eliminarItem(item.id)}
                                                            class="text-zinc-400 hover:text-rose-600 dark:hover:text-rose-400"
                                                        >
                                                            ✕
                                                        </button>
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            {/if}
                        </div>
                    </div>

                    <!-- Columna Derecha: Configuración del Receptor + Desglose Fiscal + Emisión Oficial -->
                    <div class="space-y-6 lg:col-span-5">
                        <!-- Datos del Receptor & Comprobante -->
                        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Datos Fiscales del Comprobante</h2>

                            <div class="mt-4 space-y-3 text-xs">
                                <div>
                                    <label for="posCbteTipo" class="block font-semibold text-zinc-700 dark:text-zinc-300">Tipo de Factura</label>
                                    <select
                                        id="posCbteTipo"
                                        bind:value={cbteTipo}
                                        onchange={() => consultarUltimoNumero()}
                                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    >
                                        {#each catalogos.comprobantes as c}
                                            <option value={c.id}>{c.nombre} (Letra {c.letra})</option>
                                        {/each}
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="posDocTipo" class="block font-semibold text-zinc-700 dark:text-zinc-300">Doc. Receptor</label>
                                        <select
                                            id="posDocTipo"
                                            bind:value={docTipo}
                                            class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-xs text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                        >
                                            {#each catalogos.tiposDoc as td}
                                                <option value={td.id}>{td.nombre}</option>
                                            {/each}
                                        </select>
                                    </div>
                                    <div>
                                        <label for="posDocNro" class="block font-semibold text-zinc-700 dark:text-zinc-300">N° Documento</label>
                                        <input
                                            id="posDocNro"
                                            type="number"
                                            bind:value={docNro}
                                            class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-xs font-mono text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label for="posCondIva" class="block font-semibold text-zinc-700 dark:text-zinc-300">Condición IVA Receptor (RG 5616)</label>
                                    <select
                                        id="posCondIva"
                                        bind:value={condicionIvaReceptorId}
                                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                                    >
                                        {#each catalogos.condicionesIvaReceptor as cond}
                                            <option value={cond.id}>{cond.nombre}</option>
                                        {/each}
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen Fiscal y Ecuación Contable -->
                        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <h2 class="text-sm font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Desglose Fiscal (Agrupación ARCA)</h2>
                            <div class="mt-3 space-y-2 text-xs">
                                <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
                                    <span class="text-zinc-500">Base Neta Gravada (21%):</span>
                                    <span class="font-mono font-medium">${neto21.toLocaleString('es-AR', { minimumFractionDigits: 2 })}</span>
                                </div>
                                {#if neto105 > 0}
                                    <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
                                        <span class="text-zinc-500">Base Neta Gravada (10.5%):</span>
                                        <span class="font-mono font-medium">${neto105.toLocaleString('es-AR', { minimumFractionDigits: 2 })}</span>
                                    </div>
                                {/if}
                                {#if exento > 0}
                                    <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
                                        <span class="text-zinc-500">Importe Exento:</span>
                                        <span class="font-mono font-medium">${exento.toLocaleString('es-AR', { minimumFractionDigits: 2 })}</span>
                                    </div>
                                {/if}
                                {#if discriminaIva}
                                    <div class="flex justify-between py-1 border-b border-zinc-100 dark:border-zinc-800">
                                        <span class="text-zinc-500">IVA Débito Fiscal:</span>
                                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                            ${totalIva.toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                                        </span>
                                    </div>
                                {/if}
                                <div class="flex justify-between pt-2 text-base font-black">
                                    <span>TOTAL A FACTURAR:</span>
                                    <span class="text-emerald-600 dark:text-emerald-400">
                                        ${totalFactura.toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                                    </span>
                                </div>
                            </div>

                            <!-- Botón de Emisión Oficial -->
                            <div class="mt-5">
                                <button
                                    type="button"
                                    onclick={emitirFacturaDesdeCarrito}
                                    disabled={loadingEmitir || carrito.length === 0}
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3.5 text-sm font-bold text-white shadow-lg transition-all hover:bg-emerald-700 disabled:opacity-50 dark:bg-emerald-500 dark:text-zinc-950 dark:hover:bg-emerald-400"
                                >
                                    {#if loadingEmitir}
                                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                                        <span>Autorizando con ARCA (WSFEv1)...</span>
                                    {:else}
                                        <span>⚡ Emitir Factura Electrónica (CAE)</span>
                                    {/if}
                                </button>
                            </div>
                        </div>

                        <!-- Panel de Resultado ARCA -->
                        {#if facturaResponse}
                            <div class="rounded-xl border-2 p-5 shadow-md {facturaResponse.resultado === 'A'
                                ? 'border-emerald-500 bg-emerald-50/40 dark:border-emerald-600 dark:bg-emerald-950/20'
                                : 'border-rose-500 bg-rose-50/40 dark:border-rose-600 dark:bg-rose-950/20'}">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        {#if facturaResponse.resultado === 'A'}
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-white font-bold text-sm">✓</span>
                                            <div>
                                                <h3 class="text-sm font-bold text-emerald-900 dark:text-emerald-200">Factura Autorizada por ARCA</h3>
                                                <p class="text-[11px] text-emerald-700 dark:text-emerald-300">Pto Vta {facturaResponse.pto_vta} | Comp. N° {facturaResponse.cbte_desde}</p>
                                            </div>
                                        {:else}
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-rose-500 text-white font-bold text-sm">✕</span>
                                            <div>
                                                <h3 class="text-sm font-bold text-rose-900 dark:text-rose-200">Comprobante Rechazado</h3>
                                                <p class="text-[11px] text-rose-700 dark:text-rose-300">Verifique los errores informados</p>
                                            </div>
                                        {/if}
                                    </div>

                                    {#if facturaResponse.cae}
                                        <button
                                            onclick={() => (mostrarModalPdf = true)}
                                            class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900"
                                        >
                                            Ver Factura PDF
                                        </button>
                                    {/if}
                                </div>

                                {#if facturaResponse.cae}
                                    <div class="mt-3 grid grid-cols-2 gap-2 rounded-lg bg-white/80 p-3 text-xs dark:bg-zinc-900/80 font-mono">
                                        <div>
                                            <span class="text-zinc-500">CAE:</span>
                                            <p class="font-bold text-zinc-900 dark:text-zinc-100">{facturaResponse.cae}</p>
                                        </div>
                                        <div>
                                            <span class="text-zinc-500">Vto CAE:</span>
                                            <p class="font-bold text-zinc-900 dark:text-zinc-100">{facturaResponse.cae_fch_vto || 'N/A'}</p>
                                        </div>
                                    </div>
                                {/if}

                                {#if facturaResponse.errores && facturaResponse.errores.length > 0}
                                    <div class="mt-3 space-y-1">
                                        <p class="text-xs font-bold text-rose-800 dark:text-rose-300">Errores de ARCA:</p>
                                        {#each facturaResponse.errores as err}
                                            <div class="rounded bg-rose-100/80 px-2.5 py-1 text-xs text-rose-900 dark:bg-rose-950 dark:text-rose-200">
                                                <strong>[{err.code}]:</strong> {err.msg}
                                            </div>
                                        {/each}
                                    </div>
                                {/if}
                            </div>
                        {/if}
                    </div>
                </div>
            {/if}

            <!-- TAB 2: GESTIÓN DE PUNTOS DE VENTA -->
            {#if activeTab === 'ptos_venta'}
                <div class="max-w-4xl space-y-6">
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Gestión de Puntos de Venta para Testing</h2>
                        <p class="mt-1 text-xs text-zinc-500">
                            En ARCA Homologación podés testear cualquier número de punto de venta. Cada número mantiene un contador correlativo independiente.
                        </p>

                        <!-- Tabla de Puntos de Venta -->
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full border-collapse text-left text-xs">
                                <thead>
                                    <tr class="border-b border-zinc-200 bg-zinc-50 font-bold dark:border-zinc-800 dark:bg-zinc-800/40">
                                        <th class="py-2.5 px-4">Punto</th>
                                        <th class="py-2.5 px-4">Nombre / Sucursal</th>
                                        <th class="py-2.5 px-4">Tipo</th>
                                        <th class="py-2.5 px-4 text-center">Estado</th>
                                        <th class="py-2.5 px-4 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    {#each puntosVenta as pv}
                                        <tr class={ptoVta === pv.id ? 'bg-emerald-50/50 dark:bg-emerald-950/20' : ''}>
                                            <td class="py-3 px-4 font-mono font-bold text-sm">
                                                {String(pv.id).padStart(4, '0')}
                                            </td>
                                            <td class="py-3 px-4 font-medium">
                                                {pv.nombre}
                                                <p class="text-[10px] text-zinc-400">{pv.descripcion}</p>
                                            </td>
                                            <td class="py-3 px-4 font-mono">{pv.tipo}</td>
                                            <td class="py-3 px-4 text-center">
                                                {#if ptoVta === pv.id}
                                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                        ACTIVO
                                                    </span>
                                                {:else}
                                                    <span class="rounded bg-zinc-100 px-2 py-0.5 text-[10px] text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                                        Disponible
                                                    </span>
                                                {/if}
                                            </td>
                                            <td class="py-3 px-4 text-right">
                                                <button
                                                    type="button"
                                                    onclick={() => {
                                                        seleccionarPuntoVenta(pv.id);
                                                        activeTab = 'pos';
                                                    }}
                                                    class="rounded-lg bg-zinc-900 px-3 py-1 text-xs font-semibold text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900"
                                                >
                                                    Usar en POS →
                                                </button>
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>

                        <!-- Formulario para Agregar Nuevo Punto de Venta de Prueba -->
                        <div class="mt-6 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/40">
                            <h3 class="text-xs font-bold text-zinc-900 dark:text-zinc-100">Crear Nuevo Punto de Venta de Prueba</h3>
                            <div class="mt-3 flex flex-wrap gap-3">
                                <div class="w-28">
                                    <label for="nuevoPtoNro" class="block text-[10px] font-semibold text-zinc-500">N° Punto</label>
                                    <input
                                        id="nuevoPtoNro"
                                        type="number"
                                        min="1"
                                        max="99999"
                                        bind:value={nuevoPtoNro}
                                        class="mt-1 block w-full rounded border border-zinc-300 bg-white px-2 py-1.5 text-xs font-mono dark:border-zinc-700 dark:bg-zinc-800"
                                    />
                                </div>
                                <div class="flex-1">
                                    <label for="nuevoPtoNombre" class="block text-[10px] font-semibold text-zinc-500">Nombre / Identificador</label>
                                    <input
                                        id="nuevoPtoNombre"
                                        type="text"
                                        placeholder="Ej: Punto 0004 - Sucursal Belgrano"
                                        bind:value={nuevoPtoNombre}
                                        class="mt-1 block w-full rounded border border-zinc-300 bg-white px-3 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-800"
                                    />
                                </div>
                                <div class="flex items-end">
                                    <button
                                        type="button"
                                        onclick={agregarPuntoVenta}
                                        class="rounded bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700"
                                    >
                                        + Agregar Punto
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- TAB 3: ESTADO WSAA -->
            {#if activeTab === 'wsaa'}
                <div class="max-w-4xl space-y-6">
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Ticket de Acceso WSAA (Autenticación ARCA)</h2>
                                <p class="mt-1 text-xs text-zinc-500">El Ticket de Acceso firmado con CMS/PKCS#7 contiene Token y Sign para WSFEv1.</p>
                            </div>
                            <button
                                onclick={() => solicitarTicketWsaa(true)}
                                disabled={loadingWsaa}
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                            >
                                {loadingWsaa ? 'Solicitando...' : 'Renovar Ticket de Acceso'}
                            </button>
                        </div>

                        {#if ticket}
                            <div class="mt-6 space-y-4 text-xs">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                                        <span class="text-zinc-500">CUIT Emisor:</span>
                                        <p class="font-mono font-bold text-zinc-900 dark:text-zinc-100">{ticket.cuit}</p>
                                    </div>
                                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                                        <span class="text-zinc-500">Generación:</span>
                                        <p class="font-mono text-zinc-900 dark:text-zinc-100">{ticket.generation_time}</p>
                                    </div>
                                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                                        <span class="text-zinc-500">Expiración:</span>
                                        <p class="font-mono font-bold text-zinc-900 dark:text-zinc-100">{ticket.expiration_time}</p>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Token:</span>
                                        <button
                                            onclick={() => copiarTexto(ticket?.token || '', 'token')}
                                            class="text-emerald-600 hover:underline dark:text-emerald-400 text-[11px]"
                                        >
                                            {copiedField === 'token' ? '¡Copiado!' : 'Copiar Token'}
                                        </button>
                                    </div>
                                    <pre class="mt-1 max-h-24 overflow-x-auto rounded bg-zinc-100 p-2 font-mono text-[10px] text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200">{ticket.token}</pre>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Sign:</span>
                                        <button
                                            onclick={() => copiarTexto(ticket?.sign || '', 'sign')}
                                            class="text-emerald-600 hover:underline dark:text-emerald-400 text-[11px]"
                                        >
                                            {copiedField === 'sign' ? '¡Copiado!' : 'Copiar Sign'}
                                        </button>
                                    </div>
                                    <pre class="mt-1 max-h-24 overflow-x-auto rounded bg-zinc-100 p-2 font-mono text-[10px] text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200">{ticket.sign}</pre>
                                </div>
                            </div>
                        {:else}
                            <div class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-6 text-center text-xs text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/40">
                                No se encuentra un Ticket de Acceso cargado.
                            </div>
                        {/if}
                    </div>
                </div>
            {/if}

            <!-- TAB 4: DOCS -->
            {#if activeTab === 'docs'}
                <div class="max-w-4xl space-y-6">
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Reglas Oficiales de Facturación Electrónica ARCA (WSFEv1)</h2>
                        <div class="mt-4 space-y-4 text-xs text-zinc-700 dark:text-zinc-300">
                            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/40">
                                <h3 class="font-bold text-zinc-900 dark:text-zinc-100">1. Ecuación Contable Estricta</h3>
                                <p class="mt-1 font-mono text-emerald-600 dark:text-emerald-400">ImpTotal = ImpNeto + ImpOpEx + ImpTotConc + ImpIVA + ImpTrib</p>
                                <p class="mt-1 text-zinc-500">Si los importes no cuadran exactamente con la suma de alícuotas y tributos, la factura es rechazada de inmediato.</p>
                            </div>

                            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/40">
                                <h3 class="font-bold text-zinc-900 dark:text-zinc-100">2. Resolución General RG 5616 (CondicionIVAReceptorId)</h3>
                                <p class="mt-1">A partir de la RG 5616 es obligatorio incluir el campo <code>&lt;CondicionIVAReceptorId&gt;</code> indicando si el cliente es Responsable Inscripto (1), Exento (4), Consumidor Final (5), Monotributista (6), etc.</p>
                            </div>

                            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/40">
                                <h3 class="font-bold text-zinc-900 dark:text-zinc-100">3. Comprobantes de Ajuste (Notas de Crédito / Débito)</h3>
                                <p class="mt-1">Requieren obligatoriamente la inclusión del nodo <code>&lt;CbtesAsoc&gt;</code> referenciando el tipo, punto de venta y número de la factura original que se modifica o anula.</p>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}
        </main>
    </div>

    <!-- Modal Previsualización PDF con lista exacta de ítems -->
    {#if mostrarModalPdf && facturaResponse}
        <FacturaPdfPreview
            factura={{
                pto_vta: facturaResponse.pto_vta,
                cbte_tipo: facturaResponse.cbte_tipo,
                cbte_nro: facturaResponse.cbte_desde,
                cbte_fch: facturaResponse.cbte_fch,
                concepto: concepto(),
                doc_tipo: docTipo,
                doc_nro: docNro,
                condicion_iva_receptor_id: condicionIvaReceptorId,
                imp_total: totalFactura,
                imp_neto: totalNeto,
                imp_iva: totalIva,
                imp_op_ex: exento,
                imp_tot_conc: 0,
                imp_trib: tributosImporte,
                neto21: neto21,
                neto105: neto105,
                neto27: neto27,
                cae: facturaResponse.cae || '',
                cae_fch_vto: facturaResponse.cae_fch_vto || '',
                cuit_emisor: facturaResponse.cuit,
                items: carrito.map((i) => ({
                    descripcion: i.descripcion,
                    cantidad: i.cantidad,
                    precio_unitario: i.precio_unitario,
                    alicuota_iva: i.alicuota_iva,
                    subtotal: Number((i.cantidad * i.precio_unitario).toFixed(2)),
                })),
            }}
            onclose={() => (mostrarModalPdf = false)}
        />
    {/if}
</div>
