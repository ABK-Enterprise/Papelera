<script lang="ts">
    interface ItemProducto {
        descripcion: string;
        cantidad: number;
        precio_unitario: number;
        alicuota_iva: number;
        subtotal: number;
    }

    interface FacturaData {
        pto_vta: number;
        cbte_tipo: number;
        cbte_nro: number;
        cbte_fch: string;
        concepto: number;
        doc_tipo: number;
        doc_nro: number;
        condicion_iva_receptor_id?: number;
        imp_total: number;
        imp_neto: number;
        imp_iva: number;
        imp_op_ex: number;
        imp_tot_conc: number;
        imp_trib?: number;
        neto21?: number;
        neto105?: number;
        neto27?: number;
        cae?: string;
        cae_fch_vto?: string;
        cuit_emisor: number;
        items?: ItemProducto[];
    }

    let { factura, onclose }: { factura: FacturaData; onclose?: () => void } = $props();

    let letraCbte = $derived(() => {
        switch (factura.cbte_tipo) {
            case 1:
            case 2:
            case 3:
            case 4:
            case 51:
                return 'A';
            case 6:
            case 7:
            case 8:
            case 9:
                return 'B';
            case 11:
            case 12:
            case 13:
            case 15:
                return 'C';
            default:
                return 'A';
        }
    });

    let codigoCbteStr = $derived(() => String(factura.cbte_tipo).padStart(2, '0'));

    let nombreCbte = $derived(() => {
        switch (factura.cbte_tipo) {
            case 1:
                return 'FACTURA';
            case 2:
                return 'NOTA DE DÉBITO';
            case 3:
                return 'NOTA DE CRÉDITO';
            case 4:
                return 'RECIBO';
            case 6:
                return 'FACTURA';
            case 7:
                return 'NOTA DE DÉBITO';
            case 8:
                return 'NOTA DE CRÉDITO';
            case 9:
                return 'RECIBO';
            case 11:
                return 'FACTURA';
            case 12:
                return 'NOTA DE DÉBITO';
            case 13:
                return 'NOTA DE CRÉDITO';
            case 15:
                return 'RECIBO';
            default:
                return 'FACTURA';
        }
    });

    let condicionIvaReceptorStr = $derived(() => {
        switch (factura.condicion_iva_receptor_id) {
            case 1:
                return 'IVA Responsable Inscripto';
            case 4:
                return 'IVA Sujeto Exento';
            case 5:
                return 'Consumidor Final';
            case 6:
                return 'Responsable Monotributo';
            case 7:
                return 'Sujeto No Categorizado';
            case 8:
                return 'Proveedor del Exterior';
            case 9:
                return 'Cliente del Exterior';
            default:
                return 'IVA Responsable Inscripto';
        }
    });

    let tipoDocReceptorStr = $derived(() => {
        switch (factura.doc_tipo) {
            case 80:
                return 'CUIT';
            case 96:
                return 'DNI';
            case 86:
                return 'CUIL';
            case 99:
                return 'Sin Identificar';
            default:
                return 'Doc';
        }
    });

    function formatearFecha(fch: string) {
        if (!fch || fch.length !== 8) return fch || '-';
        const y = fch.slice(0, 4);
        const m = fch.slice(4, 6);
        const d = fch.slice(6, 8);
        return `${d}/${m}/${y}`;
    }

    function formatearNumeroComprobante(nro: number) {
        return String(nro).padStart(8, '0');
    }

    function formatearPuntoVenta(pto: number) {
        return String(pto).padStart(5, '0');
    }

    function formatearCuit(c: number) {
        const s = String(c).padStart(11, '0');
        return `${s.slice(0, 2)}-${s.slice(2, 10)}-${s.slice(10)}`;
    }

    function formatearMoneda(val: number) {
        return (val || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    let qrUrl = $derived(() => {
        if (!factura?.cae) return '';
        const payload = {
            ver: 1,
            fecha: `${factura.cbte_fch.slice(0, 4)}-${factura.cbte_fch.slice(4, 6)}-${factura.cbte_fch.slice(6, 8)}`,
            cuit: factura.cuit_emisor,
            ptoVta: factura.pto_vta,
            tipoCmp: factura.cbte_tipo,
            nroCmp: factura.cbte_nro,
            importe: factura.imp_total,
            moneda: 'PES',
            ctz: 1.0,
            tipoDocRec: factura.doc_tipo,
            nroDocRec: factura.doc_nro,
            tipoCodAut: 'E',
            codAut: Number(factura.cae) || 0,
        };
        const base64 = btoa(JSON.stringify(payload));
        const afipQrUrl = `https://www.afip.gob.ar/fe/qr/?p=${base64}`;
        return `https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=${encodeURIComponent(afipQrUrl)}`;
    });
</script>

<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
    <div class="flex max-h-[95vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-zinc-900">
        <!-- Header modal -->
        <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
            <div class="flex items-center gap-2">
                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                    Comprobante Electrónico ARCA
                </span>
                <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                    {nombreCbte()} {letraCbte()} - {formatearPuntoVenta(factura.pto_vta)}-{formatearNumeroComprobante(factura.cbte_nro)}
                </h3>
            </div>
            <button
                onclick={onclose}
                class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
            >
                ✕
            </button>
        </div>

        <!-- Cuerpo comprobante -->
        <div class="overflow-y-auto p-6">
            <div class="rounded-lg border-2 border-zinc-300 bg-white p-6 text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                <!-- Cabecera Comprobante -->
                <div class="relative grid grid-cols-2 gap-4 border-b-2 border-zinc-300 pb-4 dark:border-zinc-700">
                    <!-- Letra central -->
                    <div class="absolute left-1/2 top-0 -translate-x-1/2 flex flex-col items-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-lg border-2 border-zinc-800 bg-zinc-100 text-2xl font-black dark:border-zinc-300 dark:bg-zinc-900">
                            {letraCbte()}
                        </div>
                        <span class="mt-1 text-[11px] font-bold text-zinc-500 dark:text-zinc-400">
                            CÓD. {codigoCbteStr()}
                        </span>
                    </div>

                    <!-- Datos Emisor -->
                    <div class="pr-8">
                        <h2 class="text-xl font-black tracking-tight">PAPELERA CENTRAL</h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Razón Social de Prueba S.A.</p>
                        <p class="mt-2 text-xs">Domicilio Comercial: Av. Principal 1234, CABA</p>
                        <p class="text-xs">Condición frente al IVA: <strong>IVA Responsable Inscripto</strong></p>
                    </div>

                    <!-- Datos Comprobante -->
                    <div class="pl-8 text-right">
                        <h2 class="text-xl font-black">{nombreCbte()}</h2>
                        <p class="text-sm font-bold text-zinc-700 dark:text-zinc-300">
                            Punto de Venta: {formatearPuntoVenta(factura.pto_vta)} &nbsp; Comp. Nro: {formatearNumeroComprobante(factura.cbte_nro)}
                        </p>
                        <p class="mt-2 text-xs">Fecha de Emisión: <strong>{formatearFecha(factura.cbte_fch)}</strong></p>
                        <p class="text-xs">CUIT: <strong>{formatearCuit(factura.cuit_emisor)}</strong></p>
                        <p class="text-xs">Ingresos Brutos: <strong>{formatearCuit(factura.cuit_emisor)}</strong></p>
                        <p class="text-xs">Inicio de Actividades: <strong>01/01/2020</strong></p>
                    </div>
                </div>

                <!-- Datos Receptor -->
                <div class="my-4 grid grid-cols-2 gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-xs dark:border-zinc-800 dark:bg-zinc-900/60">
                    <div>
                        <p><strong>{tipoDocReceptorStr()}:</strong> {factura.doc_nro}</p>
                        <p class="mt-1"><strong>Condición frente al IVA:</strong> {condicionIvaReceptorStr()}</p>
                    </div>
                    <div>
                        <p><strong>Condición de Venta:</strong> Contado / Transferencia</p>
                        <p class="mt-1"><strong>Concepto:</strong> {factura.concepto === 1 ? 'Productos' : factura.concepto === 2 ? 'Servicios' : 'Mixto'}</p>
                    </div>
                </div>

                <!-- Tabla de Detalle de Productos -->
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr class="border-b border-zinc-300 bg-zinc-100 font-bold dark:border-zinc-700 dark:bg-zinc-900">
                            <th class="py-2 px-3">Descripción / Producto</th>
                            <th class="py-2 px-3 text-right">Cant.</th>
                            <th class="py-2 px-3 text-right">Precio Unit.</th>
                            <th class="py-2 px-3 text-right">IVA</th>
                            <th class="py-2 px-3 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        {#if factura.items && factura.items.length > 0}
                            {#each factura.items as item}
                                <tr>
                                    <td class="py-2.5 px-3 font-medium">{item.descripcion}</td>
                                    <td class="py-2.5 px-3 text-right font-mono">{item.cantidad}</td>
                                    <td class="py-2.5 px-3 text-right font-mono">${formatearMoneda(item.precio_unitario)}</td>
                                    <td class="py-2.5 px-3 text-right font-mono">{item.alicuota_iva}%</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold">${formatearMoneda(item.subtotal)}</td>
                                </tr>
                            {/each}
                        {:else}
                            <tr>
                                <td class="py-3 px-3 font-medium">Conceptos facturados en Homologación ARCA</td>
                                <td class="py-3 px-3 text-right">1</td>
                                <td class="py-3 px-3 text-right">${formatearMoneda(factura.imp_neto > 0 ? factura.imp_neto : factura.imp_total)}</td>
                                <td class="py-3 px-3 text-right">{factura.imp_iva > 0 ? `$${formatearMoneda(factura.imp_iva)}` : '0%'}</td>
                                <td class="py-3 px-3 text-right font-semibold">${formatearMoneda(factura.imp_total)}</td>
                            </tr>
                        {/if}
                    </tbody>
                </table>

                <!-- Totales y Resumen -->
                <div class="mt-6 flex justify-between border-t-2 border-zinc-300 pt-4 dark:border-zinc-700">
                    <div class="flex items-end gap-4">
                        {#if qrUrl()}
                            <div class="flex flex-col items-center rounded-lg border border-zinc-200 p-2 dark:border-zinc-800">
                                <img src={qrUrl()} alt="Código QR ARCA" class="h-24 w-24" />
                                <span class="mt-1 text-[10px] font-bold text-zinc-500">QR Oficial ARCA</span>
                            </div>
                        {/if}
                        <div class="text-xs text-zinc-600 dark:text-zinc-400">
                            <p class="font-bold text-zinc-900 dark:text-zinc-100">CAE N°: {factura.cae || 'N/A'}</p>
                            <p>Fecha Vto. CAE: {factura.cae_fch_vto ? formatearFecha(factura.cae_fch_vto) : 'N/A'}</p>
                            <p class="text-[10px] text-zinc-400">Comprobante Autorizado por ARCA (Testing)</p>
                        </div>
                    </div>

                    <div class="w-64 space-y-1 text-right text-xs">
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Importe Neto Gravado:</span>
                            <span class="font-medium">${formatearMoneda(factura.imp_neto)}</span>
                        </div>
                        {#if factura.imp_op_ex > 0}
                            <div class="flex justify-between">
                                <span class="text-zinc-500">Importe Exento:</span>
                                <span>${formatearMoneda(factura.imp_op_ex)}</span>
                            </div>
                        {/if}
                        {#if factura.imp_tot_conc > 0}
                            <div class="flex justify-between">
                                <span class="text-zinc-500">No Gravado:</span>
                                <span>${formatearMoneda(factura.imp_tot_conc)}</span>
                            </div>
                        {/if}
                        {#if factura.imp_iva > 0}
                            <div class="flex justify-between">
                                <span class="text-zinc-500">IVA Total:</span>
                                <span class="font-medium">${formatearMoneda(factura.imp_iva)}</span>
                            </div>
                        {/if}
                        {#if (factura.imp_trib || 0) > 0}
                            <div class="flex justify-between">
                                <span class="text-zinc-500">Tributos / Percep.:</span>
                                <span>${formatearMoneda(factura.imp_trib || 0)}</span>
                            </div>
                        {/if}
                        <div class="flex justify-between border-t border-zinc-300 pt-2 text-sm font-black dark:border-zinc-700">
                            <span>IMPORTE TOTAL:</span>
                            <span class="text-emerald-600 dark:text-emerald-400">${formatearMoneda(factura.imp_total)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer modal -->
        <div class="flex justify-end gap-3 border-t border-zinc-200 bg-zinc-50 px-6 py-3 dark:border-zinc-800 dark:bg-zinc-950">
            <button
                onclick={onclose}
                class="rounded-lg bg-zinc-800 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
            >
                Cerrar
            </button>
        </div>
    </div>
</div>
