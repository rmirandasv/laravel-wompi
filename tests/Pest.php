<?php

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Feature');

/**
 * The trimmed copy of the Wompi OpenAPI specification used by the contract
 * tests. It is refreshed by the "Refresh Wompi spec" workflow.
 *
 * @return array<string, mixed>
 */
function wompi_spec(): array
{
    static $spec = null;

    if ($spec === null) {
        $spec = json_decode(
            (string) file_get_contents(__DIR__.'/Fixtures/wompi-swagger.json'),
            true
        );
    }

    return $spec;
}

/**
 * Whether Wompi documents the given verb and path template.
 */
function wompi_spec_has(string $verb, string $pathTemplate): bool
{
    $paths = wompi_spec()['paths'] ?? [];

    return isset($paths[$pathTemplate][strtolower($verb)]);
}

/**
 * Whether a concrete path matches a path template such as "EnlacePago/{id}".
 */
function wompi_path_matches(string $template, string $actual): bool
{
    $parts = preg_split('#(\{[^/}]+\})#', $template, -1, PREG_SPLIT_DELIM_CAPTURE);

    $regex = '';

    foreach ($parts as $part) {
        if ($part !== '' && $part[0] === '{') {
            $regex .= '[^/]+';
        } else {
            $regex .= preg_quote($part, '#');
        }
    }

    return preg_match('#^'.$regex.'$#', $actual) === 1;
}

/**
 * Run a client method against a fake API and return the recorded request.
 *
 * @return array{verb: string, path: string, query: string, body: array, headers: array}
 */
function wompi_record(callable $call): array
{
    Http::fake([
        'https://id.wompi.sv/connect/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ], 200),
        'https://api.wompi.sv/*' => Http::response([], 200),
    ]);

    $client = app(\Rmirandasv\Wompi\Contracts\WompiClientInterface::class);

    $call($client);

    foreach (Http::recorded() as [$request, $response]) {
        if (str_starts_with($request->url(), 'https://api.wompi.sv/')) {
            return [
                'verb' => $request->method(),
                'path' => (string) parse_url($request->url(), PHP_URL_PATH),
                'query' => $request->url(),
                'body' => $request->data(),
                'headers' => $request->headers(),
            ];
        }
    }

    throw new RuntimeException('No request was recorded against the Wompi API.');
}

/**
 * Count the recorded requests that match the given filter.
 */
function wompi_count_sent(callable $filter): int
{
    return count(collect(Http::recorded())->filter(
        fn ($pair) => $filter($pair[0])
    ));
}

/**
 * A minimal but valid 3DS payload.
 *
 * @return array<string, mixed>
 */
function wompi_3ds_payload(): array
{
    return [
        'monto' => 25.50,
        'tarjetaCreditoDebido' => [
            'numeroTarjeta' => '4111111111111111',
            'cvv' => '123',
            'mesVencimiento' => 12,
            'anioVencimiento' => 2030,
        ],
        'urlRedirect' => 'https://example.com/payment/return',
        'nombre' => 'JUAN',
        'apellido' => 'PEREZ',
        'email' => 'juan@example.com',
        'ciudad' => 'SAN SALVADOR',
        'direccion' => 'Calle 1 #2-3',
        'idPais' => 'SV',
        'idRegion' => 'SV-SS',
        'codigoPostal' => '1101',
        'telefono' => '70000000',
    ];
}

/**
 * A webhook payload in the PascalCase shape Wompi actually sends.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function wompi_webhook_payload(array $overrides = []): array
{
    return array_merge([
        'IdCuenta' => '980b36e6-15ab-463f-4444-ada3e396fe48',
        'FechaTransaccion' => '2020-07-07T21:27:03.3403497-06:00',
        'Monto' => 1,
        'ModuloUtilizado' => 'BotonPago',
        'FormaPagoUtilizada' => 'PagoNormal',
        'IdTransaccion' => '2bedafea-0924-49f0-927d-8c638e193990',
        'ResultadoTransaccion' => 'ExitosaAprobada',
        'CodigoAutorizacion' => 'ba7dbfd3-50d1-403c-bbfd-d3be5dc766f8',
        'IdIntentoPago' => 'c6e10505-cada-4ae7-9892-d8786b7f455f',
        'Cantidad' => 1,
        'EsProductiva' => true,
        'Aplicativo' => [
            'Nombre' => 'Sitio Web Bitworks',
            'Url' => 'https://www.bitworks.com.sv/',
            'Id' => 'd432aef2-3333-4a75-4444-22e20789834a',
        ],
        'EnlacePago' => [
            'Id' => 66,
            'IdentificadorEnlaceComercio' => 'OC1234',
            'NombreProducto' => 'Camisa Azula',
        ],
        'cliente' => [
            'Nombre' => 'JUAN PEREZ',
            'Email' => 'juan@example.com',
        ],
    ], $overrides);
}
