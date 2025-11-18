<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime;

use Waffle\Commons\Http\Emitter\ResponseEmitter;
use Waffle\Commons\Http\Factory\GlobalsFactory;
use Waffle\Interface\KernelInterface;

class WaffleRuntime implements RuntimeInterface
{
    /**
     * Orchestrates the request lifecycle.
     *
     * 1. Creates a PSR-7 ServerRequest from globals.
     * 2. Passes the request to the Kernel to get a Response.
     * 3. Emits the response to the client.
     */
    public function run(KernelInterface $kernel): void
    {
        // 1. Create the request from PHP globals (POST, GET, etc.)
        $factory = new GlobalsFactory();
        $request = $factory->createFromGlobals();

        // 2. Handle the request via the Kernel
        $response = $kernel->handle($request);

        // 3. Emit the response headers and body
        $emitter = new ResponseEmitter();
        $emitter->emit($response);
    }
}
