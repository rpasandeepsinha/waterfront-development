<?php

declare(strict_types=1);

namespace Tests\Domain\Payments\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Events\Dispatcher as EventDispatcher;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Waterfront\Domain\Payments\Events\PaymentUpdatedEvent;
use Waterfront\Domain\Payments\Exceptions\CreatePaymentException;
use Waterfront\Domain\Payments\Exceptions\FetchPaymentException;
use Waterfront\Domain\Payments\Exceptions\PaymentException;
use Waterfront\Domain\Payments\Interfaces\PaymentInterface;
use Waterfront\Domain\Payments\Jobs\RequestDirectDebitMandateJob;
use Waterfront\Domain\Payments\Models\CreatePayment\PaymentParameters;
use Waterfront\Domain\Payments\Models\Payment;
use Waterfront\Domain\Payments\Models\Result;
use Waterfront\Domain\Payments\Services\PaymentService;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(PaymentService::class)]
#[AllowMockObjectsWithoutExpectations]
class PaymentServiceTest extends IntegrationTestCase
{
    private Customer $customer;

    private Order $order;

    private EventDispatcher&MockObject $eventDispatcherMock;

    private JobDispatcher&MockObject $jobDispatcherMock;

    private PaymentInterface&MockObject $clientMock;

    private LoggerInterface&MockObject $loggerMock;

    private PaymentService $paymentService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcherMock = self::createMock(EventDispatcher::class);
        $this->jobDispatcherMock = self::createMock(JobDispatcher::class);
        $this->clientMock = self::createMock(PaymentInterface::class);
        $this->loggerMock = self::createMock(LoggerInterface::class);
        $this->paymentService = new PaymentService(
            client: $this->clientMock,
            eventDispatcher: $this->eventDispatcherMock,
            jobDispatcher: $this->jobDispatcherMock,
            logger: $this->loggerMock,
        );

