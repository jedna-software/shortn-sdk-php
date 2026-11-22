<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * Teams: `/api/v1/workspaces/{id}/teams` and `/api/v1/teams/{slug}`.
 * Scopes `teams:read`, `teams:write`. Answers have no `data` wrapper.
 *
 * A slug is unique only within its workspace: pass the workspace's public ID as
 * `$workspaceId` to the slug methods when the key reaches several workspaces
 * (sent as `?workspace=`); an ambiguous slug answers 409.
 */
final class TeamsResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @return array{teams: null|list<array{id:int, public_id:string, name:string, slug:string, ...}>}
     */
    public function list(string $workspaceId): array
    {
        return $this->client->request('GET', '/workspaces/' . Client::segment($workspaceId) . '/teams');
    }

    /**
     * @param array{name: string, description?: string, ...} $params
     * @return array{id:int, slug:string, public_id:string, ...}
     */
    public function create(string $workspaceId, array $params): array
    {
        return $this->client->request('POST', '/workspaces/' . Client::segment($workspaceId) . '/teams', [], $params);
    }

    /**
     * @return array{id:int, public_id:string, name:string, slug:string, workspace_public_id:string, ...}
     */
    public function get(string $slug, ?string $workspaceId = null): array
    {
        return $this->client->request('GET', '/teams/' . Client::segment($slug), ['workspace' => $workspaceId]);
    }

    /**
     * @param array{name?: string, description?: string, ...} $params
     * @return array{id:int, public_id:string, name:string, slug:string, ...}
     */
    public function update(string $slug, array $params, ?string $workspaceId = null): array
    {
        return $this->client->request('PUT', '/teams/' . Client::segment($slug), ['workspace' => $workspaceId], $params);
    }

    /**
     * Archive a team. A team with assigned resources answers 409.
     *
     * @return array{success:bool, ...}
     */
    public function delete(string $slug, ?string $workspaceId = null): array
    {
        return $this->client->request('DELETE', '/teams/' . Client::segment($slug), ['workspace' => $workspaceId]);
    }
}
