<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Results;

use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Validation\ValidationResult;

class BasekitSiteResult extends SitebuilderResult
{
    public bool $failed {
        get => parent::$failed::get() || $this->domain === null;
    }

    public function __construct(
        ProvisionRequestInterface $provisionData,
        ProvisionStatus $provisionStatus,
        public ?int $siteRef = null,
        public ?string $domain = null,
        ?Throwable $exception = null,
        ?ValidationResult $validationResult = null
    ) {
        parent::__construct($provisionData, $provisionStatus, $exception, $validationResult);
    }
}
