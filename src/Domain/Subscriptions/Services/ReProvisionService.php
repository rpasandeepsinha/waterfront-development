<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Backup\Jobs\UpgradeBackupJob;
use Waterfront\Domain\Hosting\Jobs\DowngradeHostingJob;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionChangeRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;

readonly class ReProvisionService
{
    public function __construct(
        private Dispatcher $dispatcher,
        private SubscriptionChangeRepository $subscriptionChangeRepository,
        private LoggerInterface $logger,
    ) {
    }

    // If you add a new handleMethod to this, make sure you also add it to this: handleMutationThatMightRequireTechnicalChangeAtActualRenewalDate()
    public function reProvisionSubscription(Subscription $subscription, SubscriptionMutation $mutation): void
    {
        $job = match ($subscription->product->productGroup->slug) {
            ProductGroupType::HOSTING => $this->handleHosting($subscription, $mutation),
            ProductGroupType::BACKUP => $this->handleBackup($subscription, $mutation),
            ProductGroupType::EXTENSION,
            ProductGroupType::REDIRECT,
            ProductGroupType::OTHER,
            ProductGroupType::SSL,
            ProductGroupType::DNS,
            ProductGroupType::RESELLER_HOSTING,
            ProductGroupType::VPS,
            ProductGroupType::MANUAL_SUBSCRIPTION,
            ProductGroupType::DOMAIN_EXPANSION,
            ProductGroupType::RESELLER_DISCOUNT,
            ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE,
            ProductGroupType::CLOUDSTACK_MANAGER_DOMAIN,
            ProductGroupType::CLOUDSTACK_VOLUME,
            ProductGroupType::CLOUDSTACK_OS,
            ProductGroupType::MICROSOFT_365,
            ProductGroupType::ONE_TIME_SERVICE,
            ProductGroupType::VOLUME_DISCOUNT,
            ProductGroupType::ADD_ON => throw new NotImplementedException(
                'Something requires to be reprovisioned but has no function to handle it'
            )
        };
        $this->dispatcher->dispatch($job);
    }

    private function handleHosting(Subscription $subscription, SubscriptionMutation $mutation): DowngradeHostingJob
    {
        $unCompletedRequests = $this->subscriptionChangeRepository->getOpenSubscriptionChangeRequest($subscription);

        if (count($unCompletedRequests) === 1) {
            $unCompletedRequest = $unCompletedRequests->firstOrFail();

            if ($unCompletedRequest->type === ProductChangeType::DOWNGRADE) {
                return new DowngradeHostingJob($subscription, $mutation, $unCompletedRequest);
            }
        }

        $this->logger->warning(
            'reProvisionSubscription service plan for subscription {subscription.uuid} failed',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::META => [
                    'mutation_id' => $mutation->id,
                ],
            ]
        );
        throw new Exception('Reprovisioning of a hosting subscription was required but it was something other than a downgrade or too many requests where found.');
    }

    private function handleBackup(Subscription $subscription, SubscriptionMutation $mutation): UpgradeBackupJob
    {
        $unCompletedRequests = $this->subscriptionChangeRepository->getOpenSubscriptionChangeRequest($subscription);

        try {
            $request = $unCompletedRequests->sole();
        } catch (ItemNotFoundException | MultipleItemsFoundException) {
            $this->logger->warning(
                'reProvisionSubscription service plan for subscription {subscription.uuid} failed',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::META => [
                        'mutation_id' => $mutation->id,
                        'open_requests_count' => $unCompletedRequests->count(),
                    ],
                ]
            );

            throw new Exception(
                'Reprovisioning of a backup subscription requires exactly one open change request.'
            );
        }

        if ($request->type !== ProductChangeType::UPGRADE) {
            throw new Exception(
                'Reprovisioning of a backup subscription was required but the open request was not an upgrade.'
            );
        }

        return new UpgradeBackupJob(
            subscription: $subscription,
            subscriptionMutation: $mutation,
            subscriptionChange: $request,
        );
    }
}
