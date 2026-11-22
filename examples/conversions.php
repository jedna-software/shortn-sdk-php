<?php

declare(strict_types=1);

// Send a lead, a sale and a refund for a click, then read the reports.
// test_mode keeps the events out of live reports.

require __DIR__ . '/../vendor/autoload.php';

use Shortn\Sdk\Client;

$shortn = new Client(getenv('SHORTN_API_KEY') ?: null, getenv('SHORTN_BASE_URL') ?: Client::DEFAULT_BASE_URL);
$clickId = getenv('SHORTN_CLICK_ID') ?: 'clk_...';

// Check a payload without recording it.
$check = $shortn->conversions->validateLead(['click_id' => $clickId, 'idempotency_key' => 'signup_8412', 'test_mode' => true]);

// The idempotency_key makes the call safe to retry: the SDK retries it on 429/503.
$lead = $shortn->conversions->trackLead([
    'click_id' => $clickId,
    'event_name' => 'Sign up',
    'lead_category' => 'signup',
    'idempotency_key' => 'signup_8412',
    'test_mode' => true,
]);

$sale = $shortn->conversions->trackSale([
    'click_id' => $clickId,
    'amount_cents' => 4900,          // 49.00 EUR
    'currency' => 'EUR',
    'invoice_id' => 'inv_123',
    'idempotency_key' => 'sale_inv_123',
    'test_mode' => true,
]);

$refund = $shortn->conversions->trackRefund([
    'invoice_id' => 'inv_123',
    'refund_id' => 're_456',
    'amount_cents' => 1900,
    'reason' => 'Damaged item',
    'test_mode' => true,
]);

$events = $shortn->conversions->events(['environment' => 'test', 'event_type' => 'sale', 'limit' => 10]);
$timeline = $shortn->conversions->timeline(['environment' => 'test', 'interval' => 'week', 'from' => '2026-10-01']);
$summary = $shortn->conversions->summary();

print_r([
    'valid' => $check['valid'],
    'lead_duplicate' => $lead['event']['duplicate'],
    'sale' => $sale['event']['amount'] . ' ' . $sale['event']['currency'],
    'refund_status' => $refund['event']['refund_status'] ?? null,
    'test_sales' => count($events['data']),
    'weeks' => count($timeline['data']),
    'live_net_revenue_cents' => $summary['data']['net_revenue_cents'],
]);
