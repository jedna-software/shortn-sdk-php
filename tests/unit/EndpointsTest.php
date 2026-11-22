<?php

declare(strict_types=1);

namespace Shortn\Sdk\Tests;

use Shortn\Sdk\Client;

// One row per SDK method: what it sends (method, path with escaping, query, JSON body)
// and that it hands back the decoded answer untouched.

const ODD = 'a b/c?d#é';          // a path value that needs escaping
const ODD_ESCAPED = 'a%20b%2Fc%3Fd%23%C3%A9';
const WS = 'ws 1/x';
const WS_ESCAPED = 'ws%201%2Fx';

/** @return list<array{0:string,1:callable(Client):mixed,2:string,3:string,4:array<string,string>,5:mixed}> */
function endpoint_cases(): array
{
    return [
        // links
        ['links.list', fn (Client $c) => $c->links->list(['limit' => 20, 'offset' => 40]), 'GET', '/links', ['limit' => '20', 'offset' => '40'], null],
        ['links.list (no query)', fn (Client $c) => $c->links->list(), 'GET', '/links', [], null],
        ['links.get', fn (Client $c) => $c->links->get(ODD), 'GET', '/links/' . ODD_ESCAPED, [], null],
        ['links.create', fn (Client $c) => $c->links->create(['url' => 'https://example.com/a?b=c', 'title' => 'Spring', 'tag_public_ids' => ['t1'], 'workspace_public_id' => 'w1', 'description' => null]),
            'POST', '/links', [], ['url' => 'https://example.com/a?b=c', 'title' => 'Spring', 'tag_public_ids' => ['t1'], 'workspace_public_id' => 'w1']],
        ['links.update', fn (Client $c) => $c->links->update(ODD, ['title' => 'New', 'team_public_ids' => []]), 'PATCH', '/links/' . ODD_ESCAPED, [], ['title' => 'New', 'team_public_ids' => []]],
        ['links.delete', fn (Client $c) => $c->links->delete(ODD), 'DELETE', '/links/' . ODD_ESCAPED, [], null],
        ['links.enable', fn (Client $c) => $c->links->enable(ODD), 'POST', '/links/' . ODD_ESCAPED . '/enable', [], null],
        ['links.setAbWinner', fn (Client $c) => $c->links->setAbWinner(ODD, ['variant' => 'B']), 'POST', '/links/' . ODD_ESCAPED . '/ab/winner', [], ['variant' => 'B']],
        // analytics
        ['analytics.link', fn (Client $c) => $c->analytics->link(ODD), 'GET', '/analytics/links/' . ODD_ESCAPED, [], null],
        ['analytics.link (query)', fn (Client $c) => $c->analytics->link('l1', ['x' => 'y', 'unset' => null]), 'GET', '/analytics/links/l1', ['x' => 'y'], null],
        // workspaces
        ['workspaces.list', fn (Client $c) => $c->workspaces->list(), 'GET', '/workspaces', [], null],
        ['workspaces.get', fn (Client $c) => $c->workspaces->get(WS), 'GET', '/workspaces/' . WS_ESCAPED, [], null],
        // tags
        ['tags.list', fn (Client $c) => $c->tags->list(WS, ['team_public_ids' => ['t1', 't2'], 'team' => null, 'report_scope' => 1]), 'GET', '/workspaces/' . WS_ESCAPED . '/tags', ['team_public_ids' => 't1,t2', 'report_scope' => '1'], null],
        ['tags.create', fn (Client $c) => $c->tags->create(WS, ['name' => 'Spring', 'color' => '#3b82f6']), 'POST', '/workspaces/' . WS_ESCAPED . '/tags', [], ['name' => 'Spring', 'color' => '#3b82f6']],
        ['tags.get', fn (Client $c) => $c->tags->get(ODD), 'GET', '/tags/' . ODD_ESCAPED, [], null],
        ['tags.update', fn (Client $c) => $c->tags->update(ODD, ['color' => '#16a34a']), 'PUT', '/tags/' . ODD_ESCAPED, [], ['color' => '#16a34a']],
        ['tags.delete', fn (Client $c) => $c->tags->delete(ODD), 'DELETE', '/tags/' . ODD_ESCAPED, [], null],
        // utm templates
        ['utmTemplates.list', fn (Client $c) => $c->utmTemplates->list(WS, ['include_team_scoped' => true]), 'GET', '/workspaces/' . WS_ESCAPED . '/utm-templates', ['include_team_scoped' => '1'], null],
        ['utmTemplates.create', fn (Client $c) => $c->utmTemplates->create(WS, ['name' => 'News', 'utm_source' => 'newsletter']), 'POST', '/workspaces/' . WS_ESCAPED . '/utm-templates', [], ['name' => 'News', 'utm_source' => 'newsletter']],
        ['utmTemplates.update', fn (Client $c) => $c->utmTemplates->update(ODD, ['name' => 'News', 'lost_access_action' => 'copy']), 'PUT', '/utm-templates/' . ODD_ESCAPED, [], ['name' => 'News', 'lost_access_action' => 'copy']],
        ['utmTemplates.delete', fn (Client $c) => $c->utmTemplates->delete(ODD, ['links' => 'clear']), 'DELETE', '/utm-templates/' . ODD_ESCAPED, ['links' => 'clear'], null],
        ['utmTemplates.delete (default)', fn (Client $c) => $c->utmTemplates->delete('t1'), 'DELETE', '/utm-templates/t1', [], null],
        // destinations
        ['destinations.list', fn (Client $c) => $c->destinations->list(WS), 'GET', '/workspaces/' . WS_ESCAPED . '/destinations', [], null],
        ['destinations.create', fn (Client $c) => $c->destinations->create(WS, ['name' => 'Hook', 'type' => 'webhook', 'config' => ['webhook_url' => 'https://example.com/hooks/x']]),
            'POST', '/workspaces/' . WS_ESCAPED . '/destinations', [], ['name' => 'Hook', 'type' => 'webhook', 'config' => ['webhook_url' => 'https://example.com/hooks/x']]],
        ['destinations.update', fn (Client $c) => $c->destinations->update(WS, ODD, ['name' => 'Hook 2', 'type' => 'webhook']), 'PUT', '/workspaces/' . WS_ESCAPED . '/destinations/' . ODD_ESCAPED, [], ['name' => 'Hook 2', 'type' => 'webhook']],
        ['destinations.delete', fn (Client $c) => $c->destinations->delete(WS, ODD), 'DELETE', '/workspaces/' . WS_ESCAPED . '/destinations/' . ODD_ESCAPED, [], null],
        // teams
        ['teams.list', fn (Client $c) => $c->teams->list(WS), 'GET', '/workspaces/' . WS_ESCAPED . '/teams', [], null],
        ['teams.create', fn (Client $c) => $c->teams->create(WS, ['name' => 'Paid Social', 'description' => 'Ads']), 'POST', '/workspaces/' . WS_ESCAPED . '/teams', [], ['name' => 'Paid Social', 'description' => 'Ads']],
        ['teams.get', fn (Client $c) => $c->teams->get(ODD), 'GET', '/teams/' . ODD_ESCAPED, [], null],
        ['teams.get (workspace)', fn (Client $c) => $c->teams->get('paid-social', WS), 'GET', '/teams/paid-social', ['workspace' => WS], null],
        ['teams.update', fn (Client $c) => $c->teams->update(ODD, ['name' => 'EU'], 'w1'), 'PUT', '/teams/' . ODD_ESCAPED, ['workspace' => 'w1'], ['name' => 'EU']],
        ['teams.delete', fn (Client $c) => $c->teams->delete(ODD), 'DELETE', '/teams/' . ODD_ESCAPED, [], null],
        // domains
        ['domains.list', fn (Client $c) => $c->domains->list(), 'GET', '/domains', [], null],
        ['domains.create', fn (Client $c) => $c->domains->create(['domain' => 'go.example.com']), 'POST', '/domains', [], ['domain' => 'go.example.com']],
        ['domains.verify', fn (Client $c) => $c->domains->verify(14), 'POST', '/domains/14/verify', [], null],
        ['domains.delete', fn (Client $c) => $c->domains->delete('14'), 'DELETE', '/domains/14', [], null],
        // reports
        ['reports.listRules', fn (Client $c) => $c->reports->listRules(['workspace_public_id' => 'w1', 'schedule_types' => ['every_x', 'event']]), 'GET', '/reports/rules', ['workspace_public_id' => 'w1', 'schedule_types' => 'every_x,event'], null],
        ['reports.getRule', fn (Client $c) => $c->reports->getRule(ODD), 'GET', '/reports/rules/' . ODD_ESCAPED, [], null],
        ['reports.createRule', fn (Client $c) => $c->reports->createRule(['report_type' => 'custom_builder_v1', 'interval_seconds' => 604800, 'definition_json' => ['version' => 'custom_builder.v1', 'options' => (object) []]]),
            'POST', '/reports/rules', [], ['report_type' => 'custom_builder_v1', 'interval_seconds' => 604800, 'definition_json' => ['version' => 'custom_builder.v1', 'options' => []]]],
        ['reports.updateRule', fn (Client $c) => $c->reports->updateRule(ODD, ['enabled' => false]), 'PATCH', '/reports/rules/' . ODD_ESCAPED, [], ['enabled' => false]],
        ['reports.deleteRule', fn (Client $c) => $c->reports->deleteRule(ODD), 'DELETE', '/reports/rules/' . ODD_ESCAPED, [], null],
        // conversions
        ['conversions.trackLead', fn (Client $c) => $c->conversions->trackLead(['click_id' => 'clk_1', 'idempotency_key' => 'lead-1', 'event_name' => 'Sign up', 'test_mode' => true]),
            'POST', '/conversions/leads', [], ['click_id' => 'clk_1', 'idempotency_key' => 'lead-1', 'event_name' => 'Sign up', 'test_mode' => true]],
        ['conversions.validateLead', fn (Client $c) => $c->conversions->validateLead(['click_id' => 'clk_1', 'idempotency_key' => 'lead-1']), 'POST', '/conversions/leads/validations', [], ['click_id' => 'clk_1', 'idempotency_key' => 'lead-1']],
        ['conversions.trackSale', fn (Client $c) => $c->conversions->trackSale(['click_id' => 'clk_1', 'amount_cents' => 4900, 'currency' => 'EUR', 'invoice_id' => 'inv_1']),
            'POST', '/conversions/sales', [], ['click_id' => 'clk_1', 'amount_cents' => 4900, 'currency' => 'EUR', 'invoice_id' => 'inv_1']],
        ['conversions.validateSale', fn (Client $c) => $c->conversions->validateSale(['click_id' => 'clk_1', 'amount' => '49.00', 'currency' => 'EUR', 'invoice_id' => 'inv_1']),
            'POST', '/conversions/sales/validations', [], ['click_id' => 'clk_1', 'amount' => '49.00', 'currency' => 'EUR', 'invoice_id' => 'inv_1']],
        ['conversions.trackRefund', fn (Client $c) => $c->conversions->trackRefund(['invoice_id' => 'inv_1', 'refund_id' => 're_1', 'amount_cents' => 1900]),
            'POST', '/conversions/refunds', [], ['invoice_id' => 'inv_1', 'refund_id' => 're_1', 'amount_cents' => 1900]],
        ['conversions.validateRefund', fn (Client $c) => $c->conversions->validateRefund(['invoice_id' => 'inv_1', 'refund_id' => 're_1', 'amount_cents' => 1900]),
            'POST', '/conversions/refunds/validations', [], ['invoice_id' => 'inv_1', 'refund_id' => 're_1', 'amount_cents' => 1900]],
        ['conversions.summary', fn (Client $c) => $c->conversions->summary(), 'GET', '/conversions/summary', [], null],
        ['conversions.attribution', fn (Client $c) => $c->conversions->attribution(['environment' => 'test', 'limit' => 10]), 'GET', '/conversions/attribution', ['environment' => 'test', 'limit' => '10'], null],
        ['conversions.timeline', fn (Client $c) => $c->conversions->timeline(['interval' => 'week', 'from' => new \DateTimeImmutable('2026-11-01T00:00:00+00:00')]), 'GET', '/conversions/timeline', ['interval' => 'week', 'from' => '2026-11-01T00:00:00+00:00'], null],
        ['conversions.events', fn (Client $c) => $c->conversions->events(['event_type' => 'sale', 'q' => 'a&b=c', 'offset' => 0]), 'GET', '/conversions/events', ['event_type' => 'sale', 'q' => 'a&b=c', 'offset' => '0'], null],
        // public
        ['public.validateUrl', fn (Client $c) => $c->public->validateUrl('example.com/a b?x=1&y=2'), 'GET', '/validate', ['url' => 'example.com/a b?x=1&y=2'], null],
        ['public.plans', fn (Client $c) => $c->public->plans(), 'GET', '/plans', [], null],
    ];
}

