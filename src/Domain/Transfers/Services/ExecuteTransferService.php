<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Exceptions\TransferException;
use Waterfront\Domain\Transfers\Interfaces\ExecuteTransferInterface;
use Waterfront\Domain\Transfers\Models\Transfer;

class ExecuteTransferService implements ExecuteTransferInterface
{
    public function __construct(
        private readonly ExecuteExtensionTransferService $extensionTransferService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Idempotent method that can be called multiple times on a transfer. Retrying failed transfers can be done by calling this method.
     *
     * @throws TransferException
     */
    public function execute(Transfer $transfer): Transfer
    {
        $transfer->refresh();
        $subscriptions = $this->filterExecutedSubscriptions($transfer);
        $this->validateSubscriptionsAreTransferable($transfer, $subscriptions);

        $transfer->start();

        $subscriptions->each(function (Subscription $subscription) use ($transfer): void {
            try {
                $this->technical($transfer, $subscription);
            } catch (TransferException $exception) {
                $completedException = new TransferException(
                    sprintf(
                        'An error occurred while transferring with Transfer ID {%s}: {%s} with REASON: {%s}',
                        $transfer->id,
                        $subscription->id,
                        $exception->getMessage()
                    )
                );

                $this->logger->error($completedException->getMessage());

                $subscription->pivot->failed_at = CarbonImmutable::now();
                $subscription->pivot->reason_failed = $completedException->getMessage();
                $subscription->pivot->save();
                return;
            }

            $this->administrative($transfer, $subscription);

            $subscription->pivot->executed_at = CarbonImmutable::now();
            $subscription->pivot->failed_at = null;
            $subscription->pivot->save();
        });
        $transfer->complete();

        return $transfer;
    }

    /**
     * @throws TransferException
     */
    private function technical(Transfer $transfer, Subscription $subscription): void
    {
        $subscription->loadMissing(['product', 'product.productGroup']);

        match ($subscription->product->productGroup->slug) {
            ProductGroupType::EXTENSION => $this->extensionTransferService->execute(subscription: $subscription, receiver: $transfer->toCustomer),
            default => 'Do nothing'
        };
    }

    /**
     * @throws TransferException
     */
    private function administrative(Transfer $transfer, Subscription $subscription): void
    {
        match ($subscription->product->productGroup->slug) {
            ProductGroupType::ADD_ON,
            ProductGroupType::BACKUP,
            ProductGroupType::CLOUDSTACK_MANAGER_DOMAIN,
            ProductGroupType::CLOUDSTACK_OS,
            ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE,
            ProductGroupType::CLOUDSTACK_VOLUME,
            ProductGroupType::DNS,
            ProductGroupType::EXTENSION,
            ProductGroupType::HOSTING,
            ProductGroupType::OTHER,
            ProductGroupType::REDIRECT,
            ProductGroupType::RESELLER_HOSTING,
            ProductGroupType::SSL,
            ProductGroupType::VPS => $this->updateCustomerId($subscription, $transfer->toCustomer),
            ProductGroupType::DOMAIN_EXPANSION,
            ProductGroupType::RESELLER_DISCOUNT,
            ProductGroupType::MICROSOFT_365,
            ProductGroupType::MANUAL_SUBSCRIPTION,
            ProductGroupType::ONE_TIME_SERVICE,
            ProductGroupType::VOLUME_DISCOUNT => throw new TransferException(sprintf('Transfer is not supported for product group %s', $subscription->product->productGroup->slug->value)),
        };
    }

    private function updateCustomerId(Subscription $subscription, Customer $receiver): void
    {
        $subscription->customer_id = $receiver->id;
        $subscription->save();
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     *
     * @throws TransferException
     */
    private function validateSubscriptionsAreTransferable(Transfer $transfer, Collection $subscriptions): void
    {
        if (! $transfer->isAccepted()) {
            throw new TransferException(
                sprintf(
                    'Transfer has not been accepted. Transfer ID: {%s}',
                    $transfer->id,
                )
            );
        }

        // If the subscriptions under a transfer do not have the same customer
        // as the from customer related to the transfer we should abort.
        if (! $this->validateTransferSubscriptionsOriginatingCustomer($transfer, $subscriptions)) {
            throw new TransferException(
                sprintf(
                    'Transfer contains subscriptions not owned by the initiating customer. Transfer ID: {%s}',
                    $transfer->id,
                )
            );
        }

        // If the pivot entry from the subscription to the transfer is not the latest
        // and is not in an 'accept' state we should abort.
        if (! $this->validateTransferSubscriptionsPivotState($subscriptions)) {
            throw new TransferException(
                sprintf(
                    'Transfer contains subscriptions with a more recent transfer not in accepted state, can not continue. Transfer ID: {%s}',
                    $transfer->id,
                )
            );
        }
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function filterExecutedSubscriptions(Transfer $transfer): Collection
    {
        return $transfer->subscriptions->filter(fn (Subscription $subscription) => $subscription->pivot->executed_at === null);
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    private function validateTransferSubscriptionsOriginatingCustomer(Transfer $transfer, Collection $subscriptions): bool
    {
        $filter = fn (Subscription $subscription): bool => $subscription->customer_id === $transfer->from_customer_id;

        return $subscriptions->filter($filter)->count() === $subscriptions->count();
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    private function validateTransferSubscriptionsPivotState(Collection $subscriptions): bool
    {
        $filter = function (Subscription $subscription): bool {
            $relevantTransfer = $subscription->transfers()->latest()->first();

            return ! is_null($relevantTransfer) && $relevantTransfer->isAccepted();
        };

        return $subscriptions->filter($filter)->count() === $subscriptions->count();
    }
}
