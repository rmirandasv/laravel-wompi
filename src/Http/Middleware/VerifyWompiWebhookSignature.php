<?php

namespace Rmirandasv\Wompi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Rmirandasv\Wompi\Contracts\WompiClientInterface;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;

class VerifyWompiWebhookSignature
{
    public function __construct(protected WompiClientInterface $wompi)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $wompiHash = $request->header('wompi_hash');

        if (! $wompiHash) {
            return response()->json(['error' => 'Webhook request invalid: wompi_hash header is required'], 400);
        }

        $body = $request->getContent();

        if (empty($body)) {
            return response()->json(['error' => 'Webhook request invalid: body is required'], 400);
        }

        try {
            if (! $this->wompi->validateWebhookSignature($body, $wompiHash)) {
                return response()->json(['error' => 'Webhook request invalid: signature is invalid'], 403);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return $next($request);
    }
}
