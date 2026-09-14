<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Subscriptions;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\DTO\CreateSubscriptionDTO;
use Waterfront\Domain\Customers\DTO\CreateSubscriptionsDTO;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Ferry\Dto\Parameter;
use Waterfront\Domain\Ferry\Dto\ResponseDto;
use Waterfront\Domain\Ferry\Dto\SuccessDto;
use Waterfront\Domain\Ferry\Repositories\MigratableSubscriptionRepository;
use Waterfront\Domain\Hosting\Factories\PlaceHolderDeploymentFactory;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\LabelService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Support\Enums\LoggingContextKeys;

class StoreSubscriptionAction
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionService $subscriptionService,
        private readonly LabelService $labelService,
        private readonly MigratableSubscriptionRepository $migratableSubscriptionRepository,
        private readonly PlaceHolderDeploymentFactory $technicalPlaceHolderSubscriptionFactory,
        private readonly LoggerInterface $logger,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly SitebuilderService $sitebuilderService,
    ) {
    }

    /**
     * @return Subscription[]
     */
    public function execute(CreateSubscriptionsDTO $createSubscriptions, ResponseDto $responseDto): array
    {
        $this->checkIfAllRequiredPlaceholderProvidersExist($createSubscriptions);

        $batch = $createSubscriptions->getSubscriptions();
        $customer = $createSubscriptions->getCustomer();

        if (! $responseDto->hasFailures()) {
            $this->touchMigrationCustomerMigratedAtTimestamp(
                createSubscriptions: $createSubscriptions,
            );
        }

        $migratedCustomer = $customer->migratedCustomers->firstOrFail();
        $subscriptions = [];

        foreach ($batch as $externalSubscription) {
            $alreadyCreated = $this->isSubscriptionAlreadyCreated(
                externalSubscription: $externalSubscription,
                customer: $customer,
                migratedCustomer: $migratedCustomer,
                responseDto: $responseDto,
            );

            if ($alreadyCreated) {
                continue;
            }

            $this->logger->debug('Creating subscription for migration', [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::META => [
                    'subscription_data' => $externalSubscription->toArray(),
                ],
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->id,
            ]);

            $subscription = $this->createSubscriptions(
                customer: $customer,
                externalSubscription: $externalSubscription,
                responseDto: $responseDto,
            );

            $this->createMigrationSubscriptionAdministration(
                externalSubscription: $externalSubscription,
                subscription: $subscription,
                customer: $customer,
            );

            $this->addSuccessMessage(
                responseDto: $responseDto,
                subscription: $subscription,
                createSubscriptionDTO: $externalSubscription,
            );

            $subscriptions[] = $subscription;
        }

        return $subscriptions;
    }

    /**
     * Check to prevent (duplicate) subscriptions without migration data + deployments from being created.
     *
     * @see https://yh-jira.atlassian.net/browse/SWD-8704
     */
    private function checkIfAllRequiredPlaceholderProvidersExist(CreateSubscriptionsDTO $createSubscriptions): void
    {
        if ($createSubscriptions->hasDomainSubscriptions()) {
            $this->technicalPlaceHolderSubscriptionFactory->checkIfDomainPlaceholderProvidersExist();
        }

        if ($createSubscriptions->hasHostingSubscriptions()) {
            $this->technicalPlaceHolderSubscriptionFactory->checkIfHostingPlaceholderProvidersExist();
        }

        if ($createSubscriptions->hasMailOnlySubscriptions()) {
            $this->technicalPlaceHolderSubscriptionFactory->checkIfMailOnlyPlaceholderProvidersExist();
        }

        if ($createSubscriptions->hasSitebuilderSubscriptions()) {
            /**
             * Use feature flag to determine if we need to check for both the
             * sitebuilder provider and mail only provider or just the mail
             * only provider. We use the customers email for this check.
             */
            $email = $createSubscriptions->getCustomer()->email;
            if ($this->sitebuilderService->hasSitebuilderThroughGateway($email)) {
                $this->technicalPlaceHolderSubscriptionFactory->checkIfMailOnlyPlaceholderProvidersExist();
            } else {
                $this->technicalPlaceHolderSubscriptionFactory->checkIfSitebuilderPlaceholderProvidersExist();
            }
        }

        if ($createSubscriptions->hasSslSubscriptions()) {
            $this->technicalPlaceHolderSubscriptionFactory->checkIfSslPlaceholderProvidersExist();
        }
    }

    private function touchMigrationCustomerMigratedAtTimestamp(
        CreateSubscriptionsDTO $createSubscriptions,
    ): void {
        $createSubscriptions
            ->getCustomer()
            ->migratedCustomers()
            ->where([
                'reference_customer_number' => $createSubscriptions->getReferenceCustomerId(),
            ])
            ->firstOrFail()
            ->update(['migrated_at' => CarbonImmutable::now()]);
    }

    private function isSubscriptionAlreadyCreated(
        CreateSubscriptionDTO $externalSubscription,
        Customer $customer,
        MigratedCustomer $migratedCustomer,
        ResponseDto $responseDto,
    ): bool {
        $alreadyExists = $this->migratableSubscriptionRepository->isSubscriptionAlreadyCreated(
            subscriptionReferenceId: $externalSubscription->referenceSubscriptionId,
            productReferenceId: $externalSubscription->referenceProductId,
            bu: $migratedCustomer->reference_name,
        );

        if ($alreadyExists) {
            $this->logger->debug('Migrated Subscription already created!', [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::META => [
                    'subscription_data' => $externalSubscription->toArray(),
                ],
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->id,
            ]);

            $extensionSubscription = $this->migratableSubscriptionRepository->getAlreadyExistingSubscriptionFromMigration(
                subscriptionReferenceId: $externalSubscription->referenceSubscriptionId,
                productReferenceId: $externalSubscription->referenceProductId,
                productSlug: $externalSubscription->slug,
            );

            if ($extensionSubscription->product->productGroup->slug === ProductGroupType::EXTENSION) {
                $this->addFreeDnsSuccessMessage($responseDto, $extensionSubscription, $externalSubscription);
            }

            $this->addSuccessMessage($responseDto, $extensionSubscription, $externalSubscription);
        }

        return $alreadyExists;
    }

    private function createSubscriptions(
        Customer $customer,
        CreateSubscriptionDTO $externalSubscription,
        ResponseDto $responseDto,
    ): Subscription {
        $subscription = $this->subscriptionRepository->createFromMigration($customer, $externalSubscription);

        $this->attachSubscriptionLabels($subscription, $externalSubscription);

        $this->technicalPlaceHolderSubscriptionFactory->create(
            $subscription,
            $externalSubscription->implementableProduct,
        );

        if ($subscription->product->productGroup->slug === ProductGroupType::EXTENSION) {
            $this->createFreeDnsSubscription($subscription, $responseDto, $externalSubscription);
        }

        return $subscription;
    }

    private function createMigrationSubscriptionAdministration(
        CreateSubscriptionDTO $externalSubscription,
        Subscription $subscription,
        Customer $customer,
    ): void {
        $migratedSubscription = $subscription
            ->migratedSubscriptions()
            ->create([
                'reference_product_id' => $externalSubscription->referenceProductId,
                'reference_subscription_id' => $externalSubscription->referenceSubscriptionId,
            ]);

        /** @var MigratedCustomer $migratedCustomer */
        $migratedCustomer = $customer
            ->migratedCustomers()
            ->where([
                'reference_customer_number' => $externalSubscription->createSubscriptions->getReferenceCustomerId(),
            ])
            ->firstOrFail();

        $migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);
    }

    private function createFreeDnsSubscription(
        Subscription $extensionSubscription,
        ResponseDto $responseDto,
        CreateSubscriptionDTO $externalSubscription,
    ): void {
        $freeDnsSubscription =
            $this->subscriptionService->createFreeDnsSubscriptionForExtension($extensionSubscription);

        if ($freeDnsSubscription instanceof Subscription) {
            $this->addSuccessMessage($responseDto, $freeDnsSubscription, $externalSubscription);
            $this->dnsDeploymentRepository->create(
                $freeDnsSubscription->uuid,
                nameserverType: NameserverType::EXTERNAL,
            );
        } else {
            $this->addFreeDnsSuccessMessage($responseDto, $extensionSubscription, $externalSubscription);
        }
    }

    private function attachSubscriptionLabels(
        Subscription $subscription,
        CreateSubscriptionDTO $externalSubscription,
    ): void {
        $labels = $externalSubscription->labels;

        if ($labels === null) {
            return;
        }

        $this->labelService->attachLabelsToSubscription(
            labelValues: $labels,
            subscription: $subscription,
        );
    }

    private function addSuccessMessage(
        ResponseDto $responseDto,
        Subscription $subscription,
        CreateSubscriptionDTO $createSubscriptionDTO,
    ): void {
        $responseDto->addSuccess(
            SuccessDto::create(
                'Migrated successfully',
                [],
                [
                    Parameter::create('subscription_id', $subscription->id),
                    Parameter::create('product_group_slug', $subscription->product->productGroup->slug->value),
                    Parameter::create('product_slug', $subscription->product->slug),
                    Parameter::create('reference_subscription_id', $createSubscriptionDTO->referenceSubscriptionId),
                ],
            ),
        );
    }

    private function addFreeDnsSuccessMessage(
        ResponseDto $responseDto,
        Subscription $extensionSubscription,
        CreateSubscriptionDTO $externalSubscription,
    ): void {
        $domain = $extensionSubscription->domain;
        assert(is_string($domain));
        $freeDnsSubscription = $this->migratableSubscriptionRepository->getAlreadyExistingFreeDnsSubscription($domain);

        $this->addSuccessMessage($responseDto, $freeDnsSubscription, $externalSubscription);
    }
}
