<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain\DTO;

use RealtimeRegister\Domain\ProcessCollection;
use Waterfront\Apps\OneOffScripts\Domain\Enum\FailedDomainSubscriptionRepairPath;

readonly class FailedDomainSubscriptionRepair
{
    public function __construct(
        public FailedDomainSubscriptionRepairPath $path,
        public ?ProcessCollection $processCollection = null,
        public ?string $reason = null,
    ) {
    }
}
