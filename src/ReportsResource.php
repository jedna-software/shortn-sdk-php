<?php

declare(strict_types=1);

namespace Shortn\Sdk;

/**
 * Report and alert rules: `/api/v1/reports/rules`. Scopes `reports:read`, `reports:write`.
 */
final class ReportsResource
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array{workspace_public_id?: string, schedule_types?: string|list<string>, link_public_ids?: string|list<string>, team_public_ids?: string|list<string>, ...} $query
     * @return array{data: list<array<string,mixed>>}
     */
    public function listRules(array $query = []): array
    {
        return $this->client->request('GET', '/reports/rules', $query);
    }

    /**
     * @return array{data: array<string,mixed>}
     */
    public function getRule(string $id): array
    {
        return $this->client->request('GET', '/reports/rules/' . Client::segment($id));
    }

    /**
     * @param array{
     *     workspace_public_id?: string,
     *     report_type: string,
     *     schedule_type?: string,
     *     interval_seconds?: int,
     *     initial_run_at?: string,
     *     report_name?: string,
     *     definition_json: array<string,mixed>,
     *     destination_public_ids?: list<string>,
     *     team_public_ids?: list<string>,
     *     include_unassigned_links?: bool,
     *     link_public_ids?: list<string>,
     *     ...
     * } $params
     * @return array{data: array<string,mixed>}
     */
    public function createRule(array $params): array
    {
        return $this->client->request('POST', '/reports/rules', [], $params);
    }

    /**
     * Change what you send and keep the rest, e.g. `['enabled' => false]` to pause.
     *
     * @param array<string,mixed> $params
     * @return array{data: array<string,mixed>}
     */
    public function updateRule(string $id, array $params): array
    {
        return $this->client->request('PATCH', '/reports/rules/' . Client::segment($id), [], $params);
    }

    /**
     * Answers 204 with no body, returned as `[]`.
     *
     * @return array<mixed>
     */
    public function deleteRule(string $id): array
    {
        return $this->client->request('DELETE', '/reports/rules/' . Client::segment($id));
    }
}
