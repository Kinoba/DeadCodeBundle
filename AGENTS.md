# AI Contribution Guidelines

Welcome, AI assistant. Please follow these guidelines when contributing to this repository.

## Project Overview

DeadCodeBundle is a Symfony bundle that detects dead code in production by sampling real-traffic
line coverage with PCOV and aggregating the results in Redis. It's a small, single-purpose
bundle (a listener, two services, a controller, a command) — keep it that way.

**Requirements:** PHP 8.1+, Symfony 6.4/7.x/8.x, ext-pcov, Redis (via `predis/predis`)

**Status:** Beta — APIs, config keys and storage formats may still change.

## General Rules

- Language: American English for code, comments, commits, branches
- Code quotes: single quotes in PHP
- Every PHP file starts with `declare(strict_types=1);`
- Security: this bundle reads request-derived file paths and renders coverage data in HTML —
  keep the dashboard/API output escaped (Twig auto-escaping) and never pass user input into
  Redis keys or file-system paths unvalidated

### Do Not Edit

- `vendor/` — managed by Composer
- `composer.lock` — update only via Composer commands
- `.phpunit.cache/` — PHPUnit's own cache

## Architecture

Coverage flows through four collaborators in `src/`:

1. **`EventListener\CoverageListener`** — on `kernel.request`, asks `CoverageCollector` whether
   to sample this request and starts PCOV; on `kernel.terminate`, stops it and hands the result
   to `RedisStorage`.
2. **`Service\CoverageCollector`** — wraps the `pcov\*` functions, decides sampling
   (`sampling_rate`) and normalizes hit counts (PCOV's `-1` "not executed" sentinel is clamped
   to `0`).
3. **`Service\RedisStorage`** — persists per-file hit counts in Redis (summed across
   requests/processes), with a configurable TTL.
4. **`Service\CoverageReporter`** — reads aggregated data back, merges it with source files from
   disk, and builds the structure consumed by `Controller\DashboardController`, the JSON API and
   `Command\GenerateReportCommand`.

Config is defined in `DependencyInjection\Configuration` (the `dead_code` key) and applied in
`DependencyInjection\DeadCodeExtension`. Services are wired explicitly in
[config/services.php](config/services.php) — no autowiring, except `DashboardController` which
opts in explicitly (needed for `AbstractController::setContainer()`).

Don't add new runtime dependencies or services unless the feature genuinely needs them — this
bundle intentionally has a very small surface area.

### Main Namespace

`Kinoba\DeadCodeBundle\`

## Commands

There's no Makefile; run tools directly.

### Setup

```console
composer install
```

If `pcov` isn't installed locally, bypass the platform check:

```console
composer install --ignore-platform-req=ext-pcov
```

Do not install the real `pcov` PECL extension just to develop locally — `tests/pcov_stub.php`
provides a stub used automatically when the extension is absent.

### Before Committing

Run the same checks as CI ([.github/workflows/ci.yml](.github/workflows/ci.yml)):

```console
vendor/bin/phpunit
vendor/bin/mago format --check
vendor/bin/mago lint
vendor/bin/mago analyze
```

Run a single test with `vendor/bin/phpunit --filter=testFoo` or a single directory with
`vendor/bin/phpunit tests/Service`.

## Git and Pull Requests

### Commit Messages

- Use imperative mood: "Add feature" not "Added feature"
- First line: concise summary
- Reference issues when applicable: "Fix #123"
- No period at end of subject line

## PHP Code Standards

`mago format`/`mago lint`/`mago analyze` (config in [mago.toml](mago.toml)) enforce most style
and catch static-analysis issues — run them and let them handle formatting. The conventions
below are what they don't enforce.

### Conventions

- `declare(strict_types=1);` at the top of every PHP file
- Explicit property declarations and constructor assignments (no constructor property promotion
  in the current codebase — match existing style within a class you're editing)
- `final class` for controllers and test cases; other services are left non-final unless there's
  a reason to seal them
- No service autowiring — wire new services explicitly in [config/services.php](config/services.php)
- Config files in PHP format (`config/services.php`, `config/routing.php`)
- Handle exceptions explicitly (no silent catches)
- Comments: only for non-obvious code (e.g. PCOV sentinel handling, mago quirks); one line, no
  period at the end

### Naming

- Variables/methods: `camelCase`
- Config/routes: `snake_case`
- Classes: `UpperCamelCase`
- Interfaces: `*Interface`
- Suffix classes by role where it's already the convention: `*Controller`, `*Listener`,
  `*Command`, `*Extension`, `*Test`

### PHPDoc

- Add `@var`/`@param`/`@return` shapes where mago's analyzer needs them to resolve generics or
  untyped array shapes (see `Configuration::getConfigTreeBuilder()` and
  `DeadCodeExtension::load()` for examples) — this is required here, not optional style
- Group annotations by type; no single-line docblocks

## Templates (Twig)

There is a single template, [templates/dashboard.html.twig](templates/dashboard.html.twig). It's
intentionally standalone (no `extends`, no CSS/JS framework) with CSS/JS inlined in
`<style>`/`<script>` tags.

## Tests

- PHPUnit test classes live under `tests/`, mirror the `src/` structure, and are named
  `*Test.php` (suffix required by [phpunit.xml.dist](phpunit.xml.dist))
- Use `final class ... extends TestCase` with `#[CoversClass(...)]`
- Stubs live in `tests/Stub/` (e.g. `FakePredisClient`, `RecordingLogger`) — prefer these over
  mocking frameworks for this codebase
