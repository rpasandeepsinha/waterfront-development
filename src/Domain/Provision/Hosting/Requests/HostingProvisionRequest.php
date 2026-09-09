<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Requests;

use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;
use Waterfront\Domain\Provision\Requests\ProvisionRequest;

abstract class HostingProvisionRequest extends ProvisionRequest implements ProvisionContextRequestInterface
{
    public ProvisionType $type = ProvisionType::HOSTING;
}
