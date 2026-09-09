<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class Microsoft365CreateDomainRequest extends Microsoft365ProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::CREATE_MICROSOFT365_DOMAIN;

    public protected(set) bool $requiresValidation = false;

    /**
     * @param string        $domainName The domain represented as fully qualified domain name to create.
     * @param UuidInterface $context    The Microsoft 365 tenant UUID the domain should be created under.
     * @param UuidInterface $tagUuid    Subscription UUID used as trace tag for retries.
     */
    public function __construct(
        public string $domainName,
        public protected(set) UuidInterface $context,
        public UuidInterface $tagUuid,
    ) {
        $this->tag = $this->tagUuid;
    }

    public function requiresValidation(): bool
    {
        return false;
    }
}
