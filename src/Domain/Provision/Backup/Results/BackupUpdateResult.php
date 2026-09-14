<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Results;

use Exception;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Results\AbstractProvisionResult;
use Waterfront\Domain\Provision\Validation\ValidationResult;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;

class BackupUpdateResult extends AbstractProvisionResult
{
    public function __construct(
        ProvisionRequestInterface $provisionData,
        ProvisionStatus $provisionStatus,
        public ?OfferingItems $offeringItems = null,
        ?Exception $exception = null,
        ?ValidationResult $validationResult = null,
    ) {
        parent::__construct($provisionData, $provisionStatus, $exception, $validationResult);
    }
}
