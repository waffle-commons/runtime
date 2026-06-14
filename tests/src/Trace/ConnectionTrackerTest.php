<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Runtime\Trace;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Contracts\Data\Connection\ConnectionKind;
use Waffle\Commons\Runtime\Trace\ConnectionTracker;
use WaffleTests\Commons\Runtime\AbstractTestCase;

#[CoversClass(ConnectionTracker::class)]
final class ConnectionTrackerTest extends AbstractTestCase
{
    public function testOpenConnectionIsReported(): void
    {
        $tracker = new ConnectionTracker();
        $tracker->trackOpen('pdo-1', ConnectionKind::Pdo);

        self::assertSame([['id' => 'pdo-1', 'kind' => ConnectionKind::Pdo]], $tracker->openConnections());
    }

    public function testCloseRemovesFromTheLedger(): void
    {
        $tracker = new ConnectionTracker();
        $tracker->trackOpen('redis-1', ConnectionKind::Redis);
        $tracker->trackClose('redis-1');

        self::assertSame([], $tracker->openConnections());
    }

    public function testClosingAnUnknownIdIsANoOp(): void
    {
        $tracker = new ConnectionTracker();
        $tracker->trackClose('never-opened');

        self::assertSame([], $tracker->openConnections());
    }

    public function testReopeningSameIdKeepsLatestKind(): void
    {
        $tracker = new ConnectionTracker();
        $tracker->trackOpen('h', ConnectionKind::Pdo);
        $tracker->trackOpen('h', ConnectionKind::Stream);

        self::assertSame([['id' => 'h', 'kind' => ConnectionKind::Stream]], $tracker->openConnections());
    }

    public function testEveryOpenConnectionIsReported(): void
    {
        $tracker = new ConnectionTracker();
        $tracker->trackOpen('a', ConnectionKind::Pdo);
        $tracker->trackOpen('b', ConnectionKind::Redis);
        $tracker->trackOpen('c', ConnectionKind::Stream);

        self::assertCount(3, $tracker->openConnections());
    }

    public function testResetClearsTheLedgerBetweenRequests(): void
    {
        $tracker = new ConnectionTracker();
        $tracker->trackOpen('a', ConnectionKind::Pdo);
        $tracker->trackOpen('b', ConnectionKind::Redis);

        $tracker->reset();

        self::assertSame([], $tracker->openConnections());
    }
}
