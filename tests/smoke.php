<?php

declare(strict_types=1);

// Opt-in smoke test against a live (development) Shortn instance.
//
//   SHORTN_API_KEY=... SHORTN_BASE_URL=https://staging.example.com \
//   SHORTN_SMOKE_WORKSPACE=<workspace public id> SHORTN_SMOKE_CLICK_ID=<click id> \
//   php tests/smoke.php
//
// Skipped unless SHORTN_API_KEY is set. Everything it creates lives in
// SHORTN_SMOKE_WORKSPACE, has a random suffix, and is removed again, also when a
// step fails. Conversions are sent in test mode. It refuses to run against
// production.

require_once __DIR__ . '/bootstrap.php';

use Shortn\Sdk\ApiError;
use Shortn\Sdk\Client;
use Shortn\Sdk\RetryOptions;

$apiKey = getenv('SHORTN_API_KEY') ?: '';
if (trim($apiKey) === '') {
    echo "smoke: skipped (SHORTN_API_KEY is not set)\n";
    exit(0);
}
$baseUrl = getenv('SHORTN_BASE_URL') ?: '';
$workspaceId = getenv('SHORTN_SMOKE_WORKSPACE') ?: '';
$clickId = getenv('SHORTN_SMOKE_CLICK_ID') ?: '';
if ($baseUrl === '' || $workspaceId === '' || $clickId === '') {
    fwrite(STDERR, "smoke: SHORTN_BASE_URL, SHORTN_SMOKE_WORKSPACE and SHORTN_SMOKE_CLICK_ID are required\n");
    exit(2);
}
$host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
if ($host === 'app.shortn.link') {
    fwrite(STDERR, "smoke: refusing to run against production ({$host})\n");
    exit(2);
}

$retry = new RetryOptions(maxRetries: 4, initialDelay: 1.0, maxDelay: 10.0, maxRetryAfter: 65.0);
$shortn = new Client(apiKey: $apiKey, baseUrl: $baseUrl, timeout: 30.0, retry: $retry, userAgent: 'smoke-test');
$anonymous = new Client(baseUrl: $baseUrl, retry: $retry, userAgent: 'smoke-test');

$suffix = bin2hex(random_bytes(3));
$results = ['ok' => 0, 'fail' => 0];
/** @var ArrayObject<string,array{0:string,1:callable():mixed}> what to remove at the end */
$cleanup = new ArrayObject();

/** Run one step; failures are reported and the run continues. */
$step = function (string $name, callable $fn) use (&$results): mixed {
    try {
        $value = $fn();
        $results['ok']++;
        echo "  ok    {$name}\n";
        return $value;
    } catch (Throwable $error) {
        $results['fail']++;
        echo "  FAIL  {$name}: " . describe($error) . "\n";
        return null;
    }
};

/**
 * A step the API answers with an error by design: a matching ApiError passes,
 * as does success (the behaviour changed); anything else fails as usual.
 *
 * @param callable(ApiError):bool $isExpected
 */
$byDesign = function (string $name, string $behaviour, callable $isExpected, callable $fn) use (&$results): void {
    try {
        $fn();
        $results['ok']++;
        echo "  ok    {$name} (succeeded; expected by design: {$behaviour})\n";
    } catch (ApiError $error) {
        if ($isExpected($error)) {
            $results['ok']++;
            echo "  ok    {$name}: " . describe($error) . " (by design: {$behaviour})\n";
            return;
        }
        $results['fail']++;
        echo "  FAIL  {$name}: " . describe($error) . "\n";
    } catch (Throwable $error) {
        $results['fail']++;
        echo "  FAIL  {$name}: " . describe($error) . "\n";
    }
};

