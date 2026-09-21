<?php

namespace App\Services\Arca;

use App\Exceptions\ArcaAuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

class ArcaAuthService
{
    protected string $environment;
    protected string $wsaaUrl;
    protected string $wsaaWsdl;
    protected int $cacheTtl;

    protected ?SoapClient $customSoapClient = null;

    public function __construct(?SoapClient $customSoapClient = null)
    {
        $this->customSoapClient = $customSoapClient;
        $this->environment = config('arca.environment', 'testing');
        $this->wsaaUrl = $this->environment === 'production'
            ? config('arca.wsaa.production_url')
            : config('arca.wsaa.homologation_url');
        $this->wsaaWsdl = $this->environment === 'production'
            ? config('arca.wsaa.wsdl_production')
            : config('arca.wsaa.wsdl_homologation');
        $this->cacheTtl = (int) config('arca.wsaa.token_cache_ttl', 41400);
    }

    public function setSoapClient(?SoapClient $customSoapClient): self
    {
        $this->customSoapClient = $customSoapClient;

        return $this;
    }

    /**
     * Get a valid Access Ticket (Token and Sign) for the given CUIT and service.
     * Checks cache first; if missing, checks persistent disk cache;
     * if expired or missing, requests a new one from WSAA.
     *
     * @return array{token: string, sign: string, expiration: string, cuit: string}
     *
     * @throws ArcaAuthenticationException
     */
    public function getAccessTicket(
        ?string $cuit = null,
        ?string $certPath = null,
        ?string $keyPath = null,
        ?string $passphrase = null,
        string $service = 'wsfe'
    ): array {
        $cuit = $cuit ?: config('arca.testing.cuit');
        $certPath = $certPath ?: config('arca.testing.cert_path');
        $keyPath = $keyPath ?: config('arca.testing.key_path');
        $passphrase = $passphrase ?? config('arca.testing.passphrase', '');

        $cacheKey = "arca_ta_{$cuit}_{$service}_{$this->environment}";
        $storageFilePath = $this->getStorageCacheFilePath($cuit, $service);

        // 1. Check Laravel Cache
        $cachedTicket = Cache::get($cacheKey);
        if ($this->isValidTicket($cachedTicket)) {
            return $cachedTicket;
        }

        // 2. Check Persistent Disk Cache Fallback
        if (file_exists($storageFilePath)) {
            $fileContent = @file_get_contents($storageFilePath);
            if ($fileContent) {
                $diskTicket = json_decode($fileContent, true);
                if ($this->isValidTicket($diskTicket)) {
                    Cache::put($cacheKey, $diskTicket, $this->cacheTtl);

                    return $diskTicket;
                }
            }
        }

        // 3. Authenticate against WSAA
        $ticket = $this->requestNewTicketFromWsaa($cuit, $certPath, $keyPath, $passphrase, $service);

        // 4. Store in Cache and Disk with strict 11h 30m TTL
        Cache::put($cacheKey, $ticket, $this->cacheTtl);
        $this->saveTicketToDisk($storageFilePath, $ticket);

        return $ticket;
    }

    /**
     * Check if a ticket has valid token, sign, and has at least 5 minutes remaining.
     */
    protected function isValidTicket(?array $ticket): bool
    {
        if (! $ticket || empty($ticket['token']) || empty($ticket['sign'])) {
            return false;
        }

        $expiration = isset($ticket['expiration']) ? Carbon::parse($ticket['expiration']) : null;

        return ! $expiration || $expiration->isAfter(Carbon::now()->addMinutes(5));
    }

