<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DNS\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Waterfront\Domain\DNS\Models\DnsDeployment;

class DnsProvisioned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public DnsDeployment $dnsDeployment,
    ) {
    }
}
