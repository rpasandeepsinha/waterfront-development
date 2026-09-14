<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Waterfront\Domain\Payments\Events\PaymentUpdatedEvent;
use Waterfront\Domain\Payments\Exceptions\CreatePaymentException;
use Waterfront\Domain\Payments\Exceptions\FetchPaymentException;
use Waterfront\Domain\Payments\Exceptions\PaymentException;
use Waterfront\Domain\Payments\Helpers\Format;
use Waterfront\Domain\Payments\Interfaces\PaymentInterface;
use Waterfront\Domain\Payments\Jobs\RequestDirectDebitMandateJob;
use Waterfront\Domain\Payments\Models\CreatePayment\PaymentParameters;
use Waterfront\Domain\Payments\Models\Payment;
use Waterfront\Domain\Payments\Models\Result;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class PaymentService
{
    public function __construct(
        private readonly PaymentInterface $client,
        private readonly EventDispatcher $eventDispatcher,
        private readonly JobDispatcher $jobDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws PaymentException
     */
    public function createPayment(
        Customer $customer,
        PaymentParameters $parameters,
        ?bool $createDirectDebitMandate,
    ): Payment {
        $result = $this->client->createPayment($parameters);
        if ($result->getStatus() === Result::STATUS_ERROR) {
            throw new CreatePaymentException($result->getErrorMessage(), $result->getErrorCode());
        }

        $paymentData = $result->getPaymentData();
        assert(is_array($paymentData['amount']));
        assert(is_string($paymentData['status']));
        assert(is_array($paymentData['_links']));
        assert(is_array($paymentData['_links']['checkout']));
        assert(is_string($paymentData['_links']['checkout']['href']));
        assert(is_string($paymentData['id']));

        $payment = new Payment();
        $payment->external_id = $paymentData['id'];
        $payment->amount = (int) round(Format::stringToNumber($paymentData['amount']['value']) * 100);
        $payment->status = PaymentStatus::from($paymentData['status']);
        $payment->create_direct_debit_mandate = $createDirectDebitMandate;

        $orderId = Arr::get($parameters->metaData, 'order', '');
        assert(is_string($orderId) || is_int($orderId));
        if ($orderId !== '') {
            $payment->order_id = (int) $orderId;
        }

        $payment->checkout_url = $paymentData['_links']['checkout']['href'];
        $customer->payments()->save($payment);

        return $payment;
    }

    /**
     * @throws PaymentException
     */
    public function syncPayment(Payment|string $payment): Payment
    {
        if (is_string($payment)) {
            $payment = Payment::where('external_id', $payment)->firstOrFail();
        }

        $result = $this->client->fetchPayment($payment->external_id);
        if ($result->getStatus() === Result::STATUS_ERROR) {
            throw new FetchPaymentException($result->getErrorMessage(), $result->getErrorCode());
        }

        $paymentData = $result->getPaymentData();
        assert(is_string($paymentData['status']));
        $status = PaymentStatus::tryFrom($paymentData['status']);
        Assert::notNull($status);

        // https://docs.mollie.com/payments/status-changes This fix leans on this mollie api spec. When a refund happens.
        // The status of the fetchPayment will still be paid! Should stay consistent in the api v2 implementation of mollie
        if ($payment->status !== $status) {
            $payment->status = $status;
            $payment->save();
            /** @var Payment $payment */
            $payment = $payment->fresh();

            $this->eventDispatcher->dispatch(new PaymentUpdatedEvent($payment, $result));
        }

        return $payment;
    }

    public function isPaymentMethodThatSupportsDirectDebitCreation(string $paymentMethod): bool
    {
        return $paymentMethod === 'ideal';
    }

    /**
     * @throws MollieMandateApiException
     * @throws MollieCustomerApiException
     * @throws PaytMandateApiException
     */
    public function createDirectDebitMandateFromPaymentResult(Customer $customer, Result $paymentResult): void
    {
        if ($customer->has_direct_debit) {
            $this->logger->info('Payments: customer already has direct debit mandate, skipping creation from payment result', [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
            ]);

            return;
        }

        /** @var string|null $consumerAccount */
        $consumerAccount = Arr::get($paymentResult->getPaymentData(), 'details.consumerAccount');
        if ($consumerAccount === null) {
            $this->logger->error(
                'Payments: cannot create direct debit mandate from payment result - details.consumerAccount is missing from response data',
                [
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::RESPONSE_DATA => (string) json_encode($paymentResult),
                ],
            );

            return;
        }

        $this->logger->debug(
            sprintf('Dispatching request direct debit mandate job for customer %s', $customer->customer_number),
            [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::RESPONSE_DATA => (string) json_encode($paymentResult),
            ],
        );

        $this->jobDispatcher->dispatch(
            new RequestDirectDebitMandateJob(
                $customer->name,
                $consumerAccount,
                null,
                $customer,
                CarbonImmutable::now(),
            ),
        );
    }
}
