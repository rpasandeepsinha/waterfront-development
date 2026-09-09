<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Interfaces;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Waterfront\Domain\Provision\Hosting\Requests\HostingCreateRequest;
use Waterfront\Domain\Provision\Interfaces\ProvisionTypeValidatorInterface;

interface HostingRequestValidatorInterface extends ProvisionTypeValidatorInterface
{
    public function getCreateRequestValidator(HostingCreateRequest $createRequest): ValidatorContract;

    // TODO add other methods that need validators such as getSSOValidator, getUpdateRequestValidator, etc.
}
