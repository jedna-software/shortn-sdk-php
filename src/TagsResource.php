<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * Tags: `/api/v1/workspaces/{id}/tags` and `/api/v1/tags/{id}`. Scopes `tags:read`, `tags:write`.
 * These endpoints answer without a `data` wrapper.
 */
final class TagsResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * A workspace's tags, sorted by name. `tags` is null when there are none.
     *
     * @param array{team_public_ids?: string|list<string>, team?: string, report_scope?: string|int|bool, ...} $query
     * @return array{tags: null|list<array<string,mixed>>}
     */
    public function list(string $workspaceId, array $query = []): array
    {
        return $this->client->request('GET', '/workspaces/' . Client::segment($workspaceId) . '/tags', $query);
    }

    /**
     * @param array{name: string, color: string, team_public_ids?: list<string>, ...} $params
     * @return array{id:int, public_id:string, name:string, color:string, workspace_wide:bool, ...}
     */
    public function create(string $workspaceId, array $params): array
    {
        return $this->client->request('POST', '/workspaces/' . Client::segment($workspaceId) . '/tags', [], $params);
    }

    /**
     * @param string $id the tag's `public_id`
     * @return array{id:int, public_id:string, name:string, color:string, workspace_wide:bool, ...}
     */
    public function get(string $id): array
    {
        return $this->client->request('GET', '/tags/' . Client::segment($id));
    }

    /**
     * Change `name` and/or `color`, or replace the teams with `team_public_ids`
     * (not both in one request).
     *
     * @param array{name?: string, color?: string, team_public_ids?: list<string>, ...} $params
     * @return array{id:int, public_id:string, name:string, color:string, ...}
     */
    public function update(string $id, array $params): array
    {
        return $this->client->request('PUT', '/tags/' . Client::segment($id), [], $params);
    }

    /**
     * @return array{message:string, links_updated:int, ...}
     */
    public function delete(string $id): array
    {
        return $this->client->request('DELETE', '/tags/' . Client::segment($id));
    }
}
