<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\Actions;

class GetSubjectTypeAction
{
    public function execute(string $subjectNamespace): string
    {
        switch ($subjectNamespace) {
            /*
             * We're supporting old namespaces because models have moved, we don't have morphmapping yet.
             * In the audit log refactor we will start to support this so this can be less complex. (Ticket: WATER-4467)
             */
            case 'Modules\DomainService\Models\Subscription':
            case 'Waterfront\Domain\Domains\Models\DomainSubscription':
                return 'DomainDeployment';
            case 'Modules\HostingService\Models\Subscription':
            case 'Waterfront\Domain\Hosting\Models\HostingSubscription':
                return 'HostingDeployment';
            case 'Waterfront\Domain\Ssl\Models\SslSubscription':
            case 'Modules\SslService\Models\Subscription':
                return 'SslDeployment';

            case "App\Models\CustomerAddress":
            case "App\Models\Customer":
            case "App\Models\CustomerContact":
            case "Modules\Customer\Models\Customer":
            case "Modules\Customer\Models\CustomerAddress":
            case "Modules\Customer\Models\CustomerContact":
            case "Modules\Customer\Models\CustomerWallet":
                return 'customer';
        }

        $exploded = explode('\\', $subjectNamespace);

        return end($exploded);
    }
}
