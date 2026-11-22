# Gap list

## Covered

Every route of the public API (`/api/v1`) that a server can call, 50 in all: links (7),
analytics (1), workspaces (2), tags (5), UTM templates (4), destinations (4), teams (5),
custom domains (4), report and alert rules (5), conversions (10), and the key-less
`/validate`, `/qr` and `/plans`. See the README for the method list.

Each method has an offline unit test (method, escaped path, query, JSON body, headers,
decoded answer), plus tests for error mapping and retry rules. The opt-in smoke test
exercises every method against a development instance except `domains.create`,
`domains.verify` and `domains.delete` (adding a domain starts certificate work) and
`links.setAbWinner` with a running test (it checks the `409 ab_test_not_running` answer
instead).

## Not in the SDK, on purpose

- Key-less `POST /api/v1/links` and `GET /api/v1/links/{code}/analytics`: they need a
  human check passed on shortn.link (`X-Turnstile-Token`), so a server can't use them.
  With a key, use `links->create()` and `analytics->link()`.
- Dashboard (`/api/app`) endpoints: they use browser sessions, not API keys.

## Left to do

- Typed response objects (DTOs) for users who prefer them to arrays; arrays stay the
  base so new fields need no release.
- Pagination helpers (an iterator over `links->list()` and `conversions->events()`).
- A PSR-18 HTTP client adapter as an alternative to the built-in transport.
- Static analysis (PHPStan) in CI for the PHPDoc array shapes.

## Behaviour to know

- Test mode and refunds (by design): a `test_mode` refund names a live sale's `invoice_id`
  and is recorded as a test event that leaves the sale's totals alone; a test-mode sale can't
  be refunded, so the smoke test expects `404 conversion_sale_not_found` for that step.
  Test-mode leads and sales are never deduplicated: each request records a new test event,
  so the SDK never retries a test-mode conversion.
