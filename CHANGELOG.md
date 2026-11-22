# Changelog

## 0.1.0 - 2026-11-22

First release.

- `Client` with API key (optional for the key-less helpers), base URL (default
  `https://app.shortn.link`, `/api/v1` appended), timeout, retry options, user-agent
  suffix, and replaceable `transport` and `sleep` callables. HTTP through cURL, or PHP
  streams when cURL isn't loaded. PHP 8.1 or newer, no dependencies.
- Resources for the whole public API, 50 methods:
  - `links`: `list`, `get`, `create`, `update`, `delete` (suspend), `enable`, `setAbWinner`;
  - `analytics`: `link`;
  - `workspaces`: `list`, `get`;
  - `tags`: `list`, `create`, `get`, `update`, `delete`;
  - `utmTemplates`: `list`, `create`, `update`, `delete`;
  - `destinations`: `list`, `create`, `update`, `delete`;
  - `teams`: `list`, `create`, `get`, `update`, `delete` (archive), with the optional workspace for slugs;
  - `domains`: `list`, `create`, `verify`, `delete`;
  - `reports`: `listRules`, `getRule`, `createRule`, `updateRule`, `deleteRule`;
  - `conversions`: `trackLead`, `validateLead`, `trackSale`, `validateSale`,
    `trackRefund`, `validateRefund`, `summary`, `attribution`, `timeline`, `events`;
  - `public` (no key): `validateUrl`, `qrCode` (image bytes), `plans`.
- Answers are returned as decoded arrays, so new API fields need no SDK release;
  `lastResponse()` gives the status and headers of the last response.
- `ApiError` with HTTP status, `code`, message, `hint`, `field`, `details`, request ID,
  `Retry-After`, the decoded body (plan-limit fields included) and the raw body.
  `TransportException` for requests that got no response.
- Retries on 429, 503 and network errors for `GET`, `PUT`, `DELETE` and for `POST`s
  that carry an `idempotency_key`, honouring `Retry-After`, with bounded exponential
  backoff and jitter; `RetryOptions::none()` turns them off. A `test_mode` conversion is
  never retried, because the API never deduplicates test events.
- Path parameters are URL-escaped and must not be empty; query parameters are sent only
  when set.
- Encoding that matches what the API reads: whole-number floats are sent as integers
  (`round($total * 100)` becomes `1999`, not `1999.0`); an empty array under `metadata`,
  `config`, `device_targets`, `os_targets`, `browser_targets`, `language_targets` or
  `attribution` is sent as `{}`; `DateTimeInterface` values are sent as RFC 3339 in bodies
  and queries; query booleans are sent as `1`/`0` (`true`/`false` for `public->qrCode()`).
- The cURL transport sets `CURLOPT_NOSIGNAL`.
- Offline unit tests for every method (`composer test`) and an opt-in smoke test
  against a development instance (`composer smoke`).
