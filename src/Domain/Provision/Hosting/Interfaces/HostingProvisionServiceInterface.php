<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Interfaces;

use Waterfront\Domain\Provision\Hosting\Requests\HostingCreateRequest;
use Waterfront\Domain\Provision\Hosting\Requests\HostingSsoRequest;
use Waterfront\Domain\Provision\Hosting\Results\HostingResult;
use Waterfront\Domain\Provision\Interfaces\TypeProvisionServiceInterface;

interface HostingProvisionServiceInterface extends TypeProvisionServiceInterface
{
    public function create(HostingCreateRequest $provisionData): HostingResult;

    public function getSso(HostingSsoRequest $provisionData): HostingResult;
}
