<?php

namespace App\Services\Arca;

use App\Exceptions\ArcaException;
use Illuminate\Support\Carbon;
use SoapClient;
use SoapFault;

class ArcaWsfeService
{
    protected string $environment;

    protected string $wsfeUrl;

    protected string $wsfeWsdl;

    protected int $timeout;

    public function __construct(
        protected ArcaAuthService $authService,
        protected ?SoapClient $customSoapClient = null,
    ) {
        $this->environment = config('arca.environment', 'testing');
        $this->wsfeUrl = $this->environment === 'production'
            ? config('arca.wsfe.production_url')
            : config('arca.wsfe.homologation_url');
        $this->wsfeWsdl = $this->environment === 'production'
            ? config('arca.wsfe.wsdl_production')
            : config('arca.wsfe.wsdl_homologation');
        $this->timeout = (int) config('arca.wsfe.timeout', 30);
    }

    public function setSoapClient(?SoapClient $customSoapClient): self
    {
        $this->customSoapClient = $customSoapClient;

        return $this;
    }

    public function getSoapClient(): SoapClient
    {
        if ($this->customSoapClient !== null) {
            return $this->customSoapClient;
        }

        return new SoapClient($this->wsfeWsdl, [
            'soap_version' => SOAP_1_2,
            'location' => $this->wsfeUrl,
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => $this->timeout,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ]);
    }

    /**
     * Get the last authorized comprobante number for a given point of sale and comprobante type.
     *
     * @throws ArcaException
     */
    public function getUltimoComprobante(int $ptoVta, int $cbteTipo, ?string $cuit = null): int
    {
        $cuit = $cuit ?: config('arca.testing.cuit');
        $cleanCuit = (float) preg_replace('/\D/', '', (string) $cuit);

        $ticket = $this->authService->getAccessTicket((string) $cleanCuit, service: 'wsfe');

        $auth = [
            'Token' => $ticket['token'],
            'Sign' => $ticket['sign'],
            'Cuit' => $cleanCuit,
        ];

        try {
            $client = $this->getSoapClient();
            $response = $client->FECompUltimoAutorizado([
                'Auth' => $auth,
                'PtoVta' => $ptoVta,
                'CbteTipo' => $cbteTipo,
            ]);

            $result = $response->FECompUltimoAutorizadoResult ?? $response['FECompUltimoAutorizadoResult'] ?? $response;

            // Check for errors
            $errors = is_object($result) ? ($result->Errors ?? null) : ($result['Errors'] ?? null);
            if ($errors) {
                $err = $this->extractFirstError($errors, null);
                if ($err) {
                    $translated = ArcaErrorTranslator::translate($err['code'], $err['msg']);
                    throw new ArcaException($translated, $err['code']);
                }
            }

            $cbteNro = is_object($result) ? ($result->CbteNro ?? 0) : ($result['CbteNro'] ?? 0);

            return (int) $cbteNro;
        } catch (SoapFault $e) {
            $translated = ArcaErrorTranslator::translate('SoapFault', $e->getMessage());
            throw new ArcaException($translated, 'SoapFault', (int) $e->getCode(), $e);
        } catch (ArcaException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $translated = ArcaErrorTranslator::translate('SoapFault', $e->getMessage());
            throw new ArcaException($translated, 'SoapFault', 0, $e);
        }
    }

