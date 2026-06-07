<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime\Exception;

use RuntimeException;
use Throwable;
use Waffle\Commons\Contracts\Exception\Validation\ValidationExceptionInterface;

/**
 * Thrown when a PHP 8.5 Property Hook in a runtime value object rejects an input
 * value (e.g. an empty audit script path in {@see \Waffle\Commons\Runtime\Audit\IgorAuditConfig}).
 *
 * Implements the contracts marker so the framework's RFC 7807 renderer maps it
 * to HTTP 422 when it ever surfaces on a request path; on the CLI audit path it
 * simply signals malformed input.
 */
final class ValidationException extends RuntimeException implements ValidationExceptionInterface
{
    public function __construct(
        string $message,
        private(set) ?string $field = null,
        int $code = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    #[\Override]
    public function getField(): ?string
    {
        return $this->field;
    }
}
