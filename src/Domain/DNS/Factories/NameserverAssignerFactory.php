<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Factories;

use Waterfront\Domain\DNS\Interfaces\NameserverAssignerInterface;
use Waterfront\Domain\DNS\Services\DnsExternalNameserverAssigner;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\DNS\Services\DnsVanityNameserverAssigner;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;

class NameserverAssignerFactory
{
    public function __construct(
        private readonly DnsNameserverAssigner $dnsNameserverAssigner,
        private readonly DnsVanityNameserverAssigner $dnsVanityNameserverAssigner,
        private readonly DnsExternalNameserverAssigner $dnsExternalNameserverAssigner
    ) {
    }

    public function createAssigner(NameserverType $nameserverType): NameserverAssignerInterface
    {
        return match ($nameserverType) {
            NameserverType::INTERNAL => $this->dnsNameserverAssigner,
            NameserverType::VANITY => $this->dnsVanityNameserverAssigner,
            NameserverType::EXTERNAL => $this->dnsExternalNameserverAssigner,
        };
    }
}
