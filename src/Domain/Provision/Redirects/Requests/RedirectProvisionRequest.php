<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Requests;

use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;
use Waterfront\Domain\Provision\Requests\ProvisionRequest;

abstract class RedirectProvisionRequest extends ProvisionRequest implements ProvisionContextRequestInterface
{
    public protected(set) ProvisionType $type = ProvisionType::REDIRECT;

    public ?ProvisionProvider $provider = ProvisionProvider::CADDY;
}
