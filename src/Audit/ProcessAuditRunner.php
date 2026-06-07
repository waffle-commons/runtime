<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime\Audit;

use Closure;
use Waffle\Commons\Contracts\Runtime\AuditRunnerInterface;

/**
 * {@see AuditRunnerInterface} adapter backed by `proc_open`.
 *
 * The script is executed WITHOUT a shell (argv array form), so there is no
 * string interpolation / injection surface and none of the lint-banned
 * `exec`/`system`/`shell_exec`/`passthru` helpers are touched. Stdout and stderr
 * are read non-blocking and split into lines, so a long audit streams to the
 * caller as it runs instead of buffering until completion.
 *
 * Not `final` only so tests can override the {@see self::openProcess()} seam and
 * exercise the start-failure path; it carries no other extension point.
 */
readonly class ProcessAuditRunner implements AuditRunnerInterface
{
    /** Bytes pulled from each pipe per read. */
    public const int READ_CHUNK = 8192;

    /** Idle poll budget for stream_select, in microseconds. */
    public const int SELECT_TIMEOUT_US = 200_000;

    /** Exit code reported when the process cannot be started at all. */
    public const int EXIT_CANNOT_EXECUTE = 127;

    #[\Override]
    public function run(string $scriptPath, string $workingDirectory, array $arguments, Closure $onLine): int
    {
        $command = new IgorAuditConfig($scriptPath, $arguments)->toCommand();

        if (!is_dir($workingDirectory)) {
            $onLine(sprintf('Audit working directory does not exist: %s', $workingDirectory), true);

            return self::EXIT_CANNOT_EXECUTE;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $process = $this->openProcess($command, $descriptors, $pipes, $workingDirectory);
        // `?? null` + is_resource narrows each pipe to a resource for strict array-index analysis.
        $stdin = $pipes[0] ?? null;
        $stdout = $pipes[1] ?? null;
        $stderr = $pipes[2] ?? null;
        if (!is_resource($process) || !is_resource($stdin) || !is_resource($stdout) || !is_resource($stderr)) {
            $onLine(sprintf('Unable to start audit process: %s', implode(' ', $command)), true);

            return self::EXIT_CANNOT_EXECUTE;
        }

        fclose($stdin);
        stream_set_blocking($stdout, false);
        stream_set_blocking($stderr, false);

        $buffers = [1 => '', 2 => ''];
        $running = true;
        while ($running) {
            $read = [$stdout, $stderr];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 0, self::SELECT_TIMEOUT_US) === false) {
                break;
            }

            foreach ($read ?? [] as $stream) {
                $isError = $stream === $stderr;
                $key = $isError ? 2 : 1;
                $chunk = fread($stream, self::READ_CHUNK);
                if (!is_string($chunk) || $chunk === '') {
                    continue;
                }
                $buffers[$key] = $this->emitLines($buffers[$key] . $chunk, $isError, $onLine);
            }

            $status = proc_get_status($process);
            $running = $status['running'] === true || !feof($stdout) || !feof($stderr);
        }

        // Flush any trailing partial line that never ended with a newline.
        foreach ([1 => false, 2 => true] as $key => $isError) {
            if ($buffers[$key] === '') {
                continue;
            }
            $onLine($buffers[$key], $isError);
        }

        fclose($stdout);
        fclose($stderr);

        return proc_close($process);
    }

    /**
     * Seam over native `proc_open` so the start-failure branch is testable.
     *
     * @param list<string> $command
     * @param array<int, array{0: string, 1: string}> $descriptors
     * @param array<int, resource> $pipes Populated by reference with the child's stdio pipes.
     *
     * @return resource|false
     */
    protected function openProcess(array $command, array $descriptors, array &$pipes, string $workingDirectory)
    {
        return proc_open($command, $descriptors, $pipes, $workingDirectory);
    }

    /**
     * Emits each complete line in $buffer via $onLine; returns the trailing
     * partial line (text after the last newline) for the next read to complete.
     *
     * @param Closure(string $line, bool $isError): void $onLine
     */
    private function emitLines(string $buffer, bool $isError, Closure $onLine): string
    {
        while (($pos = strpos($buffer, "\n")) !== false) {
            $onLine(rtrim(substr($buffer, 0, $pos), "\r"), $isError);
            $buffer = substr($buffer, $pos + 1);
        }

        return $buffer;
    }
}
