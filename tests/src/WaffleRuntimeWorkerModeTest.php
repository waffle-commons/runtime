<?php

declare(strict_types=1);

// Namespace-level shims for the WaffleRuntime worker-mode branch.
// PHP resolves unqualified calls in the current namespace first; the production runtime
// uses unqualified `function_exists()` and `frankenphp_handle_request()` so these shims
// take effect only while this test file is loaded.
namespace Waffle\Commons\Runtime {
    use WaffleTests\Commons\Runtime\WorkerModeMockState;

    function function_exists(string $name): bool
    {
        if ($name === 'frankenphp_handle_request') {
            return WorkerModeMockState::$frankenphpAvailable;
        }
        return \function_exists($name);
    }

    function frankenphp_handle_request(callable $handler): bool
    {
        return WorkerModeMockState::handle($handler);
    }
}

namespace WaffleTests\Commons\Runtime {
    use PHPUnit\Framework\MockObject\MockObject;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Waffle\Commons\Contracts\Core\KernelInterface;
    use Waffle\Commons\Contracts\Http\ResponseEmitterInterface;
    use Waffle\Commons\Http\Factory\GlobalsFactory;
    use Waffle\Commons\Runtime\WaffleRuntime;

    /**
     * Holds state for the namespace-level shims above. Reset before each test.
     */
    final class WorkerModeMockState
    {
        public static bool $frankenphpAvailable = false;

        /**
         * @var list<bool> Queue of return values for successive frankenphp_handle_request() calls.
         *                When exhausted, returns false (worker shutdown).
         */
        public static array $runningQueue = [];

        public static int $handleInvocations = 0;

        /**
         * @var list<callable> Captured handlers passed to frankenphp_handle_request().
         */
        public static array $capturedHandlers = [];

        public static function reset(): void
        {
            self::$frankenphpAvailable = false;
            self::$runningQueue = [];
            self::$handleInvocations = 0;
            self::$capturedHandlers = [];
        }

        public static function handle(callable $handler): bool
        {
            self::$handleInvocations++;
            $running = array_shift(self::$runningQueue) ?? false;
            if ($running) {
                // Real FrankenPHP only invokes the handler when a request is delivered.
                // A false return = shutdown signal, no handler invocation.
                self::$capturedHandlers[] = $handler;
                $handler();
            }
            return $running;
        }
    }

    class WaffleRuntimeWorkerModeTest extends AbstractTestCase
    {
        /** @var KernelInterface&MockObject */
        private $kernel;
        /** @var ResponseEmitterInterface&MockObject */
        private $emitter;
        /** @var GlobalsFactory&MockObject */
        private $globalsFactory;

        #[\Override]
        protected function setUp(): void
        {
            parent::setUp();
            WorkerModeMockState::reset();

            $this->kernel = $this->createMock(KernelInterface::class);
            $this->emitter = $this->createMock(ResponseEmitterInterface::class);
            $this->globalsFactory = $this->createMock(GlobalsFactory::class);
        }

        #[\Override]
        protected function tearDown(): void
        {
            WorkerModeMockState::reset();
            parent::tearDown();
        }

        public function testWorkerModeLoopsUntilFrankenphpReturnsFalse(): void
        {
            WorkerModeMockState::$frankenphpAvailable = true;
            // Three handled requests, then worker shutdown signal (false).
            WorkerModeMockState::$runningQueue = [true, true, true, false];

            $request = $this->createStub(ServerRequestInterface::class);
            $response = $this->createStub(ResponseInterface::class);

            $this->kernel->expects($this->once())->method('boot')->willReturnSelf();
            $this->kernel->expects($this->once())->method('configure');
            $this->kernel->expects($this->exactly(3))->method('handle')->willReturn($response);
            $this->kernel->expects($this->once())->method('reset');

            $this->globalsFactory->expects($this->exactly(3))->method('createFromGlobals')->willReturn($request);
            $this->emitter->expects($this->exactly(3))->method('emit')->with($response);

            $runtime = new WaffleRuntime($this->globalsFactory, $this->emitter);
            $runtime->loop($this->kernel);

            static::assertSame(4, WorkerModeMockState::$handleInvocations);
        }

        public function testWorkerModeTriggersGcEveryFiftyRequests(): void
        {
            WorkerModeMockState::$frankenphpAvailable = true;
            // 50 successful requests (the modulo-50 GC trigger fires once), then maxRequests caps the loop.
            WorkerModeMockState::$runningQueue = array_fill(0, 60, true);

            $request = $this->createStub(ServerRequestInterface::class);
            $response = $this->createStub(ResponseInterface::class);

            $this->kernel->method('boot')->willReturnSelf();
            $this->kernel->method('handle')->willReturn($response);
            $this->globalsFactory->method('createFromGlobals')->willReturn($request);

            $runtime = new WaffleRuntime($this->globalsFactory, $this->emitter);
            $runtime->loop($this->kernel, maxRequests: 50);

            // 50 iterations executed under the worker-mode branch — sufficient to trip the
            // `($requestCount % 50) === 0` GC checkpoint at least once.
            static::assertSame(50, WorkerModeMockState::$handleInvocations);
        }
    }
}
