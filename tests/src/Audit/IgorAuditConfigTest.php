<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Runtime\Audit;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Runtime\Audit\IgorAuditConfig;
use Waffle\Commons\Runtime\Exception\ValidationException;
use WaffleTests\Commons\Runtime\AbstractTestCase;

#[CoversClass(IgorAuditConfig::class)]
final class IgorAuditConfigTest extends AbstractTestCase
{
    public function testToCommandWithoutArguments(): void
    {
        $config = new IgorAuditConfig('/repo/igor.sh');

        static::assertSame('/repo/igor.sh', $config->scriptPath);
        static::assertSame([], $config->arguments);
        static::assertSame(['bash', '/repo/igor.sh'], $config->toCommand());
    }

    public function testToCommandForwardsArguments(): void
    {
        $config = new IgorAuditConfig('/repo/igor.sh', ['--local', '--silent']);

        static::assertSame(['--local', '--silent'], $config->arguments);
        static::assertSame(['bash', '/repo/igor.sh', '--local', '--silent'], $config->toCommand());
    }

    public function testScriptPathIsTrimmedByTheSetHook(): void
    {
        $config = new IgorAuditConfig('   /repo/igor.sh   ');

        static::assertSame('/repo/igor.sh', $config->scriptPath);
    }

    public function testEmptyScriptPathIsRejectedByTheSetHook(): void
    {
        $this->expectException(ValidationException::class);

        new IgorAuditConfig('   ');
    }

    public function testRejectedScriptPathReportsField(): void
    {
        try {
            new IgorAuditConfig('');
            static::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            static::assertSame('scriptPath', $e->getField());
        }
    }

    public function testShellConstant(): void
    {
        static::assertSame('bash', IgorAuditConfig::SHELL);
    }
}
