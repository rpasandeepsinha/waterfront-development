<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Events;

use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;

class ZoneOutdated
{
    public function __construct(
        public DnsCustomerTemplate $template,
        public string $zone,
        public ?DnsZone $pdnsZone = null,
    ) {
    }
}
