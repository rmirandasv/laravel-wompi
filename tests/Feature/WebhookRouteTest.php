<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Rmirandasv\Wompi\Events\WompiPaymentProcessed;
use Rmirandasv\Wompi\Events\WompiWebhookReceived;
use Rmirandasv\Wompi\Facades\Wompi;

/**
 * A stand-in for the published WompiWebhookController.
 */
class WompiTestWebhookController
{
    public function __invoke(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        Wompi::handleWebhook($request);

        return response()->json(['status' => 'ok'], 200);
    }
}

describe('Webhook endpoint', function () {
    beforeEach(function () {
        Route::post('/webhooks/wompi', WompiTestWebhookController::class)
            ->middleware('wompi.webhook');
    });

    it('accepts a correctly signed webhook', function () {
        Event::fake();

        $payload = wompi_webhook_payload();
        $body = json_encode($payload);
        $hash = hash_hmac('sha256', $body, 'test_client_secret');

        $response = $this->call(
            'POST',
            '/webhooks/wompi',
            [],
            [],
            [],
            ['HTTP_WOMPI_HASH' => $hash, 'CONTENT_TYPE' => 'application/json'],
            $body
        );

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        Event::assertDispatchedTimes(WompiWebhookReceived::class, 1);
        Event::assertDispatchedTimes(WompiPaymentProcessed::class, 1);
    });

    it('rejects a webhook with an invalid signature', function () {
        Event::fake();

        $body = json_encode(wompi_webhook_payload());

        $response = $this->call(
            'POST',
            '/webhooks/wompi',
            [],
            [],
            [],
            ['HTTP_WOMPI_HASH' => 'wrong_hash', 'CONTENT_TYPE' => 'application/json'],
            $body
        );

        $response->assertStatus(403);

        Event::assertNotDispatched(WompiWebhookReceived::class);
        Event::assertNotDispatched(WompiPaymentProcessed::class);
    });

    it('rejects a webhook without the signature header', function () {
        $body = json_encode(wompi_webhook_payload());

        $response = $this->call(
            'POST',
            '/webhooks/wompi',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $body
        );

        $response->assertStatus(400)
            ->assertJson(['error' => 'Webhook request invalid: wompi_hash header is required']);
    });

    it('rejects a webhook with an empty body', function () {
        $response = $this->call(
            'POST',
            '/webhooks/wompi',
            [],
            [],
            [],
            ['HTTP_WOMPI_HASH' => 'some_hash', 'CONTENT_TYPE' => 'application/json'],
            ''
        );

        $response->assertStatus(400)
            ->assertJson(['error' => 'Webhook request invalid: body is required']);
    });

    it('does not dispatch the events twice when the handler is called again', function () {
        Event::fake();

        $payload = wompi_webhook_payload();
        $body = json_encode($payload);
        $hash = hash_hmac('sha256', $body, 'test_client_secret');

        $this->call(
            'POST',
            '/webhooks/wompi',
            [],
            [],
            [],
            ['HTTP_WOMPI_HASH' => $hash, 'CONTENT_TYPE' => 'application/json'],
            $body
        );

        Event::assertDispatchedTimes(WompiWebhookReceived::class, 1);
        Event::assertDispatchedTimes(WompiPaymentProcessed::class, 1);
    });

    it('never marks a test transaction as a successful payment', function () {
        Event::fake();

        $payload = wompi_webhook_payload(['EsProductiva' => false]);
        $body = json_encode($payload);
        $hash = hash_hmac('sha256', $body, 'test_client_secret');

        $this->call(
            'POST',
            '/webhooks/wompi',
            [],
            [],
            [],
            ['HTTP_WOMPI_HASH' => $hash, 'CONTENT_TYPE' => 'application/json'],
            $body
        );

        Event::assertDispatched(WompiPaymentProcessed::class, function ($event) {
            return $event->isSuccessful === false
                && $event->webhook->isTestTransaction() === true;
        });
    });

    it('exposes the normalized payload to the listener', function () {
        Event::fake();

        $payload = wompi_webhook_payload();
        $body = json_encode($payload);
        $hash = hash_hmac('sha256', $body, 'test_client_secret');

        $this->call(
            'POST',
            '/webhooks/wompi',
            [],
            [],
            [],
            ['HTTP_WOMPI_HASH' => $hash, 'CONTENT_TYPE' => 'application/json'],
            $body
        );

        Event::assertDispatched(WompiPaymentProcessed::class, function ($event) use ($payload) {
            return $event->webhook->reference() === 'OC1234'
                && $event->webhook->raw() === $payload
                && $event->isSuccessful === true;
        });
    });
});

describe('Publishable webhook assets', function () {
    it('ships a route file that applies the signature middleware', function () {
        $route = (string) file_get_contents(__DIR__.'/../../routes/wompi.php');

        expect($route)->toContain("middleware(['wompi.webhook'])")
            ->and($route)->toContain('withoutMiddleware(\'throttle:api\')')
            ->and($route)->toContain('WompiWebhookController');
    });

    it('ships a controller stub that dispatches the events', function () {
        $stub = (string) file_get_contents(__DIR__.'/../../stubs/WompiWebhookController.stub');

        expect($stub)->toContain('Wompi::handleWebhook($request)')
            ->and($stub)->toContain('namespace App\Http\Controllers;');
    });

    it('registers the wompi.webhook middleware alias', function () {
        $router = app('router');
        $aliases = $router->getMiddleware();

        expect($aliases)->toHaveKey('wompi.webhook');
    });
});
