<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Interfaces;

use Ramsey\Uuid\UuidInterface;

interface ProvisionContextRequestInterface extends ProvisionRequestInterface
{
    public protected(set) UuidInterface $context { get; set; }
}
