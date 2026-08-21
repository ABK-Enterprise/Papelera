<?php

namespace App\Playground\Arca;

use Exception;
use Illuminate\Support\Facades\Http;

class WsfeService
{
    public const TESTING_WSFE_URL = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';

    public function __construct(
        protected WsaaService $wsaaService
    ) {}

    /**
     * Consulta el último número de comprobante autorizado para un Punto de Venta y Tipo de Comprobante.
     *
     * @param  array{token: string, sign: string, cuit: int}|null  $auth
     */
    public function consultarUltimoComprobante(int $ptoVta, int $cbteTipo, ?array $auth = null): int
    {
        $auth = $auth ?? $this->wsaaService->obtenerTicketVigente();

        $soapBody = $this->construirSobreFeCompUltimoAutorizado($auth, $ptoVta, $cbteTipo);
        $xmlResponse = $this->enviarSoapRequest('FECompUltimoAutorizado', $soapBody);

        return $this->parsearFeCompUltimoAutorizado($xmlResponse);
    }

    /**
     * Consulta el listado de Puntos de Venta registrados y habilitados en ARCA.
     *
     * @param  array{token: string, sign: string, cuit: int}|null  $auth
     * @return array<int, array{nro: int, emision_tipo: string, bloqueado: string, fch_baja: ?string}>
     */
    public function consultarPuntosVenta(?array $auth = null): array
    {
        $auth = $auth ?? $this->wsaaService->obtenerTicketVigente();

        $soapBody = $this->construirSobreFeParamGetPtosVenta($auth);
        $xmlResponse = $this->enviarSoapRequest('FEParamGetPtosVenta', $soapBody);

        return $this->parsearFeParamGetPtosVenta($xmlResponse);
    }

    /**
     * Emite y solicita autorización electrónica (CAE) para un comprobante (Factura A/B/C, ND, NC).
     *
     * @param  array<string, mixed>  $datos
     * @param  array{token: string, sign: string, cuit: int}|null  $auth
     * @return array{
     *     resultado: string,
     *     cuit: int,
     *     pto_vta: int,
     *     cbte_tipo: int,
     *     cbte_desde: int,
     *     cbte_hasta: int,
     *     cbte_fch: string,
     *     cae: ?string,
     *     cae_fch_vto: ?string,
     *     observaciones: array<int, array{code: int, msg: string}>,
     *     errores: array<int, array{code: int, msg: string}>,
     *     eventos: array<int, array{code: int, msg: string}>,
     *     raw_xml: string
     * }
     */
    public function emitirFactura(array $datos, ?array $auth = null): array
    {
        $this->validarEcuacionContable($datos);

        $auth = $auth ?? $this->wsaaService->obtenerTicketVigente();

        $soapBody = $this->construirSobreFecaeSolicitar($auth, $datos);
        $xmlResponse = $this->enviarSoapRequest('FECAESolicitar', $soapBody);

        return $this->parsearFecaeSolicitar($xmlResponse, (int) $auth['cuit']);
    }

    /**
     * Valida la ecuación contable exigida por ARCA: ImpTotal = ImpNeto + ImpOpEx + ImpTotConc + ImpIVA + ImpTrib
     *
     * @param  array<string, mixed>  $datos
     */
    public function validarEcuacionContable(array $datos): void
    {
        $impTotal = (float) ($datos['imp_total'] ?? 0);
        $impNeto = (float) ($datos['imp_neto'] ?? 0);
        $impOpEx = (float) ($datos['imp_op_ex'] ?? 0);
        $impTotConc = (float) ($datos['imp_tot_conc'] ?? 0);
        $impIva = (float) ($datos['imp_iva'] ?? 0);
        $impTrib = (float) ($datos['imp_trib'] ?? 0);

        $suma = round($impNeto + $impOpEx + $impTotConc + $impIva + $impTrib, 2);
        $diff = abs(round($impTotal - $suma, 2));

        if ($diff > 0.01) {
            throw new Exception("Error en ecuación contable: ImpTotal ({$impTotal}) no coincide con la suma de componentes ({$suma}). Diferencia: {$diff}");
        }

        // Si hay desglose de alícuotas de IVA, validar suma
        if (! empty($datos['iva']) && is_array($datos['iva'])) {
            $sumaIvaDetalle = 0.0;
            foreach ($datos['iva'] as $alicuota) {
                $sumaIvaDetalle += (float) ($alicuota['importe'] ?? 0);
            }
            $sumaIvaDetalle = round($sumaIvaDetalle, 2);
            $diffIva = abs(round($impIva - $sumaIvaDetalle, 2));
            if ($diffIva > 0.01) {
                throw new Exception("Error en IVA: ImpIVA ({$impIva}) no coincide con la suma del detalle de alícuotas ({$sumaIvaDetalle})");
            }
        }
    }