    /**
     * Generate TRA XML, sign with PKCS#7 / CMS, and call WSAA LoginCms.
     *
     * @return array{token: string, sign: string, expiration: string, cuit: string}
     */
    public function requestNewTicketFromWsaa(
        string $cuit,
        string $certPath,
        string $keyPath,
        string $passphrase,
        string $service = 'wsfe'
    ): array {
        $this->validateCertificates($certPath, $keyPath);

        // 1. Build TRA XML
        $traXml = $this->createTraXml($service);

        // 2. Sign TRA via CMS (PKCS#7)
        $cmsBase64 = $this->signTra($traXml, $certPath, $keyPath, $passphrase);

        // 3. Call WSAA via SOAP
        try {
            $soapClient = $this->customSoapClient ?: new SoapClient($this->wsaaWsdl, [
                'soap_version' => SOAP_1_2,
                'location' => $this->wsaaUrl,
                'trace' => 1,
                'exceptions' => true,
                'connection_timeout' => 30,
            ]);

            $loginResult = $soapClient->loginCms([
                'in0' => $cmsBase64,
            ]);

            $responseXmlStr = $loginResult->loginCmsReturn ?? null;
            if (! $responseXmlStr) {
                throw new ArcaAuthenticationException('WSAA devolvió una respuesta vacía o sin el nodo loginCmsReturn.');
            }

            return $this->parseLoginTicketResponse($responseXmlStr, $cuit);
        } catch (SoapFault $sf) {
            $storageFilePath = $this->getStorageCacheFilePath($cuit, $service);

            // Resiliency fallback: if ARCA says already authenticated and disk cache has a ticket, recover it
            $isAlreadyAuth = (isset($sf->faultcode) && str_contains((string) $sf->faultcode, 'coe.alreadyAuthenticated'))
                || str_contains($sf->getMessage(), 'ya posee un TA valido');

            if ($isAlreadyAuth && file_exists($storageFilePath)) {
                $fileContent = @file_get_contents($storageFilePath);
                if ($fileContent) {
                    $diskTicket = json_decode($fileContent, true);
                    if ($diskTicket && ! empty($diskTicket['token'])) {
                        Log::info("ARCA WSAA: CEE ya autenticado previamente para CUIT {$cuit}. Recuperado desde disco.");

                        return $diskTicket;
                    }
                }
            }

            Log::error('ARCA WSAA SoapFault: ' . $sf->getMessage(), [
                'faultcode' => $sf->faultcode ?? null,
                'faultstring' => $sf->faultstring ?? null,
                'environment' => $this->environment,
                'cuit' => $cuit,
            ]);

            throw new ArcaAuthenticationException(
                "Error en la autenticación con ARCA (WSAA): {$sf->getMessage()}",
                $sf->faultcode ?? null,
                0,
                $sf
            );
        } catch (\Throwable $e) {
            if ($e instanceof ArcaAuthenticationException) {
                throw $e;
            }

            Log::error('ARCA WSAA Error Inesperado: ' . $e->getMessage(), [
                'cuit' => $cuit,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ArcaAuthenticationException(
                "Fallo inesperado al autenticar con ARCA: {$e->getMessage()}",
                null,
                0,
                $e
            );
        }
    }

    /**
     * File path for disk backup of the Access Ticket.
     */
    protected function getStorageCacheFilePath(string $cuit, string $service): string
    {
        $dir = storage_path('app/arca');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return "{$dir}/ta_{$cuit}_{$service}_{$this->environment}.json";
    }

    /**
     * Persist ticket to disk safely.
     */
    protected function saveTicketToDisk(string $filePath, array $ticket): void
    {
        @file_put_contents($filePath, json_encode($ticket, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Create the Access Ticket Request (TRA) XML string.
     */
    protected function createTraXml(string $service): string
    {
        $uniqueId = (int) (Carbon::now()->timestamp + rand(100, 999));
        // Clock skew adjustment: Subtract 10 minutes from generationTime
        $generationTime = Carbon::now()->subMinutes(10)->format('Y-m-d\TH:i:sP');
        $expirationTime = Carbon::now()->addMinutes(10)->format('Y-m-d\TH:i:sP');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<loginTicketRequest version="1.0">' . "\n" .
            '    <header>' . "\n" .
            "        <uniqueId>{$uniqueId}</uniqueId>\n" .
            "        <generationTime>{$generationTime}</generationTime>\n" .
            "        <expirationTime>{$expirationTime}</expirationTime>\n" .
            "    </header>\n" .
            "    <service>{$service}</service>\n" .
            '</loginTicketRequest>';
    }

    /**
     * Sign the TRA XML using OpenSSL PKCS#7 (CMS).
     */
    protected function signTra(string $traXml, string $certPath, string $keyPath, string $passphrase): string
    {
        $cert = file_get_contents($certPath);
        $key = file_get_contents($keyPath);

        if ($cert === false || $key === false) {
            throw new ArcaAuthenticationException('No se pudieron leer los archivos del certificado o clave privada de ARCA.');
        }

        $privateKey = openssl_pkey_get_private($key, $passphrase);
        if ($privateKey === false) {
            throw new ArcaAuthenticationException('La clave privada de ARCA es inválida o la contraseña es incorrecta.');
        }

        $tempTraFile = tempnam(sys_get_temp_dir(), 'tra_');
        $tempCmsFile = tempnam(sys_get_temp_dir(), 'cms_');

        try {
            file_put_contents($tempTraFile, $traXml);

            $signed = openssl_pkcs7_sign(
                $tempTraFile,
                $tempCmsFile,
                $cert,
                $privateKey,
                [],
                0
            );

            if (! $signed) {
                $errorMsg = openssl_error_string() ?: 'Error desconocido al firmar el TRA con OpenSSL.';
                throw new ArcaAuthenticationException("Fallo al generar la firma PKCS#7 para ARCA: {$errorMsg}");
            }

            $cmsRaw = file_get_contents($tempCmsFile);
            if ($cmsRaw === false) {
                throw new ArcaAuthenticationException('No se pudo leer el archivo CMS firmado generado.');
            }

            // Extract pure Base64 payload by stripping MIME headers and boundaries
            return $this->extractBase64FromCms($cmsRaw);
        } finally {
            if (file_exists($tempTraFile)) {
                @unlink($tempTraFile);
            }
            if (file_exists($tempCmsFile)) {
                @unlink($tempCmsFile);
            }
        }
    }

    /**
     * Extract pure Base64 content from PKCS#7 multipart MIME output.
     */
    protected function extractBase64FromCms(string $cmsRaw): string
    {
        $parts = explode("\n\n", str_replace("\r", '', $cmsRaw));
        if (count($parts) >= 2) {
            // The body is in the second or later block
            array_shift($parts);
            $body = implode('', $parts);
            // Remove boundary lines if present
            $lines = explode("\n", $body);
            $cleanLines = array_filter($lines, function ($line) {
                return ! str_starts_with(trim($line), '---') && ! empty(trim($line));
            });

            return implode('', $cleanLines);
        }

        // Fallback: clean all linebreaks and non-base64 characters
        return preg_replace('/[^A-Za-z0-9+\/=]/', '', $cmsRaw) ?: $cmsRaw;
    }

    /**
     * Parse WSAA loginTicketResponse XML string.
     *
     * @return array{token: string, sign: string, expiration: string, cuit: string}
     */
    protected function parseLoginTicketResponse(string $xmlString, string $cuit): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if ($xml === false) {
            throw new ArcaAuthenticationException('La respuesta XML de WSAA es inválida o no pudo ser procesada.');
        }

        $token = (string) ($xml->credentials->token ?? '');
        $sign = (string) ($xml->credentials->sign ?? '');
        $expiration = (string) ($xml->header->expirationTime ?? '');

        if (empty($token) || empty($sign)) {
            throw new ArcaAuthenticationException('El ticket de acceso de ARCA no contiene Token o Sign válidos.');
        }

        return [
            'token' => $token,
            'sign' => $sign,
            'expiration' => $expiration,
            'cuit' => $cuit,
        ];
    }

    /**
     * Validate that certificate and key files exist and are readable.
     */
    protected function validateCertificates(string $certPath, string $keyPath): void
    {
        if (! file_exists($certPath)) {
            throw new ArcaAuthenticationException("El certificado de ARCA no existe en la ruta: {$certPath}");
        }

        if (! file_exists($keyPath)) {
            throw new ArcaAuthenticationException("La clave privada de ARCA no existe en la ruta: {$keyPath}");
        }
    }
}
