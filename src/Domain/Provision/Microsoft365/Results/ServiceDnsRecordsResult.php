<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Results;

use SandwaveIo\Microsoft\Graph\Models\DomainDnsRecord;
use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Validation\ValidationResult;

class ServiceDnsRecordsResult extends Microsoft365Result
{
    public bool $failed {
        get => parent::$failed::get() || $this->records === null;
    }

    /**
     * @param array<DomainDnsRecord>|null $records
     */
    public function __construct(
        public ProvisionRequestInterface $provisionData,
        public ProvisionStatus $provisionStatus,
        public ?array $records = null,
        public ?Throwable $exception = null,
        public ?ValidationResult $validationResult = null
    ) {
        parent::__construct(
            provisionData: $provisionData,
            provisionStatus: $provisionStatus,
            exception: $exception,
            validationResult: $validationResult
        );
    }
}
