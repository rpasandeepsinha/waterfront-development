<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Interfaces;

use Illuminate\Contracts\Validation\Validator;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;

interface ProvisionServiceFactoryInterface
{
    public function getValidator(ProvisionProvider $provider, ProvisionRequestInterface $provisionRequest): Validator;

    public function getProviderService(ProvisionProvider $provider): TypeProvisionServiceInterface;
}
