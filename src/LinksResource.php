<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * Links: `/api/v1/links`. Scopes `links:read` and `links:write`.
 *
 * A link is addressed by its `public_id`, which create and list return (not its short code).
 */
final class LinksResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * List links, newest first.
     *
     * @param array{limit?: int, offset?: int, ...} $query
     * @return array{data: list<array<string,mixed>>, pagination: array{limit:int, offset:int, count:int, has_more:bool, next_offset:?int}}
     */
    public function list(array $query = []): array
    {
        return $this->client->request('GET', '/links', $query);
    }

    /**
     * @return array{data: array<string,mixed>}
     */
    public function get(string $publicId): array
    {
        return $this->client->request('GET', '/links/' . Client::segment($publicId));
    }

    /**
     * Create a link (answers 201). `url` and `title` are required; unknown fields
     * are refused by the API with `400 unsupported_fields`.
     *
     * @param array{
     *     url: string,
     *     title: string,
     *     description?: string,
     *     custom_code?: string,
     *     short_domain?: string,
     *     workspace_public_id?: string,
     *     team_public_ids?: list<string>,
     *     tag_public_ids?: list<string>,
     *     utm_template_id?: string,
     *     utm_source?: string, utm_medium?: string, utm_campaign?: string,
     *     utm_term?: string, utm_content?: string, utm_id?: string,
     *     starts_at?: string, expires_at?: string, expiration_action?: string,
     *     access_mode?: string, password?: string, access_duration_hours?: int, jit_allowlist?: list<string>,
     *     targeting_geo_overrides?: list<array{target:string,url:string}>,
     *     language_targets?: array<string,string>, device_targets?: array<string,string>,
     *     os_targets?: array<string,string>, browser_targets?: array<string,string>,
     *     mobile_target_ios?: string, mobile_target_android?: string, targeting_priority?: list<string>,
     *     ab_variants?: list<array{url:string,split_percent?:int}>,
     *     conversion_enabled?: bool, conversion_attribution_model?: string,
     *     conversion_attribution_window_days?: int, attribution?: array<string,string>,
     *     preview_title?: string, preview_description?: string, preview_image?: string,
     *     preview_custom?: bool, cloaking_enabled?: bool,
     *     ...
     * } $params
     * @return array{public_id:string, short_url:string, short_code:string, original_url:string, title:string, link_status:string, ...}
     */
    public function create(array $params): array
    {
        return $this->client->request('POST', '/links', [], $params);
    }

    /**
     * Change a link's title, description, destination, short code, domain,
     * status or teams. Send `team_public_ids` in a request of its own.
     *
     * @param array{title?: string, description?: string, url?: string, short_code?: string, domain?: string, status?: string, active?: bool, team_public_ids?: list<string>, ...} $params
     * @return array{message?: string, ...}
     */
    public function update(string $publicId, array $params): array
    {
        return $this->client->request('PATCH', '/links/' . Client::segment($publicId), [], $params);
    }

    /**
     * Suspend a link: it stops redirecting; analytics and history are kept.
     *
     * @return array{message?: string, ...}
     */
    public function delete(string $publicId): array
    {
        return $this->client->request('DELETE', '/links/' . Client::segment($publicId));
    }

    /**
     * Make a suspended link active again.
     *
     * @return array{message?: string, ...}
     */
    public function enable(string $publicId): array
    {
        return $this->client->request('POST', '/links/' . Client::segment($publicId) . '/enable');
    }

    /**
     * End the link's A/B test with a winner.
     *
     * @param array{variant: string, ...} $params
     * @return array{winner: string, test: int, destination: string, ...}
     */
    public function setAbWinner(string $publicId, array $params): array
    {
        return $this->client->request('POST', '/links/' . Client::segment($publicId) . '/ab/winner', [], $params);
    }
}
