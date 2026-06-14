<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime\Trace;

use Waffle\Commons\Contracts\Data\Connection\ConnectionKind;
use Waffle\Commons\Contracts\Data\Connection\ConnectionTrackerInterface;
use Waffle\Commons\Contracts\Service\ResettableInterface;

/**
 * Default {@see ConnectionTrackerInterface}: the per-request ledger of persistent
 * connections for the DIAG-03 orphaned-connection tracer.
 *
 * Connection owners (relational pools, Redis adapters, stream wrappers) report
 * {@see self::trackOpen()} / {@see self::trackClose()}; at request end a listener
 * inspects {@see self::openConnections()} and warns about anything still open.
 *
 * The ledger is request-scoped mutable state, but it implements
 * {@see \Waffle\Commons\Contracts\Service\ResettableInterface}: the kernel clears
 * it on every worker loop via `reset()`, so no connection id ever bleeds across
 * requests (FrankenPHP statelessness mandate).
 */
final class ConnectionTracker implements ConnectionTrackerInterface, ResettableInterface
{
    /** @var array<string, ConnectionKind> Open connection id ⇒ its kind. */
    private array $open = [];

    #[\Override]
    public function trackOpen(string $id, ConnectionKind $kind): void
    {
        $this->open[$id] = $kind;
    }

    #[\Override]
    public function trackClose(string $id): void
    {
        unset($this->open[$id]);
    }

    #[\Override]
    public function openConnections(): array
    {
        $connections = [];
        foreach ($this->open as $id => $kind) {
            $connections[] = ['id' => $id, 'kind' => $kind];
        }

        return $connections;
    }

    #[\Override]
    public function reset(): void
    {
        $this->open = [];
    }
}
