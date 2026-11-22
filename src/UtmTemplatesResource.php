<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * UTM templates: `/api/v1/workspaces/{id}/utm-templates` and `/api/v1/utm-templates/{id}`.
 * Scopes `utm-templates:read`, `utm-templates:write`.
 */
final class UtmTemplatesResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Workspace-wide templates by default; `include_team_scoped` adds team templates.
     *
     * @param array{include_team_scoped?: bool|int|string, team_public_ids?: string|list<string>, ...} $query
     * @return array{data: list<array<string,mixed>>}
     */
    public function list(string $workspaceId, array $query = []): array
    {
        return $this->client->request('GET', '/workspaces/' . Client::segment($workspaceId) . '/utm-templates', $query);
    }

    /**
     * @param array{name?: string, utm_source?: string, utm_medium?: string, utm_campaign?: string, utm_term?: string, utm_content?: string, utm_id?: string, team_public_ids?: list<string>, ...} $params
     * @return array{data: array<string,mixed>}
     */
    public function create(string $workspaceId, array $params): array
    {
        return $this->client->request('POST', '/workspaces/' . Client::segment($workspaceId) . '/utm-templates', [], $params);
    }

    /**
     * Replace a template: values left out are cleared.
     *
     * @param array{name?: string, utm_source?: string, utm_medium?: string, utm_campaign?: string, utm_term?: string, utm_content?: string, utm_id?: string, team_public_ids?: list<string>, workspace_public_id?: string, lost_access_action?: string, ...} $params
     * @return array{data: array<string,mixed>, move_impact?: mixed, ...}
     */
    public function update(string $templateId, array $params): array
    {
        return $this->client->request('PUT', '/utm-templates/' . Client::segment($templateId), [], $params);
    }

    /**
     * @param array{links?: string, ...} $query `links`: `keep` (default) or `clear`
     * @return array{data: array{public_id:string, deleted:bool, links:string, links_updated:int}}
     */
    public function delete(string $templateId, array $query = []): array
    {
        return $this->client->request('DELETE', '/utm-templates/' . Client::segment($templateId), $query);
    }
}
