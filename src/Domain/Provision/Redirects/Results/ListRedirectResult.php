<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Results;

use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Validation\ValidationResult;

class ListRedirectResult extends RedirectResult
{
    public bool $failed {
        get => parent::$failed::get() || $this->redirects === null;
    }

    /**
     * @param GetRedirectResult[]|null $redirects
     */
    public function __construct(
        public ProvisionRequestInterface $provisionData,
        public ProvisionStatus $provisionStatus,
        public ?array $redirects = null,
        public ?Throwable $exception = null,
        public ?ValidationResult $validationResult = null
    ) {
        parent::__construct(
            provisionData: $this->provisionData,
            provisionStatus: $this->provisionStatus,
            exception: $this->exception,
            validationResult: $this->validationResult
        );
    }
}
