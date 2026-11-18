<?php

/**
 * EJEMPLO: Cómo probar un Controller que usa el paquete Wompi
 *
 * Este archivo muestra cómo integrar el paquete Wompi en tu aplicación
 * Laravel y cómo escribir tests para sus controladores.
 *
 * Nota: los ejemplos usan el contrato de la v2 del paquete. Consulta
 * UPGRADE.md para el mapeo desde la v1.
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\Events\WompiPaymentProcessed;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;

describe('Payment Controller', function () {

    // Ejemplo 1: Test de creación de enlace de pago
    it('creates payment link successfully', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/EnlacePago' => Http::response([
                'idEnlace' => 1384854,
                'urlEnlace' => 'https://wompi.sv/pay/1384854',
                'urlQrCodeEnlace' => 'https://wompi.sv/qr/1384854',
                'estaProductivo' => true,
            ], 200),
        ]);

        // Simular request POST a tu controlador
        $response = $this->postJson('/api/payments/create', [
            'amount' => 100.00,
            'description' => 'Compra de producto',
            'customer_email' => 'cliente@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'payment_link',
                    'payment_id',
                ],
            ]);

        // Verificar que se llamó a Wompi correctamente
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/EnlacePago')
                && $request['monto'] === 100.00
                && $request['identificadorEnlaceComercio'] === 'ORDER-123';
        });
    });

    // Ejemplo 2: Test de webhook handling
    it('processes successful payment webhook', function () {
        // El webhook real de Wompi viene en PascalCase.
        $webhookData = [
            'IdTransaccion' => 'txn_webhook_123',
            'Monto' => 250.00,
            'ResultadoTransaccion' => 'ExitosaAprobada',
            'CodigoAutorizacion' => 'AUTH123',
            'FormaPagoUtilizada' => 'TarjetaCreditoDebito',
            'EsProductiva' => true,
            'EnlacePago' => [
                'Id' => 66,
                'IdentificadorEnlaceComercio' => 'ORDER-123',
                'NombreProducto' => 'Compra de producto',
            ],
        ];

        $webhookBody = json_encode($webhookData);

        // Calcular el hash como lo haría Wompi
        $wompiHash = hash_hmac('sha256', $webhookBody, config('wompi.client_secret'));

        // Simular webhook POST de Wompi
        $response = $this->postJson('/api/webhooks/wompi', $webhookData, [
            'wompi_hash' => $wompiHash,
        ]);

        $response->assertStatus(200);

        // Verificar que la orden se actualizó en la base de datos
        $this->assertDatabaseHas('orders', [
            'transaction_id' => 'txn_webhook_123',
            'status' => 'paid',
            'amount' => 250.00,
        ]);
    });

    // Ejemplo 3: Test de webhook con firma inválida
    it('rejects webhook with invalid signature', function () {
        $webhookData = [
            'IdTransaccion' => 'txn_fake',
            'Monto' => 999.99,
        ];

        // Enviar con hash inválido
        $response = $this->postJson('/api/webhooks/wompi', $webhookData, [
            'wompi_hash' => 'invalid_signature',
        ]);

        // El middleware responde 403: nunca 200, para no ocultar un ataque.
        $response->assertStatus(403);

        // Verificar que NO se actualizó la base de datos
        $this->assertDatabaseMissing('orders', [
            'transaction_id' => 'txn_fake',
        ]);
    });

    // Ejemplo 4: Test de tokenización de tarjeta
    it('tokenizes credit card for subscription', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/Tokenizacion' => Http::response([
                'token' => 'tok_customer_456',
                'tarjetaEnmascarada' => '424242******4242',
            ], 200),
        ]);

        $response = $this->postJson('/api/subscriptions/card', [
            'card_number' => '4242424242424242',
            'cvv' => '123',
            'exp_month' => 12,
            'exp_year' => 2030,
            'card_holder' => 'Juan Perez',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verificar que el token se guardó en la base de datos
        $this->assertDatabaseHas('payment_methods', [
            'token_id' => 'tok_customer_456',
            'last_four' => '4242',
        ]);
    });

    // Ejemplo 5: Test del enlace de pago recurrente (suscripción)
    it('creates a recurring payment link for a subscription', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/EnlacePagoRecurrente' => Http::response([
                'idEnlace' => 'rec-1',
                'urlEnlace' => 'https://wompi.sv/s/rec-1',
                'urlQrCodeEnlace' => 'https://wompi.sv/qr/rec-1',
                'estaProductivo' => true,
            ], 200),
        ]);

        $response = $this->postJson('/api/subscriptions', [
            'day_of_payment' => 5,
            'name' => 'Plan mensual',
            'amount' => 29.99,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.id', 'rec-1');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/EnlacePagoRecurrente')
                && $request['diaDePago'] === 5
                && $request['monto'] === 29.99;
        });
    });

    // Ejemplo 6: Test de manejo de errores
    it('handles payment gateway errors gracefully', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/EnlacePago' => Http::response([
                'servicioError' => 'EnlacePago',
                'subTipoError' => 'DATOS_INVALIDOS',
                'mensajes' => ['El monto debe ser mayor a 0'],
            ], 400),
        ]);

        $response = $this->postJson('/api/payments/create', [
            'amount' => -10.00,
            'description' => 'Invalid amount',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Payment processing failed',
            ]);
    });

    // Ejemplo 7: Test de validación de redirect URL
    it('validates redirect from payment gateway', function () {
        $redirectParams = [
            'idTransaccion' => 'txn_redirect_123',
            'monto' => '100.00',
            'esReal' => 'True',
            'formaPago' => 'TarjetaCreditoDebito',
            'esAprobada' => 'True',
            'codigoAutorizacion' => 'AUTH789',
            'mensaje' => 'Transaccion Aprobada',
        ];

        // Calcular hash correcto
        $concatenated = implode('', array_values($redirectParams));
        $hash = hash_hmac('sha256', $concatenated, config('wompi.client_secret'));

        // Simular redirect GET
        $response = $this->get('/payments/callback?'.http_build_query($redirectParams).'&hash='.$hash);

        $response->assertStatus(200)
            ->assertViewIs('payments.success')
            ->assertViewHas('transaction_id', 'txn_redirect_123');
    });

    // Ejemplo 8: Test con Mock del WompiClient completo
    it('can mock the entire WompiClient service', function () {
        $mockClient = \Mockery::mock(\Rmirandasv\Wompi\Contracts\WompiClientInterface::class);
        $mockClient->shouldReceive('createPaymentLink')
            ->once()
            ->with(\Mockery::type('array'))
            ->andReturn(new \Rmirandasv\Wompi\DTOs\Responses\PaymentLinkResponseDTO([
                'idEnlace' => 1384854,
                'urlEnlace' => 'https://mocked.wompi.sv/pay',
            ]));

        $this->app->instance(\Rmirandasv\Wompi\Contracts\WompiClientInterface::class, $mockClient);

        // Usar el servicio mockeado
        $response = $this->postJson('/api/payments/create', [
            'amount' => 50.00,
            'description' => 'Test with mock',
        ]);

        $response->assertStatus(201);
    });

    // Ejemplo 9: Test del evento con una transacción de prueba
    it('never marks a test transaction as paid', function () {
        Illuminate\Support\Facades\Event::fake();

        $webhookData = [
            'IdTransaccion' => 'txn_test_123',
            'Monto' => 250.00,
            'ResultadoTransaccion' => 'ExitosaAprobada',
            // EsProductiva false => fue una transacción de prueba, no se cobró.
            'EsProductiva' => false,
            'EnlacePago' => [
                'IdentificadorEnlaceComercio' => 'ORDER-123',
            ],
        ];

        $webhookBody = json_encode($webhookData);
        $wompiHash = hash_hmac('sha256', $webhookBody, config('wompi.client_secret'));

        $this->postJson('/api/webhooks/wompi', $webhookData, [
            'wompi_hash' => $wompiHash,
        ])->assertStatus(200);

        Illuminate\Support\Facades\Event::assertDispatched(
            WompiPaymentProcessed::class,
            fn ($event) => $event->isSuccessful === false
                && $event->webhook->isTestTransaction() === true
        );

        $this->assertDatabaseMissing('orders', [
            'transaction_id' => 'txn_test_123',
            'status' => 'paid',
        ]);
    });
});

/**
 * EJEMPLO DE CONTROLLER
 *
 * Aquí está el controlador que se está probando arriba:
 */

