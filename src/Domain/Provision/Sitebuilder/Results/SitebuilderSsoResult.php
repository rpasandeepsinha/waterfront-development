<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Results;

use SensitiveParameter;
use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Validation\ValidationResult;

class SitebuilderSsoResult extends SitebuilderResult
{
    public bool $failed {
        get => parent::$failed::get() || $this->ssoUrl === null;
    }

    public function __construct(
        ProvisionRequestInterface $provisionData,
        ProvisionStatus $provisionStatus,
        #[SensitiveParameter]
        public ?string $ssoUrl = null,
        ?Throwable $exception = null,
        ?ValidationResult $validationResult = null,
    ) {
        parent::__construct($provisionData, $provisionStatus, $exception, $validationResult);
    }
}
