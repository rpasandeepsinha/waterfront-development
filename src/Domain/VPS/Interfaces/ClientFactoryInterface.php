<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Interfaces;

use Waterfront\Domain\VPS\Exceptions\ClientFactoryException;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Infra\CloudStackClient\CloudStackClient;

interface ClientFactoryInterface
{
    /**
     * @throws ClientFactoryException
     */
    public function create(ManagerDomainDeployment $deployment): CloudStackClient;
}
