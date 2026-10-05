<?php

use App\Exceptions\ArcaAuthenticationException;
use App\Services\Arca\ArcaAuthService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Cache::flush();
    $storageFiles = glob(storage_path('app/arca/ta_*_*.json'));
    foreach ($storageFiles as $file) {
        @unlink($file);
    }
});

afterEach(function () {
    $storageFiles = glob(storage_path('app/arca/ta_*_*.json'));
    foreach ($storageFiles as $file) {
        @unlink($file);
    }
});

test('it throws exception when certificate or key file does not exist', function () {
    $service = new ArcaAuthService;

    $service->getAccessTicket(
        cuit: '20463191280',
        certPath: '/non/existent/cert.crt',
        keyPath: '/non/existent/key.key'
    );
})->throws(ArcaAuthenticationException::class, 'El certificado digital de ARCA no está cargado');

test('it creates valid TRA XML with required tags and clock skew adjustment', function () {
    $service = new ArcaAuthService;
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('createTraXml');
    $method->setAccessible(true);

    Carbon::setTestNow('2026-09-13 15:00:00');

    $xmlStr = $method->invoke($service, 'wsfe');

    expect($xmlStr)->toContain('<loginTicketRequest version="1.0">')
        ->toContain('<service>wsfe</service>')
        ->toContain('<uniqueId>')
        ->toContain('<generationTime>2026-09-13T14:50:00')
        ->toContain('<expirationTime>2026-09-13T15:10:00');
});

test('it correctly parses loginTicketResponse XML into ticket array', function () {
    $service = new ArcaAuthService;
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseLoginTicketResponse');
    $method->setAccessible(true);

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <loginTicketResponse version="1.0">
        <header>
            <source>CN=wsaahomo, O=AFIP, C=AR, SERIALNUMBER=CUIT 33693450239</source>
            <destination>SERIALNUMBER=CUIT 20463191280, CN=papelera</destination>
            <uniqueId>123456789</uniqueId>
            <generationTime>2026-09-13T14:50:00-03:00</generationTime>
            <expirationTime>2026-09-14T02:50:00-03:00</expirationTime>
        </header>
        <credentials>
            <token>MOCK_TOKEN_BASE64_XYZ</token>
            <sign>MOCK_SIGN_BASE64_ABC</sign>
        </credentials>
    </loginTicketResponse>';

    $ticket = $method->invoke($service, $xml, '20463191280');

    expect($ticket)->toBeArray()
        ->and($ticket['token'])->toBe('MOCK_TOKEN_BASE64_XYZ')
        ->and($ticket['sign'])->toBe('MOCK_SIGN_BASE64_ABC')
        ->and($ticket['expiration'])->toBe('2026-09-14T02:50:00-03:00')
        ->and($ticket['cuit'])->toBe('20463191280');
});

test('it reuses cached ticket without calling WSAA when ticket is still valid', function () {
    $cuit = '20463191280';
    $serviceName = 'wsfe';
    $env = config('arca.environment', 'testing');
    $cacheKey = "arca_ta_{$cuit}_{$serviceName}_{$env}";

    $validTicket = [
        'token' => 'CACHED_TOKEN_123',
        'sign' => 'CACHED_SIGN_456',
        'expiration' => Carbon::now()->addHours(6)->toIso8601String(),
        'cuit' => $cuit,
    ];

    Cache::put($cacheKey, $validTicket, 41400);

    // Create a mock SoapClient that must NEVER be called
    $mockSoapClient = Mockery::mock(SoapClient::class);
    $mockSoapClient->shouldNotReceive('loginCms');

    $service = new ArcaAuthService;
    $service->setSoapClient($mockSoapClient);

    $retrievedTicket = $service->getAccessTicket($cuit, service: $serviceName);

    expect($retrievedTicket)->toBe($validTicket);
});

test('it calls WSAA loginCms, parses response, and saves ticket in cache when cache is empty', function () {
    $cuit = '20463191280';
    $serviceName = 'wsfe';
    $env = config('arca.environment', 'testing');
    $cacheKey = "arca_ta_{$cuit}_{$serviceName}_{$env}";

    $fakeXmlResponse = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <loginTicketResponse version="1.0">
        <header>
            <source>CN=wsaahomo, O=AFIP, C=AR</source>
            <destination>SERIALNUMBER=CUIT 20463191280</destination>
            <uniqueId>987654321</uniqueId>
            <generationTime>2026-09-13T14:50:00-03:00</generationTime>
            <expirationTime>'.Carbon::now()->addHours(11)->toIso8601String().'</expirationTime>
        </header>
        <credentials>
            <token>NEW_FRESH_TOKEN_789</token>
            <sign>NEW_FRESH_SIGN_012</sign>
        </credentials>
    </loginTicketResponse>';

    $mockSoapClient = Mockery::mock(SoapClient::class);
    $mockSoapClient->shouldReceive('loginCms')
        ->once()
        ->with(Mockery::on(function ($args) {
            return isset($args['in0']) && is_string($args['in0']) && strlen($args['in0']) > 50;
        }))
        ->andReturn((object) ['loginCmsReturn' => $fakeXmlResponse]);

    $service = new ArcaAuthService;
    $service->setSoapClient($mockSoapClient);

    $ticket = $service->getAccessTicket($cuit, service: $serviceName);

    expect($ticket['token'])->toBe('NEW_FRESH_TOKEN_789')
        ->and($ticket['sign'])->toBe('NEW_FRESH_SIGN_012')
        ->and(Cache::get($cacheKey))->toBe($ticket);
});

