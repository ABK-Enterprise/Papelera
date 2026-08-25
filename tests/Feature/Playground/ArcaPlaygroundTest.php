<?php

use App\Models\User;
use App\Playground\Arca\WsaaService;
use App\Playground\Arca\WsfeService;
use Inertia\Testing\AssertableInertia as Assert;

test('unauthenticated guests cannot access arca playground', function () {
    $this->get(route('playground.arca'))
        ->assertRedirect(route('login'));

    $this->postJson(route('playground.arca.ultimo-comprobante'), [])
        ->assertUnauthorized();
});

test('playground arca page can be rendered by superadmin', function () {
    $superadmin = User::factory()->create(['is_superadmin' => true]);

    $response = $this->actingAs($superadmin)->get(route('playground.arca'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('playground/Arca')
            ->has('catalogos')
            ->has('catalogos.comprobantes')
            ->has('catalogos.conceptos')
            ->has('catalogos.condicionesIvaReceptor')
            ->has('catalogos.alicuotasIva')
        );
});

test('wsaa service generates valid tra xml', function () {
    $wsaa = new WsaaService;
    $xml = $wsaa->generarTraXml('wsfe');

    expect($xml)->toContain('<service>wsfe</service>')
        ->toContain('<destination>')
        ->toContain('<uniqueId>')
        ->toContain('<generationTime>')
        ->toContain('<expirationTime>');
});

test('wsfe service validates equation error correctly', function () {
    $wsfe = app(WsfeService::class);

    expect(fn () => $wsfe->validarEcuacionContable([
        'imp_total' => 1210.00,
        'imp_neto' => 1000.00,
        'imp_op_ex' => 0.00,
        'imp_tot_conc' => 0.00,
        'imp_iva' => 100.00, // Invalid: should be 210
        'imp_trib' => 0.00,
    ]))->toThrow(Exception::class);
});

test('wsfe service accepts valid equation', function () {
    $wsfe = app(WsfeService::class);

    $wsfe->validarEcuacionContable([
        'imp_total' => 1210.00,
        'imp_neto' => 1000.00,
        'imp_op_ex' => 0.00,
        'imp_tot_conc' => 0.00,
        'imp_iva' => 210.00,
        'imp_trib' => 0.00,
        'iva' => [
            ['id' => 5, 'base_imp' => 1000.00, 'importe' => 210.00],
        ],
    ]);

    expect(true)->toBeTrue();
});

test('consultar ultimo comprobante endpoint validates input', function () {
    $superadmin = User::factory()->create(['is_superadmin' => true]);

    $response = $this->actingAs($superadmin)->postJson(route('playground.arca.ultimo-comprobante'), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['pto_vta', 'cbte_tipo']);
});

test('emitir factura endpoint validates required fields', function () {
    $superadmin = User::factory()->create(['is_superadmin' => true]);

    $response = $this->actingAs($superadmin)->postJson(route('playground.arca.emitir'), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['pto_vta', 'cbte_tipo', 'concepto', 'doc_tipo', 'doc_nro', 'imp_total']);
});
