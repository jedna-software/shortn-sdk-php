<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * Workspaces: `/api/v1/workspaces`. Scope `workspaces:read`.
 */
final class WorkspacesResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Every workspace the key can reach (not paginated).
     *
     * @return array{data: list<array{public_id:string, name:string, slug:string, is_personal:bool, active:bool, role:string, ...}>}
     */
    public function list(): array
    {
        return $this->client->request('GET', '/workspaces');
    }

    /**
     * @return array{data: array{public_id:string, name:string, slug:string, is_personal:bool, active:bool, role:string, ...}}
     */
    public function get(string $publicId): array
    {
        return $this->client->request('GET', '/workspaces/' . Client::segment($publicId));
    }
}
