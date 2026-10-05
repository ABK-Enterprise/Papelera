<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ARCA (AFIP) Environment
    |--------------------------------------------------------------------------
    |
    | Define whether the system communicates with ARCA's testing (homologación)
    | or production web services. Defaults to 'testing'.
    |
    */
    'environment' => env('ARCA_ENVIRONMENT', env('ARCA_ENV', 'testing')),

    /*
    |--------------------------------------------------------------------------
    | WSAA (Web Service de Autenticación y Autorización)
    |--------------------------------------------------------------------------
    |
    | Configuration for ticket generation, authentication CMS endpoints and
    | token caching duration. ARCA tickets are valid for 12 hours; we cache
    | for 11h 30m (41,400 seconds) to avoid edge-of-expiry rejections.
    |
    */
    'wsaa' => [
        'homologation_url' => env('ARCA_WSAA_HOMO_URL', 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms'),
        'production_url' => env('ARCA_WSAA_PROD_URL', 'https://wsaa.afip.gov.ar/ws/services/LoginCms'),
        'wsdl_homologation' => env('ARCA_WSAA_HOMO_WSDL', 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL'),
        'wsdl_production' => env('ARCA_WSAA_PROD_WSDL', 'https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL'),
        'token_cache_ttl' => (int) env('ARCA_TOKEN_CACHE_TTL', 41400), // 11h 30m in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | WSFEv1 (Facturación Electrónica Nacional)
    |--------------------------------------------------------------------------
    |
    | Endpoints for the electronic invoicing service version 1 (RG 4291).
    |
    */
    'wsfe' => [
        'homologation_url' => env('ARCA_WSFE_HOMO_URL', 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx'),
        'production_url' => env('ARCA_WSFE_PROD_URL', 'https://servicios1.afip.gov.ar/wsfev1/service.asmx'),
        'wsdl_homologation' => env('ARCA_WSFE_HOMO_WSDL', 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL'),
        'wsdl_production' => env('ARCA_WSFE_PROD_WSDL', 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL'),
        'service_name' => 'wsfe',
        'timeout' => (int) env('ARCA_WSFE_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Production Credentials (12-Factor App & File Fallbacks)
    |--------------------------------------------------------------------------
    |
    | Credentials for production environment. Supports direct Base64 encoded
    | strings, raw PEM multiline strings from environment variables, or physical
    | file paths.
    |
    */
    'production' => [
        'cuit' => (string) env('ARCA_PROD_CUIT', env('ARCA_CUIT', '')),
        'cert_base64' => env('ARCA_CERT_BASE64'),
        'cert_content' => env('ARCA_CERT_CONTENT'),
        'cert_path' => env('ARCA_CERT_PATH'),
        'key_base64' => env('ARCA_KEY_BASE64'),
        'key_content' => env('ARCA_KEY_CONTENT'),
        'key_path' => env('ARCA_KEY_PATH'),
        'passphrase' => env('ARCA_PASSPHRASE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing / Homologation Default Credentials
    |--------------------------------------------------------------------------
    |
    | Used as default fallback in testing environment.
    |
    */
    'testing' => [
        'cuit' => (string) env('ARCA_TESTING_CUIT', env('ARCA_CUIT', '20463191280')),
        'punto_venta' => (int) env('ARCA_TESTING_PTO_VTA', 1),
        'cert_base64' => env('ARCA_TESTING_CERT_BASE64', env('ARCA_CERT_BASE64')),
        'cert_content' => env('ARCA_TESTING_CERT_CONTENT', env('ARCA_CERT_CONTENT')),
        'cert_path' => env('ARCA_TESTING_CERT_PATH', env('ARCA_CERT_PATH', file_exists(storage_path('app/arca/MiPedidoCSR.crt')) ? storage_path('app/arca/MiPedidoCSR.crt') : null)),
        'key_base64' => env('ARCA_TESTING_KEY_BASE64', env('ARCA_KEY_BASE64')),
        'key_content' => env('ARCA_TESTING_KEY_CONTENT', env('ARCA_KEY_CONTENT')),
        'key_path' => env('ARCA_TESTING_KEY_PATH', env('ARCA_KEY_PATH', file_exists(storage_path('app/arca/Papelera.key')) ? storage_path('app/arca/Papelera.key') : null)),
        'passphrase' => env('ARCA_TESTING_PASSPHRASE', env('ARCA_PASSPHRASE', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legal Thresholds for Anonymous Final Consumers (DocTipo 99)
    |--------------------------------------------------------------------------
    |
    | If the order total exceeds these amounts, ARCA mandates identifying
    | the buyer with DNI (DocTipo 96) or CUIT (DocTipo 80).
    |
    */
    'thresholds' => [
        'anonymous_consumer_cash_limit' => (float) env('ARCA_ANONYMOUS_CASH_LIMIT', 191624.00),
        'anonymous_consumer_electronic_limit' => (float) env('ARCA_ANONYMOUS_ELECTRONIC_LIMIT', 383248.00),
    ],

    'consumer_final_thresholds' => [
        'cash' => (float) env('ARCA_CONSUMER_FINAL_CASH_THRESHOLD', 10000000.00),
    ],

    /*
    |--------------------------------------------------------------------------
    | Official QR Configuration (RG 4892/2020)
    |--------------------------------------------------------------------------
    |
    | Base URL to construct the official AFIP verification QR code.
    |
    */
    'qr' => [
        'base_url' => 'https://www.afip.gob.ar/fe/qr/?p=',
    ],
];
