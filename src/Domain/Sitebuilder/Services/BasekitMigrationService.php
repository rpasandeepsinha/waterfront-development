<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder\Services;

use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitContextRepository;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitSitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\SitebuilderDeploymentRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Sitebuilder\Enums\BasekitDeletionOutcome;
use Waterfront\Domain\Sitebuilder\Enums\BasekitMigrationEligibility;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class BasekitMigrationService
{
    public function __construct(
        private readonly ProvisionTraceabilityService $traceability,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly HostingDeploymentRepository $hostingDeploymentRepository,
        private readonly SitebuilderDeploymentRepository $sitebuilderDeploymentRepository,
        private readonly BasekitSitebuilderDeploymentRepository $basekitDeploymentRepository,
        private readonly BasekitContextRepository $basekitContextRepository,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function assessEligibility(Subscription $subscription): BasekitMigrationEligibility
    {
        if (! $subscription->product->isSitebuilderProduct()) {
            return BasekitMigrationEligibility::NOT_SITEBUILDER;
        }

        $existing = $subscription->provisionSitebuilderDeployment;
        if ($existing !== null) {
            return BasekitMigrationEligibility::ALREADY_MIGRATED;
        }

        $packageReference = $this->productSpecRepository->getStringValueOfSpecification($subscription->product, ProductSpecName::BASEKIT_PACKAGE_REFERENCE);
        if ($packageReference === null) {
            return BasekitMigrationEligibility::NO_PACKAGE_REFERENCE;
        }

        $customer = $subscription->customer;
        $firstName = trim($customer->first_name ?? '');
        $lastName = trim($customer->last_name ?? '');
        $email = trim($customer->email ?? '');
        $domain = trim($subscription->domain ?? '');

        if ($domain === '' || $email === '' || $firstName === '' || $lastName === '') {
            return BasekitMigrationEligibility::MISSING_DATA;
        }

        return BasekitMigrationEligibility::ELIGIBLE;
    }

    public function assessDeletion(Subscription $subscription): BasekitDeletionOutcome
    {
        $deployment = $subscription->hostingDeployment;

        if ($deployment === null) {
            return BasekitDeletionOutcome::SKIPPED_NO_DEPLOYMENT;
        }

        if ($deployment->deleted_at !== null) {
            return BasekitDeletionOutcome::SKIPPED_ALREADY_DELETED;
        }

        return BasekitDeletionOutcome::TO_DELETE;
    }

    public function migrate(string $scriptSlug, string $subscriptionUuid): void
    {
        $subscription = $this->subscriptionRepository->getByUuid($subscriptionUuid);
        if ($subscription === null) {
            $this->logger->warning(sprintf('subscription not found for basekit migration: %s', $subscriptionUuid), [
                LoggingContextKeys::ONE_OFF_SCRIPT => $scriptSlug,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
            ]);
            return;
        }

        $existing = $subscription->provisionSitebuilderDeployment;
        if ($existing !== null) {
            $this->logger->info('Skip Basekit migration: already migrated', [
                LoggingContextKeys::ONE_OFF_SCRIPT => $scriptSlug,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
            ]);
            return;
        }

        $packageReference = $this->productSpecRepository->getStringValueOfSpecification($subscription->product, ProductSpecName::BASEKIT_PACKAGE_REFERENCE);

        if ($packageReference === null) {
            $this->logger->warning('No BASEKIT_PACKAGE_REFERENCE; skipping', [
                LoggingContextKeys::ONE_OFF_SCRIPT => $scriptSlug,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
            ]);
            return;
        }

        $customer = $subscription->customer;
        $firstName = trim($customer->first_name ?? '');
        $lastName = trim($customer->last_name ?? '');
        $email = trim($customer->email ?? '');
        $domain = trim($subscription->domain ?? '');

        if ($domain === '' || $email === '' || $firstName === '' || $lastName === '') {
            $this->logger->warning('Missing data (domain/first/last/email); skipping', [
                LoggingContextKeys::ONE_OFF_SCRIPT => $scriptSlug,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
                LoggingContextKeys::META => [
                    'domain' => $domain,
                    'email' => $email,
                    'firstname' => $firstName,
                    'lastname' => $lastName,
                ],
            ]);
            return;
        }

        $hostingDeployment = $subscription->hostingDeployment;
        if ($hostingDeployment === null) {
            $this->logger->error(sprintf('Basekit migration aborted: HostingDeployment could not be found for subscription: %s', $subscriptionUuid), [
                LoggingContextKeys::ONE_OFF_SCRIPT => $scriptSlug,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
            ]);
            return;
        }

        $siteReference = $hostingDeployment->basekit_site_ref;
        $userReference = $hostingDeployment->basekit_user_ref;

        if ($siteReference === null || $userReference === null) {
            $this->logger->error('Basekit migration aborted: missing Basekit references on HostingDeployment', [
                LoggingContextKeys::ONE_OFF_SCRIPT => $scriptSlug,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
                LoggingContextKeys::META => [
                    'hosting_deployment_uuid' => $hostingDeployment->uuid,
                    'site_ref' => $siteReference === null,
                    'user_ref' => $userReference === null,
                    'domain' => $domain,
                ],
            ]);
            return;
        }

        $request = new CreateSitebuilderRequest(
            domain: $domain,
            packages: [(int) $packageReference],
            firstname: $firstName,
            lastname: $lastName,
            email: $email,
            contractPeriod: $subscription->contract_period,
            context: Uuid::fromString($subscription->uuid),
        );

        $request->tag = Uuid::fromString($subscription->uuid);

        DB::transaction(function () use ($request, $siteReference, $userReference): void {
            $requestId = $this->traceability->storeRequest($request, ProvisionProvider::BASEKIT);
            $request->requestId = $requestId;

            $sitebuilderDeployment = $this->sitebuilderDeploymentRepository->create(
                requestId: $requestId,
                domain: $request->domain
            );
            $this->basekitDeploymentRepository->create($sitebuilderDeployment, $siteReference);

            if ($this->basekitContextRepository->findByContext($request->context) === null) {
                $this->basekitContextRepository->create($request->context, $userReference);
            }
        });

        $this->logger->info('Basekit migration completed', [
            LoggingContextKeys::ONE_OFF_SCRIPT => $scriptSlug,
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::META => ['subscription_id' => $subscription->id],
        ]);
    }

    public function deleteSitebuilderHostingDeployment(string $scriptSlug, string $subscriptionUuid): void
    {
        $hostingDeployment = $this->hostingDeploymentRepository->findByUuid($subscriptionUuid);

        if ($hostingDeployment === null) {
            $this->logger->warning('hosting deployment not found for deletion', [
                LoggingContextKeys::ONE_OFF_SCRIPT => $scriptSlug,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
            ]);
            return;
        }

        if ($hostingDeployment->trashed()) {
            $this->logger->info('Skip soft delete: already deleted', [
                LoggingContextKeys::ONE_OFF_SCRIPT => $scriptSlug,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
            ]);
            return;
        }

        $hostingDeployment->delete();

        $this->logger->info('Soft-deleted Basekit HostingDeployment', [
            LoggingContextKeys::ONE_OFF_SCRIPT => $scriptSlug,
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
        ]);
    }
}
