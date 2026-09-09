<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Services;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBaseKitDetails;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Ferry\Exceptions\HostingInstanceNotFoundException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitSiteByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitUserByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\RollbackBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitSiteResult;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitUserResult;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitUser;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderSiteInterface;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderUserInterface;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class SitebuilderProxy
{
    public function __construct(
        private readonly SitebuilderService $sitebuilderService,
        private readonly LoggerInterface $logger,
        private readonly ProviderRepository $providerRepository,
        private readonly SitebuilderDeploymentRepository $sitebuilderDeploymentRepository,
        private readonly ProvisionGateway $provisionGateway,
    ) {
    }

    /**
     * @throws HostingDetailsNotSupportedException
     */
    public function createBasekitDeploymentFromMigration(
        HostingDeployment $hostingDeployment,
        SitebuilderBaseKitDetails $baseKitDetails,
        Server $server,
        Provider $hostingProvider,
    ): void {
        $hostingDeployment->loadMissing('subscription.customer');
        $subscription = $hostingDeployment->subscription;
        $customer = $subscription->customer;

        if ($this->sitebuilderService->hasSitebuilderThroughGateway($customer->email)) {
            $subscription = $hostingDeployment->subscription;
            $context = Uuid::fromString($subscription->uuid);

            $basekitSiteByRefRequest = new GetBasekitSiteByRefRequest(
                context: $context,
                siteRef: $baseKitDetails->basekitSiteRef
            );

            $basekitSiteByRefRequest->provider = ProvisionProvider::BASEKIT;
            $basekitSiteByRefRequest->tag = $context;

            $basekitSiteByRefResult = $this->provisionGateway->request($basekitSiteByRefRequest);

            if (
                ! $basekitSiteByRefResult instanceof BasekitSiteResult
                || $basekitSiteByRefResult->failed
            ) {
                $this->logger->warning('Provisioning Basekit sitebuilder deployment from migration failed, could not fetch basekit domain', [
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $basekitSiteByRefRequest->requestId,
                ]);

                throw new HostingDetailsNotSupportedException(
                    hostingDetails: $baseKitDetails,
                    previous: $basekitSiteByRefResult->exception
                );
            }

            $createBasekitDeploymentsRequest = new CreateBasekitDeploymentsFromMigrationRequest(
                // @phpstan-ignore argument.type (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
                domain: $basekitSiteByRefResult->domain,
                userRef: $baseKitDetails->basekitUserRef,
                siteRef: $baseKitDetails->basekitSiteRef,
                context: $context
            );

            $createBasekitDeploymentsRequest->provider = ProvisionProvider::BASEKIT;
            $createBasekitDeploymentsRequest->tag = $context;

            $createBasekitDeploymentsResult = $this->provisionGateway->request($createBasekitDeploymentsRequest);

            if ($createBasekitDeploymentsResult->failed) {
                throw new HostingDetailsNotSupportedException(
                    hostingDetails: $baseKitDetails,
                    previous: $createBasekitDeploymentsResult->exception
                );
            }

            $subscription->domain = $basekitSiteByRefResult->domain;
            $subscription->save();

            return;
        }

        $hostingDeployment->basekit_site_ref = $baseKitDetails->basekitSiteRef;
        $hostingDeployment->basekit_user_ref = $baseKitDetails->basekitUserRef;
        $hostingDeployment->basekitServer()->associate($server);
        $hostingDeployment->sitebuilderProvider()->associate($hostingProvider);
    }

    /**
     * @throws HostingDetailsNotSupportedException
     */
    public function rollbackSitebuilderDeployementFromMigration(Subscription $subscription, SitebuilderBaseKitDetails $sitebuilderBaseKitDetails, HostingDeployment $hostingDeployment): void
    {
        $customer = $subscription->customer;
        if ($this->sitebuilderService->hasSitebuilderThroughGateway($customer->email)) {
            $deploymentsForTag = $this->sitebuilderDeploymentRepository->countCreateRequestsByTag(
                Uuid::fromString($subscription->uuid)
            );

            if ($deploymentsForTag === 0) {
                $this->logger->info('No Basekit sitebuilder deployment linked to tag, skipping gateway rollback.', [
                    LoggingContextKeys::PROVISIONING_CONTEXT => $subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                ]);

                return;
            }

            $rollbackBasekitDeploymentsRequest = new RollbackBasekitDeploymentsFromMigrationRequest(
                context: Uuid::fromString($subscription->uuid),
                tagUuid: Uuid::fromString($subscription->uuid)
            );

            $rollbackBasekitDeploymentsRequest->provider = ProvisionProvider::BASEKIT;
            $rollbackBasekitDeploymentsResult = $this->provisionGateway->request($rollbackBasekitDeploymentsRequest);

            if ($rollbackBasekitDeploymentsResult->failed) {
                throw new HostingDetailsNotSupportedException(
                    hostingDetails: $sitebuilderBaseKitDetails,
                    previous: $rollbackBasekitDeploymentsResult->exception
                );
            }
            return;
        }

        $hostingDeployment->basekit_site_ref = null;
        $hostingDeployment->basekit_user_ref = null;
        $hostingDeployment->basekitServer()->disassociate();

        // Reset sitebuilder provider to Placeholder
        $placeholderProvider = $this->providerRepository->getByType(ProviderType::SITEBUILDER, ProviderSlug::PLACEHOLDER);
        $hostingDeployment->sitebuilderProvider()->associate($placeholderProvider);
    }

    /**
     * @throws GuzzleException
     */
    public function assertSitebuilderSiteExists(?string $customerEmail, int $siteRef, Server $server): bool
    {
        if (! $this->sitebuilderService->hasSitebuilderThroughGateway($customerEmail)) {
            $this->sitebuilderService->getSiteFromRef(
                siteRef: $siteRef,
                server: $server,
            );

            return true;
        }

        /**
         * The context of a sitebuilder (basekit) is currently set to a subscription UUID.
         * We do not have any subscription context here, so we generate a random UUID.
         * This is fine because we only need to check for existence of the siteRef.
         */
        $result = $this->fetchBasekitSiteThroughGateway(Uuid::uuid4(), $siteRef);

        return $result instanceof BasekitSiteResult
            && $result->succeeded;
    }

    /**
     * @throws HostingInstanceNotFoundException
     */
    public function fetchSitebuilderSite(int $siteRef, Subscription $subscription, HostingMigrationPayload $payload, Server $server): BasekitSiteResult|SitebuilderSiteInterface
    {
        if ($this->sitebuilderService->hasSitebuilderThroughGateway($subscription->customer->email)) {
            $context = Uuid::fromString($subscription->uuid);

            $result = $this->fetchBasekitSiteThroughGateway($context, $siteRef);

            if (! $result instanceof BasekitSiteResult) {
                throw new HostingInstanceNotFoundException($payload, $server, $subscription);
            }

            if ($result->failed) {
                throw new HostingInstanceNotFoundException($payload, $server, $subscription, $result->exception);
            }

            return $result;
        }

        try {
            $sitebuilderSite = $this->sitebuilderService->getSiteFromRef($siteRef, $server, $payload->driver);
        } catch (Throwable $exception) {
            throw new HostingInstanceNotFoundException($payload, $server, $subscription, $exception);
        }

        return $sitebuilderSite;
    }

    /**
     * @throws HostingInstanceNotFoundException
     */
    public function getSitebuilderUser(
        int $userRef,
        Subscription $subscription,
        HostingMigrationPayload $payload,
        Server $server
    ): SitebuilderUserInterface {
        if ($this->sitebuilderService->hasSitebuilderThroughGateway($subscription->customer->email)) {
            $context = Uuid::fromString($subscription->uuid);
            $getBasekitUserByRefRequest = new GetBasekitUserByRefRequest(
                context: $context,
                userRef: $userRef,
            );

            $getBasekitUserByRefRequest->provider = ProvisionProvider::from($payload->driver);
            $getBasekitUserByRefRequest->tag = $context;

            $basekitUserByRefResult = $this->provisionGateway->request($getBasekitUserByRefRequest);

            if (! $basekitUserByRefResult instanceof BasekitUserResult || $basekitUserByRefResult->provisionStatus !== ProvisionStatus::SUCCESS) {
                throw new HostingInstanceNotFoundException($payload, $server, $subscription, $basekitUserByRefResult->exception);
            }

            Assert::notNull($basekitUserByRefResult->userId);
            Assert::notNull($basekitUserByRefResult->email);

            return new BaseKitUser(
                id: $basekitUserByRefResult->userId,
                email: $basekitUserByRefResult->email
            );
        }

        return $this->sitebuilderService->getUserFromRef($userRef, $server, $payload->driver);
    }

    private function fetchBasekitSiteThroughGateway(UuidInterface $context, int $siteRef): ?BasekitSiteResult
    {
        $request = new GetBasekitSiteByRefRequest(
            context: $context,
            siteRef: $siteRef,
        );

        $request->provider = ProvisionProvider::BASEKIT;
        $request->tag = $context;

        $result = $this->provisionGateway->request($request);

        return $result instanceof BasekitSiteResult ? $result : null;
    }
}
