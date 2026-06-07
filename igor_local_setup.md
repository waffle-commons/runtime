# Igor-PHP — Local Setup & Violation-Resolution Guide (`runtime`)

This guide explains how to install, run, and clear the findings reported by
**Igor-PHP** on the `waffle-commons/runtime` component, so the runtime keeps its
zero-memory-drift invariant (**ΔM = 0**) under FrankenPHP resident-worker mode.

Igor-PHP is an ultra-fast static linter written in Go, purpose-built for FrankenPHP
worker mode. It does not execute your code; it parses the AST and flags the three
classes of defect that cause state to bleed from request *N* into request *N+1*:
persistent **state mutation**, **incomplete resets**, and **dangerous global access**.

> **Policy.** Like every other static gate in this monorepo, Igor runs in Docker and
> follows the **zero-baseline** rule (AGENTS.md §3). Findings are fixed, never
> suppressed — do not commit an Igor baseline file.
>
> **Symfony note.** Igor's automatic container-service audit is Symfony-specific.
> Waffle is not Symfony, so that auto-discovery does not apply; Igor still runs its
> framework-agnostic mutation / reset / global-access analysis on our source.

## 1. Install Igor-PHP

### Option A — Composer dev-dependency (recommended)

This mirrors how `mago` is installed and keeps the toolchain reproducible. Run it
inside the `waffle-dev` container:

```bash
docker exec -it -w /waffle-commons/runtime waffle-dev \
    composer require --dev igor-php/igor-php
```

Composer resolves the correct version, updates `composer.lock`, and exposes the
binary at `vendor/bin/igor-php`.

### Option B — Standalone Go binary

If you prefer a global binary (e.g. for use outside the container), install it with
the Go toolchain (Go ≥ 1.21):

```bash
go install github.com/igor-php/igor-php@latest
```

The wrapper script auto-detects a binary on `PATH` when `vendor/bin/igor-php` is
absent.

## 2. Run the audit

Any of the following work from the component root, inside `waffle-dev`:

```bash
# Composer alias (added to runtime/composer.json):
composer igor

# Colored wrapper with explicit pass/fail messaging:
./bin/run-igor.sh

# Raw binary (reads ./igor.json by default):
vendor/bin/igor-php .
```

Configuration lives in `runtime/igor.json`. Only keys Igor actually understands are
set — `exclude` (skip `vendor/`, `tests/`, `var/`), `safe_namespaces` (the stateless
`contracts` interfaces), and `verbose`. There is intentionally **no** `baseline` key.

A successful run exits `0`; any finding exits non-zero and the wrapper exits `1`.

## 3. Resolving violations

### 3.1 State mutation — persistent writes that survive the loop

**Symptom.** A shared (container-singleton) service writes request data into a static
property or an unbounded instance collection that is never cleared. Under worker mode
the allocation grows every request until the worker is OOM-killed.

Incorrect — a static registry that accumulates forever:

```php
<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime;

final class RequestAuditor
{
    /** @var list<string> */
    private static array $history = []; // grows every request — never collected

    public function record(string $path): void
    {
        self::$history[] = $path;
    }
}
```

Correct — keep per-request state on an instance and release it via the reset contract
(see §3.2), or do not retain it at all:

```php
<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime;

use Waffle\Commons\Contracts\Service\ResettableInterface;

final class RequestAuditor implements ResettableInterface
{
    /** @var list<string> */
    private array $history = []; // per-request, released on reset()

    public function record(string $path): void
    {
        $this->history[] = $path;
    }

    #[\Override]
    public function reset(): void
    {
        $this->history = [];
    }
}
```

### 3.2 Incomplete reset — a property the handler forgot

**Symptom.** A service implements
`Waffle\Commons\Contracts\Service\ResettableInterface` but `reset()` misses a mutable
property. The kernel calls `reset()` between requests, yet the stale value (e.g. the
previous user id) leaks into the next request — both a memory leak and a
state-confusion / security defect.

PHP 8.5 changes *which* properties need resetting:

- **`readonly` properties** are assigned once in the constructor and can never be
  reassigned — they are inherently reset-safe and must **not** appear in `reset()`.
  Hold only immutable dependencies (or fully-immutable value objects) as `readonly`.
- **Asymmetric-visibility** properties (`public private(set)`) are publicly read-only
  but writable from inside the class, so `reset()` *can and must* re-blank them.
- **Hooked** properties are reset by assigning through the setter hook (which re-runs
  validation). A hooked property cannot be `readonly`; if it carries per-request
  state, reset it like any other mutable property.

Incorrect — `$lastUserId` is never cleared:

```php
<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime;

use Waffle\Commons\Contracts\Service\ResettableInterface;

final class UserContext implements ResettableInterface
{
    /** @var array<string, string> */
    public private(set) array $claims = [];

    public private(set) ?string $lastUserId = null;

    #[\Override]
    public function reset(): void
    {
        $this->claims = [];
        // BUG: $lastUserId survives — request N+1 sees request N's user.
    }
}
```

Correct — every mutable property returns to its constructor default:

```php
<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime;

use Waffle\Commons\Contracts\Service\ResettableInterface;

final class UserContext implements ResettableInterface
{
    /** @var array<string, string> */
    public private(set) array $claims = [];

    public private(set) ?string $lastUserId = null;

    #[\Override]
    public function reset(): void
    {
        $this->claims = [];
        $this->lastUserId = null; // fully blanked for the next request
    }
}
```

### 3.3 Dangerous globals — superglobals, process exits, ambient state

**Symptom.** Code reads a superglobal, calls `exit`/`die`, or mutates a global PHP
setting. In a resident worker these either leak request state across iterations or
terminate the whole worker process — both forbidden by the statelessness mandate
(AGENTS.md §2).

Incorrect — reading the request from a superglobal and poisoning global state:

```php
$page = (int) ($_GET['page'] ?? 1);        // superglobal — leaks across requests
date_default_timezone_set('Europe/Paris'); // poisons every future request
```

Correct — take input from the injected PSR-7 request; never touch process globals:

```php
use Psr\Http\Message\ServerRequestInterface;

public function paginate(ServerRequestInterface $request): int
{
    return (int) ($request->getQueryParams()['page'] ?? 1);
}
```

For request construction inside the runtime, use the injected
`Waffle\Commons\Http\Factory\GlobalsFactory` rather than reading superglobals
directly — `WaffleRuntime` already does this in its per-request handler.

## 4. Definition of done

A memory-sensitive change is complete only when, inside `waffle-dev`:

```bash
composer igor   # ΔM = 0 — no Igor findings
composer mago   # zero-baseline static gates green
composer tests  # ≥95% coverage green
```

All three must pass before pushing changes to `runtime`, `container`, or `pipeline`.
