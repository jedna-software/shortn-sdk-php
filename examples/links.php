<?php

declare(strict_types=1);

// Create, read, update, list and suspend links, and read their click totals.

require __DIR__ . '/../vendor/autoload.php';

use Shortn\Sdk\Client;

$shortn = new Client(getenv('SHORTN_API_KEY') ?: null, getenv('SHORTN_BASE_URL') ?: Client::DEFAULT_BASE_URL);

$workspaces = $shortn->workspaces->list()['data'];
$workspaceId = getenv('SHORTN_WORKSPACE') ?: $workspaces[0]['public_id'];

$link = $shortn->links->create([
    'url' => 'https://example.com/spring',
    'title' => 'Spring campaign',
    'workspace_public_id' => $workspaceId,
]);
$id = $link['public_id'];

$shortn->links->update($id, ['title' => 'Spring sale']);
$current = $shortn->links->get($id)['data'];
$totals = $shortn->analytics->link($id);

// Walk every page of links.
$offset = 0;
do {
    $page = $shortn->links->list(['limit' => 100, 'offset' => $offset]);
    foreach ($page['data'] as $row) {
        echo $row['short_code'] . "\t" . $row['clicks'] . "\t" . $row['title'] . PHP_EOL;
    }
    $offset = $page['pagination']['next_offset'];
} while ($page['pagination']['has_more']);

$shortn->links->delete($id);   // suspends the link; analytics are kept
$shortn->links->enable($id);   // makes it active again

echo $current['title'] . ': ' . $totals['total_clicks'] . ' clicks' . PHP_EOL;
