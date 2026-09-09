<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Services;

use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Hosting\Interfaces\HostingProvisionServiceInterface;
use Waterfront\Domain\Provision\Hosting\Requests\HostingCreateRequest;
use Waterfront\Domain\Provision\Hosting\Requests\HostingSsoRequest;
use Waterfront\Domain\Provision\Hosting\Results\HostingResult;

class PleskProvisionService implements HostingProvisionServiceInterface
{
    public function create(HostingCreateRequest $provisionData): HostingResult
    {
        // TODO implement Plesk create logic here.

        return new HostingResult($provisionData, ProvisionStatus::PENDING);
    }

    public function getSso(HostingSsoRequest $provisionData): HostingResult
    {
        // TODO implement Plesk getSSO logic here.

        return new HostingResult($provisionData, ProvisionStatus::SUCCESS);
    }
}
