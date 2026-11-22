<?php

declare(strict_types=1);

// Create a conversion-tracked link (Professional plan or higher, with conversion
// tracking on in the workspace).

require __DIR__ . '/../vendor/autoload.php';

use Shortn\Sdk\ApiError;
use Shortn\Sdk\Client;

$shortn = new Client(getenv('SHORTN_API_KEY') ?: null, getenv('SHORTN_BASE_URL') ?: Client::DEFAULT_BASE_URL);

try {
    $link = $shortn->links->create([
        'url' => 'https://example.com/pricing',
        'title' => 'Pricing',
        'workspace_public_id' => getenv('SHORTN_WORKSPACE') ?: null,
        'conversion_enabled' => true,
        'attribution' => [
            'type' => 'campaign',
            'external_id' => 'spring-launch',
            'label' => 'Spring launch',
        ],
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
        'utm_campaign' => 'spring_launch',
    ]);
} catch (ApiError $error) {
    // plan_limit_exceeded carries feature, limit, current and required_plan in the body.
    fwrite(STDERR, sprintf("%d %s: %s\n", $error->statusCode, $error->errorCode ?? '-', $error->getMessage()));
    exit(1);
}

echo $link['short_url'] . ' (' . $link['public_id'] . ')' . PHP_EOL;
