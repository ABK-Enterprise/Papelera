<?php

namespace App\Services\Arca;

class ArcaErrorTranslator
{
    /**
     * Translate an ARCA/AFIP error code or SoapFault to a user-friendly, domain-specific message.
     */
    public static function translate(int|string $code, ?string $originalMessage = null): string
    {
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
            602, '602' => 'El comprobante consultado no fue emitido previamente en ARCA.',
            default => ! empty($originalMessage)
                ? "Error fiscal de ARCA (Código {$code}): {$originalMessage}"
                : "Error fiscal de ARCA (Código {$code})",
        };
    }
}
