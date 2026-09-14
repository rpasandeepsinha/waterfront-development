<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting;

use Waterfront\Domain\Hosting\DTO\HostingModelData;
use Waterfront\Domain\Hosting\Exceptions\UnableToConvertHostingModelException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingDeploymentService;
use Waterfront\Domain\Servers\Models\Server;

class GenericHostingModelConverter
{
    public function __construct(
        private readonly HostingDeploymentService $hostingDeploymentService,
    ) {
    }

    /**
     * @throws UnableToConvertHostingModelException
     */
    public function execute(HostingDeployment $hostingSubscription): HostingModelData
    {
        $server = $this->determineServer($hostingSubscription);

        if ($server === null) {
            throw new UnableToConvertHostingModelException('No server was coupled to given hosting');
        }

        $username = $this->hostingDeploymentService->getUsername($hostingSubscription);
        if ($username === null) {
            throw new UnableToConvertHostingModelException('No username was coupled to given hosting server');
        }

        return new HostingModelData($server, $username);
    }

    private function determineServer(HostingDeployment $hostingDeployment): ?Server
    {
        if ($hostingDeployment->subscription->product->isMailOnlyServer()) {
            return $hostingDeployment->mailOnlyServer;
        }

        if ($hostingDeployment->subscription->product->isSitebuilderProduct()) {
            return $hostingDeployment->basekitServer;
        }

        if ($hostingDeployment->isDefaultHostingSubscription()) {
            return $hostingDeployment->server;
        }

        return null;
    }
}
