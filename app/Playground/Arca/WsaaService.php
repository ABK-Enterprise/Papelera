<?php

namespace App\Playground\Arca;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

class WsaaService
{
    public const TESTING_WSAA_URL = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';

    public const TESTING_DESTINATION_DN = 'cn=wsaahomo,o=afip,c=ar,serialNumber=CUIT 33693450239';

    /**
     * Obtiene un Ticket de Acceso vigente desde caché o solicitando uno nuevo al WSAA.
     *
     * @return array{
     *     token: string,
     *     sign: string,
     *     cuit: int,
     *     generation_time: string,
     *     expiration_time: string,
     *     is_valid: bool
     * }
     */
    public function obtenerTicketVigente(
        int $cuit = 20463191280,
        string $service = 'wsfe',
        bool $forceRenew = false
    ): array {
        if (! $forceRenew) {
            $cached = $this->leerTicketDesdeCache($cuit, $service);
            if ($cached !== null && $this->estaTicketVigente($cached)) {
                $cached['is_valid'] = true;

                return $cached;
            }
        }

        return $this->solicitarNuevoTicket($cuit, $service);
    }

    /**
     * Genera un nuevo Ticket de Requerimiento de Acceso (TRA), lo firma digitalmente y consulta al WSAA.
     *
     * @return array{
     *     token: string,
     *     sign: string,
     *     cuit: int,
     *     generation_time: string,
     *     expiration_time: string,
     *     is_valid: bool
     * }
     */
    public function solicitarNuevoTicket(
        int $cuit = 20463191280,
        string $service = 'wsfe'
    ): array {
        $certPath = $this->resolverRutaCertificado('MiPedidoCSR.crt');
        $keyPath = $this->resolverRutaCertificado('Papelera.key');

        if (! file_exists($certPath)) {
            throw new Exception("Certificado no encontrado en: {$certPath}");
        }

        if (! file_exists($keyPath)) {
            throw new Exception("Llave privada no encontrada en: {$keyPath}");
        }

        // 1. Generar XML del TRA con tolerancia horaria UTC-3
        $traXml = $this->generarTraXml($service);

        // 2. Firmar el TRA en formato CMS / PKCS#7 y obtener Base64
        $cmsBase64 = $this->firmarTraCms($traXml, $certPath, $keyPath);

        // 3. Enviar solicitud SOAP al WSAA
        $soapEnvelope = $this->construirSobreSoapLoginCms($cmsBase64);

        $httpRequest = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => '',
        ])->timeout(30);

        if (app()->environment('local')) {
            $httpRequest = $httpRequest->withoutVerifying();
        }

        $response = $httpRequest->send('POST', self::TESTING_WSAA_URL, [
            'body' => $soapEnvelope,
        ]);

        $xmlBody = $response->body();

        // Si WSAA devuelve faultstring "El CEE ya posee un TA valido" (coe.alreadyAuthenticated)
        if (str_contains($xmlBody, 'alreadyAuthenticated') || str_contains($xmlBody, 'ya posee un TA valido')) {
            $cached = $this->leerTicketDesdeCache($cuit, $service);
            if ($cached !== null) {
                $cached['is_valid'] = true;

                return $cached;
            }
            throw new Exception('ARCA informa que este CUIT ya posee un Ticket de Acceso vigente en sus servidores (coe.alreadyAuthenticated).');
        }

        if (! $response->successful()) {
            throw new Exception("Error HTTP en WSAA ({$response->status()}): {$xmlBody}");
        }

        // 4. Parsear respuesta SOAP
        $ticket = $this->parsearRespuestaLoginCms($xmlBody, $cuit);

        // 5. Guardar en caché local
        $this->guardarTicketEnCache($ticket, $cuit, $service);

        return $ticket;
    }

    /**
     * Construye el XML de requerimiento de acceso (TRA).
     */
    public function generarTraXml(string $service): string
    {
        $ahoraAr = Carbon::now('America/Argentina/Buenos_Aires');
        $genTime = $ahoraAr->copy()->subMinutes(10)->toIso8601String();
        $expTime = $ahoraAr->copy()->addMinutes(10)->toIso8601String();
        $uniqueId = time() + random_int(1, 10000);
        $destination = htmlspecialchars(self::TESTING_DESTINATION_DN, \ENT_XML1);
        $serviceSafe = htmlspecialchars($service, \ENT_XML1);

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<loginTicketRequest version=\"1.0\">\n"
            ."  <header>\n"
            ."    <destination>{$destination}</destination>\n"
            ."    <uniqueId>{$uniqueId}</uniqueId>\n"
            ."    <generationTime>{$genTime}</generationTime>\n"
            ."    <expirationTime>{$expTime}</expirationTime>\n"
            ."  </header>\n"
            ."  <service>{$serviceSafe}</service>\n"
            .'</loginTicketRequest>';
    }

    /**
     * Firma el XML del TRA con OpenSSL en formato PKCS#7 / CMS y retorna la cadena Base64 requerida por WSAA.
     */
    public function firmarTraCms(string $traXml, string $certPath, string $keyPath): string
    {
        $tempTra = tempnam(sys_get_temp_dir(), 'tra_');
        $tempCms = tempnam(sys_get_temp_dir(), 'cms_');

        try {
            file_put_contents($tempTra, $traXml);

            $flags = (defined('PKCS7_NOSMIMECAP') ? \PKCS7_NOSMIMECAP : 0) | (defined('PKCS7_BINARY') ? \PKCS7_BINARY : 128);

            $signed = openssl_pkcs7_sign(
                $tempTra,
                $tempCms,
                "file://{$certPath}",
                ["file://{$keyPath}", ''],
                [],
                $flags
            );

            if (! $signed) {
                $errorMsg = openssl_error_string() ?: 'Error desconocido al firmar con openssl_pkcs7_sign';
                throw new Exception("Fallo en firma CMS: {$errorMsg}");
            }

            $cmsRaw = file_get_contents($tempCms);
            if ($cmsRaw === false || empty($cmsRaw)) {
                throw new Exception('No se pudo leer el archivo temporal de firma CMS');
            }

            // Separar cabeceras S/MIME y extraer el bloque Base64 DER
            $cmsNormalized = str_replace(["\r\n", "\r"], "\n", $cmsRaw);
            $parts = explode("\n\n", $cmsNormalized, 2);
            $base64Data = isset($parts[1]) ? trim($parts[1]) : trim($parts[0]);

            // Remover saltos de línea internos
            return str_replace(["\n", "\r", ' '], '', $base64Data);
        } finally {
            if (file_exists($tempTra)) {
                @unlink($tempTra);
            }
            if (file_exists($tempCms)) {
                @unlink($tempCms);
            }
        }
    }

    /**
     * Construye el sobre SOAP para el método loginCms.
     */
    public function construirSobreSoapLoginCms(string $cmsBase64): string
    {
        $escapedCms = htmlspecialchars($cmsBase64, \ENT_XML1);

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:wsaa=\"http://wsaa.view.sua.dvadac.desein.afip.gov\">\n"
            ."  <soapenv:Header/>\n"
            ."  <soapenv:Body>\n"
            ."    <wsaa:loginCms>\n"
            ."      <wsaa:in0>{$escapedCms}</wsaa:in0>\n"
            ."    </wsaa:loginCms>\n"
            ."  </soapenv:Body>\n"
            .'</soapenv:Envelope>';
    }

    /**
     * Parsea la respuesta XML de loginCms de WSAA.
     *
     * @return array{
     *     token: string,
     *     sign: string,
     *     cuit: int,
     *     generation_time: string,
     *     expiration_time: string,
     *     is_valid: bool
     * }
     */
    public function parsearRespuestaLoginCms(string $xmlSoap, int $cuit): array
    {
        if (str_contains($xmlSoap, 'faultcode') || str_contains($xmlSoap, 'Fault')) {
            $faultStr = 'Fallo SOAP en WSAA';
            if (preg_match('/<faultstring[^>]*>(.*?)<\/faultstring>/is', $xmlSoap, $m)) {
                $faultStr = html_entity_decode(strip_tags($m[1]));
            }
            throw new Exception("WSAA SOAP Fault: {$faultStr}");
        }

        // Extraer loginCmsReturn
        if (! preg_match('/<loginCmsReturn[^>]*>(.*?)<\/loginCmsReturn>/is', $xmlSoap, $matches)) {
            throw new Exception("Respuesta de WSAA no contiene el nodo loginCmsReturn:\n{$xmlSoap}");
        }

        $loginCmsReturnXml = html_entity_decode($matches[1], \ENT_QUOTES | \ENT_XML1, 'UTF-8');

        $sxe = new SimpleXMLElement($loginCmsReturnXml);
        $token = (string) ($sxe->credentials->token ?? '');
        $sign = (string) ($sxe->credentials->sign ?? '');
        $genTime = (string) ($sxe->header->generationTime ?? '');
        $expTime = (string) ($sxe->header->expirationTime ?? '');

        if (empty($token) || empty($sign)) {
            throw new Exception("Credenciales incompletas en respuesta de WSAA:\n{$loginCmsReturnXml}");
        }

        return [
            'token' => $token,
            'sign' => $sign,
            'cuit' => $cuit,
            'generation_time' => $genTime,
            'expiration_time' => $expTime,
            'is_valid' => true,
        ];
    }

    /**
     * Verifica si el ticket de acceso no ha expirado.
     *
     * @param  array{expiration_time: string}  $ticket
     */
    public function estaTicketVigente(array $ticket): bool
    {
        if (empty($ticket['expiration_time']) || empty($ticket['token']) || empty($ticket['sign'])) {
            return false;
        }

        try {
            $exp = Carbon::parse($ticket['expiration_time']);

            // Dejamos un margen de 2 minutos de seguridad antes de la expiración
            return $exp->isAfter(Carbon::now()->addMinutes(2));
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Lee el archivo de caché del Ticket de Acceso.
     *
     * @return array{
     *     token: string,
     *     sign: string,
     *     cuit: int,
     *     generation_time: string,
     *     expiration_time: string,
     *     is_valid?: bool
     * }|null
     */
    public function leerTicketDesdeCache(int $cuit, string $service): ?array
    {
        $cachePaths = [
            base_path("ARCA-certificados/.ta-cache-{$cuit}-{$service}.json"),
            storage_path("app/arca/.ta-cache-{$cuit}-{$service}.json"),
        ];

        foreach ($cachePaths as $path) {
            if (file_exists($path)) {
                $content = file_get_contents($path);
                if (! empty($content)) {
                    $json = json_decode($content, true);
                    if (is_array($json) && isset($json['token'], $json['sign'])) {
                        return $json;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Guarda el Ticket de Acceso en el archivo de caché.
     *
     * @param  array<string, mixed>  $ticket
     */
    public function guardarTicketEnCache(array $ticket, int $cuit, string $service): void
    {
        $data = [
            'token' => $ticket['token'],
            'sign' => $ticket['sign'],
            'cuit' => $cuit,
            'generation_time' => $ticket['generation_time'],
            'expiration_time' => $ticket['expiration_time'],
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $path = base_path("ARCA-certificados/.ta-cache-{$cuit}-{$service}.json");
        $dir = dirname($path);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        @file_put_contents($path, $json);
    }

    /**
     * Resuelve la ruta al archivo de certificado o llave.
     */
    public function resolverRutaCertificado(string $filename): string
    {
        $intentos = [
            base_path("ARCA-certificados/{$filename}"),
            base_path("storage/app/arca/{$filename}"),
            storage_path("arca/{$filename}"),
        ];

        foreach ($intentos as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return base_path("ARCA-certificados/{$filename}");
    }
}
