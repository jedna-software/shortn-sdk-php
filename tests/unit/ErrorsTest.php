<?php

declare(strict_types=1);

namespace Shortn\Sdk\Tests;

use Shortn\Sdk\ApiError;

// Error envelope mapping.

Harness::test('maps the structured error envelope', function (): void {
    $body = [
        'code' => 'conversion_identity_required',
        'error' => 'idempotency_key is required',
        'hint' => 'Send a stable key for this lead.',
        'details' => ['field' => 'idempotency_key'],
        'request_id' => 'req_123',
    ];
    [$client] = mock_client([json_response($body, 400, ['Shortn-Request-Id' => 'req_123'])]);
    $error = expect_throws(ApiError::class, fn () => $client->conversions->trackLead(['click_id' => 'clk']));
    assert_same(400, $error->statusCode);
    assert_same(400, $error->getCode());
    assert_same('conversion_identity_required', $error->errorCode);
    assert_same('idempotency_key is required', $error->getMessage());
    assert_same('Send a stable key for this lead.', $error->hint);
    assert_same('idempotency_key', $error->field);
    assert_same(['field' => 'idempotency_key'], $error->details);
    assert_same('req_123', $error->requestId);
    assert_same($body, $error->body);
    assert_same(json_encode($body), $error->rawBody);
    assert_same('req_123', $error->headers['shortn-request-id']);
});

Harness::test('keeps plan-limit fields reachable in the body', function (): void {
    $body = ['code' => 'plan_limit_exceeded', 'error' => 'Active links limit reached', 'feature' => 'active_links', 'limit' => 25, 'current' => 25, 'required_plan' => null, 'upgrade_url' => '/profile/billing'];
    [$client] = mock_client([json_response($body, 403)]);
    $error = expect_throws(ApiError::class, fn () => $client->links->create(['url' => 'https://example.com', 'title' => 'x']));
    assert_same('plan_limit_exceeded', $error->errorCode);
    assert_same('active_links', $error->body['feature']);
    assert_same(25, $error->body['limit']);
    assert_same(null, $error->field);
});

Harness::test('maps error-only answers without a code', function (): void {
    [$client] = mock_client([json_response(['error' => 'team not found'], 404)]);
    $error = expect_throws(ApiError::class, fn () => $client->teams->get('missing'));
    assert_same(404, $error->statusCode);
    assert_same(null, $error->errorCode);
    assert_same('team not found', $error->getMessage());
    assert_same(null, $error->hint);
    assert_same(null, $error->details);
});

Harness::test('maps a top-level field', function (): void {
    [$client] = mock_client([json_response(['code' => 'invalid', 'error' => 'bad', 'field' => 'name'], 400)]);
    $error = expect_throws(ApiError::class, fn () => $client->tags->create('w1', ['name' => '']));
    assert_same('name', $error->field);
});

Harness::test('maps a non-JSON error body', function (): void {
    [$client] = mock_client([['status' => 502, 'headers' => ['Content-Type' => 'text/html'], 'body' => '<html>Bad gateway</html>']]);
    $error = expect_throws(ApiError::class, fn () => $client->workspaces->list());
    assert_same(502, $error->statusCode);
    assert_same(null, $error->errorCode);
    assert_same('Shortn API request failed with HTTP 502.', $error->getMessage());
    assert_same('<html>Bad gateway</html>', $error->body);
    assert_same('<html>Bad gateway</html>', $error->rawBody);
});

Harness::test('maps an empty error body', function (): void {
    [$client] = mock_client([['status' => 401, 'body' => '']]);
    $error = expect_throws(ApiError::class, fn () => $client->workspaces->list());
    assert_same(401, $error->statusCode);
    assert_same(null, $error->body);
});

Harness::test('parses Retry-After as seconds or an HTTP date', function (): void {
    assert_same(7.0, ApiError::parseRetryAfter('7'));
    assert_same(null, ApiError::parseRetryAfter(null));
    assert_same(null, ApiError::parseRetryAfter('soon'));
    $future = ApiError::parseRetryAfter(gmdate('D, d M Y H:i:s \G\M\T', time() + 30));
    assert_true($future !== null && $future >= 28 && $future <= 31, 'HTTP date in ~30s');
    assert_same(0.0, ApiError::parseRetryAfter(gmdate('D, d M Y H:i:s \G\M\T', time() - 30)));
});
