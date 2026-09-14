<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Interfaces;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionType;

interface ProvisionRequestInterface
{
    public ProvisionRequestName $name { get; }

    public int $requestId { get; set; }

    public UuidInterface $tag { get; set; }

    public protected(set) ProvisionType $type { get; set; }

    public ?ProvisionProvider $provider { get; set; }

    public protected(set) bool $requiresValidation { get; set; }

    public ?UuidInterface $retryOf { get; set; }

    public ?UuidInterface $retryRequester { get; set; }

    /**
     * @phpstan-assert-if-true !null $this->retryOf
     * @phpstan-assert-if-true !null $this->retryRequester
     */
    public function isRetry(): bool;

    /**
     * @return array<LoggingContextKeys::class, mixed>
     */
    public function defaultLogContext(): array;
}
