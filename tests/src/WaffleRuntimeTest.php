<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Runtime;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Waffle\Commons\Contracts\Core\KernelInterface;
use Waffle\Commons\Contracts\Http\ResponseEmitterInterface;
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

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for all dependencies
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->emitter = $this->createMock(ResponseEmitterInterface::class);

        // Instantiate the runtime
        $this->runtime = new WaffleRuntime();
    }

    public function testRunOrchestratesRequestLifecycleCorrectly(): void
    {
        // 1. Setup Expectations

        // Dummy response object to be returned by the kernel
        $response = $this->createMock(ResponseInterface::class);

        // Expect the Kernel to handle the specific request and return our dummy response
        $this->kernel
            ->expects($this->once())
            ->method('handle')
            ->with($this->equalTo($this->request))
            ->willReturn($response);

        // Expect the Emitter to emit the exact response returned by the kernel
        $this->emitter
            ->expects($this->once())
            ->method('emit')
            ->with($this->equalTo($response));

        // 2. Execution
        $this->runtime->run($this->kernel, $this->request, $this->emitter);
    }
}