test('it throws ArcaAuthenticationException when SoapFault occurs and no disk fallback exists', function () {
    $cuit = '20463191280';
    $serviceName = 'wsfe';

    $mockSoapClient = Mockery::mock(SoapClient::class);
    $mockSoapClient->shouldReceive('loginCms')
        ->once()
        ->andThrow(new SoapFault('ns1:cms.bad.signature', 'Firma digital inválida'));

    $service = new ArcaAuthService;
    $service->setSoapClient($mockSoapClient);

    $service->getAccessTicket($cuit, service: $serviceName);
})->throws(ArcaAuthenticationException::class, 'Error en la autenticación con ARCA (WSAA): Firma digital inválida');

test('it recovers ticket from persistent disk cache when Laravel cache was flushed', function () {
    $cuit = '20463191280';
    $serviceName = 'wsfe';
    $env = config('arca.environment', 'testing');
    $cacheKey = "arca_ta_{$cuit}_{$serviceName}_{$env}";
    $storageFilePath = storage_path("app/arca/ta_{$cuit}_{$serviceName}_{$env}.json");

    $diskTicket = [
        'token' => 'DISK_TOKEN_ABC',
        'sign' => 'DISK_SIGN_DEF',
        'expiration' => Carbon::now()->addHours(8)->toIso8601String(),
        'cuit' => $cuit,
    ];

    @file_put_contents($storageFilePath, json_encode($diskTicket));

    // SoapClient should never be invoked because disk cache is valid
    $mockSoapClient = Mockery::mock(SoapClient::class);
    $mockSoapClient->shouldNotReceive('loginCms');

    $service = new ArcaAuthService;
    $service->setSoapClient($mockSoapClient);

    $ticket = $service->getAccessTicket($cuit, service: $serviceName);

    expect($ticket)->toBe($diskTicket)
        ->and(Cache::get($cacheKey))->toBe($diskTicket);
});

test('it handles coe.alreadyAuthenticated by recovering existing ticket from disk file', function () {
    $cuit = '20463191280';
    $serviceName = 'wsfe';
    $env = config('arca.environment', 'testing');
    $storageFilePath = storage_path("app/arca/ta_{$cuit}_{$serviceName}_{$env}.json");

    $diskTicket = [
        'token' => 'DISK_RECOVERED_TOKEN',
        'sign' => 'DISK_RECOVERED_SIGN',
        'expiration' => Carbon::now()->addHours(4)->toIso8601String(),
        'cuit' => $cuit,
    ];

    @file_put_contents($storageFilePath, json_encode($diskTicket));

    $mockSoapClient = Mockery::mock(SoapClient::class);
    $mockSoapClient->shouldReceive('loginCms')
        ->once()
        ->andThrow(new SoapFault('ns1:coe.alreadyAuthenticated', 'El CEE ya posee un TA valido para el acceso al WSN solicitado'));

    $service = new ArcaAuthService;
    $service->setSoapClient($mockSoapClient);

    $ticket = $service->requestNewTicketFromWsaa(
        cuit: $cuit,
        certPath: config('arca.testing.cert_path'),
        keyPath: config('arca.testing.key_path'),
        passphrase: '',
        service: $serviceName
    );

    expect($ticket)->toBe($diskTicket);
});

test('it encodes and decodes certificate and key to base64 with exact byte parity', function () {
    $certPath = storage_path('app/arca/MiPedidoCSR.crt');
    $keyPath = storage_path('app/arca/Papelera.key');

    expect(file_exists($certPath))->toBeTrue()
        ->and(file_exists($keyPath))->toBeTrue();

    $originalCert = file_get_contents($certPath);
    $originalKey = file_get_contents($keyPath);

    $encodedCert = base64_encode($originalCert);
    $encodedKey = base64_encode($originalKey);

    $decodedCert = base64_decode($encodedCert, true);
    $decodedKey = base64_decode($encodedKey, true);

    expect($decodedCert)->toBe($originalCert)
        ->and($decodedKey)->toBe($originalKey)
        ->and(strlen($decodedCert))->toBe(strlen($originalCert))
        ->and(strlen($decodedKey))->toBe(strlen($originalKey))
        ->and(md5($decodedCert))->toBe(md5($originalCert))
        ->and(md5($decodedKey))->toBe(md5($originalKey));

    // Verify OpenSSL can parse both in-memory decoded strings
    $certParsed = openssl_x509_parse($decodedCert);
    $keyParsed = openssl_pkey_get_private($decodedKey);

    expect($certParsed)->toBeArray()
        ->and($keyParsed)->not->toBeFalse();
});

