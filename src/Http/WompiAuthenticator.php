<?php

namespace Rmirandasv\Wompi\Http;

use Illuminate\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Rmirandasv\Wompi\Exceptions\WompiAuthenticationException;

/**
 * Obtains and caches the Wompi OAuth2 access token.
 *
 * The token is cached so that we do not hit the identity provider on every
 * call. The cache key is namespaced by client id, so two different Wompi
 * applications never share a token.
 */
class WompiAuthenticator
{
    private const LOCK_SECONDS = 10;

    public function __construct(
        private readonly string $authUrl,
        private readonly string $audience,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly int $ttlMargin = 60,
        private readonly ?string $cacheStore = null,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {
    }

    /**
     * Return a valid access token, fetching a new one when needed.
     */
    public function token(): string
    {
        $cached = $this->cache()->get($this->cacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            return $this->cache()->lock($this->lockKey(), self::LOCK_SECONDS)->get(
                function (): string {
                    // Another process may have refreshed the token while we
                    // were waiting for the lock.
                    $cached = $this->cache()->get($this->cacheKey());

                    if (is_string($cached) && $cached !== '') {
                        return $cached;
                    }

                    return $this->refresh();
                }
            );
        } catch (LockTimeoutException) {
            // The lock could not be acquired in time. Rather than failing the
            // request we fetch the token directly.
            return $this->refresh();
        }
    }

    /**
     * Forget the cached token, forcing the next call to request a new one.
     */
    public function invalidate(): void
    {
        $this->cache()->forget($this->cacheKey());
    }

    /**
     * Request a fresh token and store it in the cache.
     */
    private function refresh(): string
    {
        [$token, $expiresIn] = $this->fetch();

        $ttl = max(60, $expiresIn - $this->ttlMargin);

        $this->cache()->put($this->cacheKey(), $token, $ttl);

        return $token;
    }

    /**
     * Perform the OAuth2 client_credentials handshake.
     *
     * @return array{0: string, 1: int}  The token and its lifetime in seconds.
     */
    private function fetch(): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->post($this->authUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'audience' => $this->audience,
                ]);
        } catch (ConnectionException $e) {
            throw new WompiAuthenticationException(
                'Could not reach the Wompi authentication endpoint',
                0,
                $e
            );
        }

        if ($response->failed()) {
            throw new WompiAuthenticationException(
                'Failed to authenticate with Wompi',
                $response->status(),
                null,
                $response->json(),
                $response->status()
            );
        }

        $data = $response->json();
        $token = is_array($data) ? ($data['access_token'] ?? null) : null;

        if (! is_string($token) || $token === '') {
            throw new WompiAuthenticationException(
                'Wompi did not return an access token',
                $response->status(),
                null,
                is_array($data) ? $data : null,
                $response->status()
            );
        }

        $expiresIn = is_array($data) ? (int) ($data['expires_in'] ?? 3600) : 3600;

        return [$token, $expiresIn];
    }

    private function cache(): CacheRepository
    {
        return Cache::store($this->cacheStore);
    }

    private function cacheKey(): string
    {
        return $this->prefix().':'.sha1($this->clientId);
    }

    private function lockKey(): string
    {
        return $this->prefix().':lock:'.sha1($this->clientId);
    }

    private function prefix(): string
    {
        return trim((string) config('wompi.token_cache.prefix', 'wompi:access_token'), ':');
    }
}
