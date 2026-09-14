<?php

declare(strict_types=1);

namespace Waterfront\Domain\Admin\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\MarkDebtorAsAbuse;
use Waterfront\Apps\API\Compass\Exceptions\AnonymizeCustomerException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Orders\Services\OrderService;
use Waterfront\Domain\Subscriptions\Jobs\CancelSubscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Queue\HarborQueue;
use Waterfront\Support\Enums\LoggingContextKeys;

class MarkCustomerAsAbuseAction
{
    public function __construct(
        private readonly Dispatcher $jobDispatcher,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly AnonymizeIdentitiesForCustomerAction $anonymizeIdentitiesForCustomerAction,
        private readonly OrderService $orderService,
        private readonly HarborQueue $harborQueue,
        private readonly LoggerInterface $logger,
        private readonly StoreNoteAction $storeNoteAction,
    ) {
    }

    public function execute(Customer $customer): void
    {
        $this->logger->info(
            'Marking customer as abuse.',
            [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
            ],
        );

        $subscriptions = $this->subscriptionRepository->getAllActiveParentSubscriptions($customer);

        foreach ($subscriptions as $subscription) {
            $this->jobDispatcher->dispatch(new CancelSubscription($subscription));
        }

        $this->orderService->markNonProcessedAsAbuseForCustomer($customer);

        $this->harborQueue->publish(
            new MarkDebtorAsAbuse($customer->customer_number),
        );

        try {
            $this->anonymizeIdentitiesForCustomerAction->execute($customer->customer_number);
        } catch (ResourceNotFoundException|AnonymizeCustomerException $e) {
            $this->logger->warning(
                'failed to execute anonymizeCustomerAction for abuse customer',
                [
                    LoggingContextKeys::EXCEPTION => $e,
                    LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
                ],
            );
        }

        if (! $customer->is_abuse) {
            $customer->is_abuse = true;
            $customer->save();
        }

        $this->storeNoteAction->execute('Customer has been marked as abuse.', $customer);
    }
}
