<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Interfaces;

use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameCoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameDecoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Results\DomainNameCoupleResult;
use Waterfront\Domain\Provision\Interfaces\TypeProvisionServiceInterface;
use Waterfront\Domain\Provision\Results\ProvisionResult;

interface DomainNameCoupleInterface extends TypeProvisionServiceInterface
{
    public function coupleToDomainName(DomainNameCoupleRequest $request): DomainNameCoupleResult;

    public function decoupleDomainName(DomainNameDecoupleRequest $request): ProvisionResult;
}
