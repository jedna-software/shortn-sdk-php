<?php

declare(strict_types=1);

namespace Shortn\Sdk\Tests;

use Shortn\Sdk\ApiError;
use Shortn\Sdk\RetryOptions;
use Shortn\Sdk\TransportException;

// Which requests are retried, how often, and how long the client waits.

function rate_limited(?string $retryAfter = '2'): array
{
    $headers = $retryAfter === null ? [] : ['Retry-After' => $retryAfter];
    return json_response(['code' => 'api_rate_limit_exceeded', 'error' => 'API rate limit exceeded for the current plan.'], 429, $headers);
}

function unavailable(): array
{
    return json_response(['code' => 'reports_unavailable', 'error' => 'unavailable'], 503);
}

Harness::test('GET retries a 429 after Retry-After seconds', function (): void {
    [$client, $transport] = mock_client([rate_limited('2'), json_response(['data' => []])]);
    assert_same(['data' => []], $client->links->list());
    assert_same(2, count($transport->requests));
    assert_same([2.0], $transport->sleeps);
});

Harness::test('GET retries a 503 with exponential backoff, then gives up', function (): void {
    [$client, $transport] = mock_client([unavailable(), unavailable(), unavailable(), json_response([])]);
    $error = expect_throws(ApiError::class, fn () => $client->reports->listRules());
    assert_same(503, $error->statusCode);
    assert_same(3, count($transport->requests), 'one try plus maxRetries (2)');
    assert_same([0.25, 0.5], $transport->sleeps);
});

Harness::test('backoff is capped by maxDelay and jitter stays within bounds', function (): void {
    [$client, $transport] = mock_client(
        [unavailable(), unavailable(), unavailable(), unavailable(), json_response([])],
        new RetryOptions(maxRetries: 4, initialDelay: 1.0, maxDelay: 3.0, backoffFactor: 2.0, jitter: true),
    );
    $client->workspaces->list();
    assert_same(4, count($transport->sleeps));
    $caps = [1.0, 2.0, 3.0, 3.0];
    foreach ($transport->sleeps as $i => $sleep) {
        assert_true($sleep >= $caps[$i] * 0.5 && $sleep <= $caps[$i], "sleep {$i} = {$sleep}");
    }
});

Harness::test('PUT and DELETE are retried', function (): void {
    [$client, $transport] = mock_client([rate_limited('0'), json_response(['id' => 1]), unavailable(), json_response(['success' => true])]);
    $client->tags->update('t1', ['name' => 'x']);
    $client->destinations->delete('w1', 'd1');
    assert_same(['PUT', 'PUT', 'DELETE', 'DELETE'], array_column($transport->requests, 'method'));
});

Harness::test('POST with an idempotency_key is retried', function (): void {
    [$client, $transport] = mock_client([unavailable(), json_response(['type' => 'lead'])]);
    assert_same(['type' => 'lead'], $client->conversions->trackLead(['click_id' => 'clk', 'idempotency_key' => 'k1']));
    assert_same(2, count($transport->requests));
    assert_same($transport->requests[0]['body'], $transport->requests[1]['body'], 'same body resent');
});

Harness::test('POST without an idempotency_key is sent once', function (): void {
    [$client, $transport] = mock_client([unavailable(), json_response([])]);
    expect_throws(ApiError::class, fn () => $client->conversions->trackSale(['click_id' => 'clk', 'currency' => 'EUR', 'invoice_id' => 'inv']));
    [$client2, $transport2] = mock_client([rate_limited(), json_response([])]);
    expect_throws(ApiError::class, fn () => $client2->links->create(['url' => 'https://example.com', 'title' => 'x']));
    [$client3, $transport3] = mock_client([rate_limited(), json_response([])]);
    expect_throws(ApiError::class, fn () => $client3->conversions->trackLead(['click_id' => 'clk', 'idempotency_key' => '  ']));
    assert_same(1, count($transport->requests));
    assert_same(1, count($transport2->requests));
    assert_same(1, count($transport3->requests));
});

