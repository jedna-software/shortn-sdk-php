<?php

declare(strict_types=1);

// The endpoints that need no API key: URL validation, QR codes and the plan catalog.

require __DIR__ . '/../vendor/autoload.php';

use Shortn\Sdk\Client;

$shortn = new Client(baseUrl: getenv('SHORTN_BASE_URL') ?: Client::DEFAULT_BASE_URL);

$validation = $shortn->public->validateUrl('example.com/pricing');
if (!$validation['valid']) {
    fwrite(STDERR, ($validation['error'] ?? 'URL is not valid.') . PHP_EOL);
    exit(1);
}

$png = $shortn->public->qrCode([
    'url' => $validation['normalized'],
    'format' => 'png',
    'size' => 'preview-hd',
]);
file_put_contents(__DIR__ . '/pricing-qr.png', $png);

$plans = $shortn->public->plans()['plans'];

print_r([
    'normalized' => $validation['normalized'],
    'qr_bytes' => strlen($png),
    'qr_content_type' => $shortn->lastResponse()['headers']['content-type'] ?? null,
    'plans' => array_keys($plans),
]);