    /**
     * Request Electronic Authorization Code (CAE) from ARCA.
     *
     * @param  array  $params  Contains FeCabReq and FeDetReq
     * @return array{cae: string, cae_due_date: string, cbte_nro: int, resultado: string, raw_request: array, raw_response: array}
     *
     * @throws ArcaException
     */
    public function solicitarCae(array $params, ?string $cuit = null): array
    {
        $cuit = $cuit ?: config('arca.testing.cuit');
        $cleanCuit = (float) preg_replace('/\D/', '', (string) $cuit);

        $ticket = $this->authService->getAccessTicket((string) $cleanCuit, service: 'wsfe');

        $auth = [
            'Token' => $ticket['token'],
            'Sign' => $ticket['sign'],
            'Cuit' => $cleanCuit,
        ];

        $feCAEReq = isset($params['FeCAEReq']) ? $params['FeCAEReq'] : $params;

        $request = [
            'Auth' => $auth,
            'FeCAEReq' => $feCAEReq,
        ];

        try {
            $client = $this->getSoapClient();
            $response = $client->FECAESolicitar($request);

            $result = $response->FECAESolicitarResult ?? $response['FECAESolicitarResult'] ?? $response;

            $feCabResp = is_object($result) ? ($result->FeCabResp ?? null) : ($result['FeCabResp'] ?? null);
            $feDetResp = is_object($result) ? ($result->FeDetResp ?? null) : ($result['FeDetResp'] ?? null);

            $cabResultado = is_object($feCabResp) ? ($feCabResp->Resultado ?? null) : ($feCabResp['Resultado'] ?? null);

            $detList = is_object($feDetResp) ? ($feDetResp->FECAEDetResponse ?? $feDetResp) : ($feDetResp['FECAEDetResponse'] ?? $feDetResp);
            $firstDet = is_array($detList) && isset($detList[0]) ? $detList[0] : $detList;

            $detResultado = is_object($firstDet) ? ($firstDet->Resultado ?? null) : ($firstDet['Resultado'] ?? null);

            $errors = is_object($result) ? ($result->Errors ?? null) : ($result['Errors'] ?? null);
            $obs = is_object($firstDet) ? ($firstDet->Observaciones ?? null) : ($firstDet['Observaciones'] ?? null);

            if ($cabResultado === 'R' || $detResultado === 'R') {
                $err = $this->extractFirstError($errors, $obs);
                $code = $err['code'] ?? 'R';
                $msg = $err['msg'] ?? 'Comprobante rechazado por ARCA.';
                $translated = ArcaErrorTranslator::translate($code, $msg);

                throw new ArcaException($translated, $code);
            }

            // Success case
            $cae = (string) (is_object($firstDet) ? ($firstDet->CAE ?? '') : ($firstDet['CAE'] ?? ''));
            $fchVto = (string) (is_object($firstDet) ? ($firstDet->CAEFchVto ?? '') : ($firstDet['CAEFchVto'] ?? ''));

            $caeDueDate = strlen($fchVto) === 8
                ? Carbon::createFromFormat('Ymd', $fchVto)->toDateString()
                : $fchVto;

            $cbteNro = (int) (is_object($firstDet)
                ? ($firstDet->CbteDesde ?? $firstDet->CbteHasta ?? 0)
                : ($firstDet['CbteDesde'] ?? $firstDet['CbteHasta'] ?? 0));

            if ($cbteNro === 0 && isset($feCAEReq['FeDetReq']['FECAEDetRequest']['CbteDesde'])) {
                $cbteNro = (int) $feCAEReq['FeDetReq']['FECAEDetRequest']['CbteDesde'];
            }

            return [
                'cae' => $cae,
                'cae_due_date' => $caeDueDate,
                'cbte_nro' => $cbteNro,
                'resultado' => 'A',
                'raw_request' => $request,
                'raw_response' => json_decode(json_encode($response), true) ?: [],
            ];
        } catch (SoapFault $e) {
            $translated = ArcaErrorTranslator::translate('SoapFault', $e->getMessage());
            throw new ArcaException($translated, 'SoapFault', (int) $e->getCode(), $e);
        } catch (ArcaException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $translated = ArcaErrorTranslator::translate('SoapFault', $e->getMessage());
            throw new ArcaException($translated, 'SoapFault', 0, $e);
        }
    }

