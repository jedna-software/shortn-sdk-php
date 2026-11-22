<?php

declare(strict_types=1);

namespace Shortn\Sdk\Tests;

use InvalidArgumentException;
use Shortn\Sdk\ApiError;
use Shortn\Sdk\Client;
use Shortn\Sdk\RetryOptions;
use Shortn\Sdk\ShortnException;
use Shortn\Sdk\TransportException;

// Headers, URLs, encoding and the key requirement.

Harness::test('sends auth, accept and user agent headers', function (): void {
    [$client, $transport] = mock_client([json_response(['data' => []])]);
    $client->workspaces->list();
    $headers = $transport->last()['headers'];
    assert_same('Bearer sk_test_123', $headers['Authorization']);
    assert_same('application/json', $headers['Accept']);
    assert_same('shortn-sdk-php/' . Client::VERSION, $headers['User-Agent']);
    assert_same('0.1.0', Client::VERSION);
});

Harness::test('appends a custom user agent suffix', function (): void {
    $transport = new MockTransport([json_response([])]);
    $client = new Client('sk_x', userAgent: 'acme-app/2.0', transport: $transport);
    $client->workspaces->list();
    assert_same('shortn-sdk-php/0.1.0 acme-app/2.0', $transport->last()['headers']['User-Agent']);
});

Harness::test('defaults to the production base URL with /api/v1', function (): void {
    $transport = new MockTransport([json_response([])]);
    $client = new Client('sk_x', transport: $transport);
    $client->domains->list();
    assert_same('https://app.shortn.link/api/v1/domains', $transport->last()['url']);
    assert_same(30.0, $transport->last()['timeout']);
});

Harness::test('accepts a base URL with a trailing slash or /api/v1', function (): void {
    foreach (['https://dev.example.test/', 'https://dev.example.test/api/v1', 'https://dev.example.test/api/v1/'] as $base) {
        [$client, $transport] = mock_client([json_response([])], baseUrl: $base);
        $client->links->list();
        assert_same('https://dev.example.test/api/v1/links', $transport->last()['url'], $base);
    }
});

Harness::test('refuses keyed endpoints without an API key, before sending', function (): void {
    [$client, $transport] = mock_client([], apiKey: null);
    assert_true(!$client->hasApiKey());
    expect_throws(ShortnException::class, fn () => $client->links->list());
    expect_throws(ShortnException::class, fn () => $client->links->create(['url' => 'https://example.com', 'title' => 'x']));
    expect_throws(ShortnException::class, fn () => $client->conversions->summary());
    [$blank] = mock_client([], apiKey: '   ');
    expect_throws(ShortnException::class, fn () => $blank->workspaces->list());
    assert_same(0, count($transport->requests));
});

Harness::test('public helpers work without a key and send no Authorization', function (): void {
    [$client, $transport] = mock_client([json_response(['valid' => true]), json_response(['plans' => []])], apiKey: null);
    assert_same(['valid' => true], $client->public->validateUrl('example.com'));
    assert_same(['plans' => []], $client->public->plans());
    foreach ($transport->requests as $request) {
        assert_true(!isset($request['headers']['Authorization']), 'no auth header');
        assert_same('shortn-sdk-php/0.1.0', $request['headers']['User-Agent']);
    }
});

Harness::test('public helpers send the key when the client has one', function (): void {
    [$client, $transport] = mock_client([json_response(['valid' => true])]);
    $client->public->validateUrl('example.com');
    assert_same('Bearer sk_test_123', $transport->last()['headers']['Authorization']);
});

Harness::test('drops top-level nulls but keeps empty lists, false and nested values', function (): void {
    [$client, $transport] = mock_client([json_response([])]);
    $client->links->update('abc', ['title' => 'T', 'description' => null, 'team_public_ids' => [], 'active' => false]);
    assert_same('{"title":"T","team_public_ids":[],"active":false}', $transport->last()['body']);
});

Harness::test('encodes objects as {} and keeps slashes and unicode readable', function (): void {
    [$client, $transport] = mock_client([json_response([])]);
    $client->reports->createRule(['report_type' => 'custom_builder_v1', 'definition_json' => ['context_sections' => [['id' => 'top_links', 'settings' => (object) []]]], 'report_name' => 'Zprávy https://x/y']);
    assert_same('{"report_type":"custom_builder_v1","definition_json":{"context_sections":[{"id":"top_links","settings":{}}]},"report_name":"Zprávy https://x/y"}', $transport->last()['body']);
});

Harness::test('whole-number floats encode without a .0 fraction', function (): void {
    [$client, $transport] = mock_client([json_response([])]);
    $total = 19.99;
    $client->conversions->trackSale(['click_id' => 'clk', 'currency' => 'EUR', 'amount_cents' => round($total * 100), 'settlement_amount_cents' => 2000.0]);
    assert_same('{"click_id":"clk","currency":"EUR","amount_cents":1999,"settlement_amount_cents":2000}', $transport->last()['body']);
});

