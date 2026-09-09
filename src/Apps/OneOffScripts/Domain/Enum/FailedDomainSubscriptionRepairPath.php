<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain\Enum;

enum FailedDomainSubscriptionRepairPath: string
{
    case REPAIR_NAMESERVERS = 'repair_nameservers';
    case RESTORE_FROM_PROCESS = 'restore_from_process';
    case RETRY_PROVISIONING = 'retry_provisioning';
    case SYNC_STATUS_ONLY = 'sync_status_only';
    case SKIP = 'skip';
}