test('it resolves and authenticates with WSAA in production using base64 credentials', function () {
    $certContent = file_get_contents(storage_path('app/arca/MiPedidoCSR.crt'));
    $keyContent = file_get_contents(storage_path('app/arca/Papelera.key'));
    $cuit = '30712345678';

    config([
        'arca.environment' => 'production',
        'arca.production.cuit' => $cuit,
        'arca.production.cert_base64' => base64_encode($certContent),
        'arca.production.key_base64' => base64_encode($keyContent),
        'arca.production.cert_content' => null,
        'arca.production.key_content' => null,
        'arca.production.cert_path' => null,
        'arca.production.key_path' => null,
    ]);

    $service = new ArcaAuthService;

    expect($service->resolveCertificateContent())->toBe($certContent)
        ->and($service->resolvePrivateKeyContent())->toBe($keyContent)
        ->and($service->resolveCuit())->toBe($cuit);

    $fakeXmlResponse = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <loginTicketResponse version="1.0">
        <header>
            <source>CN=wsaa, O=AFIP, C=AR</source>
            <destination>SERIALNUMBER=CUIT '.$cuit.'</destination>
            <uniqueId>555666777</uniqueId>
            <generationTime>'.Carbon::now()->subMinutes(5)->toIso8601String().'</generationTime>
            <expirationTime>'.Carbon::now()->addHours(11)->toIso8601String().'</expirationTime>
        </header>
        <credentials>
            <token>PROD_BASE64_TOKEN_999</token>
            <sign>PROD_BASE64_SIGN_888</sign>
        </credentials>
    </loginTicketResponse>';

    $mockSoapClient = Mockery::mock(SoapClient::class);
    $mockSoapClient->shouldReceive('loginCms')
        ->once()
        ->with(Mockery::on(function ($args) {
            return isset($args['in0']) && is_string($args['in0']) && strlen($args['in0']) > 50;
        }))
        ->andReturn((object) ['loginCmsReturn' => $fakeXmlResponse]);

    $service->setSoapClient($mockSoapClient);

    $ticket = $service->getAccessTicket($cuit, service: 'wsfe');

    expect($ticket['token'])->toBe('PROD_BASE64_TOKEN_999')
        ->and($ticket['sign'])->toBe('PROD_BASE64_SIGN_888')
        ->and($ticket['cuit'])->toBe($cuit);
});

test('it resolves and authenticates with WSAA in production using raw PEM content credentials', function () {
    $certContent = file_get_contents(storage_path('app/arca/MiPedidoCSR.crt'));
    $keyContent = file_get_contents(storage_path('app/arca/Papelera.key'));
    $cuit = '30712345678';

    config([
        'arca.environment' => 'production',
        'arca.production.cuit' => $cuit,
        'arca.production.cert_base64' => null,
        'arca.production.key_base64' => null,
        'arca.production.cert_content' => $certContent,
        'arca.production.key_content' => $keyContent,
        'arca.production.cert_path' => null,
        'arca.production.key_path' => null,
    ]);

    $service = new ArcaAuthService;

    expect($service->resolveCertificateContent())->toBe($certContent)
        ->and($service->resolvePrivateKeyContent())->toBe($keyContent);

    $fakeXmlResponse = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <loginTicketResponse version="1.0">
        <header>
            <source>CN=wsaa, O=AFIP, C=AR</source>
            <destination>SERIALNUMBER=CUIT '.$cuit.'</destination>
            <uniqueId>111222333</uniqueId>
            <generationTime>'.Carbon::now()->subMinutes(5)->toIso8601String().'</generationTime>
            <expirationTime>'.Carbon::now()->addHours(11)->toIso8601String().'</expirationTime>
        </header>
        <credentials>
            <token>PROD_PEM_TOKEN_111</token>
            <sign>PROD_PEM_SIGN_222</sign>
        </credentials>
    </loginTicketResponse>';

    $mockSoapClient = Mockery::mock(SoapClient::class);
    $mockSoapClient->shouldReceive('loginCms')
        ->once()
        ->andReturn((object) ['loginCmsReturn' => $fakeXmlResponse]);

    $service->setSoapClient($mockSoapClient);

    $ticket = $service->getAccessTicket($cuit, service: 'wsfe');

    expect($ticket['token'])->toBe('PROD_PEM_TOKEN_111')
        ->and($ticket['sign'])->toBe('PROD_PEM_SIGN_222');
});

test('it prioritizes explicit override over base64 and pem content', function () {
    $certContent = file_get_contents(storage_path('app/arca/MiPedidoCSR.crt'));
    $fakeCert = "-----BEGIN CERTIFICATE-----\nFAKE_OVERRIDE_CERT\n-----END CERTIFICATE-----";

    config([
        'arca.environment' => 'production',
        'arca.production.cert_base64' => base64_encode($certContent),
        'arca.production.cert_content' => $certContent,
    ]);

    $service = new ArcaAuthService;

    expect($service->resolveCertificateContent($fakeCert))->toBe($fakeCert)
        ->and($service->resolveCertificateContent())->toBe($certContent);
});