function describe(Throwable $error): string
{
    if ($error instanceof ApiError) {
        return sprintf('HTTP %d %s: %s', $error->statusCode, $error->errorCode ?? '-', $error->getMessage());
    }
    return get_class($error) . ': ' . $error->getMessage();
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param list<array<string,mixed>> $rows */
function find_row(?array $rows, string $key, string $value): ?array
{
    foreach ($rows ?? [] as $row) {
        if (($row[$key] ?? null) === $value) {
            return $row;
        }
    }
    return null;
}

echo "smoke: {$host}, suffix {$suffix}\n";

try {
    // Workspaces
    $step('workspaces.list', function () use ($shortn, $workspaceId): void {
        $list = $shortn->workspaces->list();
        check(find_row($list['data'], 'public_id', $workspaceId) !== null, 'smoke workspace not listed');
    });
    $step('workspaces.get', function () use ($shortn, $workspaceId): void {
        check($shortn->workspaces->get($workspaceId)['data']['public_id'] === $workspaceId, 'wrong workspace');
    });

    // Public helpers, without a key
    $step('public.validateUrl', function () use ($anonymous): void {
        $result = $anonymous->public->validateUrl('example.com/sdk-smoke');
        check($result['valid'] === true && $result['normalized'] === 'https://example.com/sdk-smoke', 'unexpected validation ' . json_encode($result));
        $bad = $anonymous->public->validateUrl('not a url');
        check($bad['valid'] === false, 'invalid URL reported valid');
    });
    $step('public.plans', function () use ($anonymous): void {
        $plans = $anonymous->public->plans();
        check(isset($plans['plans']['free']), 'free plan missing');
    });
    $step('public.qrCode', function () use ($anonymous): void {
        $png = $anonymous->public->qrCode(['url' => 'https://example.com/sdk-smoke', 'format' => 'png']);
        check(strlen($png) > 100 && str_starts_with($png, "\x89PNG\r\n\x1a\n"), 'not a PNG');
        check(str_starts_with($anonymous->lastResponse()['headers']['content-type'] ?? '', 'image/png'), 'content type');
    });

    // Links
    $link = $step('links.create', function () use ($shortn, $workspaceId, $suffix, $cleanup): array {
        $link = $shortn->links->create([
            'url' => 'https://example.com/sdk-smoke-php',
            'title' => "SDK smoke php {$suffix}",
            'workspace_public_id' => $workspaceId,
        ]);
        check(is_string($link['public_id'] ?? null) && $link['short_code'] !== '', 'no public_id');
        $cleanup['link'] = ['links.delete', fn () => $shortn->links->delete($link['public_id'])];
        return $link;
    });
    if (is_array($link)) {
        $linkId = $link['public_id'];
        $step('links.get', function () use ($shortn, $linkId, $workspaceId): void {
            $data = $shortn->links->get($linkId)['data'];
            check($data['public_id'] === $linkId && $data['workspace_public_id'] === $workspaceId, 'wrong link');
        });
        $step('links.update', function () use ($shortn, $linkId, $suffix): void {
            $shortn->links->update($linkId, ['title' => "SDK smoke php {$suffix} updated"]);
            check($shortn->links->get($linkId)['data']['title'] === "SDK smoke php {$suffix} updated", 'title not changed');
        });
        $step('links.list', function () use ($shortn, $linkId): void {
            $page = $shortn->links->list(['limit' => 100]);
            check(find_row($page['data'], 'public_id', $linkId) !== null, 'new link not in the first page');
            check(isset($page['pagination']['has_more']), 'pagination missing');
        });
        $step('analytics.link', function () use ($shortn, $linkId): void {
            $totals = $shortn->analytics->link($linkId);
            check(array_key_exists('total_clicks', $totals) && array_key_exists('clicks_by_country', $totals), 'totals missing');
        });
        $step('links.setAbWinner (no test running: 409)', function () use ($shortn, $linkId): void {
            try {
                $shortn->links->setAbWinner($linkId, ['variant' => 'B']);
            } catch (ApiError $error) {
                check($error->statusCode === 409 && $error->errorCode === 'ab_test_not_running', 'unexpected ' . describe($error));
                return;
            }
            throw new RuntimeException('expected 409 ab_test_not_running');
        });
        $step('links.delete', function () use ($shortn, $linkId): void {
            $shortn->links->delete($linkId);
            check($shortn->links->get($linkId)['data']['status'] === 'suspended', 'not suspended');
        });
        $step('links.enable', function () use ($shortn, $linkId, $cleanup): void {
            $shortn->links->enable($linkId);
            check($shortn->links->get($linkId)['data']['status'] === 'active', 'not active');
            $shortn->links->delete($linkId);
            unset($cleanup['link']);
        });
    }
    $step('links.get (unknown: 404)', function () use ($shortn): void {
        try {
            $shortn->links->get('00000000-0000-4000-8000-000000000000');
        } catch (ApiError $error) {
            check($error->statusCode === 404 && $error->errorCode === 'link_not_found', 'unexpected ' . describe($error));
            return;
        }
        throw new RuntimeException('expected 404');
    });

    // Tags
    $tag = $step('tags.create', function () use ($shortn, $workspaceId, $suffix, $cleanup): array {
        $tag = $shortn->tags->create($workspaceId, ['name' => "sdk-php-{$suffix}", 'color' => '#3b82f6']);
        $cleanup['tag'] = ['tags.delete', fn () => $shortn->tags->delete($tag['public_id'])];
        return $tag;
    });
    if (is_array($tag)) {
        $tagId = $tag['public_id'];
        $step('tags.get', fn () => check($shortn->tags->get($tagId)['name'] === "sdk-php-{$suffix}", 'wrong tag'));
        $step('tags.update', fn () => check($shortn->tags->update($tagId, ['color' => '#16a34a'])['color'] === '#16a34a', 'color not changed'));
        $step('tags.list', fn () => check(find_row($shortn->tags->list($workspaceId)['tags'], 'public_id', $tagId) !== null, 'tag not listed'));
        $step('tags.delete', function () use ($shortn, $tagId, $cleanup): void {
            check($shortn->tags->delete($tagId)['message'] !== '', 'no message');
            unset($cleanup['tag']);
        });
    }

    // UTM templates
    $template = $step('utmTemplates.create', function () use ($shortn, $workspaceId, $suffix, $cleanup): array {
        $template = $shortn->utmTemplates->create($workspaceId, ['name' => "SDK smoke php {$suffix}", 'utm_source' => 'sdk', 'utm_medium' => 'smoke'])['data'];
        $cleanup['template'] = ['utmTemplates.delete', fn () => $shortn->utmTemplates->delete($template['public_id'])];
        return $template;
    });
    if (is_array($template)) {
        $templateId = $template['public_id'];
        $step('utmTemplates.update', function () use ($shortn, $templateId, $suffix): void {
            $data = $shortn->utmTemplates->update($templateId, ['name' => "SDK smoke php {$suffix}", 'utm_source' => 'sdk', 'utm_medium' => 'smoke', 'utm_campaign' => 'updated'])['data'];
            check($data['utm_campaign'] === 'updated', 'campaign not changed');
        });
        $step('utmTemplates.list', fn () => check(find_row($shortn->utmTemplates->list($workspaceId)['data'], 'public_id', $templateId) !== null, 'template not listed'));
        $step('utmTemplates.delete', function () use ($shortn, $templateId, $cleanup): void {
            check($shortn->utmTemplates->delete($templateId, ['links' => 'keep'])['data']['deleted'] === true, 'not deleted');
            unset($cleanup['template']);
        });
    }

    // Teams
    $team = $step('teams.create', function () use ($shortn, $workspaceId, $suffix, $cleanup): array {
        $team = $shortn->teams->create($workspaceId, ['name' => "SDK smoke php {$suffix}", 'description' => 'Created by the PHP SDK smoke test']);
        $cleanup['team'] = ['teams.delete', fn () => $shortn->teams->delete($team['slug'], $workspaceId)];
        return $team;
    });
    if (is_array($team)) {
        $slug = $team['slug'];
        $step('teams.get', fn () => check($shortn->teams->get($slug, $workspaceId)['public_id'] === $team['public_id'], 'wrong team'));
        $step('teams.update', fn () => check($shortn->teams->update($slug, ['name' => "SDK smoke php {$suffix} updated"], $workspaceId)['name'] === "SDK smoke php {$suffix} updated", 'name not changed'));
        $step('teams.list', fn () => check(find_row($shortn->teams->list($workspaceId)['teams'], 'public_id', $team['public_id']) !== null, 'team not listed'));
        $step('teams.delete', function () use ($shortn, $slug, $workspaceId, $cleanup): void {
            check($shortn->teams->delete($slug, $workspaceId)['success'] === true, 'not archived');
            unset($cleanup['team']);
        });
    }

    // Destinations
    $destination = $step('destinations.create', function () use ($shortn, $workspaceId, $suffix, $cleanup): array {
        $destination = $shortn->destinations->create($workspaceId, [
            'name' => "SDK smoke php {$suffix}",
            'type' => 'webhook',
            'config' => ['webhook_url' => 'https://example.com/hooks/sdk-smoke'],
        ]);
        $cleanup['destination'] = ['destinations.delete', fn () => $shortn->destinations->delete($workspaceId, $destination['public_id'])];
        return $destination;
    });
    if (is_array($destination)) {
        $destinationId = $destination['public_id'];
        $step('destinations.update', fn () => check($shortn->destinations->update($workspaceId, $destinationId, ['name' => "SDK smoke php {$suffix} updated", 'type' => 'webhook'])['success'] === true, 'not updated'));
        $step('destinations.list', function () use ($shortn, $workspaceId, $destinationId, $suffix): void {
            $row = find_row($shortn->destinations->list($workspaceId), 'public_id', $destinationId);
            check($row !== null && $row['name'] === "SDK smoke php {$suffix} updated", 'destination not listed with its new name');
        });
        $step('destinations.delete', function () use ($shortn, $workspaceId, $destinationId, $cleanup): void {
            check($shortn->destinations->delete($workspaceId, $destinationId)['success'] === true, 'not deleted');
            unset($cleanup['destination']);
        });
    }

    // Reports: a rule with no destinations whose first run is years away sends nothing.
    $step('reports.listRules', fn () => check(is_array($shortn->reports->listRules(['workspace_public_id' => $workspaceId])['data'] ?? null), 'no data'));
    $rule = $step('reports.createRule', function () use ($shortn, $workspaceId, $suffix, $cleanup): array {
        $rule = $shortn->reports->createRule([
            'workspace_public_id' => $workspaceId,
            'report_type' => 'custom_builder_v1',
            'schedule_type' => 'every_x',
            'interval_seconds' => 604800,
            'initial_run_at' => '2036-01-05T08:00:00Z',
            'report_name' => "SDK smoke php {$suffix}",
            'definition_json' => [
                'version' => 'custom_builder.v1',
                'trigger' => 'scheduled',
                'sections' => [['id' => 'total_clicks', 'order' => 1, 'settings' => ['window' => '7d']]],
                'options' => ['locale' => 'en-US'],
            ],
        ])['data'];
        check(is_string($rule['public_id'] ?? null), 'no public_id in ' . json_encode($rule));
        $cleanup['rule'] = ['reports.deleteRule', fn () => $shortn->reports->deleteRule($rule['public_id'])];
        return $rule;
    });
    if (is_array($rule)) {
        $ruleId = $rule['public_id'];
        $step('reports.updateRule', fn () => check($shortn->reports->updateRule($ruleId, ['enabled' => false])['data']['enabled'] === false, 'not paused'));
        $step('reports.getRule', function () use ($shortn, $ruleId, $suffix): void {
            $data = $shortn->reports->getRule($ruleId)['data'];
            check($data['report_name'] === "SDK smoke php {$suffix}" && $data['enabled'] === false, 'unexpected rule');
        });
        $step('reports.deleteRule', function () use ($shortn, $ruleId, $cleanup): void {
            check($shortn->reports->deleteRule($ruleId) === [], 'expected an empty answer');
            unset($cleanup['rule']);
        });
    }

    // Domains: list only (adding one starts certificate work).
    $step('domains.list', fn () => check(is_array($shortn->domains->list()['data'] ?? null), 'no data'));

    // Conversions, in test mode on the fixture click.
    $invoice = "sdk-php-inv-{$suffix}";
    $lead = $step('conversions.trackLead', function () use ($shortn, $clickId, $suffix): array {
        $result = $shortn->conversions->trackLead(['click_id' => $clickId, 'idempotency_key' => "sdk-php-lead-{$suffix}", 'event_name' => 'SDK smoke lead', 'lead_category' => 'signup', 'test_mode' => true]);
        check($result['type'] === 'lead' && $result['event']['is_test'] === true, 'unexpected ' . json_encode($result));
        return $result;
    });
    $step('conversions.validateLead', function () use ($shortn, $clickId, $suffix): void {
        $result = $shortn->conversions->validateLead(['click_id' => $clickId, 'idempotency_key' => "sdk-php-lead-v-{$suffix}", 'test_mode' => true]);
        check($result['valid'] === true && $result['dry_run'] === true, 'unexpected ' . json_encode($result));
    });
    $step('conversions.trackSale', function () use ($shortn, $clickId, $invoice, $suffix): void {
        $result = $shortn->conversions->trackSale(['click_id' => $clickId, 'amount_cents' => 1000, 'currency' => 'EUR', 'invoice_id' => $invoice, 'idempotency_key' => "sdk-php-sale-{$suffix}", 'test_mode' => true]);
        check($result['type'] === 'sale' && $result['event']['amount_cents'] === 1000, 'unexpected ' . json_encode($result));
    });
    $step('conversions.validateSale', function () use ($shortn, $clickId, $suffix): void {
        $result = $shortn->conversions->validateSale(['click_id' => $clickId, 'amount' => '5.00', 'currency' => 'EUR', 'invoice_id' => "sdk-php-inv-v-{$suffix}", 'test_mode' => true]);
        check($result['valid'] === true, 'unexpected ' . json_encode($result));
    });
    // By design: a test-mode refund names a live sale's invoice_id, so a
    // test-mode sale can't be refunded and the API answers 404
    // conversion_sale_not_found. Test-mode events are never deduplicated.
    $refundBehaviour = 'a test-mode sale cannot be refunded';
    $saleNotFound = static fn (ApiError $error): bool => $error->statusCode === 404 && $error->errorCode === 'conversion_sale_not_found';
    $byDesign('conversions.trackRefund', $refundBehaviour, $saleNotFound, function () use ($shortn, $invoice, $suffix): void {
        $result = $shortn->conversions->trackRefund(['invoice_id' => $invoice, 'refund_id' => "sdk-php-re-{$suffix}", 'amount_cents' => 300, 'reason' => 'SDK smoke', 'idempotency_key' => "sdk-php-refund-{$suffix}", 'test_mode' => true]);
        check($result['type'] === 'refund', 'unexpected ' . json_encode($result));
    });
    $byDesign('conversions.validateRefund', $refundBehaviour, $saleNotFound, function () use ($shortn, $invoice, $suffix): void {
        $result = $shortn->conversions->validateRefund(['invoice_id' => $invoice, 'refund_id' => "sdk-php-re-v-{$suffix}", 'amount_cents' => 300, 'test_mode' => true]);
        check($result['valid'] === true, 'unexpected ' . json_encode($result));
    });
    $step('conversions.validateLead (missing key: 400)', function () use ($shortn, $clickId): void {
        try {
            $shortn->conversions->validateLead(['click_id' => $clickId, 'test_mode' => true]);
        } catch (ApiError $error) {
            check($error->statusCode === 400 && $error->errorCode === 'conversion_identity_required' && $error->field === 'idempotency_key', 'unexpected ' . describe($error));
            return;
        }
        throw new RuntimeException('expected 400');
    });
    $step('conversions.summary', fn () => check(isset($shortn->conversions->summary(['environment' => 'test'])['data']['leads']), 'no totals'));
    $step('conversions.attribution', fn () => check(is_array($shortn->conversions->attribution(['environment' => 'test', 'limit' => 5])['data'] ?? null), 'no rows'));
    $step('conversions.timeline', fn () => check(is_array($shortn->conversions->timeline(['environment' => 'test', 'interval' => 'day'])['data'] ?? null), 'no points'));
    $step('conversions.events', function () use ($shortn, $lead): void {
        $query = ['environment' => 'test', 'limit' => 10];
        if (is_array($lead)) {
            $query['event'] = $lead['event']['public_id'];
        }
        $events = $shortn->conversions->events($query);
        check(($events['environment'] ?? null) === 'test', 'environment not echoed');
        if (is_array($lead)) {
            check(find_row($events['data'], 'public_id', $lead['event']['public_id']) !== null, 'lead not found in test events');
        }
    });
} finally {
    foreach (array_reverse($cleanup->getArrayCopy()) as [$name, $fn]) {
        try {
            $fn();
            echo "  cleanup {$name}\n";
        } catch (Throwable $error) {
            if (!($error instanceof ApiError && $error->statusCode === 404)) {
                $results['fail']++;
                echo "  FAIL  cleanup {$name}: " . describe($error) . "\n";
            }
        }
    }
}

printf("\nsmoke: %d passed, %d failed\n", $results['ok'], $results['fail']);
exit($results['fail'] === 0 ? 0 : 1);
