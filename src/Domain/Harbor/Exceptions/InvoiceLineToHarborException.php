<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Exceptions;

use Exception;
use Throwable;

class InvoiceLineToHarborException extends Exception
{
    public static function customerNotFound(int $invoiceId): self
    {
        return new InvoiceLineToHarborException(
            sprintf(
                'The Customer for Invoice with id %s was not found!',
                $invoiceId
            )
        );
    }

    public static function addressNotSetForCustomer(string $name, int $customerId): self
    {
        return new InvoiceLineToHarborException(
            sprintf(
                'The Address for customer with name %s and id %s is not set!',
                $name,
                $customerId
            )
        );
    }

    public static function invoiceNotStoredInWaterfront(int $customerId, ?int $subscriptionId): self
    {
        return new InvoiceLineToHarborException(
            sprintf(
                'Invoice for customer %d and subscription %s should be stored in the waterfront database first',
                $customerId,
                $subscriptionId ?? 'null'
            )
        );
    }

    public static function subscriptionNotFoundException(int $invoiceId): self
    {
        return new InvoiceLineToHarborException(
            sprintf(
                'Invoice with id %d was not coupled to a subscription!',
                $invoiceId
            )
        );
    }

    public static function subscriptionNotSuccessfullyDeployedException(int $invoiceId): self
    {
        return new InvoiceLineToHarborException(
            sprintf(
                'Invoice with id %d has exceeded the maximum amount of allowed tries to see if the underlying subscription was successfully deployed!',
                $invoiceId
            )
        );
    }

    public static function propagationToHarbourException(
        ?int $invoiceId,
        Throwable $previous,
        bool $isChannelOpen,
        bool $isConnectionOpen,
        bool $isConnectionBlocked
    ): self {
        return new InvoiceLineToHarborException(
            sprintf(
                'Invoice with id %d could not be propagated to Harbour cause of issues connecting / sending / closing interactions for the message queue.
                The following state of connections {channel isChannelOpen: %b , connection isConnectionOpen: %b , connection isConnectionBlocked: %b }',
                $invoiceId,
                $isChannelOpen,
                $isConnectionOpen,
                $isConnectionBlocked
            ),
            $previous->getCode(),
            $previous
        );
    }

    public static function incompleteOrderException(
        int $orderLineItemId,
        string $domain,
        int $invoiceId,
    ): self {
        return new InvoiceLineToHarborException(
            sprintf(
                'OrderLineItem with ID: %d for domain %s had no subscription attached! This prevented invoice with id %d to be propagated to Harbor!',
                $orderLineItemId,
                $domain,
                $invoiceId
            )
        );
    }
}
