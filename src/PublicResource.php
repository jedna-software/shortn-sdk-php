<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * Endpoints that need no API key. A client with a key sends it here too.
 *
 * Creating a link without a key and the anonymous-link preview need a human
 * check passed on shortn.link, so they are not part of the SDK; with a key use
 * `links->create()` and `analytics->link()`.
 */
final class PublicResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Check whether a URL can be a link's destination. An unusable URL still
     * answers 200 with `valid: false`.
     *
     * @return array{valid:bool, normalized?:string, code?:string, error?:string, ...}
     */
    public function validateUrl(string $url): array
    {
        return $this->client->request('GET', '/validate', ['url' => $url], null, false);
    }

    /**
     * A QR code image as raw bytes (PNG by default). The content type is in
     * `Client::lastResponse()['headers']['content-type']`. Booleans such as
     * `centerLogo` are sent as `true`/`false`, the form this endpoint reads.
     *
     * @param array{url: string, format?: string, size?: string, colorMode?: string, fg?: string, bg?: string, centerLogo?: bool, ...} $params
     */
    public function qrCode(array $params): string
    {
        $params = array_map(static fn (mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value, $params);
        $format = strtolower((string) ($params['format'] ?? 'png'));
        $accept = match ($format) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };
        return $this->client->requestRaw('/qr', $params, $accept . ', */*;q=0.5', false);
    }

    /**
     * The plan catalog, keyed by plan ID. Fields are capitalized (`Price`, `MaxLinks`, ...).
     *
     * @return array{plans: array<string,array<string,mixed>>}
     */
    public function plans(): array
    {
        return $this->client->request('GET', '/plans', [], null, false);
    }
}
