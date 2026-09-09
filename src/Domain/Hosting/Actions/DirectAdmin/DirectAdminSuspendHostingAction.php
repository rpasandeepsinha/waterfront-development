<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions\DirectAdmin;

use InvalidArgumentException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;

class DirectAdminSuspendHostingAction
{
    public function __construct(private readonly BehavesAsDirectAdmin $directAdmin)
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function execute(DirectAdminServer $server, HostingDeployment $hostingDeployment): void
    {
        if ($hostingDeployment->directadmin_customer_username === null) {
            throw new InvalidArgumentException(sprintf(
                'Suspending of subscription failed, because no directadmin customer username was set for subscription uuid: %s',
                $hostingDeployment->subscription->uuid
            ));
        }

        $userApi = $this->directAdmin->user($server);
        $userApi->suspend($hostingDeployment->directadmin_customer_username);
    }
}
