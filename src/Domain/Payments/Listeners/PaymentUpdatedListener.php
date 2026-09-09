<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Listeners;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Waterfront\Domain\Payments\Events\PaymentUpdatedEvent;
use Waterfront\Domain\Payments\Services\PaymentService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Support\Enums\LoggingContextKeys;

class PaymentUpdatedListener
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly PaymentService $paymentService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws MollieMandateApiException
     * @throws MollieCustomerApiException
     * @throws PaytMandateApiException
     */
    public function handle(PaymentUpdatedEvent $event): void
    {
        $payment = $event->getPayment();

        $this->logger->debug('Handling payment updated event', [
            LoggingContextKeys::REQUEST_DATA => (string) json_encode([$event->getPayment(), $event->getPaymentResult()]),
        ]);

        if ($payment->order === null || $payment->status !== PaymentStatus::PAID) {
            return;
        }

        $this->subscriptionService->dispatchProcessOrderJob($payment->order);

        if ($payment->create_direct_debit_mandate === true) {
            $this->paymentService->createDirectDebitMandateFromPaymentResult($payment->customer, $event->getPaymentResult());
        }
    }
}
