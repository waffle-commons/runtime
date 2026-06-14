<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime\Audit;

use Waffle\Commons\Runtime\Exception\ValidationException;

/**
 * Value object describing one Igor audit invocation, consumed by
 * {@see ProcessAuditRunner} to build the process argv.
 *
 * Showcases the Waffle PHP 8.5 baseline for hooked value objects (AGENTS.md §1):
 * a typed class constant, a validating `set` property hook (which throws a domain
 * {@see ValidationException}, mirroring the Maker's generated DTOs), and
 * asymmetric visibility (`public private(set)`). A class with a writable `set`
 * hook cannot be `readonly`, so it is a `final class` with per-property locks.
 */
final class IgorAuditConfig
{
    /** Shell interpreter the audit script (igor.sh) runs under. */
    public const string SHELL = 'bash';

    /** Trimmed, non-empty path to the audit script; validated on write. */
    public string $scriptPath {
        set(string $value) {
            $trimmed = mb_trim($value);
            if ($trimmed === '') {
                throw new ValidationException('The audit script path must not be empty.', 'scriptPath');
            }
            $this->scriptPath = $trimmed;
        }
    }

    /** @var list<string> Flags forwarded to the script; publicly read-only. */
    public private(set) array $arguments;

    /**
     * @param list<string> $arguments
     */
    public function __construct(string $scriptPath, array $arguments = [])
    {
        $this->scriptPath = $scriptPath;
        $this->arguments = $arguments;
    }

    /**
     * Builds the argv: `[SHELL, scriptPath, ...arguments]`.
     *
     * @return list<string>
     */
    public function toCommand(): array
    {
        return [self::SHELL, $this->scriptPath, ...$this->arguments];
    }
}