Harness::test('empty arrays under object fields are sent as {}', function (): void {
    [$client, $transport] = mock_client([json_response([]), json_response([]), json_response([])]);
    $client->conversions->trackLead(['click_id' => 'clk', 'idempotency_key' => 'k', 'metadata' => []]);
    assert_same('{"click_id":"clk","idempotency_key":"k","metadata":{}}', $transport->requests[0]['body']);
    $client->destinations->create('w1', ['name' => 'Ops', 'type' => 'email', 'config' => []]);
    assert_same('{"name":"Ops","type":"email","config":{}}', $transport->requests[1]['body']);
    $client->links->update('abc', ['device_targets' => [], 'os_targets' => [], 'browser_targets' => [], 'language_targets' => [], 'attribution' => [], 'team_public_ids' => [], 'metadata' => ['plan' => 'pro']]);
    assert_same('{"device_targets":{},"os_targets":{},"browser_targets":{},"language_targets":{},"attribution":{},"team_public_ids":[],"metadata":{"plan":"pro"}}', $transport->requests[2]['body'], 'lists stay lists; filled objects unchanged');
});

Harness::test('dates in a body are sent as RFC 3339', function (): void {
    [$client, $transport] = mock_client([json_response([])]);
    $at = new \DateTimeImmutable('2026-11-21T10:15:00+01:00');
    $client->reports->createRule(['report_type' => 'custom_builder_v1', 'initial_run_at' => $at, 'definition_json' => ['window' => ['from' => new \DateTime('2026-11-01T00:00:00Z')]]]);
    assert_same('{"report_type":"custom_builder_v1","initial_run_at":"2026-11-21T10:15:00+01:00","definition_json":{"window":{"from":"2026-11-01T00:00:00+00:00"}}}', $transport->last()['body']);
});

Harness::test('query booleans are sent as 1 and 0', function (): void {
    [$client, $transport] = mock_client([json_response([]), json_response([])]);
    $client->tags->list('w1', ['report_scope' => true]);
    assert_same(['report_scope' => '1'], $transport->requests[0]['query']);
    $client->conversions->events(['include_total' => true, 'limit' => 10]);
    assert_same('include_total=1&limit=10', $transport->requests[1]['rawQuery']);
});

Harness::test('bodiless POST sends Content-Length 0 and no Content-Type', function (): void {
    [$client, $transport] = mock_client([json_response(['message' => 'URL enabled successfully'])]);
    $client->links->enable('abc');
    $request = $transport->last();
    assert_same(null, $request['body']);
    assert_same('0', $request['headers']['Content-Length'] ?? null);
    assert_true(!isset($request['headers']['Content-Type']));
});

Harness::test('query strings use RFC 3986 escaping and only set values', function (): void {
    assert_same('q=a%20b%26c&flag=0&on=1&ids=a%2Cb%2Cc', Client::buildQuery(['q' => 'a b&c', 'skip' => null, 'flag' => false, 'on' => true, 'empty' => [], 'ids' => ['a,b', 'c']]));
    assert_same('', Client::buildQuery([]));
    expect_throws(InvalidArgumentException::class, fn () => Client::buildQuery(['bad' => [['nested']]]));
});

Harness::test('empty path parameters are refused before sending', function (): void {
    [$client, $transport] = mock_client();
    expect_throws(InvalidArgumentException::class, fn () => $client->links->delete(''));
    expect_throws(InvalidArgumentException::class, fn () => $client->tags->get('  '));
    expect_throws(InvalidArgumentException::class, fn () => $client->destinations->delete('w1', ''));
    assert_same(0, count($transport->requests));
    assert_same('a%2F..%2Fb', Client::segment('a/../b'));
});

Harness::test('an empty answer (204) decodes to an empty array', function (): void {
    [$client] = mock_client([['status' => 204, 'body' => '']]);
    assert_same([], $client->reports->deleteRule('r1'));
});

Harness::test('a JSON list answer is returned as a list', function (): void {
    [$client] = mock_client([json_response([['public_id' => 'd1'], ['public_id' => 'd2']])]);
    $list = $client->destinations->list('w1');
    assert_same('d2', $list[1]['public_id']);
});

Harness::test('a success answer that is not JSON raises ShortnException', function (): void {
    [$client] = mock_client([['status' => 200, 'body' => '<html>']]);
    expect_throws(ShortnException::class, fn () => $client->workspaces->list());
});

Harness::test('lastResponse exposes status and lower-cased headers', function (): void {
    [$client] = mock_client([json_response(['total_clicks' => 1], 200, ['X-Analytics-As-Of' => 'current', 'X-RateLimit-Remaining' => '239'])]);
    assert_same(null, (new Client('k', transport: new MockTransport()))->lastResponse());
    $client->analytics->link('l1');
    $last = $client->lastResponse();
    assert_same(200, $last['status']);
    assert_same('current', $last['headers']['x-analytics-as-of']);
    assert_same('239', $last['headers']['x-ratelimit-remaining']);
});

Harness::test('a transport that answers nonsense raises ShortnException', function (): void {
    $client = new Client('k', transport: fn () => 'nope');
    expect_throws(ShortnException::class, fn () => $client->links->list());
});

Harness::test('exception hierarchy', function (): void {
    assert_true(is_subclass_of(ApiError::class, ShortnException::class));
    assert_true(is_subclass_of(TransportException::class, ShortnException::class));
    assert_true(is_subclass_of(ShortnException::class, \RuntimeException::class));
    $none = RetryOptions::none();
    assert_true($none->disabled);
});
