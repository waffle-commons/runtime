<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Runtime;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Waffle\Commons\Contracts\Core\KernelInterface;
use Waffle\Commons\Contracts\Http\ResponseEmitterInterface;
use Waffle\Commons\Http\Factory\GlobalsFactory;
use Waffle\Commons\Runtime\WaffleRuntime;

/**
 * Tests for the WaffleRuntime agnostic orchestrator.
 */
class WaffleRuntimeTest extends AbstractTestCase
{
    private WaffleRuntime $runtime;

    /** @var KernelInterface&MockObject */
    private $kernel;

    /** @var ServerRequestInterface&MockObject */
    private $request;

    /** @var ResponseEmitterInterface&MockObject */
    private $emitter;

    /** @var GlobalsFactory&MockObject */
    private $globalsFactory;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for all dependencies
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->request = $this->createStub(ServerRequestInterface::class);
        $this->emitter = $this->createMock(ResponseEmitterInterface::class);
        $this->globalsFactory = $this->createMock(GlobalsFactory::class);

        // Instantiate the runtime with mocked dependencies
        $this->runtime = new WaffleRuntime($this->globalsFactory, $this->emitter);
    }

    public function testLoopOrchestratesRequestLifecycleCorrectly(): void
    {
        // 1. Setup Expectations for Boot (Called Once)
        $this->kernel
            ->expects($this->once())
            ->method('boot')
            ->willReturnSelf();

        $this->kernel->expects($this->once())->method('configure');

        // 2. Setup Expectations for Loop (Called Once in CLI fallback)

        // Factory creates the request
        $this->globalsFactory
            ->expects($this->once())
            ->method('createFromGlobals')
            ->willReturn($this->request);

        // Dummy response object
        $response = $this->createStub(ResponseInterface::class);

        // Kernel handles the request
        $this->kernel
            ->expects($this->once())
            ->method('handle')
            ->with($this->equalTo($this->request))
            ->willReturn($response);

        // Emitter emits the response
        $this->emitter
            ->expects($this->once())
            ->method('emit')
            ->with($this->equalTo($response));

        // 3. Execution
        // Since we are not in FrankenPHP (function doesn't exist), it executes once and breaks.
        $this->runtime->loop($this->kernel);
    }
}
