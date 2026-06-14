[![Discord](https://img.shields.io/discord/755288001592033391?logo=discord)](https://discord.gg/eKgywnfXr2)
[![PHP Version Require](http://poser.pugx.org/waffle-commons/runtime/require/php)](https://packagist.org/packages/waffle-commons/runtime)
[![PHP CI](https://github.com/waffle-commons/runtime/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/runtime/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/runtime/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/runtime)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/runtime/v)](https://packagist.org/packages/waffle-commons/runtime)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/runtime/v/unstable)](https://packagist.org/packages/waffle-commons/runtime)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/runtime.svg)](https://packagist.org/packages/waffle-commons/runtime)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/runtime)](https://github.com/waffle-commons/runtime/blob/main/LICENSE.md)

Waffle Runtime Component
========================

> **Release:** `0.1.0-beta4` &nbsp;|&nbsp; [`CHANGELOG.md`](./CHANGELOG.md)

`WaffleRuntime` is the agnostic application runner. It owns the request loop in FrankenPHP worker mode and falls back gracefully to a single-shot execution under the classic PHP SAPI when `frankenphp_handle_request()` is unavailable.

The runtime contains **no concrete framework dependencies** — it knows only the `contracts` interfaces `KernelInterface`, `ResponseEmitterInterface`, and `GlobalsFactoryInterface`. The concrete `GlobalsFactory` / `ResponseEmitter` (from `http`) are injected by the application.

## 📦 Installation

```bash
composer require waffle-commons/runtime
```

## 🧱 Surface

A single class: `Waffle\Commons\Runtime\WaffleRuntime` implementing `Waffle\Commons\Contracts\Runtime\RuntimeInterface`.

```php
public function __construct(
    GlobalsFactoryInterface $globalsFactory,   // required — wired by the app bootstrap
    ResponseEmitterInterface $emitter,         // required — wired by the app bootstrap
);

public function loop(KernelInterface $kernel, int $maxRequests = 500): void;
```

## 🚀 Bootstrap (the entire `public/index.php`)

```php
<?php
declare(strict_types=1);

use Waffle\Commons\Http\Emitter\ResponseEmitter;
use Waffle\Commons\Http\Factory\GlobalsFactory;
use Waffle\Commons\Runtime\WaffleRuntime;
use App\Factory\AppKernelFactory;

require __DIR__ . '/../vendor/autoload.php';

define('APP_ROOT', dirname(__DIR__));

$kernel = AppKernelFactory::create(env: getenv('APP_ENV') ?: 'prod', debug: false);

// The app wires the concrete http factory + emitter into the agnostic runtime.
(new WaffleRuntime(new GlobalsFactory(), new ResponseEmitter()))->loop($kernel, maxRequests: 500);
```

## 🔄 The loop contract

1. **Boot once.** `$kernel->boot()->configure()` runs exactly once when the FrankenPHP worker starts.
2. **Iterate.** Up to `$maxRequests` times, the runtime calls `frankenphp_handle_request($handler)` where `$handler`:
   - rebuilds a PSR-7 `ServerRequest` from the *current* superglobals (FrankenPHP repopulates them per request),
   - calls `$kernel->handle($request)` (the hot path),
   - emits the response via the injected `ResponseEmitterInterface`.
3. **Garbage-collect periodically.** Every 50 requests, `gc_collect_cycles()` is called to keep long-running worker memory bounded.
4. **Reset on exit.** When the loop exits (max reached or FrankenPHP signaled stop), `$kernel->reset()` clears request-scoped state.

If `frankenphp_handle_request` is not defined (classic SAPI), the runtime executes the handler once and exits — no infinite loop.

## 🐘 PHP 8.5 features used

- `final class WaffleRuntime` — no inheritance.
- Typed constructor parameters injected via `contracts` interfaces (no concrete defaults).
- First-class callable closure in the handler block.
- Typed `KernelInterface` + `ResponseEmitterInterface` + `GlobalsFactoryInterface` dependencies.

## 🧭 Architectural boundary (`mago guard`)

An active dependency **perimeter** is enforced on every CI run by `vendor/bin/mago guard` (bundled into `composer mago`; zero baselines). The rules live in [`mago.toml`](./mago.toml) under `[guard.perimeter]` — a forbidden `use` statement fails the build, not a reviewer.

Production code under `Waffle\Commons\Runtime` may depend **only** on:

- `Waffle\Commons\Runtime\**` — itself
- `Waffle\Commons\Contracts\**` — the shared contracts package, the only Waffle dependency
- `Psr\**` — PSR interfaces (PSR-7 / PSR-17)
- `@global` + `Psl\**` — PHP core (including the FrankenPHP `frankenphp_handle_request` global) and the PHP Standard Library

Test code under `WaffleTests\Commons\Runtime` is unrestricted (`@all`); `WaffleRuntimeWorkerModeTest` is listed in `[guard].excludes` because it re-declares the production namespace to stub `frankenphp_handle_request`. Structural rules are guarded too: interfaces must be named `*Interface`, `Exception\**` classes must end in `*Exception`, and any `Enum\**` namespace may hold only `enum` declarations.

Contract-first, component-agnostic by construction: components compose through `waffle-commons/contracts`, never ad-hoc through one another.

## 🧪 Testing

```bash
docker exec -w /waffle-commons/runtime waffle-dev composer tests
```

The `WaffleRuntimeWorkerModeTest` namespaces the production namespace to override `frankenphp_handle_request` via `php-mock-phpunit`; it is listed in `mago.toml [guard].excludes`.

## 📄 License

MIT — see [LICENSE.md](./LICENSE.md).
