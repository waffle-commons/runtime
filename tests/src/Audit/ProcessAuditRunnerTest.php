<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Runtime\Audit;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Runtime\Audit\ProcessAuditRunner;
use WaffleTests\Commons\Runtime\AbstractTestCase;

#[CoversClass(ProcessAuditRunner::class)]
final class ProcessAuditRunnerTest extends AbstractTestCase
{
    public function testStreamsStdoutAndStderrAndReturnsExitCode(): void
    {
        /** @var list<array{0: string, 1: bool}> $lines */
        $lines = [];

        // scriptPath '-c' + an inline body → `bash -c '<body>'`, exercising the runner
        // without needing a temp script file on disk.
        $exit = new ProcessAuditRunner()->run(
            '-c',
            $this->workingDirectory(),
            ['printf "alpha\nbeta\n"; printf "boom\n" >&2; exit 3'],
            static function (string $line, bool $isError) use (&$lines): void {
                $lines[] = [$line, $isError];
            },
        );

        static::assertSame(3, $exit);
        static::assertContains(['alpha', false], $lines);
        static::assertContains(['beta', false], $lines);
        static::assertContains(['boom', true], $lines);
    }

    public function testTrailingLineWithoutNewlineIsFlushed(): void
    {
        /** @var list<array{0: string, 1: bool}> $lines */
        $lines = [];

        $exit = new ProcessAuditRunner()->run(
            '-c',
            $this->workingDirectory(),
            ['printf "no-newline"'],
            static function (string $line, bool $isError) use (&$lines): void {
                $lines[] = [$line, $isError];
            },
        );

        static::assertSame(0, $exit);
        static::assertContains(['no-newline', false], $lines);
    }

    public function testMissingWorkingDirectoryReportsCannotExecute(): void
    {
        /** @var list<array{0: string, 1: bool}> $lines */
        $lines = [];

        $exit = new ProcessAuditRunner()->run('-c', APP_ROOT . '/var/__igor_no_such_dir__', ['true'], static function (
            string $line,
            bool $isError,
        ) use (&$lines): void {
            $lines[] = [$line, $isError];
        });

        static::assertSame(ProcessAuditRunner::EXIT_CANNOT_EXECUTE, $exit);
        static::assertNotSame([], $lines);
        static::assertTrue($lines[0][1], 'The failure must be reported on the error stream');
    }

    public function testProcessStartFailureReportsCannotExecute(): void
    {
        /** @var list<array{0: string, 1: bool}> $lines */
        $lines = [];

        $exit = new StartFailureRunner()->run('-c', $this->workingDirectory(), ['true'], static function (
            string $line,
            bool $isError,
        ) use (&$lines): void {
            $lines[] = [$line, $isError];
        });

        static::assertSame(ProcessAuditRunner::EXIT_CANNOT_EXECUTE, $exit);
        static::assertNotSame([], $lines);
        static::assertTrue($lines[0][1], 'A start failure must be reported on the error stream');
    }

    private function workingDirectory(): string
    {
        $dir = APP_ROOT . '/var';

        return is_dir($dir) ? $dir : APP_ROOT;
    }
}

/**
 * Test double that forces the {@see ProcessAuditRunner::openProcess()} seam to
 * fail, exercising the "cannot start" branch deterministically.
 */
final readonly class StartFailureRunner extends ProcessAuditRunner
{
    #[\Override]
    protected function openProcess(array $command, array $descriptors, array &$pipes, string $workingDirectory)
    {
        return false;
    }
}
