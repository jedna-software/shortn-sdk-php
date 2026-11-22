<?php

declare(strict_types=1);

namespace Shortn\Sdk\Tests;

use Shortn\Sdk\ApiError;

// The QR helper answers bytes, not JSON.

Harness::test('endpoint public.qrCode returns the image bytes', function (): void {
    $png = "\x89PNG\r\n\x1a\n" . random_bytes(16);
    [$client, $transport] = mock_client([['status' => 200, 'headers' => ['Content-Type' => 'image/png'], 'body' => $png]], apiKey: null);
    $bytes = $client->public->qrCode(['url' => 'https://shtnl.ink/abC12x9', 'size' => 'download', 'colorMode' => 'flat', 'fg' => '1f2937', 'bg' => null]);
    $request = $transport->last();
    assert_same($png, $bytes);
    assert_same('GET', $request['method']);
    assert_same('/api/v1/qr', $request['path']);
    assert_same(['url' => 'https://shtnl.ink/abC12x9', 'size' => 'download', 'colorMode' => 'flat', 'fg' => '1f2937'], $request['query']);
    assert_same('url=https%3A%2F%2Fshtnl.ink%2FabC12x9&size=download&colorMode=flat&fg=1f2937', $request['rawQuery']);
    assert_same('image/png, */*;q=0.5', $request['headers']['Accept']);
    assert_true(!isset($request['headers']['Authorization']));
    assert_same('image/png', $client->lastResponse()['headers']['content-type']);
});

Harness::test('QR booleans are sent as true and false', function (): void {
    [$client, $transport] = mock_client([['status' => 200, 'body' => 'png']], apiKey: null);
    $client->public->qrCode(['url' => 'https://example.com', 'centerLogo' => true, 'autoFrameTextColor' => false]);
    assert_same(['url' => 'https://example.com', 'centerLogo' => 'true', 'autoFrameTextColor' => 'false'], $transport->last()['query']);
});

Harness::test('QR Accept header follows the format', function (): void {
    [$client, $transport] = mock_client([['status' => 200, 'body' => '<svg/>'], ['status' => 200, 'body' => 'jpg']]);
    assert_same('<svg/>', $client->public->qrCode(['url' => 'https://example.com', 'format' => 'svg']));
    assert_same('image/svg+xml, */*;q=0.5', $transport->requests[0]['headers']['Accept']);
    $client->public->qrCode(['url' => 'https://example.com', 'format' => 'jpg']);
    assert_same('image/jpeg, */*;q=0.5', $transport->requests[1]['headers']['Accept']);
});

Harness::test('QR errors are mapped like JSON errors', function (): void {
    [$client] = mock_client([json_response(['code' => 'plan_limit_exceeded', 'error' => 'QR customization needs a plan', 'feature' => 'qr_customization'], 403)], apiKey: null);
    $error = expect_throws(ApiError::class, fn () => $client->public->qrCode(['url' => 'https://example.com', 'colorMode' => 'gradient']));
    assert_same('plan_limit_exceeded', $error->errorCode);
    assert_same('qr_customization', $error->body['feature']);
});