    /**
     * Consult an already issued comprobante from ARCA.
     * Returns null if comprobante was not found (error 602).
     *
     * @return array{cae: string, cae_due_date: string, cbte_nro: int, resultado: string, raw_response: array}|null
     *
     * @throws ArcaException
     */
    public function consultarComprobante(int $ptoVta, int $cbteTipo, int $cbteNro, ?string $cuit = null): ?array
    {
        $cuit = $cuit ?: config('arca.testing.cuit');
        $cleanCuit = (float) preg_replace('/\D/', '', (string) $cuit);

        $ticket = $this->authService->getAccessTicket((string) $cleanCuit, service: 'wsfe');

        $auth = [
            'Token' => $ticket['token'],
            'Sign' => $ticket['sign'],
            'Cuit' => $cleanCuit,
        ];

        $request = [
            'Auth' => $auth,
            'FeCompConsReq' => [
                'CbteTipo' => $cbteTipo,
                'CbteNro' => $cbteNro,
                'PtoVta' => $ptoVta,
            ],
        ];

        try {
            $client = $this->getSoapClient();
            $response = $client->FECompConsultar($request);

            $result = $response->FECompConsultarResult ?? $response['FECompConsultarResult'] ?? $response;

            // Check for Errors
            $errors = is_object($result) ? ($result->Errors ?? null) : ($result['Errors'] ?? null);
            if ($errors) {
                $err = $this->extractFirstError($errors, null);
                if ($err) {
                    if ($err['code'] === '602' || str_contains($err['msg'], '602')) {
                        return null;
                    }

                    $translated = ArcaErrorTranslator::translate($err['code'], $err['msg']);
                    throw new ArcaException($translated, $err['code']);
                }
            }

            $resultGet = is_object($result) ? ($result->ResultGet ?? null) : ($result['ResultGet'] ?? null);
            if (! $resultGet) {
                return null;
            }

            $cae = (string) (is_object($resultGet) ? ($resultGet->CodAutorizacion ?? '') : ($resultGet['CodAutorizacion'] ?? ''));
            $fchVto = (string) (is_object($resultGet) ? ($resultGet->FchVto ?? '') : ($resultGet['FchVto'] ?? ''));

            if (empty($cae)) {
                return null;
            }

            $caeDueDate = strlen($fchVto) === 8
                ? Carbon::createFromFormat('Ymd', $fchVto)->toDateString()
                : $fchVto;

            $resCbteNro = (int) (is_object($resultGet) ? ($resultGet->CbteDesde ?? $resultGet->CbteNro ?? $cbteNro) : ($resultGet['CbteDesde'] ?? $resultGet['CbteNro'] ?? $cbteNro));
            $resultado = (string) (is_object($resultGet) ? ($resultGet->Resultado ?? 'A') : ($resultGet['Resultado'] ?? 'A'));

            return [
                'cae' => $cae,
                'cae_due_date' => $caeDueDate,
                'cbte_nro' => $resCbteNro,
                'resultado' => $resultado,
                'raw_response' => json_decode(json_encode($response), true) ?: [],
            ];
        } catch (SoapFault $e) {
            if (str_contains($e->getMessage(), '602') || $e->getCode() === 602) {
                return null;
            }

            $translated = ArcaErrorTranslator::translate('SoapFault', $e->getMessage());
            throw new ArcaException($translated, 'SoapFault', (int) $e->getCode(), $e);
        } catch (ArcaException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $translated = ArcaErrorTranslator::translate('SoapFault', $e->getMessage());
            throw new ArcaException($translated, 'SoapFault', 0, $e);
        }
    }

    /**
     * Helper to extract the first error code and message from response structures.
     */
    protected function extractFirstError(mixed $errors, mixed $observations): ?array
    {
        if ($errors) {
            $err = is_object($errors) && property_exists($errors, 'Err')
                ? $errors->Err
                : (is_array($errors) && isset($errors['Err']) ? $errors['Err'] : $errors);

            $first = is_array($err) && isset($err[0]) ? $err[0] : $err;

            if ($first) {
                $code = is_object($first) ? ($first->Code ?? null) : ($first['Code'] ?? null);
                $msg = is_object($first) ? ($first->Msg ?? null) : ($first['Msg'] ?? null);
                if ($code !== null) {
                    return ['code' => (string) $code, 'msg' => (string) $msg];
                }
            }
        }

        if ($observations) {
            $obs = is_object($observations) && property_exists($observations, 'Obs')
                ? $observations->Obs
                : (is_array($observations) && isset($observations['Obs']) ? $observations['Obs'] : $observations);

            $first = is_array($obs) && isset($obs[0]) ? $obs[0] : $obs;

            if ($first) {
                $code = is_object($first) ? ($first->Code ?? null) : ($first['Code'] ?? null);
                $msg = is_object($first) ? ($first->Msg ?? null) : ($first['Msg'] ?? null);
                if ($code !== null) {
                    return ['code' => (string) $code, 'msg' => (string) $msg];
                }
            }
        }

        return null;
    }
}
