<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime;

use Waffle\Commons\Http\Factory\RequestFactory;
use Waffle\Interface\KernelInterface;

class WaffleRuntime implements RuntimeInterface
{
    public function run(KernelInterface $kernel) : void
    {
        $request = new RequestFactory()->createFromGlobals();
        // $response = $kernel->handle($request);
        // new ResponseEmitter()->emit($response);
    }
}