    /**
     * Envía una petición HTTP POST con el sobre SOAP al WSFEv1 de ARCA.
     */
    protected function enviarSoapRequest(string $action, string $soapBody): string
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => "http://ar.gov.afip.dif.FEV1/{$action}",
        ])->timeout(35)->send('POST', self::TESTING_WSFE_URL, [
            'body' => $soapBody,
        ]);

        $body = $response->body();

        if (! $response->successful() && empty($body)) {
            throw new Exception("Error HTTP en WSFEv1 ({$response->status()})");
        }

        return $body;
    }

    /**
     * Construye el sobre SOAP para FECompUltimoAutorizado.
     *
     * @param  array{token: string, sign: string, cuit: int}  $auth
     */
    public function construirSobreFeCompUltimoAutorizado(array $auth, int $ptoVta, int $cbteTipo): string
    {
        $token = htmlspecialchars($auth['token'], \ENT_XML1);
        $sign = htmlspecialchars($auth['sign'], \ENT_XML1);
        $cuit = (int) $auth['cuit'];

        return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
            ."<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">\n"
            ."  <soap:Body>\n"
            ."    <FECompUltimoAutorizado xmlns=\"http://ar.gov.afip.dif.FEV1/\">\n"
            ."      <Auth>\n"
            ."        <Token>{$token}</Token>\n"
            ."        <Sign>{$sign}</Sign>\n"
            ."        <Cuit>{$cuit}</Cuit>\n"
            ."      </Auth>\n"
            ."      <PtoVta>{$ptoVta}</PtoVta>\n"
            ."      <CbteTipo>{$cbteTipo}</CbteTipo>\n"
            ."    </FECompUltimoAutorizado>\n"
            ."  </soap:Body>\n"
            .'</soap:Envelope>';
    }

    /**
     * Construye el sobre SOAP para FEParamGetPtosVenta.
     *
     * @param  array{token: string, sign: string, cuit: int}  $auth
     */
    public function construirSobreFeParamGetPtosVenta(array $auth): string
    {
        $token = htmlspecialchars($auth['token'], \ENT_XML1);
        $sign = htmlspecialchars($auth['sign'], \ENT_XML1);
        $cuit = (int) $auth['cuit'];

        return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
            ."<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">\n"
            ."  <soap:Body>\n"
            ."    <FEParamGetPtosVenta xmlns=\"http://ar.gov.afip.dif.FEV1/\">\n"
            ."      <Auth>\n"
            ."        <Token>{$token}</Token>\n"
            ."        <Sign>{$sign}</Sign>\n"
            ."        <Cuit>{$cuit}</Cuit>\n"
            ."      </Auth>\n"
            ."    </FEParamGetPtosVenta>\n"
            ."  </soap:Body>\n"
            .'</soap:Envelope>';
    }

    /**
     * Construye el sobre SOAP completo para FECAESolicitar.
     *
     * @param  array{token: string, sign: string, cuit: int}  $auth
     * @param  array<string, mixed>  $req
     */
    public function construirSobreFecaeSolicitar(array $auth, array $req): string
    {
        $token = htmlspecialchars($auth['token'], \ENT_XML1);
        $sign = htmlspecialchars($auth['sign'], \ENT_XML1);
        $cuit = (int) $auth['cuit'];

        $ptoVta = (int) ($req['pto_vta'] ?? 1);
        $cbteTipo = (int) ($req['cbte_tipo'] ?? 1);
        $concepto = (int) ($req['concepto'] ?? 1);
        $docTipo = (int) ($req['doc_tipo'] ?? 80);
        $docNro = (string) ($req['doc_nro'] ?? '0');
        $cbteDesde = (int) ($req['cbte_desde'] ?? 1);
        $cbteHasta = (int) ($req['cbte_hasta'] ?? 1);
        $cbteFch = preg_replace('/\D/', '', (string) ($req['cbte_fch'] ?? date('Ymd')));

        $impTotal = number_format((float) ($req['imp_total'] ?? 0), 2, '.', '');
        $impTotConc = number_format((float) ($req['imp_tot_conc'] ?? 0), 2, '.', '');
        $impNeto = number_format((float) ($req['imp_neto'] ?? 0), 2, '.', '');
        $impOpEx = number_format((float) ($req['imp_op_ex'] ?? 0), 2, '.', '');
        $impTrib = number_format((float) ($req['imp_trib'] ?? 0), 2, '.', '');
        $impIva = number_format((float) ($req['imp_iva'] ?? 0), 2, '.', '');

        $monId = htmlspecialchars((string) ($req['mon_id'] ?? 'PES'), \ENT_XML1);
        $monCotiz = number_format((float) ($req['mon_cotiz'] ?? 1.0), 3, '.', '');

        // Fechas de servicios si aplica
        $fechasXml = '';
        if (! empty($req['fch_serv_desde'])) {
            $f = preg_replace('/\D/', '', (string) $req['fch_serv_desde']);
            $fechasXml .= "            <FchServDesde>{$f}</FchServDesde>\n";
        }
        if (! empty($req['fch_serv_hasta'])) {
            $f = preg_replace('/\D/', '', (string) $req['fch_serv_hasta']);
            $fechasXml .= "            <FchServHasta>{$f}</FchServHasta>\n";
        }
        if (! empty($req['fch_vto_pago'])) {
            $f = preg_replace('/\D/', '', (string) $req['fch_vto_pago']);
            $fechasXml .= "            <FchVtoPago>{$f}</FchVtoPago>\n";
        }

        // Condición IVA Receptor (RG 5616)
        $condicionIvaXml = '';
        if (! empty($req['condicion_iva_receptor_id'])) {
            $condId = (int) $req['condicion_iva_receptor_id'];
            $condicionIvaXml = "            <CondicionIVAReceptorId>{$condId}</CondicionIVAReceptorId>\n";
        }

        // Comprobantes Asociados
        $cbtesAsocXml = '';
        if (! empty($req['cbtes_asoc']) && is_array($req['cbtes_asoc'])) {
            $cbtesAsocXml .= "            <CbtesAsoc>\n";
            foreach ($req['cbtes_asoc'] as $c) {
                $tipo = (int) ($c['tipo'] ?? 1);
                $asocPto = (int) ($c['pto_vta'] ?? 1);
                $nro = (int) ($c['nro'] ?? 1);
                $cbtesAsocXml .= "              <CbteAsoc>\n";
                $cbtesAsocXml .= "                <Tipo>{$tipo}</Tipo>\n";
                $cbtesAsocXml .= "                <PtoVta>{$asocPto}</PtoVta>\n";
                $cbtesAsocXml .= "                <Nro>{$nro}</Nro>\n";
                if (! empty($c['cuit'])) {
                    $asocCuit = (string) $c['cuit'];
                    $cbtesAsocXml .= "                <Cuit>{$asocCuit}</Cuit>\n";
                }
                $cbtesAsocXml .= "              </CbteAsoc>\n";
            }
            $cbtesAsocXml .= "            </CbtesAsoc>\n";
        }

        // Tributos Adicionales
        $tributosXml = '';
        if (! empty($req['tributos']) && is_array($req['tributos'])) {
            $tributosXml .= "            <Tributos>\n";
            foreach ($req['tributos'] as $t) {
                $tId = (int) ($t['id'] ?? 1);
                $desc = htmlspecialchars((string) ($t['desc'] ?? ''), \ENT_XML1);
                $baseImp = number_format((float) ($t['base_imp'] ?? 0), 2, '.', '');
                $alic = number_format((float) ($t['alic'] ?? 0), 2, '.', '');
                $importe = number_format((float) ($t['importe'] ?? 0), 2, '.', '');

                $tributosXml .= "              <Tributo>\n";
                $tributosXml .= "                <Id>{$tId}</Id>\n";
                $tributosXml .= "                <Desc>{$desc}</Desc>\n";
                $tributosXml .= "                <BaseImp>{$baseImp}</BaseImp>\n";
                $tributosXml .= "                <Alic>{$alic}</Alic>\n";
                $tributosXml .= "                <Importe>{$importe}</Importe>\n";
                $tributosXml .= "              </Tributo>\n";
            }
            $tributosXml .= "            </Tributos>\n";
        }

        // Desglose de IVA
        $ivaXml = '';
        if (! empty($req['iva']) && is_array($req['iva'])) {
            $ivaXml .= "            <Iva>\n";
            foreach ($req['iva'] as $i) {
                $iId = (int) ($i['id'] ?? 5);
                $baseImp = number_format((float) ($i['base_imp'] ?? 0), 2, '.', '');
                $importe = number_format((float) ($i['importe'] ?? 0), 2, '.', '');

                $ivaXml .= "              <AlicIva>\n";
                $ivaXml .= "                <Id>{$iId}</Id>\n";
                $ivaXml .= "                <BaseImp>{$baseImp}</BaseImp>\n";
                $ivaXml .= "                <Importe>{$importe}</Importe>\n";
                $ivaXml .= "              </AlicIva>\n";
            }
            $ivaXml .= "            </Iva>\n";
        }

        return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
            ."<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">\n"
            ."  <soap:Body>\n"
            ."    <FECAESolicitar xmlns=\"http://ar.gov.afip.dif.FEV1/\">\n"
            ."      <Auth>\n"
            ."        <Token>{$token}</Token>\n"
            ."        <Sign>{$sign}</Sign>\n"
            ."        <Cuit>{$cuit}</Cuit>\n"
            ."      </Auth>\n"
            ."      <FeCAEReq>\n"
            ."        <FeCabReq>\n"
            ."          <CantReg>1</CantReg>\n"
            ."          <PtoVta>{$ptoVta}</PtoVta>\n"
            ."          <CbteTipo>{$cbteTipo}</CbteTipo>\n"
            ."        </FeCabReq>\n"
            ."        <FeDetReq>\n"
            ."          <FECAEDetRequest>\n"
            ."            <Concepto>{$concepto}</Concepto>\n"
            ."            <DocTipo>{$docTipo}</DocTipo>\n"
            ."            <DocNro>{$docNro}</DocNro>\n"
            ."            <CbteDesde>{$cbteDesde}</CbteDesde>\n"
            ."            <CbteHasta>{$cbteHasta}</CbteHasta>\n"
            ."            <CbteFch>{$cbteFch}</CbteFch>\n"
            ."            <ImpTotal>{$impTotal}</ImpTotal>\n"
            ."            <ImpTotConc>{$impTotConc}</ImpTotConc>\n"
            ."            <ImpNeto>{$impNeto}</ImpNeto>\n"
            ."            <ImpOpEx>{$impOpEx}</ImpOpEx>\n"
            ."            <ImpTrib>{$impTrib}</ImpTrib>\n"
            ."            <ImpIVA>{$impIva}</ImpIVA>\n"
            .$fechasXml
            ."            <MonId>{$monId}</MonId>\n"
            ."            <MonCotiz>{$monCotiz}</MonCotiz>\n"
            .$condicionIvaXml
            .$cbtesAsocXml
            .$tributosXml
            .$ivaXml
            ."          </FECAEDetRequest>\n"
            ."        </FeDetReq>\n"
            ."      </FeCAEReq>\n"
            ."    </FECAESolicitar>\n"
            ."  </soap:Body>\n"
            .'</soap:Envelope>';
    }

    /**
     * Parsea la respuesta XML de FECompUltimoAutorizado.
     */
    public function parsearFeCompUltimoAutorizado(string $xml): int
    {
        $this->verificarSoapFault($xml);

        $errores = $this->extraerMensajesArca($xml, 'Errors', 'Err');
        if (! empty($errores)) {
            $errList = implode('; ', array_map(fn ($e) => "[{$e['code']}] {$e['msg']}", $errores));
            throw new Exception("Error en ARCA: {$errList}");
        }

        if (preg_match('/<CbteNro[^>]*>(\d+)<\/CbteNro>/is', $xml, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * Parsea la respuesta XML de FEParamGetPtosVenta.
     *
     * @return array<int, array{nro: int, emision_tipo: string, bloqueado: string, fch_baja: ?string}>
     */
    public function parsearFeParamGetPtosVenta(string $xml): array
    {
        $this->verificarSoapFault($xml);

        $errores = $this->extraerMensajesArca($xml, 'Errors', 'Err');
        $erroresBloqueantes = array_filter($errores, fn ($e) => (int) $e['code'] !== 602);
        if (! empty($erroresBloqueantes)) {
            $errList = implode('; ', array_map(fn ($e) => "[{$e['code']}] {$e['msg']}", $erroresBloqueantes));
            throw new Exception("Error en ARCA: {$errList}");
        }

        $items = [];
        if (preg_match_all('/<PtoVenta[^>]*>(.*?)<\/PtoVenta>/is', $xml, $matches)) {
            foreach ($matches[1] as $block) {
                $nro = preg_match('/<Nro[^>]*>(\d+)<\/Nro>/is', $block, $m) ? (int) $m[1] : 0;
                $emisionTipo = preg_match('/<EmisionTipo[^>]*>(.*?)<\/EmisionTipo>/is', $block, $m) ? trim($m[1]) : '';
                $bloqueado = preg_match('/<Bloqueado[^>]*>(.*?)<\/Bloqueado>/is', $block, $m) ? trim($m[1]) : 'N';
                $fchBaja = preg_match('/<FchBaja[^>]*>(.*?)<\/FchBaja>/is', $block, $m) ? trim($m[1]) : null;

                $items[] = [
                    'nro' => $nro,
                    'emision_tipo' => $emisionTipo,
                    'bloqueado' => $bloqueado,
                    'fch_baja' => ($fchBaja === 'NULL' || empty($fchBaja)) ? null : $fchBaja,
                ];
            }
        }

        return $items;
    }

    /**
     * Parsea la respuesta XML de FECAESolicitar.
     *
     * @return array{
     *     resultado: string,
     *     cuit: int,
     *     pto_vta: int,
     *     cbte_tipo: int,
     *     cbte_desde: int,
     *     cbte_hasta: int,
     *     cbte_fch: string,
     *     cae: ?string,
     *     cae_fch_vto: ?string,
     *     observaciones: array<int, array{code: int, msg: string}>,
     *     errores: array<int, array{code: int, msg: string}>,
     *     eventos: array<int, array{code: int, msg: string}>,
     *     raw_xml: string
     * }
     */
    public function parsearFecaeSolicitar(string $xml, int $cuit): array
    {
        $this->verificarSoapFault($xml);

        $resultado = 'R';
        if (preg_match('/<Resultado[^>]*>(.*?)<\/Resultado>/is', $xml, $m)) {
            $resultado = trim($m[1]);
        }

        $ptoVta = 0;
        if (preg_match('/<PtoVta[^>]*>(\d+)<\/PtoVta>/is', $xml, $m)) {
            $ptoVta = (int) $m[1];
        }

        $cbteTipo = 0;
        if (preg_match('/<CbteTipo[^>]*>(\d+)<\/CbteTipo>/is', $xml, $m)) {
            $cbteTipo = (int) $m[1];
        }

        $cbteDesde = 0;
        if (preg_match('/<CbteDesde[^>]*>(\d+)<\/CbteDesde>/is', $xml, $m)) {
            $cbteDesde = (int) $m[1];
        }

        $cbteHasta = 0;
        if (preg_match('/<CbteHasta[^>]*>(\d+)<\/CbteHasta>/is', $xml, $m)) {
            $cbteHasta = (int) $m[1];
        }

        $cbteFch = '';
        if (preg_match('/<CbteFch[^>]*>(.*?)<\/CbteFch>/is', $xml, $m)) {
            $cbteFch = trim($m[1]);
        }

        $cae = null;
        if (preg_match('/<CAE[^>]*>(\d+)<\/CAE>/is', $xml, $m)) {
            $cae = trim($m[1]);
        }

        $caeFchVto = null;
        if (preg_match('/<CAEFchVto[^>]*>(.*?)<\/CAEFchVto>/is', $xml, $m)) {
            $caeFchVto = trim($m[1]);
        }

        $observaciones = $this->extraerMensajesArca($xml, 'Observaciones', 'Obs');
        $errores = $this->extraerMensajesArca($xml, 'Errors', 'Err');
        $eventos = $this->extraerMensajesArca($xml, 'Events', 'Evt');

        return [
            'resultado' => $resultado,
            'cuit' => $cuit,
            'pto_vta' => $ptoVta,
            'cbte_tipo' => $cbteTipo,
            'cbte_desde' => $cbteDesde,
            'cbte_hasta' => $cbteHasta,
            'cbte_fch' => $cbteFch,
            'cae' => $cae,
            'cae_fch_vto' => $caeFchVto,
            'observaciones' => $observaciones,
            'errores' => $errores,
            'eventos' => $eventos,
            'raw_xml' => $xml,
        ];
    }

    /**
     * Verifica si existe un SOAP Fault y lanza excepción.
     */
    protected function verificarSoapFault(string $xml): void
    {
        if (str_contains($xml, 'faultcode') || str_contains($xml, 'Fault')) {
            $faultStr = 'Fallo SOAP en WSFE';
            if (preg_match('/<faultstring[^>]*>(.*?)<\/faultstring>/is', $xml, $m)) {
                $faultStr = html_entity_decode(strip_tags($m[1]));
            }
            throw new Exception("ARCA WSFEv1 SOAP Fault: {$faultStr}");
        }
    }

    /**
     * Extrae arrays de mensajes de ARCA (<Errors><Err>, <Observaciones><Obs>, etc.)
     *
     * @return array<int, array{code: int, msg: string}>
     */
    protected function extraerMensajesArca(string $xml, string $parentTag, string $itemTag): array
    {
        $messages = [];

        if (preg_match("/<{$parentTag}[^>]*>(.*?)<\/{$parentTag}>/is", $xml, $parentMatch)) {
            $content = $parentMatch[1];
            if (preg_match_all("/<{$itemTag}[^>]*>(.*?)<\/{$itemTag}>/is", $content, $itemMatches)) {
                foreach ($itemMatches[1] as $itemBlock) {
                    $code = 0;
                    if (preg_match('/<Code[^>]*>(\d+)<\/Code>/is', $itemBlock, $m)) {
                        $code = (int) $m[1];
                    }
                    $msg = '';
                    if (preg_match('/<Msg[^>]*>(.*?)<\/Msg>/is', $itemBlock, $m)) {
                        $msg = html_entity_decode(strip_tags($m[1]), \ENT_QUOTES | \ENT_XML1, 'UTF-8');
                    }
                    $messages[] = [
                        'code' => $code,
                        'msg' => $msg,
                    ];
                }
            }
        }

        return $messages;
    }
}
