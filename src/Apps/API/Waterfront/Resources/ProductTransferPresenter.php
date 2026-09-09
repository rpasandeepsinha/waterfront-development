<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Transfers\Enums\TransferType;
use Waterfront\Domain\Transfers\Models\Transfer;

class ProductTransferPresenter
{
    public function __construct(
        private readonly SubscriptionPresenter $subscriptionPresenter,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Transfer $productTransfer, Customer $authenticatedCustomer): array
    {
        return [
            'id'                    => $productTransfer->uuid,
            'from_customer_number'  => $productTransfer->fromCustomer->customer_number,
            'to_customer_number'    => $productTransfer->toCustomer->customer_number,
            'is_open'               => $productTransfer->isOpen(),
            'status'                => strtoupper($productTransfer->getStatus()->value),
            'type'                  => $this->determineTransferType($productTransfer, $authenticatedCustomer)->value,
            'accepted_at'           => $productTransfer->accepted_at?->toW3cString(),
            'completed_at'          => $productTransfer->completed_at?->toW3cString(),
            'canceled_at'           => $productTransfer->canceled_at?->toW3cString(),
            'rejected_at'           => $productTransfer->rejected_at?->toW3cString(),
            'started_at'            => $productTransfer->started_at?->toW3cString(),
            'created_at'            => $productTransfer->created_at?->toW3cString(),
            'updated_at'            => $productTransfer->updated_at?->toW3cString(),
            'subscriptions'         => $this->subscriptionPresenter->collectionToArray($productTransfer->subscriptions),
        ];
    }

    /**
     * @param Collection<int, Transfer> $productTransfers
     *
     * @return array<mixed>
     */
    public function collectionToArray(Collection $productTransfers, Customer $authenticatedCustomer): array
    {
        $array = [];

        $productTransfers->each(function (Transfer $transfer) use (&$array, $authenticatedCustomer) {
            $array[] = $this->toArray($transfer, $authenticatedCustomer);
        });

        return $array;
    }

    private function determineTransferType(Transfer $transfer, Customer $authenticatedCustomer): TransferType
    {
        return $authenticatedCustomer->id !== $transfer->from_customer_id ? TransferType::INCOMING : TransferType::OUTGOING;
    }
}
