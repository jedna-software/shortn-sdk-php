<?php

declare(strict_types=1);

namespace Shortn\Sdk;

use Closure;
use InvalidArgumentException;

/**
 * Shortn API client.
 *
 * ```php
 * $shortn = new Client(apiKey: getenv('SHORTN_API_KEY'));
 * $links = $shortn->links->list(['limit' => 20]);
 * ```
 *
 * Every resource method returns the decoded JSON response as an array, so
 * fields added to the API later are reachable without an SDK update.
 */
final class Client
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://app.shortn.link';

    /** Top-level body fields the API reads as JSON objects, so an empty PHP array is sent as `{}`. */
    private const OBJECT_FIELDS = [
        'metadata',
        'config',
        'device_targets',
        'os_targets',
        'browser_targets',
        'language_targets',
        'attribution',
    ];

    public readonly LinksResource $links;
    public readonly AnalyticsResource $analytics;
    public readonly WorkspacesResource $workspaces;
    public readonly TagsResource $tags;
    public readonly UtmTemplatesResource $utmTemplates;
    public readonly DestinationsResource $destinations;
    public readonly TeamsResource $teams;
    public readonly DomainsResource $domains;
    public readonly ReportsResource $reports;
    public readonly ConversionsResource $conversions;
    /** Endpoints that need no API key. */
    public readonly PublicResource $public;

    private readonly ?string $apiKey;
    private readonly string $baseUrl;
    private readonly RetryOptions $retry;
    private readonly string $userAgent;
    private readonly Closure $transport;
    private readonly Closure $sleep;

    /** @var null|array{status:int,headers:array<string,string>} */
    private ?array $lastResponse = null;

    /**
     * @param null|string $apiKey a `sk_live_...` key; only the public helpers work without one
     * @param string $baseUrl the app's origin; the SDK appends `/api/v1`
     * @param float $timeout seconds per attempt
     * @param null|RetryOptions $retry retry policy; `RetryOptions::none()` turns retries off
     * @param null|string $userAgent appended to `shortn-sdk-php/<version>`
     * @param null|callable(string,string,array<string,string>,null|string,float):array{status:int,headers?:array<string,string|list<string>>,body?:string} $transport
     *        replaces the HTTP layer (tests, custom HTTP clients); receives method, URL, headers,
     *        body and timeout; must throw TransportException when no response was received
     * @param null|callable(float):void $sleep replaces the pause between retries (seconds)
     */
    public function __construct(
        ?string $apiKey = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly float $timeout = 30.0,
        ?RetryOptions $retry = null,
        ?string $userAgent = null,
        ?callable $transport = null,
        ?callable $sleep = null,
    ) {
        $apiKey = $apiKey === null ? null : trim($apiKey);
        $this->apiKey = $apiKey === '' ? null : $apiKey;
        $baseUrl = rtrim(trim($baseUrl), '/');
        if (str_ends_with($baseUrl, '/api/v1')) {
            $baseUrl = substr($baseUrl, 0, -strlen('/api/v1'));
        }
        if ($baseUrl === '') {
            throw new InvalidArgumentException('baseUrl must not be empty.');
        }
        $this->baseUrl = $baseUrl;
        $this->retry = $retry ?? new RetryOptions();
        $suffix = $userAgent === null ? '' : trim($userAgent);
        $this->userAgent = 'shortn-sdk-php/' . self::VERSION . ($suffix === '' ? '' : ' ' . $suffix);
        $this->transport = $transport === null
            ? Closure::fromCallable([HttpTransport::class, 'send'])
            : Closure::fromCallable($transport);
        $this->sleep = $sleep === null
            ? static function (float $seconds): void {
                if ($seconds > 0) {
                    usleep((int) round($seconds * 1_000_000));
                }
            }
            : Closure::fromCallable($sleep);

        $this->links = new LinksResource($this);
        $this->analytics = new AnalyticsResource($this);
        $this->workspaces = new WorkspacesResource($this);
        $this->tags = new TagsResource($this);
        $this->utmTemplates = new UtmTemplatesResource($this);
        $this->destinations = new DestinationsResource($this);
        $this->teams = new TeamsResource($this);
        $this->domains = new DomainsResource($this);
        $this->reports = new ReportsResource($this);
        $this->conversions = new ConversionsResource($this);
        $this->public = new PublicResource($this);
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== null;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Status and lower-cased headers of the last HTTP response received
     * (rate-limit headers, `x-analytics-as-of`, `shortn-request-id`, the QR content type).
     *
     * @return null|array{status:int,headers:array<string,string>}
     */
    public function lastResponse(): ?array
    {
        return $this->lastResponse;
    }

    /**
     * Send a JSON request to `/api/v1{$path}` and decode the answer. Use it for
     * endpoints the SDK does not wrap yet.
     *
     * @param array<string,mixed> $query null values are left out
     * @param null|array<string,mixed> $body top-level null values are left out; dates are sent as RFC 3339
     * @param bool $requireKey refuse to send without an API key
     * @return array<mixed> the decoded JSON body; `[]` for an empty body
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, bool $requireKey = true): array
    {
        $method = strtoupper($method);
        $payload = null;
        $headers = ['Accept' => 'application/json'];
        if ($body !== null) {
            $body = array_filter($body, static fn (mixed $value): bool => $value !== null);
            // No JSON_PRESERVE_ZERO_FRACTION: the API's integer fields refuse `1999.0`.
            $payload = json_encode(
                self::prepareBody($body),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->send($method, $path, $query, $headers, $payload, self::isRetryable($method, $body), $requireKey);
        $raw = $response['body'];
        if (trim($raw) === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $error) {
            throw new ShortnException('Shortn API returned a response that is not JSON: ' . $error->getMessage(), 0, $error);
        }
        return is_array($decoded) ? $decoded : ['value' => $decoded];
    }

    /**
     * Send a GET request and return the raw response body (for binary answers such as QR codes).
     *
     * @param array<string,mixed> $query
     */
    public function requestRaw(string $path, array $query = [], string $accept = '*/*', bool $requireKey = true): string
    {
        return $this->send('GET', $path, $query, ['Accept' => $accept], null, true, $requireKey)['body'];
    }

    /**
     * URL-escape one path segment. Empty values are refused, so a missing ID can
     * never turn `DELETE /links/{code}` into a request on another route.
     */
    public static function segment(string|int $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new InvalidArgumentException('Path parameters must not be empty.');
        }
        return rawurlencode($value);
    }

    /**
     * Build a query string from the parameters the caller set. Null values and
     * empty lists are left out, booleans become `1`/`0`, lists are joined
     * with commas and dates are sent as RFC 3339.
     *
     * @param array<string,mixed> $query
     */
    public static function buildQuery(array $query): string
    {
        $parts = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === []) {
                continue;
            }
            if (is_array($value)) {
                $value = implode(',', array_map([self::class, 'scalarToString'], $value));
            } else {
                $value = self::scalarToString($value);
            }
            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode($value);
        }
        return implode('&', $parts);
    }

    private static function scalarToString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::RFC3339);
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }
        throw new InvalidArgumentException('Query values must be scalars, dates or lists of them.');
    }

    /**
     * Shape a request body for JSON: an empty body or an empty array under a
     * key the API reads as an object becomes `{}`, and dates anywhere in the
     * body are sent as RFC 3339.
     *
     * @param array<string,mixed> $body
     */
    private static function prepareBody(array $body): array|\stdClass
    {
        if ($body === []) {
            return new \stdClass();
        }
        foreach ($body as $key => $value) {
            if ($value === [] && in_array($key, self::OBJECT_FIELDS, true)) {
                $body[$key] = new \stdClass();
            } else {
                $body[$key] = self::formatDates($value);
            }
        }
        return $body;
    }

    private static function formatDates(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::RFC3339);
        }
        if (is_array($value)) {
            return array_map([self::class, 'formatDates'], $value);
        }
        return $value;
    }

    /** @param null|array<string,mixed> $body */
    private static function isRetryable(string $method, ?array $body): bool
    {
        if (in_array($method, ['GET', 'PUT', 'DELETE'], true)) {
            return true;
        }
        if ($method === 'POST' && $body !== null) {
            // The API never deduplicates test-mode conversions, so a resent one
            // would be recorded twice, idempotency_key or not.
            if (($body['test_mode'] ?? null) === true) {
                return false;
            }
            $key = $body['idempotency_key'] ?? null;
            return is_string($key) && trim($key) !== '';
        }
        return false;
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    private function send(string $method, string $path, array $query, array $headers, ?string $payload, bool $retryable, bool $requireKey): array
    {
        if ($requireKey && $this->apiKey === null) {
            throw new ShortnException('This endpoint needs an API key: pass apiKey to the Client.');
        }
        $headers['User-Agent'] = $this->userAgent;
        if ($this->apiKey !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        if ($payload === null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $headers['Content-Length'] = '0';
        }
        $qs = self::buildQuery($query);
        $url = $this->baseUrl . '/api/v1' . $path . ($qs === '' ? '' : '?' . $qs);

        $attempt = 0;
        while (true) {
            try {
                $response = self::normalizeResponse(($this->transport)($method, $url, $headers, $payload, $this->timeout));
            } catch (TransportException $error) {
                if (!$retryable || !$this->canRetry($attempt)) {
                    throw $error;
                }
                ($this->sleep)($this->backoff($attempt));
                $attempt++;
                continue;
            }

            $this->lastResponse = ['status' => $response['status'], 'headers' => $response['headers']];
            if ($response['status'] >= 200 && $response['status'] < 300) {
                return $response;
            }

            $error = ApiError::fromResponse($response['status'], $response['body'], $response['headers']);
            $retryStatus = $response['status'] === 429 || $response['status'] === 503;
            if (!$retryStatus || !$retryable || !$this->canRetry($attempt)) {
                throw $error;
            }
            if ($error->retryAfter !== null && $error->retryAfter > $this->retry->maxRetryAfter) {
                throw $error;
            }
            ($this->sleep)($error->retryAfter ?? $this->backoff($attempt));
            $attempt++;
        }
    }

    private function canRetry(int $attempt): bool
    {
        return !$this->retry->disabled && $attempt < $this->retry->maxRetries;
    }

    private function backoff(int $attempt): float
    {
        $delay = min($this->retry->initialDelay * ($this->retry->backoffFactor ** $attempt), $this->retry->maxDelay);
        if ($this->retry->jitter && $delay > 0) {
            $delay *= 0.5 + (mt_rand() / mt_getrandmax()) * 0.5;
        }
        return max(0.0, (float) $delay);
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function normalizeResponse(mixed $response): array
    {
        if (!is_array($response) || !isset($response['status'])) {
            throw new ShortnException('The transport must return an array with a status.');
        }
        $headers = [];
        foreach ((array) ($response['headers'] ?? []) as $name => $value) {
            $headers[strtolower((string) $name)] = is_array($value) ? implode(', ', $value) : (string) $value;
        }
        return [
            'status' => (int) $response['status'],
            'headers' => $headers,
            'body' => (string) ($response['body'] ?? ''),
        ];
    }
}
