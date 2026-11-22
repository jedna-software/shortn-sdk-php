<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * Custom domains: `/api/v1/domains`. Scopes `domains:read`, `domains:write`;
 * create, verify and delete need an account-wide key. Domains use a numeric `id`.
 */
final class DomainsResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @return array{data: list<array{id:int, domain:string, status:string, ready:bool, ...}>}
     */
    public function list(): array
    {
        return $this->client->request('GET', '/domains');
    }

    /**
     * Add a domain (answers 201); create the records in `domain.dns`, then verify it.
     *
     * @param array{domain: string, ...} $params
     * @return array{domain: array<string,mixed>}
     */
    public function create(array $params): array
    {
        return $this->client->request('POST', '/domains', [], $params);
    }

    /**
     * Check the DNS records now. Answers 200 once ready, 202 while being set up.
     *
     * @return array{domain: array<string,mixed>, verification: array<string,mixed>}
     */
    public function verify(int|string $id): array
    {
        return $this->client->request('POST', '/domains/' . Client::segment($id) . '/verify');
    }

    /**
     * @return array{deleted:bool, id:int, domain: array<string,mixed>}
     */
    public function delete(int|string $id): array
    {
        return $this->client->request('DELETE', '/domains/' . Client::segment($id));
    }
}
