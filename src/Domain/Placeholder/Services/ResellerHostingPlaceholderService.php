<?php

declare(strict_types=1);

namespace Waterfront\Domain\Placeholder\Services;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\ResellerHosting\Interfaces\ResellerHostingServiceInterface;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\ResellerHosting\Parameters\AppResellerHostingDomainCoupleParameters;
use Waterfront\Domain\ResellerHosting\Parameters\ResellerHostingParameters;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Exceptions\NotImplementedException;

class ResellerHostingPlaceholderService extends PlaceHolderService implements ResellerHostingServiceInterface
{
    public function findServer(): Server
    {
        throw new NotImplementedException();
    }

    public function createReseller(ResellerHostingParameters $parameters, Server $server): Result
    {
        throw new NotImplementedException();
    }

    public function getSubAccounts(ResellerHostingDeployment $resellerHostingDeployment): array
    {
        throw new NotImplementedException();
    }

    public function create(
        string $contactPersonName,
        string $contactEmail,
        string $customerEmail,
        UuidInterface $customerUuid,
        string $subscriptionUuid,
        array $specs,
        int $providerId,
        ?Server $server = null,
    ): string {
        throw new NotImplementedException();
    }

    public function generateUsername(): string
    {
        throw new NotImplementedException();
    }

    public function terminate(ResellerHostingDeployment $deployment): bool
    {
        $this->notificationService->sendTerminationNotification(
            $this->getProvisionDetailFromSubscription($deployment->subscription),
        );

        return true;
    }

    public function resetPassword(ResellerHostingParameters $parameters, UuidInterface $customerUuid): array
    {
        throw new NotImplementedException();
    }

    public function getUserStats(ResellerHostingParameters $parameters): array
    {
        throw new NotImplementedException();
    }

    public function generateDomain(string $username, string $fqdn): string
    {
        throw new NotImplementedException();
    }

    public function coupleExistingDomain(
        ResellerHostingDeployment $resellerHostingDeployment,
        Subscription $domainDeployment,
        AppResellerHostingDomainCoupleParameters $parameters,
    ): bool {
        throw new NotImplementedException();
    }

    public function modifyCustomerForResellerMigrations(string $username): bool
    {
        throw new NotImplementedException();
    }
}
