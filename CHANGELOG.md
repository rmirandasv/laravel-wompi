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