        $this->customer = new CustomerFactory()->createOne();
        $this->order = new OrderFactory()->for($this->customer)->createOne();
    }

    /**
     * @throws PaymentException
     */
    #[Test]
    public function createPaymentSucceeds(): void
    {
        $paymentParameters = $this->createPaymentParameters();
        $this->clientMock
            ->expects(self::exactly(3))
            ->method('createPayment')
            ->with($paymentParameters)
            ->willReturn($this->createPaymentClientResult());

        $payment = $this->paymentService->createPayment($this->customer, $paymentParameters, null)->refresh();

        self::assertSame('external_id_1', $payment->external_id);
        self::assertSame($this->customer->uuid->toString(), $payment->customer_uuid);
        self::assertSame(1, $payment->amount);
        self::assertSame(PaymentStatus::OPEN, $payment->status);
        self::assertSame($payment->order_id, $this->order->id);
        self::assertNull($payment->create_direct_debit_mandate);

        $paymentWithCreateDirectDebitTrue = $this->paymentService->createPayment(
            $this->customer,
            $paymentParameters,
            true,
        );
        self::assertTrue($paymentWithCreateDirectDebitTrue->create_direct_debit_mandate);
        $paymentWithCreateDirectDebitFalse = $this->paymentService->createPayment(
            $this->customer,
            $paymentParameters,
            false,
        );
        self::assertFalse($paymentWithCreateDirectDebitFalse->create_direct_debit_mandate);
    }

    /**
     * @throws PaymentException
     */
    #[Test]
    public function createPaymentErrorResult(): void
    {
        $paymentParameters = $this->createPaymentParameters();
        $this->clientMock
            ->expects(self::once())
            ->method('createPayment')
            ->with($paymentParameters)
            ->willReturn(Result::create([
                'status' => Result::STATUS_ERROR,
                'errorCode' => 1,
                'errorMessage' => 'Test error',
            ]));

        self::expectException(CreatePaymentException::class);
        $this->paymentService->createPayment($this->customer, $paymentParameters, null);
    }

    /**
     * @throws PaymentException
     */
    #[Test]
    public function syncPayment(): void
    {
        $payment = $this->createPayment();

        $paymentClientResult = $this->createPaymentClientResult(PaymentStatus::PAID);
        $this->clientMock
            ->expects(self::once())
            ->method('fetchPayment')
            ->with($payment->external_id)
            ->willReturn($paymentClientResult);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                fn ($event) => (
                    $event instanceof PaymentUpdatedEvent
                    && $event->getPayment()->id === $payment->id
                    && $event->getPayment()->status === PaymentStatus::PAID
                    && $event->getPaymentResult() === $paymentClientResult
                ),
            ));

        $syncedPayment = $this->paymentService->syncPayment($payment);

        self::assertSame($payment->id, $syncedPayment->id);
        self::assertSame(PaymentStatus::PAID, $syncedPayment->status);
    }

    /**
     * @throws PaymentException
     */
    #[Test]
    public function syncPaymentWithNoStatusUpdate(): void
    {
        $payment = $this->createPayment();

        $this->clientMock
            ->expects(self::once())
            ->method('fetchPayment')
            ->with($payment->external_id)
            ->willReturn($this->createPaymentClientResult());

        $this->eventDispatcherMock->expects(self::never())->method('dispatch');

        $this->paymentService->syncPayment($payment->external_id);
    }

    /**
     * @throws PaymentException
     */
    #[Test]
    public function syncPaymentWithStringArgument(): void
    {
        $payment = $this->createPayment();

        $this->clientMock
            ->expects(self::once())
            ->method('fetchPayment')
            ->with($payment->external_id)
            ->willReturn($this->createPaymentClientResult(PaymentStatus::PAID));

        $syncedPayment = $this->paymentService->syncPayment($payment->external_id);

        self::assertSame($payment->id, $syncedPayment->id);
        self::assertSame(PaymentStatus::PAID, $syncedPayment->status);
    }

    /**
     * @throws PaymentException
     */
    #[Test]
    public function syncPaymentWithErrorResult(): void
    {
        $payment = $this->createPayment();

        $this->clientMock
            ->expects(self::once())
            ->method('fetchPayment')
            ->with($payment->external_id)
            ->willReturn(Result::create([
                'status' => Result::STATUS_ERROR,
                'errorCode' => 1,
                'errorMessage' => 'Test error',
            ]));

        self::expectException(FetchPaymentException::class);

        $this->paymentService->syncPayment($payment);
    }

    #[Test]
    public function isPaymentMethodThatSupportsDirectDebitCreation(): void
    {
        self::assertTrue($this->paymentService->isPaymentMethodThatSupportsDirectDebitCreation('ideal'));
        self::assertFalse($this->paymentService->isPaymentMethodThatSupportsDirectDebitCreation('bancontact'));
    }

    /**
     * @throws MollieMandateApiException
     * @throws MollieCustomerApiException
     * @throws PaytMandateApiException
     */
    #[Test]
    public function createDirectDebitMandateFromPaymentResult(): void
    {
        CarbonImmutable::setTestNow('2024-04-05 16:51:00');
        $this->eventDispatcherMock->expects(self::never())->method('dispatch');

        $this->jobDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn ($job) => $job instanceof RequestDirectDebitMandateJob));

        $consumerAccount = 'NL27RABO01292845';
        $paymentClientResult = $this->createPaymentClientResult(PaymentStatus::PAID, [
            'consumerAccount' => $consumerAccount,
        ]);

        $this->loggerMock->expects(self::never())->method('error');

        $this->paymentService->createDirectDebitMandateFromPaymentResult($this->customer, $paymentClientResult);

        CarbonImmutable::setTestNow();
    }

    /**
     * @throws MollieMandateApiException
     * @throws MollieCustomerApiException
     * @throws PaytMandateApiException
     */
    #[Test]
    public function createDirectDebitMandateFromPaymentResultsWithNoConsumerAccountInResult(): void
    {
        $paymentClientResult = $this->createPaymentClientResult(PaymentStatus::PAID, null);
        $this->eventDispatcherMock->expects(self::never())->method('dispatch');

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Payments: cannot create direct debit mandate from payment result - details.consumerAccount is missing from response data',
                [
                    LoggingContextKeys::CUSTOMER_ID => $this->customer->id,
                    LoggingContextKeys::RESPONSE_DATA => (string) json_encode($paymentClientResult),
                ],
            );

        $this->paymentService->createDirectDebitMandateFromPaymentResult($this->customer, $paymentClientResult);
    }

    /**
     * @throws MollieMandateApiException
     * @throws MollieCustomerApiException
     * @throws PaytMandateApiException
     */
    #[Test]
    public function createDirectDebitMandateFromPaymentResultsWithActiveMandate(): void
    {
        $paymentClientResult = $this->createPaymentClientResult(PaymentStatus::PAID, null);
        $this->eventDispatcherMock->expects(self::never())->method('dispatch');

        $this->customer->has_direct_debit = true;

        $this->paymentService->createDirectDebitMandateFromPaymentResult($this->customer, $paymentClientResult);
    }

    private function createPaymentParameters(): PaymentParameters
    {
        return new PaymentParameters(
            currency: 'EUR',
            amount: '0.01',
            description: 'Customer validation payment for customer ' . $this->customer->id,
            redirectUrl: $this->getConfiguration()->getAsString('app.url_partner'),
            webhookUrl: $this->generateRoute('partners.payment.webhook'),
            metaData: [
                'order' => $this->order->id,
            ],
        );
    }

    /**
     * @param null|array<'consumerAccount', string> $details
     */
    private function createPaymentClientResult(
        PaymentStatus $status = PaymentStatus::OPEN,
        ?array $details = [],
    ): Result {
        return Result::create([
            'status' => Result::STATUS_OK,
            'paymentData' => [
                'id' => 'external_id_1',
                'amount' => [
                    'value' => '0.01',
                ],
                'details' => $details,
                'status' => $status->value,
                '_links' => [
                    'checkout' => [
                        'href' => '/checkout',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @throws PaymentException
     */
    private function createPayment(): Payment
    {
        $paymentParameters = $this->createPaymentParameters();

        $this->clientMock
            ->expects(self::once())
            ->method('createPayment')
            ->with($paymentParameters)
            ->willReturn($this->createPaymentClientResult());

        return $this->paymentService->createPayment($this->customer, $paymentParameters, null);
    }
}
