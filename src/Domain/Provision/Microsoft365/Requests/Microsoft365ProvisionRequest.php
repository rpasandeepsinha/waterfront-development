<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Requests;

use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;
use Waterfront\Domain\Provision\Requests\ProvisionRequest;

abstract class Microsoft365ProvisionRequest extends ProvisionRequest implements ProvisionContextRequestInterface
{
    public protected(set) ProvisionType $type = ProvisionType::M365;

    public ?ProvisionProvider $provider = ProvisionProvider::MICROSOFT_GRAPH;
}
