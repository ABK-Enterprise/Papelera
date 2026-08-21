<?php

namespace App\Http\Controllers\Playground;

use App\Http\Controllers\Controller;
use App\Playground\Arca\WsaaService;
use App\Playground\Arca\WsfeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArcaPlaygroundController extends Controller
{
    public function __construct(
        protected WsaaService $wsaaService,
        protected WsfeService $wsfeService
    ) {}

    /**
     * Muestra la vista principal del Playground de Facturación ARCA.
     */
    public function index(): Response
    {
        $cuitDefault = 20463191280;
        $serviceDefault = 'wsfe';

        $cachedTicket = $this->wsaaService->leerTicketDesdeCache($cuitDefault, $serviceDefault);
        if ($cachedTicket !== null) {
            $cachedTicket['is_valid'] = $this->wsaaService->estaTicketVigente($cachedTicket);
        }

        $comprobantes = [
            ['id' => 1, 'nombre' => 'Factura A (1)', 'letra' => 'A', 'discrimina_iva' => true, 'requiere_cuit' => true],
            ['id' => 2, 'nombre' => 'Nota de Débito A (2)', 'letra' => 'A', 'discrimina_iva' => true, 'requiere_cuit' => true, 'es_ajuste' => true],
            ['id' => 3, 'nombre' => 'Nota de Crédito A (3)', 'letra' => 'A', 'discrimina_iva' => true, 'requiere_cuit' => true, 'es_ajuste' => true],
            ['id' => 4, 'nombre' => 'Recibo A (4)', 'letra' => 'A', 'discrimina_iva' => true, 'requiere_cuit' => true],
            ['id' => 6, 'nombre' => 'Factura B (6)', 'letra' => 'B', 'discrimina_iva' => true, 'requiere_cuit' => false],
            ['id' => 7, 'nombre' => 'Nota de Débito B (7)', 'letra' => 'B', 'discrimina_iva' => true, 'requiere_cuit' => false, 'es_ajuste' => true],
            ['id' => 8, 'nombre' => 'Nota de Crédito B (8)', 'letra' => 'B', 'discrimina_iva' => true, 'requiere_cuit' => false, 'es_ajuste' => true],
            ['id' => 9, 'nombre' => 'Recibo B (9)', 'letra' => 'B', 'discrimina_iva' => true, 'requiere_cuit' => false],
            ['id' => 11, 'nombre' => 'Factura C (11)', 'letra' => 'C', 'discrimina_iva' => false, 'requiere_cuit' => false],
            ['id' => 12, 'nombre' => 'Nota de Débito C (12)', 'letra' => 'C', 'discrimina_iva' => false, 'requiere_cuit' => false, 'es_ajuste' => true],
            ['id' => 13, 'nombre' => 'Nota de Crédito C (13)', 'letra' => 'C', 'discrimina_iva' => false, 'requiere_cuit' => false, 'es_ajuste' => true],
            ['id' => 15, 'nombre' => 'Recibo C (15)', 'letra' => 'C', 'discrimina_iva' => false, 'requiere_cuit' => false],
            ['id' => 51, 'nombre' => 'Factura M (51)', 'letra' => 'M', 'discrimina_iva' => true, 'requiere_cuit' => true],
            ['id' => 52, 'nombre' => 'Nota de Débito M (52)', 'letra' => 'M', 'discrimina_iva' => true, 'requiere_cuit' => true, 'es_ajuste' => true],
            ['id' => 53, 'nombre' => 'Nota de Crédito M (53)', 'letra' => 'M', 'discrimina_iva' => true, 'requiere_cuit' => true, 'es_ajuste' => true],
        ];

        $conceptos = [
            ['id' => 1, 'nombre' => '1 - Productos / Bienes', 'requiere_fechas_servicio' => false],
            ['id' => 2, 'nombre' => '2 - Servicios', 'requiere_fechas_servicio' => true],
            ['id' => 3, 'nombre' => '3 - Productos y Servicios (Mixto)', 'requiere_fechas_servicio' => true],
        ];

        $tiposDoc = [
            ['id' => 80, 'nombre' => '80 - CUIT (11 dígitos)'],
            ['id' => 96, 'nombre' => '96 - DNI (7 u 8 dígitos)'],
            ['id' => 99, 'nombre' => '99 - Consumidor Final (Doc. Otro)'],
            ['id' => 86, 'nombre' => '86 - CUIL'],
            ['id' => 91, 'nombre' => '91 - CI Extranjera'],
            ['id' => 94, 'nombre' => '94 - Pasaporte'],
            ['id' => 87, 'nombre' => '87 - CDI'],
        ];

        $condicionesIvaReceptor = [
            ['id' => 1, 'nombre' => '1 - IVA Responsable Inscripto', 'tipico' => 'Factura A'],
            ['id' => 4, 'nombre' => '4 - IVA Sujeto Exento', 'tipico' => 'Factura B'],
            ['id' => 5, 'nombre' => '5 - Consumidor Final', 'tipico' => 'Factura B / C'],
            ['id' => 6, 'nombre' => '6 - Responsable Monotributo', 'tipico' => 'Factura A / B'],
            ['id' => 7, 'nombre' => '7 - Sujeto No Categorizado', 'tipico' => 'Factura B'],
            ['id' => 8, 'nombre' => '8 - Proveedor del Exterior', 'tipico' => 'Factura B'],
            ['id' => 9, 'nombre' => '9 - Cliente del Exterior', 'tipico' => 'Factura E'],
            ['id' => 10, 'nombre' => '10 - IVA Liberado – Ley Nº 19.640', 'tipico' => 'Tierra del Fuego'],
            ['id' => 11, 'nombre' => '11 - IVA Resp. Inscripto – Agente de Percepción', 'tipico' => 'Factura A especial'],
            ['id' => 12, 'nombre' => '12 - Pequeño Contribuyente Eventual', 'tipico' => 'Factura B'],
            ['id' => 13, 'nombre' => '13 - Monotributista Social', 'tipico' => 'Factura B'],
            ['id' => 14, 'nombre' => '14 - Pequeño Contribuyente Eventual Social', 'tipico' => 'Factura B'],
            ['id' => 15, 'nombre' => '15 - IVA No Alcanzado', 'tipico' => 'Factura B / C'],
        ];

        $alicuotasIva = [
            ['id' => 3, 'porcentaje' => 0.0, 'nombre' => '0% (Exento / Tasa Cero)'],
            ['id' => 4, 'porcentaje' => 10.5, 'nombre' => '10.5% (Bienes de capital, carnes, etc.)'],
            ['id' => 5, 'porcentaje' => 21.0, 'nombre' => '21.0% (Tasa general estándar)'],
            ['id' => 6, 'porcentaje' => 27.0, 'nombre' => '27.0% (Servicios básicos, gas, luz, telecom.)'],
            ['id' => 8, 'porcentaje' => 5.0, 'nombre' => '5.0% (Tasa reducida especial)'],
            ['id' => 9, 'porcentaje' => 2.5, 'nombre' => '2.5% (Tasa ultra reducida especial)'],
        ];

        return Inertia::render('playground/Arca', [
            'ticket' => $cachedTicket,
            'cuitDefault' => $cuitDefault,
            'serviceDefault' => $serviceDefault,
            'wsaaUrl' => WsaaService::TESTING_WSAA_URL,
            'wsfeUrl' => WsfeService::TESTING_WSFE_URL,
            'catalogos' => [
                'comprobantes' => $comprobantes,
                'conceptos' => $conceptos,
                'tiposDoc' => $tiposDoc,
                'condicionesIvaReceptor' => $condicionesIvaReceptor,
                'alicuotasIva' => $alicuotasIva,
            ],
        ]);
    }

    /**
     * Solicita o refresca el Ticket de Acceso (WSAA).
     */
    public function solicitarTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cuit' => ['nullable', 'numeric'],
            'service' => ['nullable', 'string'],
            'force_renew' => ['nullable', 'boolean'],
        ]);

        $cuit = (int) ($validated['cuit'] ?? 20463191280);
        $service = (string) ($validated['service'] ?? 'wsfe');
        $forceRenew = (bool) ($validated['force_renew'] ?? false);

        try {
            $ticket = $this->wsaaService->obtenerTicketVigente($cuit, $service, $forceRenew);

            return response()->json([
                'success' => true,
                'ticket' => $ticket,
                'message' => 'Ticket de Acceso obtenido exitosamente',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Consulta el último número de comprobante autorizado en ARCA.
     */
    public function consultarUltimoComprobante(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pto_vta' => ['required', 'integer', 'min:1', 'max:99999'],
            'cbte_tipo' => ['required', 'integer'],
            'cuit' => ['nullable', 'numeric'],
        ]);

        $ptoVta = (int) $validated['pto_vta'];
        $cbteTipo = (int) $validated['cbte_tipo'];

        try {
            $ultimoNro = $this->wsfeService->consultarUltimoComprobante($ptoVta, $cbteTipo);

            return response()->json([
                'success' => true,
                'pto_vta' => $ptoVta,
                'cbte_tipo' => $cbteTipo,
                'ultimo_nro' => $ultimoNro,
                'siguiente_nro' => $ultimoNro + 1,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Consulta los puntos de venta habilitados para el CUIT.
     */
    public function consultarPuntosVenta(Request $request): JsonResponse
    {
        try {
            $ptos = $this->wsfeService->consultarPuntosVenta();

            return response()->json([
                'success' => true,
                'puntos_venta' => $ptos,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Emite una factura electrónica en ARCA (FECAESolicitar).
     */
    public function emitirFactura(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pto_vta' => ['required', 'integer'],
            'cbte_tipo' => ['required', 'integer'],
            'concepto' => ['required', 'integer'],
            'doc_tipo' => ['required', 'integer'],
            'doc_nro' => ['required', 'numeric'],
            'cbte_desde' => ['required', 'integer'],
            'cbte_hasta' => ['required', 'integer'],
            'cbte_fch' => ['required', 'string'],
            'imp_total' => ['required', 'numeric'],
            'imp_tot_conc' => ['required', 'numeric'],
            'imp_neto' => ['required', 'numeric'],
            'imp_op_ex' => ['required', 'numeric'],
            'imp_trib' => ['required', 'numeric'],
            'imp_iva' => ['required', 'numeric'],
            'mon_id' => ['required', 'string'],
            'mon_cotiz' => ['required', 'numeric'],
            'condicion_iva_receptor_id' => ['nullable', 'integer'],
            'fch_serv_desde' => ['nullable', 'string'],
            'fch_serv_hasta' => ['nullable', 'string'],
            'fch_vto_pago' => ['nullable', 'string'],
            'iva' => ['nullable', 'array'],
            'tributos' => ['nullable', 'array'],
            'cbtes_asoc' => ['nullable', 'array'],
        ]);

        try {
            $response = $this->wsfeService->emitirFactura($validated);

            return response()->json([
                'success' => true,
                'factura' => $response,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
