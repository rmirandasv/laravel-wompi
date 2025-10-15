<?php

/**
 * EJEMPLO: Cómo probar un Controller que usa el paquete Wompi
 * 
 * Este archivo muestra cómo integrar el paquete Wompi en tu aplicación
 * Laravel y cómo escribir tests para tus controladores que lo utilizan.
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Rmirandasv\Wompi\WompiClient;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;

describe('Payment Controller', function () {

    // Ejemplo 1: Test de creación de enlace de pago
    it('creates payment link successfully', function () {
        // Mock de las respuestas de Wompi
        Http::fake([
            'https://id.wompi.sv/*' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*/EnlacePago' => Http::response([
                'id' => 'payment_123',
                'url' => 'https://wompi.sv/pay/payment_123',
                'monto' => 100.00,
                'descripcion' => 'Compra de producto',
            ], 201),
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
            return str_contains($request->url(), '/EnlacePago') &&
                   $request['monto'] === 100.00;
        });
    });

    // Ejemplo 2: Test de webhook handling
    it('processes successful payment webhook', function () {
        $webhookData = [
            'idTransaccion' => 'txn_webhook_123',
            'monto' => 250.00,
            'resultadoTransaccion' => 'ExitosaAprobada',
            'codigoAutorizacion' => 'AUTH123',
            'formaPago' => 'VISA',
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
            'idTransaccion' => 'txn_fake',
            'monto' => 999.99,
        ];

        // Enviar con hash inválido
        $response = $this->postJson('/api/webhooks/wompi', $webhookData, [
            'wompi_hash' => 'invalid_signature',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid signature',
            ]);

        // Verificar que NO se actualizó la base de datos
        $this->assertDatabaseMissing('orders', [
            'transaction_id' => 'txn_fake',
        ]);
    });

    // Ejemplo 4: Test de tokenización de tarjeta
    it('tokenizes credit card for subscription', function () {
        Http::fake([
            'https://id.wompi.sv/*' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*/Tokenizacion' => Http::response([
                'tokenId' => 'tok_customer_456',
                'ultimos4Digitos' => '4242',
                'tipoTarjeta' => 'VISA',
            ], 201),
        ]);

        $response = $this->postJson('/api/subscriptions/card', [
            'card_number' => '4242424242424242',
            'cvv' => '123',
            'exp_month' => '12',
            'exp_year' => '25',
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
            'card_type' => 'VISA',
        ]);
    });

    // Ejemplo 5: Test de cargo recurrente
    it('processes recurring charge for subscription', function () {
        // Crear método de pago tokenizado en la base de datos
        $paymentMethod = PaymentMethod::factory()->create([
            'token_id' => 'tok_existing_789',
            'last_four' => '4242',
        ]);

        Http::fake([
            'https://id.wompi.sv/*' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*/CargoRecurrente' => Http::response([
                'idTransaccion' => 'recurring_txn_001',
                'estado' => 'Aprobada',
                'monto' => 29.99,
                'codigoAutorizacion' => 'AUTH_REC_456',
            ], 201),
        ]);

        // Ejecutar comando de cargo recurrente
        $this->artisan('subscriptions:charge')
            ->assertSuccessful();

        // Verificar que se creó el registro de pago
        $this->assertDatabaseHas('subscription_payments', [
            'transaction_id' => 'recurring_txn_001',
            'status' => 'approved',
            'amount' => 29.99,
        ]);
    });

    // Ejemplo 6: Test de manejo de errores
    it('handles payment gateway errors gracefully', function () {
        Http::fake([
            'https://id.wompi.sv/*' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*/EnlacePago' => Http::response([
                'error' => 'invalid_amount',
                'message' => 'El monto debe ser mayor a 0',
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
            'esReal' => '1',
            'formaPago' => 'VISA',
            'esAprobada' => '1',
            'codigoAutorizacion' => 'AUTH789',
            'mensaje' => 'Transaccion Aprobada',
        ];

        // Calcular hash correcto
        $concatenated = implode('', array_values($redirectParams));
        $hash = hash_hmac('sha256', $concatenated, config('wompi.client_secret'));

        // Simular redirect GET
        $response = $this->get('/payments/callback?' . http_build_query($redirectParams) . '&hash=' . $hash);

        $response->assertStatus(200)
            ->assertViewIs('payments.success')
            ->assertViewHas('transaction_id', 'txn_redirect_123');
    });

    // Ejemplo 8: Test con Mock del WompiClient completo
    it('can mock the entire WompiClient service', function () {
        // Mock completo del servicio
        $mockClient = Mockery::mock(WompiClient::class);
        $mockClient->shouldReceive('createPaymentLink')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn([
                'id' => 'mocked_link_123',
                'url' => 'https://mocked.wompi.sv/pay',
            ]);

        $this->app->instance(WompiClient::class, $mockClient);

        // Usar el servicio mockeado
        $response = $this->postJson('/api/payments/create', [
            'amount' => 50.00,
            'description' => 'Test with mock',
        ]);

        $response->assertStatus(201);
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
use Rmirandasv\Wompi\WompiClient;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;

class PaymentController extends Controller
{
    public function __construct(
        private WompiClient $wompi
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
                'descripcion' => $validated['description'],
                'urlRedirect' => route('payments.callback'),
                'urlWebhook' => route('webhooks.wompi'),
            ]);

            // Guardar en base de datos
            $order = Order::create([
                'payment_id' => $paymentLink['id'],
                'amount' => $validated['amount'],
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_link' => $paymentLink['url'],
                    'payment_id' => $paymentLink['id'],
                ],
            ], 201);

        } catch (PaymentGatewayException $e) {
            return response()->json([
                'error' => 'Payment processing failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function handleWebhook(Request $request)
    {
        try {
            $data = $this->wompi->validateWebhookRequest($request);
            
            if ($this->wompi->isSuccessfulPayment($data)) {
                Order::where('transaction_id', $data['idTransaccion'])
                    ->update([
                        'status' => 'paid',
                        'authorization_code' => $data['codigoAutorizacion'] ?? null,
                    ]);
            }

            return response()->json(['success' => true]);

        } catch (PaymentGatewayException $e) {
            return response()->json([
                'error' => 'Invalid signature',
            ], 400);
        }
    }

    public function handleCallback(Request $request)
    {
        $params = $request->except('hash');
        $hash = $request->get('hash');

        if (!$this->wompi->validateRedirectParams($params, $hash)) {
            abort(400, 'Invalid signature');
        }

        if ($params['esAprobada'] === '1') {
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