foreach (endpoint_cases() as [$name, $call, $method, $path, $query, $body]) {
    Harness::test('endpoint ' . $name, function () use ($call, $method, $path, $query, $body): void {
        $answer = ['ok' => true, 'unknown_future_field' => ['nested' => 1]];
        [$client, $transport] = mock_client([json_response($answer)]);
        $result = $call($client);
        $request = $transport->last();
        assert_same(1, count($transport->requests), 'one request');
        assert_same($method, $request['method'], 'HTTP method');
        assert_same('/api/v1' . $path, $request['path'], 'path');
        assert_same($query, $request['query'], 'query');
        assert_same($body, $request['json'], 'JSON body');
        assert_same($body === null ? null : 'application/json', $request['headers']['Content-Type'] ?? null, 'content type');
        assert_same('Bearer sk_test_123', $request['headers']['Authorization'] ?? null, 'auth header');
        assert_same(12.5, $request['timeout'], 'timeout');
        assert_same($answer, $result, 'decoded answer, unknown fields kept');
    });
}

Harness::test('every public resource method has an endpoint case', function (): void {
    [$client] = mock_client();
    $covered = [];
    foreach (endpoint_cases() as [$name]) {
        $covered[explode(' ', $name)[0]] = true;
    }
    $covered['public.qrCode'] = true; // PublicTest
    $missing = [];
    foreach ((new \ReflectionClass(Client::class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
        $resource = $property->getValue($client);
        foreach ((new \ReflectionClass($resource))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) {
                continue;
            }
            $key = $property->getName() . '.' . $method->getName();
            if (!isset($covered[$key])) {
                $missing[] = $key;
            }
        }
    }
    assert_same([], $missing, 'methods without a case');
    assert_same(50, count($covered), 'method count: every route in the SDK surface');
});
