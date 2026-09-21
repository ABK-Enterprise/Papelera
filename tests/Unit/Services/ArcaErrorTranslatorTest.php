<?php

use App\Services\Arca\ArcaErrorTranslator;
use Tests\TestCase;

uses(TestCase::class);

test('it translates code 10015 to invalid CUIT message', function () {
    expect(ArcaErrorTranslator::translate(10015))
        ->toBe('El CUIT o documento ingresado no es válido ante el padrón de ARCA.')
        ->and(ArcaErrorTranslator::translate('10015'))
        ->toBe('El CUIT o documento ingresado no es válido ante el padrón de ARCA.');
});

test('it translates code 10016 to final consumer identification required message', function () {
    expect(ArcaErrorTranslator::translate(10016))
        ->toBe('Para ventas a Consumidor Final superiores al límite legal, es obligatorio ingresar el DNI o CUIT del cliente.')
        ->and(ArcaErrorTranslator::translate('10016'))
        ->toBe('Para ventas a Consumidor Final superiores al límite legal, es obligatorio ingresar el DNI o CUIT del cliente.');
});

test('it translates code 10043 to missing original comprobante for credit note message', function () {
    expect(ArcaErrorTranslator::translate(10043))
        ->toBe('No se encontró el comprobante original asociado en ARCA para emitir la Nota de Crédito.')
        ->and(ArcaErrorTranslator::translate('10043'))
        ->toBe('No se encontró el comprobante original asociado en ARCA para emitir la Nota de Crédito.');
});

test('it translates code 10013 to incompatible concept or period message', function () {
    expect(ArcaErrorTranslator::translate(10013))
        ->toBe('Los datos del concepto o período de facturación son incompatibles con la venta de productos.')
        ->and(ArcaErrorTranslator::translate('10013'))
        ->toBe('Los datos del concepto o período de facturación son incompatibles con la venta de productos.');
});

test('it translates code 602 to comprobante not issued previously message', function () {
    expect(ArcaErrorTranslator::translate(602))
        ->toBe('El comprobante consultado no fue emitido previamente en ARCA.')
        ->and(ArcaErrorTranslator::translate('602'))
        ->toBe('El comprobante consultado no fue emitido previamente en ARCA.');
});

test('it translates SoapFault and timeouts to service unavailable message', function () {
    expect(ArcaErrorTranslator::translate('SoapFault'))
        ->toBe('El servicio fiscal de ARCA no responde temporalmente. El pedido fue resguardado para reintentar.')
        ->and(ArcaErrorTranslator::translate('timeout'))
        ->toBe('El servicio fiscal de ARCA no responde temporalmente. El pedido fue resguardado para reintentar.')
        ->and(ArcaErrorTranslator::translate(500, 'Could not connect to host (timeout)'))
        ->toBe('El servicio fiscal de ARCA no responde temporalmente. El pedido fue resguardado para reintentar.')
        ->and(ArcaErrorTranslator::translate('soap_fault', 'SoapFault occurred'))
        ->toBe('El servicio fiscal de ARCA no responde temporalmente. El pedido fue resguardado para reintentar.');
});

test('it translates default unmapped codes with original message or code description', function () {
    expect(ArcaErrorTranslator::translate(99999, 'Error de prueba AFIP'))
        ->toBe('Error fiscal de ARCA (Código 99999): Error de prueba AFIP')
        ->and(ArcaErrorTranslator::translate(99999))
        ->toBe('Error fiscal de ARCA (Código 99999)');
});
