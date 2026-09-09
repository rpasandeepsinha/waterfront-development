<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Support;

use Throwable;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class LogContextBuilder
{
    /**
     * @param array<LoggingContextKeys::class, mixed> $context
     */
    private function __construct(private array $context)
    {
    }

    public static function for(ProvisionRequestInterface $request): self
    {
        return new self($request->defaultLogContext());
    }

    public function withException(Throwable $exception): self
    {
        $this->context[LoggingContextKeys::EXCEPTION] = $exception;

        return $this;
    }

    /**
     * @param array<LoggingContextKeys::class, mixed> $meta
     */
    public function withMeta(array $meta): self
    {
        $this->context[LoggingContextKeys::META] = $meta;

        return $this;
    }

    public function with(string $key, mixed $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return $this->context;
    }
}
