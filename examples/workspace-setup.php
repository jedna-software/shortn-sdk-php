<?php

declare(strict_types=1);

// Organise a workspace: a team, a tag, a UTM template, a report destination and a
// weekly report rule. Uses the first workspace the key reaches unless SHORTN_WORKSPACE is set.

require __DIR__ . '/../vendor/autoload.php';

use Shortn\Sdk\Client;

$shortn = new Client(getenv('SHORTN_API_KEY') ?: null, getenv('SHORTN_BASE_URL') ?: Client::DEFAULT_BASE_URL);
$workspaceId = getenv('SHORTN_WORKSPACE') ?: $shortn->workspaces->list()['data'][0]['public_id'];

$team = $shortn->teams->create($workspaceId, ['name' => 'Paid Social']);
$tag = $shortn->tags->create($workspaceId, ['name' => 'Spring', 'color' => '#14b8a6', 'team_public_ids' => [$team['public_id']]]);
$template = $shortn->utmTemplates->create($workspaceId, ['name' => 'Newsletter', 'utm_source' => 'newsletter', 'utm_medium' => 'email'])['data'];
$destination = $shortn->destinations->create($workspaceId, [
    'name' => 'Marketing inbox',
    'type' => 'email',
    'config' => ['to' => ['marketing@example.com']],
]);

$rule = $shortn->reports->createRule([
    'workspace_public_id' => $workspaceId,
    'report_type' => 'custom_builder_v1',
    'schedule_type' => 'every_x',
    'interval_seconds' => 604800,
    'report_name' => 'Weekly traffic',
    'destination_public_ids' => [$destination['public_id']],
    'definition_json' => [
        'version' => 'custom_builder.v1',
        'trigger' => 'scheduled',
        'sections' => [
            ['id' => 'total_clicks', 'order' => 1, 'settings' => ['window' => '7d']],
            ['id' => 'top_links', 'order' => 2, 'settings' => ['top_n' => 10]],
        ],
        'options' => ['include_comparison' => true, 'locale' => 'en-US'],
    ],
])['data'];

// Pause the rule; resume later with ['enabled' => true].
$shortn->reports->updateRule($rule['public_id'], ['enabled' => false]);

print_r([
    'team' => $team['slug'],
    'tag' => $tag['public_id'],
    'utm_template' => $template['public_id'],
    'destination' => $destination['public_id'],
    'report_rule' => $rule['public_id'],
    'domains' => array_column($shortn->domains->list()['data'], 'domain'),
]);
