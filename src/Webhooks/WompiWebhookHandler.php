<?php

namespace Rmirandasv\Wompi\Webhooks;

use Illuminate\Http\Request;
use Rmirandasv\Wompi\Events\WompiPaymentProcessed;
use Rmirandasv\Wompi\Events\WompiWebhookReceived;
use Rmirandasv\Wompi\Exceptions\ConfigurationException;
use Rmirandasv\Wompi\Exceptions\PaymentGatewayException;
use Rmirandasv\Wompi\Support\PayloadNormalizer;

/**
 * Validates, normalizes and dispatches the Wompi webhook events.
 *
 * This is the single place where the events are dispatched, so an application
 * always receives exactly one WompiWebhookReceived and one
 * WompiPaymentProcessed per webhook, no matter whether it relies on the
 * "wompi.webhook" middleware, on the published controller or on a direct call
 * to handleWebhook().
 */
class WompiWebhookHandler
{
    /**
     * The attribute holding the validated and normalized payload.
     */
    public const PAYLOAD_ATTRIBUTE = 'wompi_webhook';

    /**
     * The attribute flagging that the events were already dispatched.
     */
    public const DISPATCHED_ATTRIBUTE = 'wompi_webhook_dispatched';

    public function __construct(private readonly string $clientSecret)
    {
    }

    /**
     * Validate the request, normalize the payload and dispatch the events.
     */
    public function handle(Request $request): WompiWebhook
    {
        $webhook = $request->attributes->get(self::PAYLOAD_ATTRIBUTE);

        if (! $webhook instanceof WompiWebhook) {
            $webhook = $this->validateAndNormalize($request);
            $request->attributes->set(self::PAYLOAD_ATTRIBUTE, $webhook);
        }

        if (! $request->attributes->get(self::DISPATCHED_ATTRIBUTE, false)) {
            $request->attributes->set(self::DISPATCHED_ATTRIBUTE, true);

            event(new WompiWebhookReceived($webhook->raw(), $webhook));
            event(new WompiPaymentProcessed($webhook->raw(), $webhook->isSuccessful(), $webhook));
        }

        return $webhook;
    }

    /**
     * Validate the HMAC signature of a raw body.
     */
    public function validateSignature(string $body, string $receivedHash): bool
    {
        if ($this->clientSecret === '') {
            throw new ConfigurationException('Wompi client secret is not set');
        }

        if ($receivedHash === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $this->clientSecret), $receivedHash);
    }

    /**
     * Read the signature header of a webhook request.
     */
    public function signatureOf(Request $request): ?string
    {
        $hash = $request->header('wompi_hash');

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    /**
     * Validate the request and build the normalized payload.
     */
    private function validateAndNormalize(Request $request): WompiWebhook
    {
        $hash = $this->signatureOf($request);

        if ($hash === null) {
            throw new PaymentGatewayException('Webhook request invalid: wompi_hash header is required');
        }

        $body = $request->getContent();

        if (! is_string($body) || $body === '') {
            throw new PaymentGatewayException('Webhook request invalid: body is required');
        }

        if (! $this->validateSignature($body, $hash)) {
            throw new PaymentGatewayException('Webhook request invalid: signature is invalid');
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new PaymentGatewayException('Webhook request invalid: json is invalid');
        }

        return PayloadNormalizer::normalize($decoded);
    }
}
