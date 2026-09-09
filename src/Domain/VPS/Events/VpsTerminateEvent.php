<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Events;

use Illuminate\Queue\SerializesModels;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;

class VpsTerminateEvent
{
    use SerializesModels;

    public function __construct(public readonly VirtualMachineDeployment $vmDeployment)
    {
    }
}