Harness::test('test-mode POST is sent once, even with an idempotency_key', function (): void {
    [$client, $transport] = mock_client([unavailable(), json_response([])]);
    expect_throws(ApiError::class, fn () => $client->conversions->trackLead(['click_id' => 'clk', 'idempotency_key' => 'k1', 'test_mode' => true]));
    [$client2, $transport2] = mock_client([new TransportException('timeout'), json_response([])]);
    expect_throws(TransportException::class, fn () => $client2->conversions->trackSale(['click_id' => 'clk', 'currency' => 'EUR', 'amount_cents' => 100, 'idempotency_key' => 'k2', 'test_mode' => true]));
    assert_same(1, count($transport->requests));
    assert_same(1, count($transport2->requests));
    [$client3, $transport3] = mock_client([unavailable(), json_response(['type' => 'lead'])]);
    $client3->conversions->trackLead(['click_id' => 'clk', 'idempotency_key' => 'k3', 'test_mode' => false]);
    assert_same(2, count($transport3->requests), 'test_mode false is retried like live');
});

Harness::test('PATCH is sent once', function (): void {
    [$client, $transport] = mock_client([unavailable(), unavailable(), json_response([])]);
    expect_throws(ApiError::class, fn () => $client->links->update('abc', ['title' => 'x']));
    expect_throws(ApiError::class, fn () => $client->reports->updateRule('r1', ['enabled' => false]));
    assert_same(2, count($transport->requests), 'one per call');
});

Harness::test('other statuses are not retried', function (): void {
    foreach ([400, 401, 403, 404, 409, 500, 502] as $status) {
        [$client, $transport] = mock_client([json_response(['error' => 'x'], $status), json_response([])]);
        $error = expect_throws(ApiError::class, fn () => $client->workspaces->list());
        assert_same($status, $error->statusCode);
        assert_same(1, count($transport->requests), "status {$status}");
    }
});

Harness::test('network errors are retried for GET', function (): void {
    [$client, $transport] = mock_client([new TransportException('connection reset'), json_response(['data' => []])]);
    assert_same(['data' => []], $client->domains->list());
    assert_same(2, count($transport->requests));
    assert_same([0.25], $transport->sleeps);
});

Harness::test('network errors are not retried for unsafe POST', function (): void {
    [$client, $transport] = mock_client([new TransportException('timeout'), json_response([])]);
    expect_throws(TransportException::class, fn () => $client->links->enable('abc'));
    assert_same(1, count($transport->requests));
});

Harness::test('network errors surface after the last attempt', function (): void {
    [$client, $transport] = mock_client([new TransportException('a'), new TransportException('b'), new TransportException('c')]);
    $error = expect_throws(TransportException::class, fn () => $client->workspaces->list());
    assert_same('c', $error->getMessage());
    assert_same(3, count($transport->requests));
});

Harness::test('other transport exceptions are not retried', function (): void {
    [$client, $transport] = mock_client([new \LogicException('bug'), json_response([])]);
    expect_throws(\LogicException::class, fn () => $client->workspaces->list());
    assert_same(1, count($transport->requests));
});

Harness::test('a Retry-After longer than maxRetryAfter is returned to the caller', function (): void {
    [$client, $transport] = mock_client([rate_limited('120'), json_response([])], new RetryOptions(maxRetryAfter: 60.0));
    $error = expect_throws(ApiError::class, fn () => $client->links->list());
    assert_same(120.0, $error->retryAfter);
    assert_same(1, count($transport->requests));
    assert_same([], $transport->sleeps);
});

Harness::test('RetryOptions::none() and maxRetries 0 turn retries off', function (): void {
    foreach ([RetryOptions::none(), new RetryOptions(maxRetries: 0), new RetryOptions(disabled: true)] as $options) {
        [$client, $transport] = mock_client([rate_limited('1'), json_response([])], $options);
        expect_throws(ApiError::class, fn () => $client->links->list());
        assert_same(1, count($transport->requests));
    }
});

Harness::test('the QR helper is retried like any GET', function (): void {
    [$client, $transport] = mock_client([rate_limited('1'), ['status' => 200, 'headers' => ['Content-Type' => 'image/png'], 'body' => "\x89PNG"]], apiKey: null);
    assert_same("\x89PNG", $client->public->qrCode(['url' => 'https://example.com']));
    assert_same(2, count($transport->requests));
});
