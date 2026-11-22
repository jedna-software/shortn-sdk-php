<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * Report destinations: `/api/v1/workspaces/{id}/destinations`.
 * Scopes `destinations:read`, `destinations:write`. Answers have no `data` wrapper.
 */
final class DestinationsResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @return list<array{id:int, public_id:string, name:string, type:string, value:string, workspace_wide:bool, ...}>
     */
    public function list(string $workspaceId): array
    {
        return $this->client->request('GET', self::base($workspaceId));
    }

    /**
     * @param array{name: string, type: string, config: array<string,mixed>, team_public_ids?: list<string>, ...} $params
     *        `type`: email, webhook, slack, discord or msteams
     * @return array{id:int, public_id:string, name:string, type:string, workspace_wide:bool, ...}
     */
    public function create(string $workspaceId, array $params): array
    {
        return $this->client->request('POST', self::base($workspaceId), [], $params);
    }

    /**
     * `name` and `type` are required on every update.
     *
     * @param array{name: string, type: string, config?: array<string,mixed>, team_public_ids?: list<string>, ...} $params
     * @return array{success:bool, ...}
     */
    public function update(string $workspaceId, string $destinationId, array $params): array
    {
        return $this->client->request('PUT', self::base($workspaceId) . '/' . Client::segment($destinationId), [], $params);
    }

    /**
     * @return array{success:bool, ...}
     */
    public function delete(string $workspaceId, string $destinationId): array
    {
        return $this->client->request('DELETE', self::base($workspaceId) . '/' . Client::segment($destinationId));
    }

    private static function base(string $workspaceId): string
    {
        return '/workspaces/' . Client::segment($workspaceId) . '/destinations';
    }
}
