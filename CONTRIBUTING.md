# Contributing

Thanks for helping improve the Shortn PHP SDK.

## Development

```sh
composer run lint
composer run test    # offline unit tests, no dependencies needed
composer run smoke   # optional, against a development instance
```

The unit tests use a mocked transport and never call the network. The smoke test runs
only when `SHORTN_API_KEY` is set; point it at a development instance with
`SHORTN_BASE_URL`, never at production (it refuses `app.shortn.link`).

Keep the code compatible with the PHP version `composer.json` declares (8.1).

## Pull requests

- Keep changes focused.
- Add a unit test for every new method or behaviour (`tests/unit/`).
- Update the README, CHANGELOG and GAP_LIST when behaviour changes.
- Don't commit secrets or real customer data.
