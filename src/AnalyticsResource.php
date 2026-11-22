<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * Analytics: `/api/v1/analytics`. Scope `analytics:read`; counts toward the analytics rate limit.
 */
final class AnalyticsResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * A link's all-time click totals. The `x-analytics-as-of` header of
     * `Client::lastResponse()` tells how complete the data is.
     *
     * The endpoint reads no query parameters today; `$query` is sent as given
     * for forward compatibility and has no effect.
     *
     * @param string $id the link's `public_id`
     * @param array<string,mixed> $query currently ignored by the API
     * @return array{total_clicks:int, unique_clicks:int, clicks_by_country:array<string,int>, ...}
     */
    public function link(string $id, array $query = []): array
    {
        return $this->client->request('GET', '/analytics/links/' . Client::segment($id), $query);
    }
}
