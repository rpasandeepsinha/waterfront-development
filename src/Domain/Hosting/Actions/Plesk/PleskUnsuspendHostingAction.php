<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions\Plesk;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingPackageInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\ChangeHostingPackageStatus\Parameters as ChangeHostingPackageStatusParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Servers\Models\Server;
use Webmozart\Assert\Assert;

class PleskUnsuspendHostingAction
{
    public function __construct(
        private readonly HostingPackageInterface $hostingPackageClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Server $server, HostingDeployment $hostingDeployment): Result
    {
        $this->logger->info(
            sprintf(
                'Unsuspending hosting package for subscription: "%s" on plesk server id: "%s"',
                $hostingDeployment->subscription->uuid,
                $server->id
            )
        );
        $this->hostingPackageClient->setServer($server);

        $domain = $hostingDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $parameters = new ChangeHostingPackageStatusParameters();
        $parameters->setDomain($domain);

        $result = $this->hostingPackageClient->enable($parameters);

        $this->logger->info(
            sprintf(
                'Unsuspending hosting package for subscription: "%s" on plesk server id: "%s" with Plesk XML response: %s',
                $hostingDeployment->subscription->uuid,
                $server->id,
                $result->getResponseResult()
            )
        );

        $hostingDeployment->last_created_result = $result->getResponseResult();
        $hostingDeployment->save();

        return $result;
    }
}
