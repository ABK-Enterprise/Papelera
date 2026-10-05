<?php

namespace App\Services\Arca;

class ArcaErrorTranslator
{
    /**
     * Sanitize any exception or raw message so it never leaks filesystem paths or internal server details.
     */
    public static function sanitizePublicMessage(?string $message): string
    {
        if (empty($message)) {
            return 'Ocurrió un inconveniente con el servicio fiscal de ARCA.';
        }

        $normalized = strtolower($message);

        if (
            str_contains($normalized, 'certificado') ||
            str_contains($normalized, 'clave privada') ||
            str_contains($normalized, 'no existe en la ruta') ||
            str_contains($normalized, '/var/www') ||
            str_contains($normalized, 'storage/') ||
            str_contains($normalized, '.crt') ||
            str_contains($normalized, '.key')
        ) {
            return 'El certificado digital de ARCA no está cargado o no fue configurado correctamente en el sistema.';
        }

        if (
            str_contains($normalized, 'soapfault') ||
            str_contains($normalized, 'timeout') ||
            str_contains($normalized, 'timed out') ||
            str_contains($normalized, 'could not connect to host') ||
            str_contains($normalized, 'connection reset')
        ) {
            return 'El servicio fiscal de ARCA no responde temporalmente. El pedido fue resguardado para reintentar.';
        }

        return $message;
    }

    /**
     * Translate an ARCA/AFIP error code or SoapFault to a user-friendly, domain-specific message.
     */
    public static function translate(int|string $code, ?string $originalMessage = null): string
    {
        if (! empty($originalMessage)) {
            $sanitized = self::sanitizePublicMessage($originalMessage);
            if ($sanitized !== $originalMessage) {
                return $sanitized;
            }
        }

        $normalizedCode = is_numeric($code) ? (int) $code : strtolower(trim((string) $code));
        $normalizedMsg = strtolower($originalMessage ?? '');

        // Check for SoapFault / Timeout / Connection errors
        if (
            in_array($normalizedCode, ['soapfault', 'soap_fault', 'timeout', 'timed out', 'connection_error'], true)
            || (is_string($code) && (str_contains(strtolower($code), 'timeout') || str_contains(strtolower($code), 'soapfault')))
            || str_contains($normalizedMsg, 'timeout')
            || str_contains($normalizedMsg, 'timed out')
            || str_contains($normalizedMsg, 'could not connect to host')
            || str_contains($normalizedMsg, 'connection reset')
            || str_contains($normalizedMsg, 'soapfault')
        ) {
            return 'El servicio fiscal de ARCA no responde temporalmente. El pedido fue resguardado para reintentar.';
        }

        return match ($normalizedCode) {
            10015, '10015' => 'El CUIT o documento ingresado no es válido ante el padrón de ARCA.',
            10016, '10016' => 'Para ventas a Consumidor Final superiores al límite legal, es obligatorio ingresar el DNI o CUIT del cliente.',
            10043, '10043' => 'No se encontró el comprobante original asociado en ARCA para emitir la Nota de Crédito.',
            10013, '10013' => 'Los datos del concepto o período de facturación son incompatibles con la venta de productos.',
            10246, '10246' => 'La condición frente al IVA del receptor es obligatoria conforme a la RG 5616 de ARCA.',
            602, '602' => 'El comprobante consultado no fue emitido previamente en ARCA.',
            default => ! empty($originalMessage)
                ? 'Error fiscal de ARCA (Código '.$code.'): '.self::sanitizePublicMessage($originalMessage)
                : "Error fiscal de ARCA (Código {$code})",
        };
    }
}
