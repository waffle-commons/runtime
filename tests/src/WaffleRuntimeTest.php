<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Runtime;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Waffle\Commons\Contracts\Core\KernelInterface;
use Waffle\Commons\Contracts\Core\TerminableInterface;
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
        $this->kernel->expects($this->once())->method('boot')->willReturnSelf();

        $this->kernel->expects($this->once())->method('configure');

        // 2. Setup Expectations for Loop (Called Once in CLI fallback)

        // Factory creates the request
        $this->globalsFactory->expects($this->once())->method('createFromGlobals')->willReturn($this->request);

        // Dummy response object
        $response = $this->createStub(ResponseInterface::class);

        // Kernel handles the request
        $this->kernel
            ->expects($this->once())
            ->method('handle')
            ->with(static::equalTo($this->request))
            ->willReturn($response);

        // Emitter emits the response
        $this->emitter->expects($this->once())->method('emit')->with(static::equalTo($response));

        // 3. Execution
        // Since we are not in FrankenPHP (function doesn't exist), it executes once and breaks.
        $this->runtime->loop($this->kernel);

        $this->kernel->reset();
    }

    // This test drives a concrete terminable-kernel spy, so the shared
    // KernelInterface mock built in setUp() is intentionally unused here.
    #[AllowMockObjectsWithoutExpectations]
    public function testLoopCallsTerminateOnTerminableKernelAfterEmit(): void
    {
        $response = $this->createStub(ResponseInterface::class);

        // A concrete kernel that implements BOTH the core lifecycle and the
        // optional post-response capability. The runtime must drive terminate()
        // once per request — after emit(), before reset() — with the handled
        // request/response pair.
        $kernel = new class($response) implements KernelInterface, TerminableInterface {
            public int $bootCalls = 0;
            public int $handleCalls = 0;
            public int $resetCalls = 0;
            public int $terminateCalls = 0;
            public ?ServerRequestInterface $handledRequest = null;
            public ?ServerRequestInterface $terminatedRequest = null;
            public ?ResponseInterface $terminatedResponse = null;

            public function __construct(
                private readonly ResponseInterface $response,
            ) {}

            #[\Override]
            public function boot(): static
            {
                $this->bootCalls++;
                return $this;
            }

            #[\Override]
            public function configure(): void
            {
                // No configuration needed for the lifecycle assertions.
            }

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->handleCalls++;
                $this->handledRequest = $request;
                return $this->response;
            }

            #[\Override]
            public function reset(): void
            {
                $this->resetCalls++;
            }

            #[\Override]
            public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
            {
                $this->terminateCalls++;
                $this->terminatedRequest = $request;
                $this->terminatedResponse = $response;
            }
        };

        $this->globalsFactory->expects($this->once())->method('createFromGlobals')->willReturn($this->request);
        $this->emitter->expects($this->once())->method('emit')->with($response);

        $this->runtime->loop($kernel);

        static::assertSame(1, $kernel->bootCalls, 'boot() must run exactly once');
        static::assertSame(1, $kernel->handleCalls, 'handle() must run exactly once');
        static::assertSame(1, $kernel->terminateCalls, 'terminate() must run once on a terminable kernel');
        static::assertSame(1, $kernel->resetCalls, 'reset() must run once per request');
        static::assertSame($this->request, $kernel->handledRequest);
        static::assertSame($this->request, $kernel->terminatedRequest, 'terminate() must receive the handled request');
        static::assertSame($response, $kernel->terminatedResponse, 'terminate() must receive the emitted response');
    }
}
