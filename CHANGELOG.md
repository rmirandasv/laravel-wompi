## 2.0.0 (2026-11-18)

* build: upgrade semantic-release to v25 and the release workflow to Node 22 ([b27e984](https://github.com/rmirandasv/laravel-wompi/commit/b27e984))
* docs: rewrite the documentation for v2 ([7bb4c4b](https://github.com/rmirandasv/laravel-wompi/commit/7bb4c4b))
* ci: test the patched PHP versions and refresh the Wompi spec ([ec07ff1](https://github.com/rmirandasv/laravel-wompi/commit/ec07ff1))
* test: cover the endpoint contract and the v2 behaviour ([c1a78c0](https://github.com/rmirandasv/laravel-wompi/commit/c1a78c0))
* fix(config): point the API and auth URLs at the real Wompi endpoints ([cc9a94d](https://github.com/rmirandasv/laravel-wompi/commit/cc9a94d))
* fix(security): require a patched PHP version to prevent PHP2Shell ([782661d](https://github.com/rmirandasv/laravel-wompi/commit/782661d))
* feat(api): use the real Wompi endpoints for payments, transactions and subscriptions ([50a2b82](https://github.com/rmirandasv/laravel-wompi/commit/50a2b82))
* feat(dtos): add typed request and response DTOs for the core commerce API ([bced32b](https://github.com/rmirandasv/laravel-wompi/commit/bced32b))
* feat(http): add the transport layer, the OAuth2 authenticator and the card masker ([a914224](https://github.com/rmirandasv/laravel-wompi/commit/a914224))
* feat(webhook): normalize the payload, respect EsProductiva and dispatch once ([4d1c394](https://github.com/rmirandasv/laravel-wompi/commit/4d1c394))
* feat(webhook): publish the webhook route and the controller ([7d8072c](https://github.com/rmirandasv/laravel-wompi/commit/7d8072c))


### BREAKING CHANGE

* createRecurringCharge(), executeTestTransaction() and
getTokenizedCard() were removed, and getAplicativoData() now returns an
AplicativoResponseDTO instead of an array. Use createRecurringPaymentLink() or
createTokenizedTransaction() for subscriptions, listTokenizedCards() to read
the tokenized cards, and the Wompi panel development mode to run test
transactions.
* isSuccessfulPayment() now returns false for a test
transaction, validateWebhookRequest() returns a WompiWebhook instead of the
raw array, and the events carry the normalized webhook as an extra argument.
* the card fields are renamed to mesVencimiento and
anioVencimiento, the 3DS payload nests the card under tarjetaCreditoDebido and
now requires the customer information, and the response DTOs expose the real
Wompi fields instead of the previously documented ones.
* the package now requires PHP 8.2.35, 8.3.36, 8.4.27 or
8.5.12. Update PHP before upgrading, and check that your distribution or LTS
vendor ships the backported fix.
* WOMPI_API_URL must no longer contain the /v1 suffix and
WOMPI_AUTH_URL must point to https://id.wompi.sv/connect/token. The
WOMPI_WEBHOOK_SECRET variable was removed; use WOMPI_CLIENT_SECRET instead.

## 1.1.0 (2026-04-21)

* Merge pull request #8 from rmirandasv/feat/laravel13-support ([53c511c](https://github.com/rmirandasv/laravel-wompi/commit/53c511c)), closes [#8](https://github.com/rmirandasv/laravel-wompi/issues/8)
* feat: Laravel 13 support ([4a2d0c2](https://github.com/rmirandasv/laravel-wompi/commit/4a2d0c2))

## <small>1.0.2 (2026-04-10)</small>

* Merge pull request #7 from rmirandasv/feat/laravel-wompi-skill ([39feade](https://github.com/rmirandasv/laravel-wompi/commit/39feade)), closes [#7](https://github.com/rmirandasv/laravel-wompi/issues/7)
* docs: improve AI skill and add integration section to README ([bae7949](https://github.com/rmirandasv/laravel-wompi/commit/bae7949))

## <small>1.0.1 (2026-04-09)</small>

* Merge branch 'main' of github.com:rmirandasv/laravel-wompi ([24f693d](https://github.com/rmirandasv/laravel-wompi/commit/24f693d))
* Merge pull request #6 from rmirandasv/tests/update-wompi-client-test ([13a565c](https://github.com/rmirandasv/laravel-wompi/commit/13a565c)), closes [#6](https://github.com/rmirandasv/laravel-wompi/issues/6)
* fix: correct return type of validateRedirectParams ([42b62f3](https://github.com/rmirandasv/laravel-wompi/commit/42b62f3))
* bugfix: redirect link validation based on concatenated or json params ([9df87de](https://github.com/rmirandasv/laravel-wompi/commit/9df87de))

## 1.0.0 (2026-03-08)

* feat!: release version 1.0.0 ([bb4b5b2](https://github.com/rmirandasv/laravel-wompi/commit/bb4b5b2))
* Merge pull request #5 from rmirandasv/dev ([79d39e9](https://github.com/rmirandasv/laravel-wompi/commit/79d39e9)), closes [#5](https://github.com/rmirandasv/laravel-wompi/issues/5)
* chore: remove version from composer.josn and package.json ([de1cd12](https://github.com/rmirandasv/laravel-wompi/commit/de1cd12))


### BREAKING CHANGE

* Official release of Laravel Wompi v1.0.0 with improved architecture, DTOs, and event support.

## 0.2.0 (2026-03-08)

* Merge pull request #2 from rmirandasv/dev ([92f955b](https://github.com/rmirandasv/laravel-wompi/commit/92f955b)), closes [#2](https://github.com/rmirandasv/laravel-wompi/issues/2)
* Merge pull request #3 from rmirandasv/dev ([8d4701e](https://github.com/rmirandasv/laravel-wompi/commit/8d4701e)), closes [#3](https://github.com/rmirandasv/laravel-wompi/issues/3)
* Merge pull request #4 from rmirandasv/dev ([2f87612](https://github.com/rmirandasv/laravel-wompi/commit/2f87612)), closes [#4](https://github.com/rmirandasv/laravel-wompi/issues/4)
* fix: remove composer.lock to support multiple PHP versions ([f4fee1d](https://github.com/rmirandasv/laravel-wompi/commit/f4fee1d))
* fix: use composer update --prefer-stable in CI for better PHP compatibility ([cef2abf](https://github.com/rmirandasv/laravel-wompi/commit/cef2abf))
* chore: update composer package version ([40f4f32](https://github.com/rmirandasv/laravel-wompi/commit/40f4f32))
* chore: update package version ([be40f06](https://github.com/rmirandasv/laravel-wompi/commit/be40f06))
* feat: add support for PHP 8.4/8.5 and bump version to 0.2.0 ([eda5136](https://github.com/rmirandasv/laravel-wompi/commit/eda5136))

## 0.1.0 (2026-03-08)

* Merge pull request #1 from rmirandasv/dev ([fdbeddd](https://github.com/rmirandasv/laravel-wompi/commit/fdbeddd)), closes [#1](https://github.com/rmirandasv/laravel-wompi/issues/1)
* docs: detail specific exceptions and error body retrieval ([f4d6e89](https://github.com/rmirandasv/laravel-wompi/commit/f4d6e89))
* docs: explain webhook events and middleware usage ([f52eff4](https://github.com/rmirandasv/laravel-wompi/commit/f52eff4))
* docs: update readme with interface and dto usage ([3b4fa3f](https://github.com/rmirandasv/laravel-wompi/commit/3b4fa3f))
* feat: add webhook signature verification middleware ([37f44dd](https://github.com/rmirandasv/laravel-wompi/commit/37f44dd))
* feat: enrich API exceptions and mask sensitive data in logs ([dd2c53d](https://github.com/rmirandasv/laravel-wompi/commit/dd2c53d))
* feat: implement early local validation in request DTOs ([24801b1](https://github.com/rmirandasv/laravel-wompi/commit/24801b1))
* feat: introduce domain events for webhook processing ([b2c84c3](https://github.com/rmirandasv/laravel-wompi/commit/b2c84c3))
* feat: introduce interfaces and request/response DTOs ([53b7f0c](https://github.com/rmirandasv/laravel-wompi/commit/53b7f0c))
* test: remove strict array assertions for DTO compatibility ([3b44f0b](https://github.com/rmirandasv/laravel-wompi/commit/3b44f0b))
* fix: set testing cache and db to array and memory ([648652e](https://github.com/rmirandasv/laravel-wompi/commit/648652e))
* chore: init gemini config ([3a9cddb](https://github.com/rmirandasv/laravel-wompi/commit/3a9cddb))

## <small>0.0.1 (2025-10-15)</small>

* fix: disable Husky in CI to prevent hook conflicts during release ([87b8ff9](https://github.com/rmirandasv/laravel-wompi/commit/87b8ff9))
* fix: skip git hooks on semantic-release commits in CI ([153d4f4](https://github.com/rmirandasv/laravel-wompi/commit/153d4f4))
* ci: generate package-lock ([6873947](https://github.com/rmirandasv/laravel-wompi/commit/6873947))
