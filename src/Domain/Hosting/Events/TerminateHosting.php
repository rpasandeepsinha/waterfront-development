<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Events;

use Waterfront\Domain\Hosting\Models\HostingDeployment;

class TerminateHosting
{
    public function __construct(
        public HostingDeployment $hostingDeployment,
        public readonly ?string $technicalStatus,
    ) {
    }
}
