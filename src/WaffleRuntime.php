<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Waffle\Commons\Contracts\Core\KernelInterface;
use Waffle\Commons\Contracts\Core\TerminableInterface;
use Waffle\Commons\Contracts\Http\ResponseEmitterInterface;
use Waffle\Commons\Contracts\Runtime\RuntimeInterface;
use Waffle\Commons\Http\Emitter\ResponseEmitter;
use Waffle\Commons\Http\Factory\GlobalsFactory;

/**
 * WaffleRuntime is a purely agnostic application runner for FrankenPHP worker mode.
 *
 * Its sole responsibility is to orchestrate the request lifecycle:
 * 1. Boot the Kernel ONCE when the worker starts.
 * 2. For each incoming request: build the Request, handle it, emit the Response.
 * 3. RELEASE all request-scoped state after EVERY request, before the next loop.
 *
 * Lifecycle invariant (ΔM = 0): boot-time state is initialized once, outside the
 * loop; request-scoped state is reset after every request — never deferred to
 * worker shutdown. This is what prevents User-Context, open-transaction, and
 * prepared-statement bleed from request N into request N+1.
 *
 * It contains NO concrete domain dependencies, ensuring maximum decoupling.
 */
final class WaffleRuntime implements RuntimeInterface
{
    /**
     * Backstop cycle-collector cadence for cyclic per-request graphs (PSR-15
     * handler chains, PSR-7 message graphs) the refcounter cannot free
     * synchronously at scope exit.
     */
    private const int GC_INTERVAL = 50;

    private readonly GlobalsFactory $globalsFactory;
    private readonly ResponseEmitterInterface $emitter;

    public function __construct(?GlobalsFactory $globalsFactory = null, ?ResponseEmitterInterface $emitter = null)
    {
        $this->globalsFactory = $globalsFactory ?? new GlobalsFactory();
        $this->emitter = $emitter ?? new ResponseEmitter();
    }

    #[\Override]
    public function loop(KernelInterface $kernel, int $maxRequests = 500): void
    {
        // 1. BOOT ONCE: initialize the kernel (Container, Config, Routes). Pure
        //    boot-time state — this runs only when the worker starts.
        $kernel->boot()->configure();

        // 2. Allocation-stable handler: built once and reused for every request so
        //    the loop body itself contributes no per-iteration garbage.
        $handler = function () use ($kernel): void {
            $request = null;
            $response = null;

            try {
                // Build a fresh PSR-7 request from the per-request superglobals
                // FrankenPHP repopulates inside this callback.
                $request = $this->globalsFactory->createFromGlobals();

                // Hot path: turn the Request into a Response via the Kernel.
                $response = $kernel->handle($request);

                // Emit the Response to the client.
                $this->emitter->emit($response);

                // Post-response hook (terminate): heavy async work runs AFTER the
                // client already holds the response, BEFORE request-state reset.
                // Optional capability — skipped for non-terminable kernels.
                if ($kernel instanceof TerminableInterface) {
                    $kernel->terminate($request, $response);
                }
            } finally {
                // ΔM=0 INVARIANT — release request-scoped state AFTER each request
                // (even on throw), before the next iteration. This is the single
                // step that keeps the resident worker stateless across requests.
                $kernel->reset();

                // Absolute resource destruction: drop the per-request body file
                // descriptors now instead of waiting for refcount/GC of the
                // (possibly cyclic) message graph.
                $this->closeMessageStreams($request, $response);

                unset($request, $response);
            }
        };

        $requestCount = 0;

        // 3. LOOP MANY: process incoming requests.
        do {
            // Unqualified calls let namespace-level test shims override these;
            // PHP falls back to the global function when no namespace-local
            // definition exists, so production behavior is unaffected.
            if (!function_exists('frankenphp_handle_request')) {
                // Non-worker SAPI (CLI/test): run exactly once — the reset still
                // happens inside the handler's finally — then leave.
                $handler();
                break;
            }

            // Worker mode: block until a request arrives. On the shutdown signal
            // the handler is NOT invoked, so no spurious reset runs that iteration.
            $running = frankenphp_handle_request($handler);
            if (!$running) {
                break;
            }

            // Backstop GC for cyclic per-request garbage.
            if ((++$requestCount % self::GC_INTERVAL) === 0) {
                gc_collect_cycles();
            }
        } while ($requestCount < $maxRequests);
    }

    /**
     * Fail-safe, idempotent destruction of the request/response body streams so
     * the underlying file descriptors are released immediately at request end
     * rather than lingering until the next cycle-collector pass.
     */
    private function closeMessageStreams(?ServerRequestInterface $request, ?ResponseInterface $response): void
    {
        $request?->getBody()->close();
        $response?->getBody()->close();
    }
}