/*
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rmirandasv\Wompi\Contracts\WompiClientInterface;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;
use Rmirandasv\Wompi\Facades\Wompi;

class PaymentController extends Controller
{
    public function __construct(
        private WompiClientInterface $wompi
    ) {}

    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string',
            'customer_email' => 'required|email',
        ]);

        try {
            $paymentLink = $this->wompi->createPaymentLink([
                'monto' => $validated['amount'],
                'identificadorEnlaceComercio' => 'ORDER-123',
                'nombreProducto' => $validated['description'],
                'configuracion' => [
                    'urlRedirect' => route('payments.callback'),
                    'urlWebhook' => route('wompi.webhook'),
                ],
            ]);

            // Guardar en base de datos
            $order = Order::create([
                'payment_id' => $paymentLink->idEnlace,
                'amount' => $validated['amount'],
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_link' => $paymentLink->urlEnlace,
                    'payment_id' => $paymentLink->idEnlace,
                ],
            ], 201);

        } catch (PaymentGatewayException $e) {
            return response()->json([
                'error' => 'Payment processing failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function handleCallback(Request $request)
    {
        $params = $request->query();

        if (! $this->wompi->validateRedirectParams($params, (string) $request->input('hash'))) {
            abort(403, 'Invalid signature');
        }

        if ($this->wompi->isRedirectApproved($params)) {
            return view('payments.success', [
                'transaction_id' => $params['idTransaccion'],
                'amount' => $params['monto'],
            ]);
        }

        return view('payments.failed', [
            'message' => $params['mensaje'],
        ]);
    }
}
*/
