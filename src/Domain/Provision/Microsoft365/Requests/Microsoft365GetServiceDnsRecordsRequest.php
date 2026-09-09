<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Requests;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;

class Microsoft365GetServiceDnsRecordsRequest extends Microsoft365ProvisionRequest
{
    public ProvisionRequestName $name = ProvisionRequestName::GET_SERVICE_DNS_RECORDS_REQUEST;

    public protected(set) bool $requiresValidation = false;

    /**
     * @param string        $domainName The domain represented as fully qualified domain name.
     * @param UuidInterface $context    The Microsoft 365 tenant UUID to retrieve the DNS records from.
     * @param UuidInterface $tagUuid    Subscription UUID used as trace tag for retries.
     */
    public function __construct(
        public string $domainName,
        public protected(set) UuidInterface $context,
        public UuidInterface $tagUuid,
    ) {
        $this->tag = $this->tagUuid;
    }
}
