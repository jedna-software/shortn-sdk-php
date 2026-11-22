<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * The API answered with a non-2xx status.
 *
 * `errorCode` is the stable `code` of the error envelope (null only for an
 * answer that didn't come from the API, such as a proxy's 502 page),
 * `getMessage()` the readable `error`,
 * `field` the request field at fault (`details.field` or a top-level `field`).
 * `body` is the decoded JSON body (including plan-limit fields such as
 * `feature` and `required_plan`) and `rawBody` the body as received.
 */
final class ApiError extends ShortnException
{
    /**
     * @param array<string,string> $headers lower-cased response header names
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $errorCode = null,
        public readonly ?string $hint = null,
        public readonly ?string $field = null,
        public readonly mixed $details = null,
        public readonly mixed $body = null,
        public readonly string $rawBody = '',
        public readonly ?string $requestId = null,
        public readonly ?float $retryAfter = null,
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Build the error from a response.
     *
     * @param array<string,string> $headers lower-cased response header names
     */
    public static function fromResponse(int $status, string $rawBody, array $headers = []): self
    {
        $decoded = null;
        if ($rawBody !== '') {
            try {
                $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $decoded = null;
            }
        }
        $envelope = is_array($decoded) ? $decoded : [];

        $message = self::stringOrNull($envelope['error'] ?? null)
            ?? self::stringOrNull($envelope['message'] ?? null)
            ?? sprintf('Shortn API request failed with HTTP %d.', $status);
        $details = $envelope['details'] ?? null;
        $field = self::stringOrNull($envelope['field'] ?? null);
        if ($field === null && is_array($details)) {
            $field = self::stringOrNull($details['field'] ?? null);
        }

        return new self(
            message: $message,
            statusCode: $status,
            errorCode: self::stringOrNull($envelope['code'] ?? null),
            hint: self::stringOrNull($envelope['hint'] ?? null),
            field: $field,
            details: $details,
            body: $decoded ?? ($rawBody === '' ? null : $rawBody),
            rawBody: $rawBody,
            requestId: self::stringOrNull($envelope['request_id'] ?? null) ?? ($headers['shortn-request-id'] ?? null),
            retryAfter: self::parseRetryAfter($headers['retry-after'] ?? null),
            headers: $headers,
        );
    }

    /** Seconds from a `Retry-After` header (delta-seconds or HTTP date). */
    public static function parseRetryAfter(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }
        $time = strtotime($value);
        if ($time === false) {
            return null;
        }
        return (float) max(0, $time - time());
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return null;
    }
}
