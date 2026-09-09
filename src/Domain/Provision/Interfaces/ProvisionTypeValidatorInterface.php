<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Interfaces;

use Illuminate\Contracts\Validation\Validator;

interface ProvisionTypeValidatorInterface
{
    public function getValidatorByRequest(ProvisionRequestInterface $provisionRequest): Validator;
}
