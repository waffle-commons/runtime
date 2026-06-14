# Changelog — waffle-commons/runtime

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [0.1.0-beta4] — 2026-06-13

**Theme: worker-mode diagnostics.**

### Added
- `Trace\ConnectionTracker` — a request-scoped, dev-only registry of open PDO/Redis/stream connections (declares `implements ConnectionTrackerInterface, ResettableInterface` directly); feeds the orphaned-connection tracer (DIAG-03).

### Changed
- Perimeter restored: `WaffleRuntime` now depends only on `contracts` — `GlobalsFactory` / `ResponseEmitter` are injected via `GlobalsFactoryInterface` / `ResponseEmitterInterface` (constructor-required) and `waffle-commons/http` is dropped from `require`; concrete wiring moves to the app bootstrap.
- Worker-safety migration to igor-php 0.7 (`#[WorkerSafe]`).

## [0.1.0-beta3] — 2026-06-07

**Theme: identity federation & stateless persistence (ecosystem wave).**

### Added
- `Waffle\Commons\Runtime\Audit\ProcessAuditRunner` — `Waffle\Commons\Contracts\Runtime\AuditRunnerInterface` adapter that runs an audit script via `proc_open` (no shell — argv array form, so none of the lint-banned `exec`/`system`/`shell_exec`/`passthru` helpers are used) and streams stdout/stderr line-by-line. Powers the `igor:audit` command (the monorepo-wide `igor.sh` memory-leak / state-mutation audit). Exposes a `protected openProcess()` seam so the start-failure path is testable.
- `Waffle\Commons\Runtime\Audit\IgorAuditConfig` — hooked value object that models the audit argv: a typed class constant (`SHELL`), a validating `set` property hook on `$scriptPath`, and asymmetric visibility (`public private(set) array $arguments`).
- `Waffle\Commons\Runtime\Exception\ValidationException` — runtime `ValidationExceptionInterface` implementation thrown by `IgorAuditConfig`'s property hook.

### Changed
- Lockstep version bump; `composer.lock` refreshed with the beta-3 dependency wave.

### Tests
- `ProcessAuditRunnerTest` and `IgorAuditConfigTest` added — cover line streaming, exit-code mapping, the missing-working-directory and start-failure branches, hook validation, and the trimmed/asymmetric properties (component line coverage ≥95%).

## [0.1.0-beta2.1] — 2026-05-30

### Changed
- Lockstep re-tag of `0.1.0-beta2` (umbrella housekeeping patch) — no source changes in this component.

## [0.1.0-beta2] — 2026-05-29

### Changed
- Lockstep version bump; `composer.lock` refreshed to align with the ecosystem-wide dependency wave.

## [0.1.0-beta1]

See the umbrella [CHANGELOG](../CHANGELOG.md#010-beta1) for the full Beta-1 narrative — `WaffleRuntime` per-process FrankenPHP worker loop with classic-SAPI fallback, ownership of `GlobalsFactory` per process (no static state hand-off).
