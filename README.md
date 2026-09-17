> **🚧 Beta:** DeadCodeBundle is in active development. APIs, configuration keys and storage
> formats may still change. All feedback, bug reports and pull requests are very welcome!

# DeadCodeBundle

A Symfony bundle that detects **dead code in production** by sampling real-traffic line
coverage with [PCOV](https://github.com/krakjoe/pcov) and aggregating the results in Redis,
so you can see exactly which lines of your application are never executed by real users.

## Overview

Unlike static analysis or test-coverage tools, DeadCodeBundle measures coverage from **live
HTTP traffic**. On each request (or a sample of them), it starts PCOV, lets the request run,
then stops PCOV and stores the per-line hit counts in Redis. Over time this builds a picture
of which files, classes and lines are actually exercised in production versus code that is
never reached — a strong signal for safe removal candidates.

## Features

- **Live production coverage** — collects real per-line execution data via PCOV during actual
  HTTP requests, not synthetic tests.
- **Configurable sampling** — collect coverage on a percentage of requests (`sampling_rate`) to
  keep overhead negligible on high-traffic apps.
- **Redis-backed aggregation** — coverage from many requests/processes is merged and stored in
  Redis with a configurable TTL, so data self-expires and stays fresh.
- **Path exclusion** — ignore vendor code, cache, tests, or any other paths via `ignored_paths`.
- **Web dashboard** — a built-in `/dead-code/dashboard` page showing global coverage
  percentage, per-file breakdowns, and source code with line-by-line coverage highlighting.
- **JSON API** — `/dead-code/api` exposes the same data for scripting or external tooling.
- **CLI report** — `php bin/console dead-code:report` prints a coverage summary and highlights
  the least-covered files, ideal for CI or terminal use.
- **One-click reset** — clear all collected coverage data from the dashboard or via the
  `/dead-code/clear` route.

## Documentation

### Configuration reference

Configuration lives under the `dead_code` key, typically in `config/packages/dead_code.yaml`:

```yaml
dead_code:
  enabled: false # master on/off switch
  redis_dsn: "redis://localhost:6379" # Redis connection used to store coverage
  sampling_rate: 100 # % of requests to sample (1-100)
  cache_ttl: 86400 # seconds before stored coverage entries expire
  ignored_paths: # substrings matched against reported file paths
    - "vendor/"
    - "var/cache/"
    - "tests/"
```

| Key             | Type   | Default                               | Description                                                                             |
| --------------- | ------ | ------------------------------------- | --------------------------------------------------------------------------------------- |
| `enabled`       | bool   | `false`                               | Enables request collection. Keep `false` in environments where you don't want overhead. |
| `redis_dsn`     | string | `redis://localhost:6379`              | DSN passed to the Redis client used for storage.                                        |
| `sampling_rate` | int    | `100`                                 | Percentage (1-100) of main requests that are actually profiled.                         |
| `cache_ttl`     | int    | `86400`                               | TTL (seconds) applied to stored coverage entries in Redis.                              |
| `ignored_paths` | list   | `['vendor/', 'var/cache/', 'tests/']` | Paths excluded from collected/reported coverage.                                        |

### Routes

The bundle registers three routes (see [config/routing.php](config/routing.php)):

| Route                 | Path                   | Method | Description                                         |
| --------------------- | ---------------------- | ------ | --------------------------------------------------- |
| `dead_code_dashboard` | `/dead-code/dashboard` | GET    | HTML dashboard with global stats and per-file view. |
| `dead_code_api`       | `/dead-code/api`       | GET    | Same data as the dashboard, as JSON.                |
| `dead_code_clear`     | `/dead-code/clear`     | POST   | Clears all stored coverage data.                    |

### CLI command

```console
php bin/console dead-code:report
```

Prints the global coverage percentage and lists every file with less than 100% coverage.

### How it works

1. `CoverageListener::onKernelRequest` decides whether to collect (based on `enabled` and
   `sampling_rate`) and starts PCOV for the current request.
2. `CoverageListener::onKernelTerminate` stops PCOV, normalizes the collected line hits (PCOV's
   `-1` sentinel for "executable but not executed" is clamped to `0`), and stores them.
3. `RedisStorage` merges hit counts across requests/processes by summing them, keyed with the
   configured `cache_ttl`.
4. `CoverageReporter` reads back the aggregated data, reads matching source files from disk, and
   builds the per-file/per-line breakdown used by the dashboard, API and CLI command.

## Installation

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md) of the Composer documentation.

### Requirements

- PHP 8.1 or higher
- the [`pcov`](https://pecl.php.net/package/pcov) PHP extension (`php -m | grep pcov` to check)
- a reachable Redis server
- Symfony 6.4, 7.0 or 8.0 (`symfony/framework-bundle`, `symfony/console`, `symfony/routing`)
- optionally `symfony/twig-bundle`, to render the HTML dashboard

### Step 1: Download the bundle

```console
composer require kinoba/dead-code-bundle
```

If the `pcov` extension isn't installed yet, Composer will refuse the requirement. Either
install the extension first (`pecl install pcov`), or, for local testing only, bypass the
platform check:

```console
composer require kinoba/dead-code-bundle --ignore-platform-req=ext-pcov
```

### Step 2: Enable the bundle

If you don't use Symfony Flex, enable the bundle by adding it to
`config/bundles.php`:

```php
// config/bundles.php

return [
    // ...
    Kinoba\DeadCodeBundle\DeadCodeBundle::class => ['all' => true],
];
```

### Step 3: Import the routes

Register the bundle's routes so the dashboard, API and clear endpoints are reachable, e.g. in
`config/routes/dead_code.yaml`:

```yaml
dead_code:
  resource: "@DeadCodeBundle/config/routing.php"
  type: php
```

### Step 4: Configure the bundle

Create `config/packages/dead_code.yaml`:

```yaml
dead_code:
  enabled: "%env(bool:DEAD_CODE_ENABLED)%"
  redis_dsn: "%env(DEAD_CODE_REDIS_DSN)%"
  sampling_rate: 10
  cache_ttl: 86400
  ignored_paths:
    - "vendor/"
    - "var/cache/"
    - "tests/"
```

Then set the corresponding variables in your `.env` (or `.env.local`) file:

```dotenv
DEAD_CODE_ENABLED=1
DEAD_CODE_REDIS_DSN=redis://localhost:6379
```

Start with a low `sampling_rate` (e.g. `10`) on production traffic to keep overhead minimal,
and increase it if you need more data faster on lower-traffic environments.

### Step 5: Verify the installation

1. Make a few requests to your application with `enabled: true`.
2. Visit `/dead-code/dashboard` and confirm coverage data is appearing.
3. Or run `php bin/console dead-code:report` to see a summary in your terminal.

If the dashboard is empty, double-check that the `pcov` extension is loaded and that
`redis_dsn` points to a reachable Redis instance.

## Contributing

Contributions are very welcome, especially while the bundle is in beta!

1. Fork the repository and create a branch for your change.
2. Install dependencies: `composer install`.
3. Run the test suite before opening a PR:

   ```console
   vendor/bin/phpunit
   ```

4. Keep changes focused and add/update tests for any behavior change.
5. Open a pull request describing the motivation and the change.

If you run into a bug, have a question, or want to suggest an improvement, please open an
issue — feedback at this stage is especially valuable and directly shapes the bundle.

---

_This project is supported by [Kinoba](https://www.kinoba.fr)_

Released under the [MIT License](LICENSE)
