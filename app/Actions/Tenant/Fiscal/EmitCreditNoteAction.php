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

class EmitCreditNoteAction
{
    public function __construct(
        protected ArcaWsfeService $wsfeService,
    ) {}

    /**
     * Execute electronic credit note emission for a customer order.
     *
     * @param  array<string, mixed>  $overrides
     *
     * @throws ArcaException
     */
    public function execute(
        CustomerOrder $order,
        User $user,
        ?FiscalInvoice $originalInvoice = null,
        array $overrides = []
    ): FiscalInvoice {
        return DB::transaction(function () use ($order, $user, $originalInvoice, $overrides) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $order->id)
                ->where('tenant_id', $order->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var FiscalInvoice|null $invoice */
            $invoice = $originalInvoice
                ? FiscalInvoice::where('id', $originalInvoice->id)
                    ->where('tenant_id', $lockedOrder->tenant_id)
                    ->lockForUpdate()
                    ->first()
                : $lockedOrder->activeFiscalInvoice()->lockForUpdate()->first();

            if (! $invoice || $invoice->status !== FiscalInvoice::STATUS_APROBADO) {
                throw new ArcaException(
                    'No se encontró una factura fiscal aprobada asociada a este pedido para emitir la Nota de Crédito.'
                );
            }

            $existingCreditNote = FiscalInvoice::where('tenant_id', $lockedOrder->tenant_id)
                ->where('associated_fiscal_invoice_id', $invoice->id)
                ->where('status', FiscalInvoice::STATUS_APROBADO)
                ->lockForUpdate()
                ->exists();

            if ($existingCreditNote) {
                throw new ArcaException(
                    'La factura original ya posee una Nota de Crédito fiscal aprobada en ARCA.'
                );
            }

            $ncCbteTipo = match ($invoice->cbte_tipo) {
                FiscalInvoice::CBTE_TIPO_FACTURA_A => FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_A,
                FiscalInvoice::CBTE_TIPO_FACTURA_B => FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_B,
                FiscalInvoice::CBTE_TIPO_FACTURA_C => FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_C,
                default => throw new ArcaException(
                    "Tipo de comprobante original ({$invoice->cbte_tipo}) no admite emisión de Nota de Crédito."
                ),
            };

            $ptoVta = (int) ($overrides['punto_venta'] ?? $invoice->punto_venta);

            /** @var Tenant|null $tenant */
            $tenant = $order->tenant ?? Tenant::find($order->tenant_id);
            $cuit = (string) ($overrides['cuit'] ?? $tenant?->cuit_tax_id ?? config('arca.testing.cuit'));
            $cleanCuit = (float) preg_replace('/\D/', '', $cuit);

            $nextNro = (int) ($overrides['numero_comprobante'] ?? ($this->wsfeService->getUltimoComprobante($ptoVta, $ncCbteTipo, $cuit) + 1));

            $cbtesAsoc = [
                [
                    'Tipo' => (int) $invoice->cbte_tipo,
                    'PtoVta' => (int) $invoice->punto_venta,
                    'Nro' => (int) $invoice->numero_comprobante,
                    'Cuit' => $cleanCuit,
                    'CbteFch' => Carbon::parse($invoice->issue_date)->format('Ymd'),
                ],
            ];

            $importeTotal = isset($overrides['importe_total']) ? (float) $overrides['importe_total'] : (float) $invoice->importe_total;
            $importeNeto = isset($overrides['importe_neto']) ? (float) $overrides['importe_neto'] : (float) $invoice->importe_neto;
            $importeIva = isset($overrides['importe_iva']) ? (float) $overrides['importe_iva'] : (float) $invoice->importe_iva;
            $importeExento = isset($overrides['importe_exento']) ? (float) $overrides['importe_exento'] : (float) $invoice->importe_exento;
            $importeTributos = isset($overrides['importe_tributos']) ? (float) $overrides['importe_tributos'] : (float) $invoice->importe_tributos;

            $ivaArray = [];
            if ($ncCbteTipo !== FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_C && $importeIva > 0) {
                $savedIva = $invoice->arca_request_payload['FeCAEReq']['FeDetReq']['FECAEDetRequest']['Iva']['AlicIva']
                    ?? $invoice->arca_request_payload['FeDetReq']['FECAEDetRequest']['Iva']['AlicIva']
                    ?? $invoice->arca_request_payload['Iva']['AlicIva']
                    ?? null;

                if (! empty($savedIva) && is_array($savedIva)) {
                    $ivaArray = isset($savedIva['Id']) ? [$savedIva] : $savedIva;
                } else {
                    $order->loadMissing(['items.product']);
                    $items = $order->items;

                    $afipIvaIds = [
                        '0.00' => 3, '0' => 3,
                        '10.50' => 4, '10.5' => 4,
                        '21.00' => 5, '21' => 5,
                        '27.00' => 6, '27' => 6,
                        '5.00' => 8, '5' => 8,
                        '2.50' => 9, '2.5' => 9,
                    ];

                    if ($items->isEmpty()) {
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

                        $diff = round($importeTotal - ($totalCalculatedBase + $totalCalculatedIva), 2);
                        if ($diff != 0.0 && count($ivaArray) > 0) {
                            $ivaArray[0]['Importe'] = round($ivaArray[0]['Importe'] + $diff, 2);
                        }
                    }
                }
            }

            $issueDate = Carbon::now();
            $cbteFch = $issueDate->format('Ymd');

            $feCabReq = [
                'CantReg' => 1,
                'PtoVta' => $ptoVta,
                'CbteTipo' => $ncCbteTipo,
            ];

            $docTipo = (int) $invoice->doc_tipo;
            $docNro = (string) $invoice->doc_nro;
            $cleanDocNro = (float) preg_replace('/\D/', '', $docNro);

            $concepto = (int) ($overrides['concepto'] ?? $invoice->concepto ?? 1);

            $fecaedet = [
                'Concepto' => $concepto,
                'DocTipo' => $docTipo,
                'DocNro' => $cleanDocNro,
                'CbteDesde' => $nextNro,
                'CbteHasta' => $nextNro,
                'CbteFch' => $cbteFch,
                'ImpTotal' => $importeTotal,
                'ImpTotConc' => 0.00,
                'ImpNeto' => $importeNeto,
                'ImpOpEx' => $importeExento,
                'ImpIVA' => $importeIva,
                'ImpTrib' => $importeTributos,
                'MonId' => 'PES',
                'MonCotiz' => 1,
                'CondicionIVAReceptorId' => (int) ($invoice->receptor_condicion_iva_id ?: 5),
                'CbtesAsoc' => [
                    'CbteAsoc' => $cbtesAsoc,
                ],
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

            try {
                $caeData = $this->wsfeService->solicitarCae($payload, $cuit);
            } catch (ArcaException $e) {
                $isTimeoutOrSoapFault = $e->getArcaCode() === 'SoapFault'
                    || $e->getArcaCode() === 'timeout'
                    || str_contains(strtolower($e->getMessage()), 'no responde')
                    || str_contains(strtolower($e->getMessage()), 'timeout');

                if ($isTimeoutOrSoapFault) {
                    $consulted = null;
                    try {
                        $consulted = $this->wsfeService->consultarComprobante($ptoVta, $ncCbteTipo, $nextNro, $cuit);
                    } catch (\Throwable) {
                        $consulted = null;
                    }

                    if ($consulted && ! empty($consulted['cae']) && ($consulted['resultado'] ?? 'A') === 'A') {
                        $caeData = [
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
            } catch (\Throwable $e) {
                $translatedMsg = ArcaErrorTranslator::translate('SoapFault', $e->getMessage());
                throw new ArcaException($translatedMsg, 'SoapFault', (int) $e->getCode(), $e);
            }

            // Official AFIP QR code generation (RG 4892/2020)
            $qrPayload = [
                'ver' => 1,
                'fecha' => $issueDate->format('Y-m-d'),
                'cuit' => (int) $cleanCuit,
                'ptoVta' => (int) $ptoVta,
                'tipoCmp' => (int) $ncCbteTipo,
                'nroCmp' => (int) $nextNro,
                'importe' => (float) $importeTotal,
                'moneda' => 'PES',
                'ctz' => 1,
                'tipoDocRec' => (int) $docTipo,
                'nroDocRec' => (int) $cleanDocNro,
                'tipoCodAut' => 'E',
                'codAut' => is_numeric($caeData['cae']) ? (int) $caeData['cae'] : $caeData['cae'],
            ];

            $qrBaseUrl = config('arca.qr.base_url', 'https://www.afip.gob.ar/fe/qr/?p=');
            $qrUrl = $qrBaseUrl . base64_encode((string) json_encode($qrPayload));

            $creditNote = FiscalInvoice::create([
                'tenant_id' => $order->tenant_id,
                'customer_order_id' => $order->id,
                'created_by_user_id' => $user->id,
                'cbte_tipo' => $ncCbteTipo,
                'punto_venta' => $ptoVta,
                'numero_comprobante' => $nextNro,
                'cae' => (string) $caeData['cae'],
                'cae_due_date' => $caeData['cae_due_date'],
                'issue_date' => Carbon::now()->toDateString(),
                'doc_tipo' => $invoice->doc_tipo,
                'doc_nro' => (string) $invoice->doc_nro,
                'receptor_condicion_iva_id' => $invoice->receptor_condicion_iva_id,
                'receptor_name' => $invoice->receptor_name,
                'receptor_address' => $invoice->receptor_address,
                'concepto' => $invoice->concepto,
                'importe_total' => $importeTotal,
                'importe_neto' => $importeNeto,
                'importe_iva' => $importeIva,
                'importe_exento' => $importeExento,
                'importe_tributos' => $importeTributos,
                'associated_fiscal_invoice_id' => $invoice->id,
                'qr_data' => $qrUrl,
                'arca_request_payload' => $caeData['raw_request'] ?? $payload,
                'arca_response_payload' => $caeData['raw_response'] ?? null,
                'status' => FiscalInvoice::STATUS_APROBADO,
                'notes' => $overrides['notes'] ?? "Nota de Crédito emitida por cancelación de pedido {$order->order_number}",
            ]);

            $invoice->update(['status' => FiscalInvoice::STATUS_ANULADO_POR_NC]);

            $order->update([
                'fiscal_status' => 'anulado_fiscal',
                'fiscal_error_message' => null,
            ]);

            return $creditNote;
        });
    }
}
