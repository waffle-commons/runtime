<?php

declare(strict_types=1);

// Namespace-level shims for the WaffleRuntime worker-mode branch.
// PHP resolves unqualified calls in the current namespace first; the production runtime
// uses unqualified `function_exists()`, `getenv()` and `frankenphp_handle_request()` so
// these shims take effect only while this test file is loaded.
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

    /**
     * @return array<string, string>|string|false
     */
    function getenv(?string $name = null, bool $local_only = false): array|string|false
    {
        if ($name === 'FRANKENPHP_WORKER') {
            return WorkerModeMockState::$workerFlag;
        }
        return \getenv($name, $local_only);
    }
}

namespace WaffleTests\Commons\Runtime {
    use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\MockObject\MockObject;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Waffle\Commons\Contracts\Core\KernelInterface;
    use Waffle\Commons\Contracts\Http\GlobalsFactoryInterface;
    use Waffle\Commons\Contracts\Http\ResponseEmitterInterface;
    use Waffle\Commons\Runtime\WaffleRuntime;

    /**
     * Holds state for the namespace-level shims above. Reset before each test.
     */
    final class WorkerModeMockState
    {
        public static bool $frankenphpAvailable = false;

        /**
         * Value the getenv('FRANKENPHP_WORKER') shim returns: '1' in worker mode, false in
         * FrankenPHP classic mode or any other SAPI.
         */
        public static string|false $workerFlag = false;

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
            self::$workerFlag = false;
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
        /** @var GlobalsFactoryInterface&MockObject */
        private $globalsFactory;

        #[\Override]
        protected function setUp(): void
        {
            parent::setUp();
            WorkerModeMockState::reset();

            $this->kernel = $this->createMock(KernelInterface::class);
            $this->emitter = $this->createMock(ResponseEmitterInterface::class);
            $this->globalsFactory = $this->createMock(GlobalsFactoryInterface::class);
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
            WorkerModeMockState::$workerFlag = '1';
            // Three handled requests, then worker shutdown signal (false).
            WorkerModeMockState::$runningQueue = [true, true, true, false];

            $request = $this->createStub(ServerRequestInterface::class);
            $response = $this->createStub(ResponseInterface::class);

            $this->kernel->expects($this->once())->method('boot')->willReturnSelf();
            $this->kernel->expects($this->once())->method('configure');
            $this->kernel->expects($this->exactly(3))->method('handle')->willReturn($response);
            // ΔM=0: reset() runs once per handled request (in the handler's finally),
            // never once at worker shutdown — so 3 handled requests => 3 resets.
            $this->kernel->expects($this->exactly(3))->method('reset');

            $this->globalsFactory->expects($this->exactly(3))->method('createFromGlobals')->willReturn($request);
            $this->emitter->expects($this->exactly(3))->method('emit')->with($response);

            $runtime = new WaffleRuntime($this->globalsFactory, $this->emitter);
            $runtime->loop($this->kernel);

            static::assertSame(4, WorkerModeMockState::$handleInvocations);
        }

        #[AllowMockObjectsWithoutExpectations]
        public function testWorkerModeTriggersGcEveryFiftyRequests(): void
        {
            WorkerModeMockState::$frankenphpAvailable = true;
            WorkerModeMockState::$workerFlag = '1';
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

        /**
         * @return iterable<string, array{string|false}>
         */
        public static function nonWorkerFlags(): iterable
        {
            yield 'flag absent (FrankenPHP classic mode)' => [false];
            yield 'flag empty' => [''];
            yield 'flag zero' => ['0'];
        }

        /**
         * FrankenPHP classic mode: frankenphp_handle_request() exists but would throw
         * ("called while not in worker mode"). The runtime must handle the one current
         * request itself and never call it.
         */
        #[DataProvider('nonWorkerFlags')]
        public function testClassicModeHandlesExactlyOneRequestWithoutTheWorkerLoop(string|false $flag): void
        {
            WorkerModeMockState::$frankenphpAvailable = true;
            WorkerModeMockState::$workerFlag = $flag;
            WorkerModeMockState::$runningQueue = [true, true, true];

            $request = $this->createStub(ServerRequestInterface::class);
            $response = $this->createStub(ResponseInterface::class);

            $this->kernel->expects($this->once())->method('boot')->willReturnSelf();
            $this->kernel->expects($this->once())->method('configure');
            $this->kernel->expects($this->once())->method('handle')->willReturn($response);
            $this->kernel->expects($this->once())->method('reset');

            $this->globalsFactory->expects($this->once())->method('createFromGlobals')->willReturn($request);
            $this->emitter->expects($this->once())->method('emit')->with($response);

            $runtime = new WaffleRuntime($this->globalsFactory, $this->emitter);
            $runtime->loop($this->kernel);

            static::assertSame(0, WorkerModeMockState::$handleInvocations);
        }
    }
}
