<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime;

use Psr\Http\Message\ServerRequestInterface;
use Waffle\Commons\Contracts\Core\KernelInterface;
use Waffle\Commons\Contracts\Http\ResponseEmitterInterface;
use Waffle\Commons\Contracts\Runtime\RuntimeInterface;
use Waffle\Commons\Http\Emitter\ResponseEmitter;
use Waffle\Commons\Http\Factory\GlobalsFactory;

/**
 * WaffleRuntime is a purely agnostic application runner.
 *
 * Its sole responsibility is to orchestrate the request lifecycle:
 * 1. Receive the fully assembled Kernel and Request.
 * 2. Execute the Kernel logic.
 * 3. Output the Response using the Emitter.
 *
 * It contains NO concrete dependencies, ensuring maximum decoupling.
 */
final class WaffleRuntime implements RuntimeInterface
{
    private GlobalsFactory $globalsFactory;
    private ResponseEmitterInterface $emitter;

    public function __construct(?GlobalsFactory $globalsFactory = null, ?ResponseEmitterInterface $emitter = null)
    {
        $this->globalsFactory = $globalsFactory ?? new GlobalsFactory();
        $this->emitter = $emitter ?? new ResponseEmitter();
    }

    public function loop(KernelInterface $kernel, int $maxRequests = 500): void
    {
        // 1. Boot Once: Initialize the kernel (Container, Config, Routes)
        // This happens only once when the worker starts.
        $kernel->boot()->configure();

        $requestCount = 0;

        // 2. Loop Many: Process incoming requests
        do {
            // Prepare the handler logic
            $handler = function () use ($kernel) {
                // B. Create a fresh Request object from the updated superglobals
                $request = $this->globalsFactory->createFromGlobals();

                // C. Handle the request via the Kernel (Hot path)
                $response = $kernel->handle($request);

                // D. Emit the response to the client
                $this->emitter->emit($response);
            };

            // A. FrankenPHP: Pause execution and wait for a request
            $running = \function_exists('frankenphp_handle_request') ? \frankenphp_handle_request($handler) : false; // If function missing, we are not in worker mode -> Exit

            // Fallback: If not running under FrankenPHP worker, execute once and break
            if (!\function_exists('frankenphp_handle_request')) {
                $handler();
                break;
            }

            if (!$running) {
                break;
            }

            // E. Cleanup & Garbage Collection
            if ((++$requestCount % 50) === 0) {
                gc_collect_cycles();
            }
        } while ($requestCount < $maxRequests);
    }
}
