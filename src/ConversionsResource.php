<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * Conversions: `/api/v1/conversions`. Scopes `conversions:write` (send and validate
 * events) and `conversions:read` (reporting, analytics rate limit).
 *
 * Call these from trusted server code. Unknown body fields are refused with
 * `400 invalid_request_format`. A POST that carries an `idempotency_key` is
 * retried on 429, 503 and network errors; without one it is sent once. A
 * `test_mode` POST is always sent once: the API never deduplicates test events.
 *
 * @phpstan-type LeadParams array{
 *     click_id: string,
 *     idempotency_key: string,
 *     event_name?: string,
 *     lead_category?: string,
 *     event_quantity?: int,
 *     metadata?: array<string,mixed>,
 *     occurred_at?: string,
 *     test_mode?: bool,
 *     event_type?: string,
 *     ...
 * }
 * @phpstan-type SaleParams array{
 *     click_id: string,
 *     currency: string,
 *     amount_cents?: int,
 *     amount?: string,
 *     invoice_id?: string,
 *     idempotency_key?: string,
 *     settlement_amount_cents?: int,
 *     settlement_currency?: string,
 *     payment_processor?: string,
 *     event_name?: string,
 *     metadata?: array<string,mixed>,
 *     occurred_at?: string,
 *     test_mode?: bool,
 *     ...
 * }
 * @phpstan-type RefundParams array{
 *     invoice_id: string,
 *     refund_id: string,
 *     amount_cents?: int,
 *     amount?: string,
 *     currency?: string,
 *     reason?: string,
 *     payment_processor?: string,
 *     idempotency_key?: string,
 *     event_name?: string,
 *     metadata?: array<string,mixed>,
 *     occurred_at?: string,
 *     test_mode?: bool,
 *     ...
 * }
 * @phpstan-type ReportQuery array{
 *     from?: string|\DateTimeInterface,
 *     to?: string|\DateTimeInterface,
 *     environment?: string,
 *     limit?: int,
 *     offset?: int,
 *     interval?: string,
 *     event_type?: string,
 *     lead_category?: string,
 *     event_origin?: string,
 *     link?: string,
 *     event?: string,
 *     q?: string,
 *     ...
 * }
 */
final class ConversionsResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param LeadParams $params
     * @return array{type:string, event: array<string,mixed>, ...}
     */
    public function trackLead(array $params): array
    {
        return $this->post('/conversions/leads', $params);
    }

    /**
     * Run the lead checks without recording anything.
     *
     * @param LeadParams $params
     * @return array{valid:bool, dry_run:bool, result: array<string,mixed>, ...}
     */
    public function validateLead(array $params): array
    {
        return $this->post('/conversions/leads/validations', $params);
    }

    /**
     * Send `amount_cents` (hundredths of the main unit) or `amount`, not both.
     * `amount` must be a string such as `'19.99'`: a PHP float is refused with
     * `400 invalid_request_format`.
     *
     * @param SaleParams $params
     * @return array{type:string, event: array<string,mixed>, ...}
     */
    public function trackSale(array $params): array
    {
        return $this->post('/conversions/sales', $params);
    }

    /**
     * @param SaleParams $params
     * @return array{valid:bool, dry_run:bool, result: array<string,mixed>, ...}
     */
    public function validateSale(array $params): array
    {
        return $this->post('/conversions/sales/validations', $params);
    }

    /**
     * A refund names its sale by `invoice_id`. Send `amount_cents` or `amount`;
     * `amount` must be a string such as `'19.99'`, not a PHP float.
     *
     * @param RefundParams $params
     * @return array{type:string, event: array<string,mixed>, ...}
     */
    public function trackRefund(array $params): array
    {
        return $this->post('/conversions/refunds', $params);
    }

    /**
     * @param RefundParams $params
     * @return array{valid:bool, dry_run:bool, result: array<string,mixed>, ...}
     */
    public function validateRefund(array $params): array
    {
        return $this->post('/conversions/refunds/validations', $params);
    }

    /**
     * All-time live totals.
     *
     * @param ReportQuery $query
     * @return array{data: array<string,mixed>}
     */
    public function summary(array $query = []): array
    {
        return $this->client->request('GET', '/conversions/summary', $query);
    }

    /**
     * One row per attribution target, with `totals`, `pagination` and `date_range`.
     *
     * @param ReportQuery $query
     * @return array{data: list<array<string,mixed>>, ...}
     */
    public function attribution(array $query = []): array
    {
        return $this->client->request('GET', '/conversions/attribution', $query);
    }

    /**
     * One point per `interval`, with `date_range`.
     *
     * @param ReportQuery $query
     * @return array{data: list<array<string,mixed>>, ...}
     */
    public function timeline(array $query = []): array
    {
        return $this->client->request('GET', '/conversions/timeline', $query);
    }

    /**
     * Events, newest first, with `pagination`, `date_range` and `environment`.
     *
     * @param ReportQuery $query
     * @return array{data: list<array<string,mixed>>, pagination: array<string,mixed>, ...}
     */
    public function events(array $query = []): array
    {
        return $this->client->request('GET', '/conversions/events', $query);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<mixed>
     */
    private function post(string $path, array $params): array
    {
        return $this->client->request('POST', $path, [], $params);
    }
}
