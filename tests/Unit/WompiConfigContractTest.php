<?php

/**
 * The configuration of the package must point at the real Wompi endpoints.
 *
 * These assertions exist because v1 shipped with two wrong defaults:
 * "https://id.wompi.sv" (which serves an HTML login page, not a token) and
 * "https://api.wompi.sv/v1" (which returns 404 on every path).
 */
describe('Wompi configuration contract', function () {
    it('does not use a /v1 prefix on the API base url', function () {
        $apiUrl = (string) config('wompi.api_url');

        expect($apiUrl)->not->toEndWith('/v1')
            ->and($apiUrl)->not->toContain('/v1/')
            ->and($apiUrl)->toStartWith('https://');
    });

    it('points the auth url at the oauth token endpoint', function () {
        $authUrl = (string) config('wompi.auth_url');

        expect($authUrl)->toEndWith('/connect/token');
    });

    it('uses the wompi_api audience', function () {
        expect(config('wompi.audience'))->toBe('wompi_api');
    });

    it('does not expose a dead webhook_secret option', function () {
        expect(config('wompi.webhook_secret'))->toBeNull();
    });

    it('requires a patched PHP version', function () {
        $composer = json_decode(
            (string) file_get_contents(__DIR__.'/../../composer.json'),
            true
        );

        $constraint = $composer['require']['php'];

        expect($constraint)->toContain('8.2.35')
            ->and($constraint)->toContain('8.3.36')
            ->and($constraint)->toContain('8.4.27')
            ->and($constraint)->toContain('8.5.12');
    });

    it('installs stable packages only', function () {
        $composer = json_decode(
            (string) file_get_contents(__DIR__.'/../../composer.json'),
            true
        );

        expect($composer['minimum-stability'])->toBe('stable');
    });

    it('does not log payloads by default', function () {
        expect(config('wompi.log_payloads'))->toBeFalse();
    });
});
