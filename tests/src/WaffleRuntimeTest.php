<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Runtime;

use Waffle\Commons\Http\Emitter\ResponseEmitter;
use Waffle\Commons\Http\Response;
use Waffle\Commons\Http\ServerRequest;
use Waffle\Commons\Http\Uri;
use Waffle\Commons\Runtime\WaffleRuntime;
use Waffle\Commons\Contracts\Core\KernelInterface;

// We need to mock the emit function or the Emitter class to avoid sending headers in CLI
// Since ResponseEmitter is instantiated inside WaffleRuntime, we might need to use
// namespace mocking for 'header' and 'echo' if we want to test the output directly,
// or rely on the fact that ResponseEmitter is tested elsewhere and just verify the flow.

// A simpler approach for unit testing the Runtime's logic flow is to mock the Kernel.

class WaffleRuntimeTest extends AbstractTestCase
{
    public function testRunOrchestratesRequestLifecycle(): void
    {
        // 1. Setup Mocks
        $kernelMock = $this->createMock(KernelInterface::class);

        // We expect the Kernel to handle a request and return a response
        $kernelMock
            ->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(\Psr\Http\Message\ServerRequestInterface::class))
            ->willReturn(new Response(200, [], 'OK'));

        // 2. Execution
        // We need to suppress output because ResponseEmitter will try to echo the body
        $this->expectOutputString('OK');

        // We also need to prevent headers from being sent, or mock headers_sent/header.
        // For this integration test, we can use the @runInSeparateProcess annotation
        // or assume the Emitter works (it's tested separately).
        // However, Emitter uses `header()` which might fail or do nothing in CLI.

        $runtime = new WaffleRuntime();

        // Note: In a real unit test environment for Runtime, we would ideally inject
        // the Factory and Emitter to mock them. Since WaffleRuntime instantiates them
        // directly (new ...), we are testing the "integration" of these components.

        // To make this test pass without "headers already sent" errors or side effects,
        // we can mock the `header` function in the Emitter namespace for this test run,
        // similar to what we did for ResponseEmitterTest.

        $runtime->run($kernelMock);
    }
}

// Mocking header function for the Emitter namespace to avoid CLI errors
namespace Waffle\Commons\Http\Emitter;

if (!function_exists('Waffle\Commons\Http\Emitter\header')) {
    function header($string, $replace = true, $http_response_code = null)
    {
    }
}

if (!function_exists('Waffle\Commons\Http\Emitter\headers_sent')) {
    function headers_sent()
    {
        return false;
    }
}
