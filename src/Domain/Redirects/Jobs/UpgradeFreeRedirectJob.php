<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Jobs;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Apps\OneOffScripts\FreeRedirectActions\NovaUpgradeFreeRedirectAction;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Results\GetRedirectResult;
use Waterfront\Domain\Provision\Redirects\Results\ListRedirectResult;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class UpgradeFreeRedirectJob extends AbstractQueueableJob
{
    public int $tries = 4;

    public function __construct(
        private readonly Subscription $subscription,
    ) {
        parent::__construct();
    }

    public function handle(
        CancellationService $cancellationService,
        SubscriptionChangeService $subscriptionChangeService,
        ProvisionGateway $provisionGateway,
        ProductRepository $productRepository,
        DomainDeploymentRepository $domainDeploymentRepository,
        LoggerInterface $logger,
    ): void {
        if ($this->isValidRedirectSubscription($logger, $domainDeploymentRepository) === false) {
            return;
        }

        $redirects = [];

        if ($this->subscription->domain !== null) {
            $redirectResult = $provisionGateway->request(
                new ListRedirectsRequest(context: Uuid::fromString($this->subscription->uuid)),
            );
            assert($redirectResult instanceof ListRedirectResult);

            $redirects = $redirectResult->redirects;
            assert($redirects !== null);

            $metaData = $this->getMetaData($redirects);

            $logger->debug(
                sprintf('Found %s redirects for hosting deployment {provisioning.id}', count($redirects)),
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::PRODUCT_SLUG => $this->subscription->product->slug,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                    LoggingContextKeys::PROVISIONING_ID => $this->subscription->hostingDeployment?->id,
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaUpgradeFreeRedirectAction::SLUG,
                    LoggingContextKeys::META => [
                        'redirects' => $metaData,
                    ],
                ],
            );
        }

        if ($this->subscription->domain === null || count($redirects) === 0) {
            $logger->debug(
                'Found no redirects for hosting deployment {provisioning.id}, so cancelling this subscription.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::PRODUCT_SLUG => $this->subscription->product->slug,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                    LoggingContextKeys::PROVISIONING_ID => $this->subscription->hostingDeployment?->id,
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaUpgradeFreeRedirectAction::SLUG,
                ],
            );

            $cancellationService->cancel(
                subscription: $this->subscription,
                cancelType: SubscriptionCancelType::CANCEL_END_DATE,
                cancelReason: SubscriptionCancelReason::REASON_OTHER,
                sendMail: false,
                cancelNote: 'Customer does not use this subscription.',
            );

            return;
        }

        $logger->debug(
            'Found redirects for hosting deployment {provisioning.id}, so upgrading this subscription.',
            [
                LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                LoggingContextKeys::PRODUCT_SLUG => $this->subscription->product->slug,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                LoggingContextKeys::PROVISIONING_ID => $this->subscription->hostingDeployment?->id,
                LoggingContextKeys::ONE_OFF_SCRIPT => NovaUpgradeFreeRedirectAction::SLUG,
            ],
        );

        $redirectProduct = $productRepository->findProductBySlug(ProductType::REDIRECT->value);
        $subscriptionChangeService->createUpgradeMutation($this->subscription, $redirectProduct, null);
        $subscriptionChangeService->change(
            changeType: ProductChangeType::UPGRADE,
            subscription: $this->subscription,
            newProduct: $redirectProduct,
            invoiceTheChange: false,
            sendMail: false,
        );
    }

    /**
     * @param GetRedirectResult[] $redirects
     *
     * @return array<int, array{source: string, destination: string, type: string}>
     */
    public function getMetaData(array $redirects): array
    {
        $metaData = [];
        foreach ($redirects as $redirectResult) {
            if ($redirectResult->failed) {
                continue;
            }

            $metaData[] = [
                // @phpstan-ignore property.nonObject (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
                'source' => $redirectResult->redirect->source,
                // @phpstan-ignore property.nonObject (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
                'destination' => $redirectResult->redirect->destination,
                // @phpstan-ignore property.nonObject (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
                'type' => $redirectResult->redirect->redirectType->value,
            ];
        }

        return $metaData;
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DEFAULT;
    }

    private function isValidRedirectSubscription(
        LoggerInterface $logger,
        DomainDeploymentRepository $domainDeploymentRepository,
    ): bool {
        if ($this->subscription->product->slug !== ProductType::FREE_REDIRECT->value) {
            $logger->warning(
                'Tried to upgrade a subscription which was not a free redirect subscription with the CancelFreeRedirectJob',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::PRODUCT_SLUG => $this->subscription->product->slug,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaUpgradeFreeRedirectAction::SLUG,
                ],
            );
            $this->delete();

            return false;
        }

        if ($this->subscription->domain !== null) {
            $domainDeployment = $domainDeploymentRepository->getActiveDeploymentByDomain($this->subscription->domain);
            if ($domainDeployment?->provider->slug === ProviderSlug::PLACEHOLDER) {
                $logger->warning(
                    'Skipping the upgrade of a free redirect because it is related to an ongoing domain migration.',
                    [
                        LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                        LoggingContextKeys::PRODUCT_SLUG => $this->subscription->product->slug,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                        LoggingContextKeys::ONE_OFF_SCRIPT => NovaUpgradeFreeRedirectAction::SLUG,
                    ],
                );
                $this->delete();

                return false;
            }
        }

        return true;
    }
}
