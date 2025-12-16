<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime;

use Psr\Http\Message\ServerRequestInterface;
use Waffle\Commons\Contracts\Core\KernelInterface;
use Waffle\Commons\Contracts\Http\ResponseEmitterInterface;
use Waffle\Commons\Contracts\Runtime\RuntimeInterface;

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
    /**
     * {@inheritdoc}
     */
    public function run(
        KernelInterface $kernel,
        ServerRequestInterface $request,
        ResponseEmitterInterface $emitter,
    ): void {
        // 1. Handle the request via the Kernel
        // The Kernel is responsible for routing and controller execution.
        $response = $kernel->handle($request);

        // 2. Emit the response
        // Delegates the output logic (headers, echo body) to the emitter implementation.
        $emitter->emit($response);
    }
}
