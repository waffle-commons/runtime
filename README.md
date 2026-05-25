[![PHP Version Require](http://poser.pugx.org/waffle-commons/runtime/require/php)](https://packagist.org/packages/waffle-commons/runtime)
[![PHP CI](https://github.com/waffle-commons/runtime/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/runtime/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/runtime/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/runtime)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/runtime/v)](https://packagist.org/packages/waffle-commons/runtime)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/runtime/v/unstable)](https://packagist.org/packages/waffle-commons/runtime)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/runtime.svg)](https://packagist.org/packages/waffle-commons/runtime)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/runtime)](https://github.com/waffle-commons/runtime/blob/main/LICENSE.md)

Waffle Runtime Component
========================

> **Release:** `v0.1.0-beta1`

`WaffleRuntime` is the agnostic application runner. It owns the request loop in FrankenPHP worker mode and falls back gracefully to a single-shot execution under the classic PHP SAPI when `frankenphp_handle_request()` is unavailable.

The runtime contains **no concrete framework dependencies** — it only knows about the `KernelInterface`, `ResponseEmitterInterface`, and the `GlobalsFactory` shape. Everything else is injected.

## 📦 Installation

```bash
composer require waffle-commons/runtime
```

## 🧱 Surface

A single class: `Waffle\Commons\Runtime\WaffleRuntime` implementing `Waffle\Commons\Contracts\Runtime\RuntimeInterface`.

```php
public function __construct(
    ?GlobalsFactory $globalsFactory = null,    // defaults to new GlobalsFactory()
    ?ResponseEmitterInterface $emitter = null, // defaults to new ResponseEmitter()
);

public function loop(KernelInterface $kernel, int $maxRequests = 500): void;
```

## 🚀 Bootstrap (the entire `public/index.php`)

```php
<?php
declare(strict_types=1);

use Waffle\Commons\Runtime\WaffleRuntime;
use App\Factory\AppKernelFactory;

require __DIR__ . '/../vendor/autoload.php';

define('APP_ROOT', dirname(__DIR__));

$kernel = AppKernelFactory::create(env: getenv('APP_ENV') ?: 'prod', debug: false);

(new WaffleRuntime())->loop($kernel, maxRequests: 500);
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
- Typed nullable constructor parameters with defaults built from `Waffle\Commons\Http\*` factories.
- First-class callable closure in the handler block.
- Typed `KernelInterface` + `ResponseEmitterInterface` + `GlobalsFactory` dependencies.

## 🧪 Testing

```bash
docker exec -w /waffle-commons/runtime waffle-dev composer tests
```

The `WaffleRuntimeWorkerModeTest` namespaces the production namespace to override `frankenphp_handle_request` via `php-mock-phpunit`; it is listed in `mago.toml [guard].excludes`.

## 📄 License

MIT — see [LICENSE.md](./LICENSE.md).
