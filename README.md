# Shortn PHP SDK

PHP client for the [Shortn](https://shortn.link) public API (`/api/v1`): links,
analytics, workspaces, tags, UTM templates, report destinations, teams, custom domains,
report and alert rules, conversions, and the endpoints that need no key.

Use it from trusted server-side code only. Never ship a Shortn API key to a browser, a
mobile app or any other client the user controls.

- PHP 8.1 or newer, no dependencies (uses `ext-curl` when it's loaded, PHP streams otherwise)
- Every method returns the decoded JSON as an array, so fields the API adds later are
  there without an SDK update
- One exception type for API errors, with status, `code`, message, `hint`, `field` and `details`
- Retries on `429` and `503` (honouring `Retry-After`) and on network errors, only where
  that is safe

## Installation

```sh
composer require jednasoftware/shortn-sdk
```

## Quick start

Create a key under **Profile → API keys** in the app and give it the scopes you need
(see the [authentication docs](https://docs.shortn.link/docs/api/authentication/)).

```php
use Shortn\Sdk\Client;

$shortn = new Client(apiKey: getenv('SHORTN_API_KEY'));

$link = $shortn->links->create([
    'url' => 'https://example.com/spring',
    'title' => 'Spring campaign',
]);

echo $link['short_url'], PHP_EOL;          // https://shtnl.ink/abC12x9
```

### Client options

```php
use Shortn\Sdk\Client;
use Shortn\Sdk\RetryOptions;

$shortn = new Client(
    apiKey: getenv('SHORTN_API_KEY'),       // optional only for $shortn->public
    baseUrl: 'https://app.shortn.link',     // default; the SDK appends /api/v1
    timeout: 30.0,                          // seconds per attempt
    retry: new RetryOptions(maxRetries: 2), // or RetryOptions::none()
    userAgent: 'my-app/1.0',                // appended to shortn-sdk-php/0.1.0
    transport: null,                        // your own HTTP layer, see "Testing"
);
```

Requests carry `Authorization: Bearer <key>` and `User-Agent: shortn-sdk-php/0.1.0`.

## Resources

Methods map one to one to the API. Path parameters are URL-escaped; query parameters
are sent only when you set them (`null` values and empty lists are left out, lists are
joined with commas, booleans become `1`/`0`, `DateTimeInterface` values are sent
as RFC 3339; `public->qrCode()` sends its booleans as `true`/`false`). In a request
body, top-level `null` values are left out, `DateTimeInterface` values are sent as
RFC 3339, and whole-number floats such as `round($total * 100)` are sent as integers.
An empty PHP array encodes as `[]`, except under `metadata`, `config`, `device_targets`,
`os_targets`, `browser_targets`, `language_targets` and `attribution`, where it is sent
as `{}`; elsewhere send an empty JSON object as `(object) []` or `new stdClass()`.

| Property | Methods | Routes |
|---|---|---|
| `links` | `list(query)`, `get(publicId)`, `create(params)`, `update(publicId, params)`, `delete(publicId)`, `enable(publicId)`, `setAbWinner(publicId, params)` | `/links`, `/links/{code}`, `/links/{code}/enable`, `/links/{code}/ab/winner` |
| `analytics` | `link(id, query)` | `GET /analytics/links/{id}` |
| `workspaces` | `list()`, `get(publicId)` | `/workspaces`, `/workspaces/{publicId}` |
| `tags` | `list(workspaceId, query)`, `create(workspaceId, params)`, `get(id)`, `update(id, params)`, `delete(id)` | `/workspaces/{id}/tags`, `/tags/{id}` |
| `utmTemplates` | `list(workspaceId, query)`, `create(workspaceId, params)`, `update(templateId, params)`, `delete(templateId, query)` | `/workspaces/{id}/utm-templates`, `/utm-templates/{id}` |
| `destinations` | `list(workspaceId)`, `create(workspaceId, params)`, `update(workspaceId, destinationId, params)`, `delete(workspaceId, destinationId)` | `/workspaces/{id}/destinations[/{destinationId}]` |
| `teams` | `list(workspaceId)`, `create(workspaceId, params)`, `get(slug, workspaceId?)`, `update(slug, params, workspaceId?)`, `delete(slug, workspaceId?)` | `/workspaces/{id}/teams`, `/teams/{slug}` |
| `domains` | `list()`, `create(params)`, `verify(id)`, `delete(id)` | `/domains`, `/domains/{id}`, `/domains/{id}/verify` |
| `reports` | `listRules(query)`, `getRule(id)`, `createRule(params)`, `updateRule(id, params)`, `deleteRule(id)` | `/reports/rules[/{id}]` |
| `conversions` | `trackLead`, `validateLead`, `trackSale`, `validateSale`, `trackRefund`, `validateRefund` (params); `summary`, `attribution`, `timeline`, `events` (query) | `/conversions/...` |
| `public` | `validateUrl(url)`, `qrCode(params)`, `plans()` | `/validate`, `/qr`, `/plans` (no key needed) |

The fields, query parameters and answers of each endpoint are in the
[API reference](https://docs.shortn.link/docs/api/) and in each method's PHPDoc.

### Links

```php
$link = $shortn->links->create([
    'url' => 'https://example.com/spring',
    'title' => 'Spring campaign',
    'workspace_public_id' => $workspaceId,
    'tag_public_ids' => [$tagId],
    'utm_source' => 'newsletter',
]);
$id = $link['public_id'];

$shortn->links->update($id, ['title' => 'Spring sale', 'url' => 'https://example.com/sale']);
$data = $shortn->links->get($id)['data'];

$page = $shortn->links->list(['limit' => 100, 'offset' => 0]);
// $page['pagination']['has_more'], $page['pagination']['next_offset']

$shortn->links->delete($id);    // suspends: the link stops redirecting, analytics are kept
$shortn->links->enable($id);    // active again
$shortn->links->setAbWinner($id, ['variant' => 'B']);
```

### Analytics

```php
$totals = $shortn->analytics->link($id);
// ['total_clicks' => 1280, 'unique_clicks' => 941, 'clicks_by_country' => [...]]

$asOf = $shortn->lastResponse()['headers']['x-analytics-as-of'] ?? null; // "current" or a time
```

### Workspaces

```php
foreach ($shortn->workspaces->list()['data'] as $workspace) {
    echo $workspace['public_id'], ' ', $workspace['name'], ' ', $workspace['role'], PHP_EOL;
}
$workspace = $shortn->workspaces->get($workspaceId)['data'];
```

### Tags

```php
$tag = $shortn->tags->create($workspaceId, ['name' => 'Spring sale', 'color' => '#3b82f6']);
$shortn->tags->update($tag['public_id'], ['color' => '#16a34a']);
$tags = $shortn->tags->list($workspaceId, ['team_public_ids' => [$teamId]])['tags'] ?? [];
$shortn->tags->delete($tag['public_id']);
```

### UTM templates

```php
$template = $shortn->utmTemplates->create($workspaceId, [
    'name' => 'Newsletter', 'utm_source' => 'newsletter', 'utm_medium' => 'email',
])['data'];
// PUT replaces the template: send every value you want to keep.
$shortn->utmTemplates->update($template['public_id'], [
    'name' => 'Newsletter', 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'december',
]);
$templates = $shortn->utmTemplates->list($workspaceId, ['include_team_scoped' => true])['data'];
$shortn->utmTemplates->delete($template['public_id'], ['links' => 'keep']);
```

### Destinations

```php
$destination = $shortn->destinations->create($workspaceId, [
    'name' => 'Ops webhook',
    'type' => 'webhook',                  // email, webhook, slack, discord or msteams
    'config' => ['webhook_url' => 'https://example.com/hooks/shortn'],
]);
$shortn->destinations->update($workspaceId, $destination['public_id'], ['name' => 'Ops', 'type' => 'webhook']);
$all = $shortn->destinations->list($workspaceId);   // a plain list
$shortn->destinations->delete($workspaceId, $destination['public_id']);
```

### Teams

```php
$team = $shortn->teams->create($workspaceId, ['name' => 'Paid Social']);
// Slugs are unique per workspace: pass the workspace when the key reaches several.
$shortn->teams->update($team['slug'], ['name' => 'Paid Social EU'], $workspaceId);
$details = $shortn->teams->get($team['slug'], $workspaceId);
$teams = $shortn->teams->list($workspaceId)['teams'] ?? [];
$shortn->teams->delete($team['slug'], $workspaceId);   // archives it
```

### Domains

```php
$domains = $shortn->domains->list()['data'];
$new = $shortn->domains->create(['domain' => 'go.example.com'])['domain'];  // account-wide key
// Create the records in $new['dns'] at your DNS provider, then:
$check = $shortn->domains->verify($new['id']);
$shortn->domains->delete($new['id']);
```

### Reports and alerts

```php
$rule = $shortn->reports->createRule([
    'workspace_public_id' => $workspaceId,
    'report_type' => 'custom_builder_v1',
    'interval_seconds' => 604800,
    'report_name' => 'Weekly traffic',
    'destination_public_ids' => [$destinationId],
    'definition_json' => [
        'version' => 'custom_builder.v1',
        'trigger' => 'scheduled',
        'sections' => [['id' => 'total_clicks', 'order' => 1, 'settings' => ['window' => '7d']]],
    ],
])['data'];
$shortn->reports->updateRule($rule['public_id'], ['enabled' => false]);   // pause
$rules = $shortn->reports->listRules(['workspace_public_id' => $workspaceId, 'schedule_types' => ['every_x']]);
$shortn->reports->deleteRule($rule['public_id']);                          // 204: returns []
```

### Conversions

```php
$lead = $shortn->conversions->trackLead([
    'click_id' => $clickId,               // snt_click_id from the capture script
    'event_name' => 'Sign up',
    'lead_category' => 'signup',
    'idempotency_key' => 'signup_8412',   // required for leads
]);

$sale = $shortn->conversions->trackSale([
    'click_id' => $clickId,
    'amount_cents' => 4900,               // hundredths of the main unit, or 'amount' => '49.00' (a string)
    'currency' => 'EUR',
    'invoice_id' => 'inv_123',
]);

$refund = $shortn->conversions->trackRefund([
    'invoice_id' => 'inv_123', 'refund_id' => 're_456', 'amount_cents' => 1900,
]);

// `amount` must be a decimal string such as '19.99': a PHP float is refused with
// 400 invalid_request_format. From a float total, send 'amount_cents' => round($total * 100).
// The same checks with nothing recorded: validateLead / validateSale / validateRefund.
// Add 'test_mode' => true to record test events, and read them back with environment=test.

$summary = $shortn->conversions->summary()['data'];
$events = $shortn->conversions->events(['environment' => 'test', 'event_type' => 'sale', 'limit' => 50]);
$byTarget = $shortn->conversions->attribution(['from' => '2026-10-01', 'to' => '2026-10-31']);
$weekly = $shortn->conversions->timeline(['interval' => 'week']);
```

Conversion events hold no customer profile; keep personal data out of `event_name` and
`metadata`. On your site, the
[capture script](https://docs.shortn.link/docs/conversion-tracking/install-capture-script/)
keeps the click id and posts events to your backend, which forwards them with this SDK.

### Endpoints without a key

```php
$public = new Client();   // no key

$check = $public->public->validateUrl('example.com/menu');
// ['valid' => true, 'normalized' => 'https://example.com/menu']

$png = $public->public->qrCode(['url' => 'https://shtnl.ink/abC12x9', 'size' => 'download']);
file_put_contents('qr.png', $png);   // raw bytes; the content type is in lastResponse()

$plans = $public->public->plans()['plans'];
```

Creating a link without a key and the public preview of an anonymous link need a human
check passed on shortn.link, so they aren't in the SDK. With a key, use `links->create()`
and `analytics->link()`. A keyed client sends its key to these endpoints too. Calling any
other method without a key throws a `ShortnException` before anything is sent.

### Other endpoints

`$shortn->request($method, $path, $query, $body)` sends any JSON request under `/api/v1`
with the same headers, errors and retries. `$shortn->lastResponse()` returns the status
and lower-cased headers of the last response (rate-limit headers, `shortn-request-id`,
`x-analytics-as-of`).

## Errors

A non-2xx answer throws `Shortn\Sdk\ApiError`:

```php
use Shortn\Sdk\ApiError;
use Shortn\Sdk\TransportException;

try {
    $shortn->links->create(['url' => 'https://example.com', 'title' => 'Hi', 'custom_code' => 'spring']);
} catch (ApiError $error) {
    $error->statusCode;   // 409
    $error->errorCode;    // "custom_code_conflict" (null only for a non-API answer)
    $error->getMessage(); // the readable "error"
    $error->hint;         // "hint", when present
    $error->field;        // the field at fault (details.field), when present
    $error->details;      // "details", when present
    $error->requestId;    // conversions intake: request_id / Shortn-Request-Id
    $error->retryAfter;   // seconds, from Retry-After
    $error->body;         // the decoded body, e.g. plan limits: $error->body['feature'], ['required_plan']
    $error->rawBody;      // the body as received
} catch (TransportException $error) {
    // no response: DNS, connection, TLS or timeout
}
```

Both extend `Shortn\Sdk\ShortnException` (a `RuntimeException`), which is also thrown
when a keyed endpoint is called without a key or a success answer isn't JSON. An empty
path parameter throws `InvalidArgumentException` before anything is sent.

## Retries

The client retries a request when the answer is `429` or `503`, or when no answer came
(a `TransportException`), and only when repeating it is safe:

- `GET`, `PUT` and `DELETE` requests;
- `POST` requests whose body carries a non-empty `idempotency_key` (conversions),
  unless the body sets `test_mode` to `true`.

`PATCH` and other `POST` requests are sent once. Test-mode conversions are always sent
once: the API never deduplicates test events, so each resend would record a new one. Between attempts the client waits for
`Retry-After` when the API sends it, otherwise for an exponential backoff with jitter. A
`Retry-After` longer than `maxRetryAfter` isn't waited for: the error is thrown so you
can decide.

```php
new RetryOptions(
    maxRetries: 2,        // attempts after the first
    initialDelay: 0.5,    // seconds
    maxDelay: 8.0,
    backoffFactor: 2.0,
    jitter: true,
    maxRetryAfter: 60.0,
);
RetryOptions::none();     // no retries
```

## Testing your code

Pass a `transport` callable to replace HTTP. It receives the method, URL, headers, body
and timeout and returns `['status' => int, 'headers' => [...], 'body' => string]`; throw
`TransportException` for a network failure. `sleep` replaces the pause between retries.

```php
$shortn = new Client(
    apiKey: 'sk_test',
    transport: fn (string $method, string $url, array $headers, ?string $body, float $timeout): array =>
        ['status' => 200, 'headers' => ['Content-Type' => 'application/json'], 'body' => '{"data":[]}'],
    sleep: fn (float $seconds) => null,
);
```

## Examples

[`examples/`](./examples) has runnable scripts (they read `SHORTN_API_KEY`,
`SHORTN_BASE_URL`, `SHORTN_WORKSPACE` and `SHORTN_CLICK_ID`; see `.env.example`):

- [`links.php`](./examples/links.php): create, update, page through, suspend and re-enable links; click totals.
- [`create-conversion-link.php`](./examples/create-conversion-link.php): a conversion-tracked link with attribution, and plan-limit errors.
- [`conversions.php`](./examples/conversions.php): validate and send a lead, sale and refund in test mode; read reports.
- [`workspace-setup.php`](./examples/workspace-setup.php): a team, tag, UTM template, destination and paused weekly report.
- [`public-helpers.php`](./examples/public-helpers.php): URL validation, a QR code and the plan catalog without a key.

## Development

```sh
composer run lint
composer run test     # offline unit tests (tests/run.php)
composer run smoke    # opt-in, against a development instance; skipped without SHORTN_API_KEY
```

The smoke test needs `SHORTN_API_KEY`, `SHORTN_BASE_URL`, `SHORTN_SMOKE_WORKSPACE` and
`SHORTN_SMOKE_CLICK_ID`. It creates uniquely named links, tags, UTM templates, teams, a
webhook destination and a paused report rule in that workspace, sends test-mode
conversions, and removes what it created. It refuses to run against `app.shortn.link`.

See the [CHANGELOG](./CHANGELOG.md) and the [gap list](./GAP_LIST.md) for what this
release covers.

## License

MIT
