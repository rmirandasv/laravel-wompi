<?php

namespace Rmirandasv\Wompi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Rmirandasv\Wompi\Contracts\WompiClientInterface;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;
use Rmirandasv\Wompi\Support\PayloadNormalizer;
use Rmirandasv\Wompi\Webhooks\WompiWebhookHandler;
use Illuminate\Support\Facades\Log;

/**
 * Verifies the HMAC signature of an incoming Wompi webhook.
 *
 * The middleware authenticates the request and stores the normalized payload
 * on it. It does not dispatch the events: that is the responsibility of the
 * application layer, which guarantees a single dispatch per request.
 */
class VerifyWompiWebhookSignature
{
    public function __construct(protected WompiClientInterface $wompi)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $wompiHash = $request->header('wompi_hash');

        if (! is_string($wompiHash) || $wompiHash === '') {
            return response()->json(['error' => 'Webhook request invalid: wompi_hash header is required'], 400);
        }

        $body = $request->getContent();

        if (! is_string($body) || $body === '') {
            return response()->json(['error' => 'Webhook request invalid: body is required'], 400);
        }

        try {
            if (! $this->wompi->validateWebhookSignature($body, $wompiHash)) {
                Log::warning('Wompi webhook signature is invalid', [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Webhook request invalid: signature is invalid'], 403);
            }
        } catch (PaymentGatewayException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return response()->json(['error' => 'Webhook request invalid: json is invalid'], 400);
        }

        $request->attributes->set(
            WompiWebhookHandler::PAYLOAD_ATTRIBUTE,
            PayloadNormalizer::normalize($decoded)
        );

        return $next($request);
    }
}
