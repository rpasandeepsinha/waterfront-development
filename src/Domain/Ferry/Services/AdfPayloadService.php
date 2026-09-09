<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services;

use UnexpectedValueException;
use Waterfront\Domain\Customers\DTO\CreateSubscriptionsDTO;
use Waterfront\Domain\Customers\DTO\CustomerDTO;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableBulkCustomerState;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableBulkSubscriptionState;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableDefaultState;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableHostingState;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableMailOnlyHostingState;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableNameserverState;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableResellerHostingState;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableSitebuilderHostingState;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableTechnicalMigrationBulkCustomerState;
use Waterfront\Domain\Ferry\Dto\ADF\MigrationTypeADFPayload;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Repositories\MigratableSubscriptionRepository;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Hosting\Services\HostingDeploymentService;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class AdfPayloadService
{
    public function __construct(
        protected readonly MigratableSubscriptionRepository $migratableSubscriptionRepository,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly HostingDeploymentRepository $hostingDeploymentRepository,
        private readonly HostingDeploymentService $hostingDeploymentService,
    ) {
    }

    public function fetchMigrationADFPayload(Subscription $subscription, MigrationStep $migrationStep): MigrationTypeADFPayload
    {
        return match ($migrationStep) {
            MigrationStep::NAMESERVER, MigrationStep::ENABLE_DNSSEC => $this->retrieveBySubscriptionForMigration($subscription, $migrationStep),
            MigrationStep::HOSTING_MIGRATION => $this->retrieveHostingBySubscriptionForMigration($subscription, $migrationStep),
            MigrationStep::MAIL_ONLY_MIGRATION => $this->retrieveMailOnlyBySubscriptionForMigration($subscription, $migrationStep),
            MigrationStep::RESELLER_HOSTING_MIGRATION => $this->retrieveResellerHostingBySubscriptionForMigration($subscription, $migrationStep),
            MigrationStep::SITEBUILDER_MIGRATION => $this->retrieveSitebuilderBySubscriptionForMigration($subscription, $migrationStep),
            MigrationStep::CONFIGURE_DNS,
            MigrationStep::DOMAIN_MIGRATION,
            MigrationStep::SSL_MIGRATION,
            MigrationStep::BACKUP_MIGRATION,
            MigrationStep::REDIRECT_MIGRATION => $this->defaultPayload($subscription, $migrationStep),
            default => throw new UnexpectedValueException('Unexpected migration step: ' . $migrationStep->value),
        };
    }

    public function fetchMigrationBulkCustomerPayload(CustomerDTO $customerDTO, int|null $waterfrontCustomerId, MigrationStep $migrationStep): MigrationTypeADFPayload
    {
        return new MigratableBulkCustomerState(
            migrationStep: $migrationStep,
            referenceName: $customerDTO->buCustomerNumber,
            waterfrontCustomerId: $waterfrontCustomerId,
        );
    }

    /**
     * @param array<int<0, max>, array{waterfront_subscription_id: int, reference_subscription_id: string|null}> $subscriptions
     */
    public function fetchMigrationBulkSubscriptionPayload(CreateSubscriptionsDTO $createSubscriptionsDTO, array $subscriptions, MigrationStep $migrationStep): MigrationTypeADFPayload
    {
        return new MigratableBulkSubscriptionState(
            migrationStep: $migrationStep,
            referenceName: $createSubscriptionsDTO->getReferenceCustomerId(),
            waterfrontCustomerId: $createSubscriptionsDTO->getCustomer()->id,
            subscriptions: $subscriptions,
        );
    }

    public function fetchTechnicalMigrationBulkCustomerStatePayload(Customer $customer, MigratedCustomer $migratedCustomer, MigrationStep $migrationStep): MigrationTypeADFPayload
    {
        return new MigratableTechnicalMigrationBulkCustomerState(
            migrationStep: $migrationStep,
            referenceName: $migratedCustomer->reference_name,
            waterfrontCustomerId: $customer->id,
            waterfrontCustomerNumber: $customer->customer_number,
            referenceCustomerNumber: $migratedCustomer->reference_customer_number
        );
    }

    private function defaultPayload(Subscription $subscription, MigrationStep $migrationStep): MigratableDefaultState
    {
        return new MigratableDefaultState(
            migrationStep: $migrationStep,
            referenceName: $this->migratableSubscriptionRepository->getBuOriginNameFromSubscription($subscription),
            domain: $subscription->domain,
            migrationSubscriptionReferenceId: $this->getReferenceId($subscription)
        );
    }

    private function retrieveBySubscriptionForMigration(Subscription $subscription, MigrationStep $migrationStep): MigratableNameserverState
    {
        $deployment = $subscription->domainDeployment;

        $nameservers = [];
        if ($deployment instanceof DomainDeployment) {
            $nameservers = $this->dnsDeploymentRepository->getNameserverHostnamesFromDomainDeployment($deployment);
        }
        /** @var array<int, string> $nameservers */
        return new MigratableNameserverState(
            migrationStep: $migrationStep,
            referenceName: $this->migratableSubscriptionRepository->getBuOriginNameFromSubscription($subscription),
            domain: $subscription->domain,
            migrationSubscriptionReferenceId: $this->getReferenceId($subscription),
            nameservers: $nameservers,
        );
    }

    private function retrieveHostingBySubscriptionForMigration(Subscription $subscription, MigrationStep $migrationStep): MigratableHostingState
    {
        /** @var HostingDeployment $hostingDeployment */
        $hostingDeployment = $subscription->hostingDeployment()->firstOrFail();

        $server = $this->hostingDeploymentRepository->getServer($hostingDeployment->refresh());
        $hostname = $server?->hostname;

        return new MigratableHostingState(
            migrationStep: $migrationStep,
            referenceName: $this->migratableSubscriptionRepository->getBuOriginNameFromSubscription($subscription),
            domain: $subscription->domain,
            migrationSubscriptionReferenceId: $this->getReferenceId($subscription),
            hostname: $hostname,
            username:  $this->hostingDeploymentService->getUsername($hostingDeployment) ?? '',
            driver: $hostingDeployment->provider?->slug->value ?? '',
        );
    }

    private function retrieveMailOnlyBySubscriptionForMigration(Subscription $subscription, MigrationStep $migrationStep): MigratableMailOnlyHostingState
    {
        /** @var HostingDeployment $hostingDeployment */
        $hostingDeployment = $subscription->hostingDeployment()->firstOrFail();

        return new MigratableMailOnlyHostingState(
            migrationStep: $migrationStep,
            referenceName: $this->migratableSubscriptionRepository->getBuOriginNameFromSubscription($subscription),
            domain: $subscription->domain,
            migrationSubscriptionReferenceId: $this->getReferenceId($subscription),
            hostname: $hostingDeployment->mailOnlyServer?->hostname,
            username: $this->getMailUserName($hostingDeployment),
            driver: $hostingDeployment->mailProvider?->slug->value ?? '',
        );
    }

    private function retrieveSitebuilderBySubscriptionForMigration(Subscription $subscription, MigrationStep $migrationStep): MigratableSitebuilderHostingState
    {
        /** @var HostingDeployment $hostingDeployment */
        $hostingDeployment = $subscription->hostingDeployment()->firstOrFail();

        return new MigratableSitebuilderHostingState(
            migrationStep: $migrationStep,
            referenceName: $this->migratableSubscriptionRepository->getBuOriginNameFromSubscription($subscription),
            domain: $subscription->domain,
            migrationSubscriptionReferenceId: $this->getReferenceId($subscription),
            sitebuilderHostname: $hostingDeployment->basekitServer?->hostname,
            mailOnlyHostname: $hostingDeployment->mailOnlyServer->hostname ?? $hostingDeployment->server?->hostname,
            mailOnlyUsername: $hostingDeployment->directadmin_customer_username ?? $hostingDeployment->plesk_customer_username,
            basekitUserRef: $hostingDeployment->basekit_user_ref,
            basekitSiteRef: $hostingDeployment->basekit_site_ref,
            driver: $hostingDeployment->sitebuilderProvider?->slug->value ?? '',
        );
    }

    private function retrieveResellerHostingBySubscriptionForMigration(Subscription $subscription, MigrationStep $migrationStep): MigratableResellerHostingState
    {
        /** @var ResellerHostingDeployment $resellerHostingDeployment */
        $resellerHostingDeployment = $subscription->resellerHostingDeployment()->firstOrFail();

        $providerSlug = $resellerHostingDeployment->provider->slug->value;
        $hostname = $resellerHostingDeployment->server?->hostname;

        return new MigratableResellerHostingState(
            migrationStep: $migrationStep,
            referenceName: $this->migratableSubscriptionRepository->getBuOriginNameFromSubscription($subscription),
            domain: $subscription->domain,
            migrationSubscriptionReferenceId: $this->getReferenceId($subscription),
            hostname: $hostname,
            username: $resellerHostingDeployment->relevant_username,
            driver: $providerSlug
        );
    }

    private function getReferenceId(Subscription $subscription): string
    {
        $migrationSubscription = $subscription->migratedSubscriptions->firstOrFail();
        /** @var MigratedSubscription $migrationSubscription */
        return $migrationSubscription->reference_subscription_id ?? '';
    }

    private function getMailUserName(HostingDeployment $hostingDeployment): string
    {
        if ($hostingDeployment->mailProvider instanceof Provider) {
            return $this->hostingDeploymentService->getMailUsername($hostingDeployment) ?? '';
        }

        if ($hostingDeployment->provider instanceof Provider) {
            return $this->hostingDeploymentService->getUsername($hostingDeployment) ?? '';
        }

        return '';
    }
}
