<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Services;

use Waterfront\Domain\Hosting\DTO\DowngradeCheckResult;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Webmozart\Assert\Assert;

class HostingDowngradePossibilityChecker
{
    public function __construct(
        private readonly HostingServiceFactory $hostingServiceFactory,
        private readonly HostingDeploymentRepository $hostingDeploymentRepository,
        private readonly HostingService $hostingService,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function canDowngradeToServicePlan(
        HostingDeployment $deployment,
        string $servicePlan,
    ): DowngradeCheckResult {
        $server = $this->hostingDeploymentRepository->getServer($deployment);

        Assert::isInstanceOf($server, Server::class);

        $hostingOffer = $this->hostingService->getPackageOnServerAsDto($server, $servicePlan);
        $currentUsage = $this->hostingService->getUserStats(
            $this->subscriptionRepository->getSubscriptionByHostingDeployment($deployment),
            $this->hostingServiceFactory->getDriverFromServer($server),
        );

        if (
            ! is_null($currentUsage?->activeDomains)
            && $hostingOffer->getMaxAmountDomains() > -1
            && $currentUsage->activeDomains > $hostingOffer->getMaxAmountDomains()
        ) {
            return new DowngradeCheckResult(false, sprintf(
                'Amount of domains (%s) exceeds quota (%s)',
                $currentUsage->activeDomains,
                $hostingOffer->getMaxAmountDomains(),
            ));
        }

        if (
            ! is_null($currentUsage?->databases)
            && $hostingOffer->getMaxAmountDatabases() > -1
            && $currentUsage->databases > $hostingOffer->getMaxAmountDatabases()
        ) {
            return new DowngradeCheckResult(false, sprintf(
                'Amount of databases (%s) exceeds quota (%s)',
                $currentUsage->databases,
                $hostingOffer->getMaxAmountDatabases(),
            ));
        }

        if (
            ! is_null($currentUsage?->mailBoxes)
            && $hostingOffer->getMaxAmountMailAccounts() > -1
            && $currentUsage->mailBoxes > $hostingOffer->getMaxAmountMailAccounts()
        ) {
            return new DowngradeCheckResult(false, sprintf(
                'Amount of mail accounts (%s) exceeds quota (%s)',
                $currentUsage->mailBoxes,
                $hostingOffer->getMaxAmountMailAccounts(),
            ));
        }

        if (
            ! is_null($currentUsage?->diskSpaceInMb)
            && $hostingOffer->getMaxDiskSpaceInMB() > -1
            && $currentUsage->diskSpaceInMb > $hostingOffer->getMaxDiskSpaceInMB()
        ) {
            return new DowngradeCheckResult(false, sprintf(
                'Disk usage (%s) exceeds quota (%s)',
                $currentUsage->diskSpaceInMb,
                $hostingOffer->getMaxDiskSpaceInMB(),
            ));
        }

        return new DowngradeCheckResult(true, null);
    }
}
