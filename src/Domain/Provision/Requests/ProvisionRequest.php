<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

abstract class ProvisionRequest implements ProvisionRequestInterface
{
    public UuidInterface $tag;

    public int $requestId = 0;

    public ?ProvisionProvider $provider = null;

    public ?UuidInterface $retryOf = null;

    public ?UuidInterface $retryRequester = null;

    /**
     * Some requests have strict DTO requirements and might not
     * need specific provider validation. A given request can
     * change this variable to false which skips validation.
     */
    public protected(set) bool $requiresValidation = true;

    public function isRetry(): bool
    {
        return $this->retryOf instanceof UuidInterface
            && $this->retryRequester instanceof UuidInterface;
    }

    /**
     * @return array<LoggingContextKeys::class, mixed>
     */
    public function defaultLogContext(): array
    {
        $context = [
            LoggingContextKeys::PROVISIONING_TYPE => $this->type,
            LoggingContextKeys::PROVISIONING_PROVIDER => $this->provider,
            LoggingContextKeys::PROVISIONING_REQUEST_ID => $this->requestId,
        ];

        if ($this instanceof ProvisionContextRequestInterface) {
            $context[LoggingContextKeys::PROVISIONING_CONTEXT] = $this->context;
        }

        return $context;
    }
}
