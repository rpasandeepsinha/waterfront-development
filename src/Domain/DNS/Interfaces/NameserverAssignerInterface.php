<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Interfaces;

use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\DnsNamerverAlreadyAssignedException;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Models\DnsDeployment;

interface NameserverAssignerInterface
{
    /**
     * @throws DnsNamerverAlreadyAssignedException
     * @throws FailedToFetchNameserversException
     *
     * @return Nameserver[]
     */
    public function assign(DnsDeployment $dnsDeployment): array;

    public function clear(DnsDeployment $dnsDeployment): void;
}
