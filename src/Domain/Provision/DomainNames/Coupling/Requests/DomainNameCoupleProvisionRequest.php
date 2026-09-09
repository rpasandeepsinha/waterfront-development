<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Requests;

use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;
use Waterfront\Domain\Provision\Requests\ProvisionRequest;

abstract class DomainNameCoupleProvisionRequest extends ProvisionRequest implements ProvisionContextRequestInterface
{
    public protected(set) ProvisionType $type = ProvisionType::DOMAIN_NAME_COUPLING;
}
