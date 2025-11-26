<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime;

use Waffle\Commons\Container\Container;
use Waffle\Commons\Contracts\Core\KernelInterface;
use Waffle\Commons\Contracts\Runtime\RuntimeInterface;
use Waffle\Commons\Http\Emitter\ResponseEmitter;
use Waffle\Commons\Http\Factory\GlobalsFactory;

class WaffleRuntime implements RuntimeInterface
{
    /**
     * Orchestrates the application execution lifecycle.
     */
    public function run(KernelInterface $kernel): void
    {
        // 1. Dependency Injection Setup
        // Instantiate the concrete PSR-11 container from waffle-commons/container
        $container = new Container();

        // Inject it into the Kernel. The Kernel will wrap it with security.
        if (method_exists($kernel, 'setContainerImplementation')) {
            $kernel->setContainerImplementation($container);
        }

        // 2. Create Request
        $factory = new GlobalsFactory();
        $request = $factory->createFromGlobals();

        // 3. Handle Request via Kernel
        $response = $kernel->handle($request);

        // 4. Emit Response
        $emitter = new ResponseEmitter();
        $emitter->emit($response);
    }
}
