<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\AbstractLogger;
use Rmirandasv\Wompi\Contracts\WompiClientInterface;

/**
 * A logger that keeps every record in memory so the tests can inspect it.
 */
class WompiRecordingLogger extends AbstractLogger
{
    /**
     * @var array<int, array{level: mixed, message: string, context: array}>
     */
    public array $records = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * Everything that was written, serialized.
     */
    public function dump(): string
    {
        return json_encode($this->records);
    }
}

describe('Wompi logging security', function () {
    beforeEach(function () {
        $this->logger = new WompiRecordingLogger;

        Log::swap($this->logger);
    });

    it('never writes a card number or a cvv to the log', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([], 200),
        ]);

        app(WompiClientInterface::class)->createTransaction3DS(wompi_3ds_payload());

        $dump = $this->logger->dump();

        expect($dump)->not->toContain('4111111111111111')
            ->and($dump)->not->toContain('"cvv":"123"');
    });

    it('never writes a card number to the log even when payloads are enabled', function () {
        config()->set('wompi.log_payloads', true);

        // Rebuild the client so it picks the new configuration.
        app()->forgetInstance(\Rmirandasv\Wompi\Contracts\WompiClientInterface::class);

        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([], 200),
        ]);

        app(WompiClientInterface::class)->createTransaction3DS(wompi_3ds_payload());

        $dump = $this->logger->dump();

        expect($dump)->not->toContain('4111111111111111')
            ->and($dump)->toContain('4111********1111');
    });

    it('does not log the request payload by default', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([], 200),
        ]);

        app(WompiClientInterface::class)->createPaymentLink([
            'identificadorEnlaceComercio' => 'ORDER-1',
            'nombreProducto' => 'Test',
            'monto' => 10,
        ]);

        $dump = $this->logger->dump();

        expect($dump)->toContain('[not logged]')
            ->and($dump)->not->toContain('ORDER-1');
    });

    it('logs at debug level instead of info', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([], 200),
        ]);

        app(WompiClientInterface::class)->getAplicativoData();

        $levels = array_column($this->logger->records, 'level');

        expect($levels)->toContain('debug')
            ->and($levels)->not->toContain('info');
    });

    it('does not log the response headers of a failed request', function () {
        Http::fake([
            'https://id.wompi.sv/connect/token' => Http::response([
                'access_token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'https://api.wompi.sv/*' => Http::response([
                'servicioError' => 'EnlacePago',
                'mensajes' => ['Error'],
            ], 400),
        ]);

        try {
            app(WompiClientInterface::class)->createPaymentLink([
                'identificadorEnlaceComercio' => 'ORDER-1',
                'nombreProducto' => 'Test',
            ]);
        } catch (\Throwable) {
            // Expected.
        }

        $dump = $this->logger->dump();

        expect($dump)->not->toContain('headers')
            ->and($dump)->not->toContain('test_token');
    });
});
