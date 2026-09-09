<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Interfaces;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Waterfront\Domain\Provision\Interfaces\ProvisionTypeValidatorInterface;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365AuthorizationUrlRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365TenantIdRequest;

interface Microsoft365RequestValidatorInterface extends ProvisionTypeValidatorInterface
{
    public function getTenantIdRequestValidator(Microsoft365TenantIdRequest $tenantIdRequest): ValidatorContract;

    public function getAuthorizationUrlRequestValidator(Microsoft365AuthorizationUrlRequest $authorizationUrlRequest): ValidatorContract;
}
