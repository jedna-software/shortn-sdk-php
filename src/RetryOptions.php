<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * When and how the client retries a request.
 *
 * Retries happen on 429 and 503 responses and on network errors, and only for
 * GET, PUT and DELETE requests and for POST requests whose JSON body carries an
 * `idempotency_key`. A `Retry-After` header is honoured when it is no longer
 * than `maxRetryAfter` seconds; a longer wait is returned to the caller as the error.
 */
final class RetryOptions
{
    public function __construct(
        public readonly int $maxRetries = 2,
        public readonly float $initialDelay = 0.5,
        public readonly float $maxDelay = 8.0,
        public readonly float $backoffFactor = 2.0,
        public readonly bool $jitter = true,
        public readonly float $maxRetryAfter = 60.0,
        public readonly bool $disabled = false,
    ) {
    }

    /** Turn retries off. */
    public static function none(): self
    {
        return new self(maxRetries: 0, disabled: true);
    }
}
