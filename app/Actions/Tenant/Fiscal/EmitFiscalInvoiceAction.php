<?php

namespace App\Actions\Tenant\Fiscal;

use App\Exceptions\ArcaException;
use App\Models\CustomerOrder;
use App\Models\FiscalInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Arca\ArcaErrorTranslator;
use App\Services\Arca\ArcaWsfeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmitFiscalInvoiceAction
{
    public function __construct(
        protected ArcaWsfeService $wsfeService,
    ) {}

    /**
     * Execute electronic invoice emission for a customer order.
     *
     * @param  array<string, mixed>  $overrides
     *
     * @throws ArcaException
     */
    public function execute(CustomerOrder $order, User $user, array $overrides = []): FiscalInvoice
    {
        if ((float) $order->total_amount <= 0.00) {
            throw new ArcaException('WSFEv1 (ARCA) requiere comprobantes con importe total mayor a cero ($0.00). Los pedidos bonificados al 100% sin cargo deben canalizarse mediante remito interno de traslado.');
        }

        try {
            return DB::transaction(function () use ($order, $user, $overrides) {
                /** @var CustomerOrder|null $lockedOrder */
                $lockedOrder = CustomerOrder::where('id', $order->id)->lockForUpdate()->first();

                if (! $lockedOrder) {
                    throw new ArcaException('Pedido no encontrado.');
                }

                if ((float) $lockedOrder->total_amount <= 0.00) {
                    throw new ArcaException('WSFEv1 (ARCA) requiere comprobantes con importe total mayor a cero ($0.00). Los pedidos bonificados al 100% sin cargo deben canalizarse mediante remito interno de traslado.');
                }

                // FSM Validation
                if ($lockedOrder->isFiscalInvoiced()) {
                    throw new ArcaException('El pedido ya se encuentra facturado.');
                }

                if (! $lockedOrder->canBeFiscalInvoiced()) {
                    throw new ArcaException('El pedido no se encuentra en un estado apto para facturación.');
                }

                // Tenant & Issuer Configuration (Strictly derived from tenant context)
                /** @var Tenant|null $tenant */
                $tenant = $lockedOrder->tenant ?? Tenant::find($lockedOrder->tenant_id);
                $branchPtoVta = $lockedOrder->warehouse?->branch?->point_of_sale;
                $ptoVta = (int) ($branchPtoVta ?: config('arca.testing.punto_venta', 1));
                $cuit = (string) ($tenant?->cuit_tax_id ?: config('arca.testing.cuit'));
                $cleanCuit = (float) preg_replace('/\D/', '', $cuit);

                // Customer & Receptor Identification
                $customer = $lockedOrder->customer;

                // Determine Receptor Condición IVA ID:
                // 1 = Resp Inscripto, 4 = Exento, 5 = Consumidor Final, 6 = Monotributo
                $receptorCondicionIvaId = isset($overrides['receptor_condicion_iva_id'])
                    ? (int) $overrides['receptor_condicion_iva_id']
                    : match ($customer?->tax_condition) {
                        'responsable_inscripto' => 1,
                        'exento' => 4,
                        'monotributo' => 6,
                        default => 5,
                    };

                // Determine DocTipo & DocNro
                if (isset($overrides['doc_tipo']) && isset($overrides['doc_nro'])) {
                    $docTipo = (int) $overrides['doc_tipo'];
                    $docNro = (string) $overrides['doc_nro'];
                } else {
                    $cleanTaxId = preg_replace('/\D/', '', (string) ($customer?->tax_id ?? ''));
                    if (strlen($cleanTaxId) === 11) {
                        $docTipo = 80; // CUIT
                        $docNro = $cleanTaxId;
                    } elseif (strlen($cleanTaxId) >= 7 && strlen($cleanTaxId) <= 8) {
                        $docTipo = 96; // DNI
                        $docNro = $cleanTaxId;
                    } else {
                        $docTipo = 99; // Consumidor Final sin identificar
                        $docNro = '0';
                    }
                }

                // Legal threshold validation for anonymous final consumers (DocTipo 99)
                $consumerLimit = (float) (config('arca.consumer_final_thresholds.cash') ?? config('arca.thresholds.anonymous_consumer_cash_limit', 10000000.00));
                $orderTotal = (float) $lockedOrder->total_amount;

                if ($docTipo === 99 && $orderTotal > $consumerLimit) {
                    throw new ArcaException(
                        'Para ventas a Consumidor Final superiores al límite legal, es obligatorio ingresar el DNI o CUIT del cliente.',
                        '10016'
                    );
                }

                $receptorName = (string) ($overrides['receptor_name'] ?? $customer?->name ?? $lockedOrder->customer_name ?? 'Consumidor Final');
                $receptorAddress = $overrides['receptor_address'] ?? $customer?->address ?? $lockedOrder->delivery_address;

                // Determine CbteTipo:
                // Emisor Monotributista => Factura C (11)
                // Emisor Resp Inscripto:
                //   Receptor Resp Inscripto (1) o Monotributo (6) => Factura A (1)
                //   Receptor Consumidor Final (5) o Exento (4) => Factura B (6)
                if (isset($overrides['cbte_tipo'])) {
                    $cbteTipo = (int) $overrides['cbte_tipo'];
                } else {
                    $emisorTaxCondition = $tenant?->tax_condition ?? 'responsable_inscripto';
                    if (in_array($emisorTaxCondition, ['monotributo', 'responsable_monotributo'], true)) {
                        $cbteTipo = FiscalInvoice::CBTE_TIPO_FACTURA_C;
                    } else {
                        $cbteTipo = in_array($receptorCondicionIvaId, [1, 6], true)
                            ? FiscalInvoice::CBTE_TIPO_FACTURA_A
                            : FiscalInvoice::CBTE_TIPO_FACTURA_B;
                    }
                }

                $concepto = (int) ($overrides['concepto'] ?? 1); // 1 = Productos

                // Desglose de IVA
                $importeTotal = round($orderTotal, 2);

                if ($cbteTipo === FiscalInvoice::CBTE_TIPO_FACTURA_C) {
                    $importeNeto = $importeTotal;
                    $importeIva = 0.00;
                    $ivaArray = [];
                } else {
                    // Facturas A y B: Desglose hacia atrás para cada alícuota presente en los ítems
                    $lockedOrder->loadMissing(['items.product']);
                    $items = $lockedOrder->items;

                    $afipIvaIds = [
                        '0.00' => 3,
                        '0' => 3,
                        '10.50' => 4,
                        '10.5' => 4,
                        '21.00' => 5,
                        '21' => 5,
                        '27.00' => 6,
                        '27' => 6,
                        '5.00' => 8,
                        '5' => 8,
                        '2.50' => 9,
                        '2.5' => 9,
                    ];

                    if ($items->isEmpty()) {
                        $importeNeto = round($importeTotal / 1.21, 2);
                        $importeIva = round($importeTotal - $importeNeto, 2);
                        $ivaArray = [
                            [
                                'Id' => 5,
                                'BaseImp' => $importeNeto,
                                'Importe' => $importeIva,
                            ],
                        ];
                    } else {
                        $buckets = [];
                        $rawItemsTotal = 0.0;
                        foreach ($items as $item) {
                            $rate = (string) ($item->product?->iva_rate ?? '21.00');
                            $itemSubtotal = (float) $item->subtotal;
                            $rawItemsTotal += $itemSubtotal;
                            $buckets[$rate] = ($buckets[$rate] ?? 0.0) + $itemSubtotal;
                        }

                        $ivaArray = [];
                        $totalCalculatedBase = 0.0;
                        $totalCalculatedIva = 0.0;
                        $scale = ($rawItemsTotal > 0) ? ($importeTotal / $rawItemsTotal) : 1.0;

                        foreach ($buckets as $rateStr => $bucketTotal) {
                            $scaledTotal = round($bucketTotal * $scale, 2);
                            $rateVal = (float) $rateStr;
                            $base = round($scaledTotal / (1 + ($rateVal / 100)), 2);
                            $iva = round($scaledTotal - $base, 2);

                            $afipId = $afipIvaIds[$rateStr] ?? ($rateVal == 10.5 ? 4 : ($rateVal == 27 ? 6 : ($rateVal == 0 ? 3 : 5)));

                            $ivaArray[] = [
                                'Id' => $afipId,
                                'BaseImp' => $base,
                                'Importe' => $iva,
                            ];

                            $totalCalculatedBase += $base;
                            $totalCalculatedIva += $iva;
                        }

                        // Adjust minor rounding divergence to ensure ImpNeto + ImpIVA == ImpTotal
                        $diff = round($importeTotal - ($totalCalculatedBase + $totalCalculatedIva), 2);
                        if ($diff != 0.0 && count($ivaArray) > 0) {
                            $ivaArray[0]['Importe'] = round($ivaArray[0]['Importe'] + $diff, 2);
                            $totalCalculatedIva = round($totalCalculatedIva + $diff, 2);
                        }

                        $importeNeto = round($totalCalculatedBase, 2);
                        $importeIva = round($totalCalculatedIva, 2);
                    }
                }

                // Obtener el siguiente número correlativo de comprobante
                $nextNro = $this->wsfeService->getUltimoComprobante($ptoVta, $cbteTipo, $cuit) + 1;

                $issueDate = Carbon::now();
                $cbteFch = $issueDate->format('Ymd');

                // Armado del payload para FECAESolicitar
                $feCabReq = [
                    'CantReg' => 1,
                    'PtoVta' => $ptoVta,
                    'CbteTipo' => $cbteTipo,
                ];

                $fecaedet = [
                    'Concepto' => $concepto,
                    'DocTipo' => $docTipo,
                    'DocNro' => (float) preg_replace('/\D/', '', $docNro),
                    'CbteDesde' => $nextNro,
                    'CbteHasta' => $nextNro,
                    'CbteFch' => $cbteFch,
                    'ImpTotal' => $importeTotal,
                    'ImpTotConc' => 0.00,
                    'ImpNeto' => $importeNeto,
                    'ImpOpEx' => 0.00,
                    'ImpIVA' => $importeIva,
                    'ImpTrib' => 0.00,
                    'MonId' => 'PES',
                    'MonCotiz' => 1,
                    'CondicionIVAReceptorId' => $receptorCondicionIvaId,
                ];

                if (! empty($ivaArray)) {
                    $fecaedet['Iva'] = [
                        'AlicIva' => $ivaArray,
                    ];
                }

                $payload = [
                    'FeCabReq' => $feCabReq,
                    'FeDetReq' => [
                        'FECAEDetRequest' => $fecaedet,
                    ],
                ];

                // Llamada a solicitarCae con Timeout Handling (Capa 3 de resiliencia)
                try {
                    $caeResult = $this->wsfeService->solicitarCae($payload, $cuit);
                } catch (ArcaException $e) {
                    $isTimeoutOrSoapFault = $e->getArcaCode() === 'SoapFault'
                        || $e->getArcaCode() === 'timeout'
                        || str_contains(strtolower($e->getMessage()), 'no responde')
                        || str_contains(strtolower($e->getMessage()), 'timeout');

                    if ($isTimeoutOrSoapFault) {
                        $consulted = null;
                        try {
                            $consulted = $this->wsfeService->consultarComprobante($ptoVta, $cbteTipo, $nextNro, $cuit);
                        } catch (\Throwable) {
                            $consulted = null;
                        }

                        if ($consulted && ! empty($consulted['cae']) && ($consulted['resultado'] ?? 'A') === 'A') {
                            $caeResult = [
                                'cae' => $consulted['cae'],
                                'cae_due_date' => $consulted['cae_due_date'],
                                'cbte_nro' => $nextNro,
                                'resultado' => 'A',
                                'raw_request' => $payload,
                                'raw_response' => $consulted['raw_response'] ?? [],
                            ];
                        } else {
                            throw $e;
                        }
                    } else {
                        throw $e;
                    }
                }

                // Generación de URL de código QR oficial AFIP (RG 4892/2020)
                $cleanDocNro = (float) preg_replace('/\D/', '', $docNro);
                $qrPayload = [
                    'ver' => 1,
                    'fecha' => $issueDate->format('Y-m-d'),
                    'cuit' => (int) $cleanCuit,
                    'ptoVta' => (int) $ptoVta,
                    'tipoCmp' => (int) $cbteTipo,
                    'nroCmp' => (int) $nextNro,
                    'importe' => (float) $importeTotal,
                    'moneda' => 'PES',
                    'ctz' => 1,
                    'tipoDocRec' => (int) $docTipo,
                    'nroDocRec' => (int) $cleanDocNro,
                    'tipoCodAut' => 'E',
                    'codAut' => is_numeric($caeResult['cae']) ? (int) $caeResult['cae'] : $caeResult['cae'],
                ];

                $qrBaseUrl = config('arca.qr.base_url', 'https://www.afip.gob.ar/fe/qr/?p=');
                $qrData = $qrBaseUrl.base64_encode((string) json_encode($qrPayload));

                // Persistencia del registro en FiscalInvoice
                $fiscalInvoice = FiscalInvoice::create([
                    'tenant_id' => $lockedOrder->tenant_id,
                    'customer_order_id' => $lockedOrder->id,
                    'created_by_user_id' => $user->id,
                    'cbte_tipo' => $cbteTipo,
                    'punto_venta' => $ptoVta,
                    'numero_comprobante' => $nextNro,
                    'cae' => (string) $caeResult['cae'],
                    'cae_due_date' => $caeResult['cae_due_date'],
                    'issue_date' => $issueDate->toDateString(),
                    'doc_tipo' => $docTipo,
                    'doc_nro' => (string) $docNro,
                    'receptor_condicion_iva_id' => $receptorCondicionIvaId,
                    'receptor_name' => $receptorName,
                    'receptor_address' => $receptorAddress,
                    'concepto' => $concepto,
                    'importe_total' => $importeTotal,
                    'importe_neto' => $importeNeto,
                    'importe_iva' => $importeIva,
                    'importe_exento' => 0.00,
                    'importe_tributos' => 0.00,
                    'qr_data' => $qrData,
                    'arca_request_payload' => $caeResult['raw_request'] ?? $payload,
                    'arca_response_payload' => $caeResult['raw_response'] ?? null,
                    'status' => FiscalInvoice::STATUS_APROBADO,
                    'notes' => $overrides['notes'] ?? null,
                ]);

                // Actualización de estado en CustomerOrder
                $lockedOrder->update([
                    'fiscal_status' => 'facturado',
                    'fiscal_error_message' => null,
                ]);

                return $fiscalInvoice;
            });
        } catch (ArcaException $e) {
            if ($order->canBeFiscalInvoiced()) {
                $order->update([
                    'fiscal_status' => 'error_fiscal',
                    'fiscal_error_message' => $e->getMessage(),
                ]);
            }
            throw $e;
        } catch (\Throwable $e) {
            $translatedMsg = ArcaErrorTranslator::translate('SoapFault', $e->getMessage());
            if ($order->canBeFiscalInvoiced()) {
                $order->update([
                    'fiscal_status' => 'error_fiscal',
                    'fiscal_error_message' => $translatedMsg,
                ]);
            }
            throw new ArcaException($translatedMsg, 'SoapFault', (int) $e->getCode(), $e);
        }
    }
}
