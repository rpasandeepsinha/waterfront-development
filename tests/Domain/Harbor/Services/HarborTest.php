<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Facades\Config;
use JsonException;
use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtCollectionStatusUpdated;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines;
use SandwaveIo\HarborMessages\Message\DebtorSsoUrl;
use SandwaveIo\HarborMessages\Message\Enum\DebtCollectionStatus;
use SandwaveIo\HarborMessages\Message\InvoicePaymentAnnouncement;
use SandwaveIo\HarborMessages\Message\MandateAnnouncement;
use SandwaveIo\HarborMessages\Message\Serializer\JsonSerializer;
use SandwaveIo\HarborMessages\Message\Serializer\SerializerException;
use SandwaveIo\HarborMessages\Message\WithdrawInvoicePaymentAnnouncement;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\CustomerAddressFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Throwable;
use Waterfront\Domain\Admin\Actions\AnonymizeCustomerAction;
use Waterfront\Domain\Admin\Actions\AnonymizeIdentitiesForCustomerAction;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;
use Waterfront\Domain\Harbor\Services\Harbor;
use Waterfront\Domain\Harbor\Services\Message\Handler\DebtCollectionStatusUpdatedHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\DebtorSsoUrlHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\InvoicePaymentAnnouncementHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\MandateAnnouncementHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\WithdrawInvoicePaymentAnnouncementHandler;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Queue\HarborQueue;

#[CoversClass(Harbor::class)]
class HarborTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(HarborQueue::class, fn (): HarborQueue => new HarborQueue(
            $this->resolve(ConfigurationInterface::class),
            new JsonSerializer(),
        ));
    }

    #[Test]
    public function propagateInvoice(): void
    {
        $connection = $this->getMockedAMQPConnection();

        $serializer = new JsonSerializer();

        $harbor = Mockery::mock(Harbor::class, [
            $serializer,
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            self::createStub(DebtorSsoUrlHandler::class),
            self::createStub(InvoicePaymentAnnouncementHandler::class),
            self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
            self::createStub(DebtCollectionStatusUpdatedHandler::class),
            self::createStub(MandateAnnouncementHandler::class),
            self::createStub(LoggerInterface::class),
        ])->makePartial();
        $harbor->shouldReceive('amqpConnection')->andReturn($connection);

        $customer = new CustomerFactory()->withAddress()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->createOne();

        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($subscription)
            ->for($product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        self::assertNull($invoice->sent_to_harbor_at, 'Invoice processed date should NOT be set yet');

        $harbor->propagateInvoice($invoice);

        self::assertNotNull($invoice->sent_to_harbor_at, 'Invoice processed date should be set');
    }

    #[Test]
    public function propagateInvoiceDisabledMode(): void
    {
        Config::set('harbor.enabled', false);

        $serializer = new JsonSerializer();
        $harbor = new Harbor(
            $serializer,
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            self::createStub(DebtorSsoUrlHandler::class),
            self::createStub(InvoicePaymentAnnouncementHandler::class),
            self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
            self::createStub(DebtCollectionStatusUpdatedHandler::class),
            self::createStub(MandateAnnouncementHandler::class),
            self::createStub(LoggerInterface::class),
        );

        $customer = new CustomerFactory()->withAddress()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->createOne();

        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($subscription)
            ->for($product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        self::assertNull($invoice->sent_to_harbor_at, 'Invoice processed date should NOT be set yet');

        self::assertNull($invoice->sent_to_harbor_at, 'Invoice processed date should NOT be set yet');

        $harbor->propagateInvoice($invoice);

        self::assertNull($invoice->sent_to_harbor_at, 'Invoice processed date should be set');
    }

    #[Test]
    public function propagateInvoiceAddressNotFound(): void
    {
        $serializer = new JsonSerializer();
        $harbor = new Harbor(
            $serializer,
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            self::createStub(DebtorSsoUrlHandler::class),
            self::createStub(InvoicePaymentAnnouncementHandler::class),
            self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
            self::createStub(DebtCollectionStatusUpdatedHandler::class),
            self::createStub(MandateAnnouncementHandler::class),
            self::createStub(LoggerInterface::class),
        );

        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->createOne();

        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($subscription)
            ->for($product)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
            ]);

        $exception = InvoiceLineToHarborException::addressNotSetForCustomer($customer->name, $customer->id);

        $this->expectException(InvoiceLineToHarborException::class);
        $this->expectExceptionMessageIs($exception->getMessage());

        $harbor->propagateInvoice($invoice);
    }

    #[Test]
    public function propagateInvoiceNovaWithSubscription(): void
    {
        $connection = $this->getMockedAMQPConnection();

        $harbor = Mockery::mock(Harbor::class, [
            new JsonSerializer(),
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            self::createStub(DebtorSsoUrlHandler::class),
            self::createStub(InvoicePaymentAnnouncementHandler::class),
            self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
            self::createStub(DebtCollectionStatusUpdatedHandler::class),
            self::createStub(MandateAnnouncementHandler::class),
            self::createStub(LoggerInterface::class),
        ])->makePartial();
        $harbor->shouldReceive('amqpConnection')->andReturn($connection);

        $customer = new CustomerFactory()->withAddress()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($product)
            ->createOne();
        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($subscription)
            ->for($product)
            ->createOne();

        self::assertNull($invoice->sent_to_harbor_at, 'Invoice processed date should NOT be set yet');

        $harbor->propagateInvoice($invoice);

        self::assertNotNull($invoice->sent_to_harbor_at, 'Invoice processed date should be set');
    }

    #[Test]
    public function handlesDebtorSsoUrlMessage(): void
    {
        $serializer = new JsonSerializer();

        $debtorSsoUrlHandler = self::createMock(DebtorSsoUrlHandler::class);
        $debtorSsoUrlHandler
            ->expects(self::once())
            ->method('handle')
            ->with(
                self::equalTo(
                    new DebtorSsoUrl(123, 'http://newurlgoeshere.dev/', 'http://newurlgoeshere.dev/'),
                ),
            );

        $harbor = new Harbor(
            $serializer,
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            $debtorSsoUrlHandler,
            self::createStub(InvoicePaymentAnnouncementHandler::class),
            self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
            self::createStub(DebtCollectionStatusUpdatedHandler::class),
            self::createStub(MandateAnnouncementHandler::class),
            self::createStub(LoggerInterface::class),
        );

        $data = require __DIR__ . '/data/encoding_debtorssourl_success.php';

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        $customer = new CustomerFactory()->createOne([
            'invoice_history_url' => null,
        ]);
        $customer->customer_number = 123;
        $customer->save();

        $message = self::createMock(AMQPMessage::class);
        $message->expects(self::once())->method('getBody')->willReturn($encoded);
        $message->expects(self::once())->method('ack');

        $harbor->receiveMessage($message);
    }

    #[Test]
    public function handleInvoicePaymentAnnouncement(): void
    {
        $serializer = new JsonSerializer();
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $invoicePaymentAnnouncementHandler = self::createMock(InvoicePaymentAnnouncementHandler::class);
        $invoicePaymentAnnouncementHandler
            ->expects(self::once())
            ->method('handle')
            ->with(
                self::equalTo(
                    new InvoicePaymentAnnouncement([1, 2, 3], $now->getTimestamp()),
                ),
            );

        $harbor = new Harbor(
            $serializer,
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            self::createStub(DebtorSsoUrlHandler::class),
            $invoicePaymentAnnouncementHandler,
            self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
            self::createStub(DebtCollectionStatusUpdatedHandler::class),
            self::createStub(MandateAnnouncementHandler::class),
            self::createStub(LoggerInterface::class),
        );

        $data = require __DIR__ . '/data/encoding_invoicepaymentannouncement_success.php';

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        $message = self::createMock(AMQPMessage::class);
        $message->expects(self::once())->method('getBody')->willReturn($encoded);
        $message->expects(self::once())->method('ack');

        $harbor->receiveMessage($message);
    }

    #[Test]
    public function handleWithdrawInvoicePaymentAnnouncement(): void
    {
        $serializer = new JsonSerializer();

        $withdrawInvoicePaymentAnnouncementHandler = self::createMock(WithdrawInvoicePaymentAnnouncementHandler::class);
        $withdrawInvoicePaymentAnnouncementHandler
            ->expects(self::once())
            ->method('handle')
            ->with(
                self::equalTo(
                    new WithdrawInvoicePaymentAnnouncement([1, 2, 3]),
                ),
            );

        $harbor = new Harbor(
            $serializer,
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            self::createStub(DebtorSsoUrlHandler::class),
            self::createStub(InvoicePaymentAnnouncementHandler::class),
            $withdrawInvoicePaymentAnnouncementHandler,
            self::createStub(DebtCollectionStatusUpdatedHandler::class),
            self::createStub(MandateAnnouncementHandler::class),
            self::createStub(LoggerInterface::class),
        );

        $data = require __DIR__ . '/data/encoding_withdrawinvoiceannouncement.php';

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        $message = self::createMock(AMQPMessage::class);
        $message->expects(self::once())->method('getBody')->willReturn($encoded);
        $message->expects(self::once())->method('ack');

        $harbor->receiveMessage($message);
    }

    #[Test]
    public function handleDebtCollectionStatusUpdated(): void
    {
        $serializer = new JsonSerializer();

        $debtCollectionStatusUpdatedHandler = self::createMock(DebtCollectionStatusUpdatedHandler::class);
        $debtCollectionStatusUpdatedHandler
            ->expects(self::once())
            ->method('handle')
            ->with(
                self::equalTo(
                    new DebtCollectionStatusUpdated(
                        customerNumber: 1337,
                        customerDebtCollectionStatus: DebtCollectionStatus::BAD_DEBT,
                        invoiceNumber: 20240000002,
                        invoiceDebtCollectionStatus: DebtCollectionStatus::BAD_DEBT,
                        subscriptions: [['id' => 1111, 'debt_collection_status' => DebtCollectionStatus::BAD_DEBT]],
                    ),
                ),
            );

        /**
         *
         * 'customer_number' => 1337,
         * 'customer_debt_collection_status' => DebtCollectionStatus::BAD_DEBT->value,
         * 'invoice_number' => 20240000002,
         * 'invoice_debt_collection_status' => DebtCollectionStatus::BAD_DEBT->value,
         * 'subscriptions' => [
         * [
         * 'id' => 1111,
         * 'debt_collection_status' => DebtCollectionStatus::BAD_DEBT->value,
         * ],
         * ],.
         */
        $harbor = new Harbor(
            $serializer,
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            self::createStub(DebtorSsoUrlHandler::class),
            self::createStub(InvoicePaymentAnnouncementHandler::class),
            self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
            $debtCollectionStatusUpdatedHandler,
            self::createStub(MandateAnnouncementHandler::class),
            self::createStub(LoggerInterface::class),
        );

        $data = require __DIR__ . '/data/encoding_debtcollectionstatusupdated.php';

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        $message = self::createMock(AMQPMessage::class);
        $message->method('getBody')->willReturn($encoded);
        $message->expects(self::once())->method('ack');

        $harbor->receiveMessage($message);
    }

    #[Test]
    public function handleMandateAnnouncement(): void
    {
        $serializer = new JsonSerializer();

        $handler = self::createMock(MandateAnnouncementHandler::class);
        $handler
            ->expects(self::once())
            ->method('handle')
            ->with(
                self::equalTo(
                    new MandateAnnouncement(
                        'mdt_someid123',
                        1337,
                        true,
                    ),
                ),
            );

        $harbor = new Harbor(
            $serializer,
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            self::createStub(DebtorSsoUrlHandler::class),
            self::createStub(InvoicePaymentAnnouncementHandler::class),
            self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
            self::createStub(DebtCollectionStatusUpdatedHandler::class),
            $handler,
            self::createStub(LoggerInterface::class),
        );

        $data = require __DIR__ . '/data/encoding_mandate_announcement.php';

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        $message = self::createMock(AMQPMessage::class);
        $message->method('getBody')->willReturn($encoded);
        $message->expects(self::once())->method('ack');

        $harbor->receiveMessage($message);
    }

    /**
     * @throws JsonException
     * @throws Throwable
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws SerializerException
     */
    #[Test]
    public function receiveMessageHandlesThrowable(): void
    {
        $serializer = new JsonSerializer();

        $validSubscription = DomainSubscriptionDataProvider::subscription();

        $this->expectException(Exception::class);

        $debtCollectionStatusUpdatedHandler = self::createMock(DebtCollectionStatusUpdatedHandler::class);
        $debtCollectionStatusUpdatedHandler
            ->expects(self::once())
            ->method('handle')
            ->willThrowException(
                new Exception('Fake exception'),
            );

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Failed to process received message HarborMessage'),
            );

        $harbor = new Harbor(
            $serializer,
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            self::createStub(DebtorSsoUrlHandler::class),
            self::createStub(InvoicePaymentAnnouncementHandler::class),
            self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
            $debtCollectionStatusUpdatedHandler,
            self::createStub(MandateAnnouncementHandler::class),
            $logger,
        );

        $data = require __DIR__ . '/data/encoding_debtcollectionstatusupdated.php';

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        $message = self::createMock(AMQPMessage::class);
        $message->method('getBody')->willReturn($encoded);
        $message->expects(self::never())->method('ack');

        $harbor->receiveMessage($message);
    }

    #[Test]
    public function handleDecodeException(): void
    {
        $serializer = new JsonSerializer();
        $this->expectException(SerializerException::class);

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Failed deserializing HarborMessage'),
            );

        $harbor = new Harbor(
            $serializer,
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            self::createStub(DebtorSsoUrlHandler::class),
            self::createStub(InvoicePaymentAnnouncementHandler::class),
            self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
            self::createStub(DebtCollectionStatusUpdatedHandler::class),
            self::createStub(MandateAnnouncementHandler::class),
            $logger,
        );

        $data = require __DIR__ . '/data/encoding_class_error.php';

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        $message = self::createMock(AMQPMessage::class);
        $message->expects(self::once())->method('getBody')->willReturn($encoded);
        $message->expects(self::never())->method('ack');

        $harbor->receiveMessage($message);
    }

    #[Test]
    public function propagateCustomer(): void
    {
        $connection = $this->getMockedAMQPConnection();

        $serializer = new JsonSerializer();

        $harbor = Mockery::mock(Harbor::class, [
            $serializer,
            self::resolve(InvoiceRepository::class),
            self::resolve(MessageService::class),
            self::resolve(CustomerVatService::class),
            self::resolve(ConfigurationInterface::class),
            self::createStub(DebtorSsoUrlHandler::class),
            self::createStub(InvoicePaymentAnnouncementHandler::class),
            self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
            self::createStub(DebtCollectionStatusUpdatedHandler::class),
            self::createStub(MandateAnnouncementHandler::class),
            self::createStub(LoggerInterface::class),
        ])->makePartial();
        $harbor->shouldReceive('amqpConnection')->andReturn($connection);

        $customer = new CustomerFactory()->withAddress()->createOne();

        $harbor->propagateCustomer($customer);

        /** @var Mockery\MockInterface $channel */
        $channel = $connection->channel();
        $channel
            ->shouldHaveReceived('basic_publish')
            ->withArgs(static function (AMQPMessage $amqpMessage) use ($serializer, $customer): bool {
                $messageSend = $serializer->decode($amqpMessage->getBody());

                return (
                    $messageSend instanceof DebtorInvoiceLines
                    && $messageSend->getDebtor()->getEmail() === $customer->email
                    && count($messageSend->getInvoiceLines()) === 0
                );
            });
    }

    #[Test]
    public function anonymizedCustomerDidNotPropogate(): void
    {
        $anonymizeIdentitiesActionMock = self::createStub(
            AnonymizeIdentitiesForCustomerAction::class,
        );

        $this->app->bind(AnonymizeIdentitiesForCustomerAction::class, fn () => $anonymizeIdentitiesActionMock);

        $this->app->singleton(function (): CommunicatesWithHarbor {
            $connection = $this->getMockedAMQPConnection();
            $serializer = new JsonSerializer();

            $harbor = Mockery::mock(Harbor::class, [
                $serializer,
                self::resolve(InvoiceRepository::class),
                self::resolve(MessageService::class),
                self::resolve(CustomerVatService::class),
                self::createStub(DebtorSsoUrlHandler::class),
                self::createStub(InvoicePaymentAnnouncementHandler::class),
                self::createStub(WithdrawInvoicePaymentAnnouncementHandler::class),
                self::createStub(DebtCollectionStatusUpdatedHandler::class),
                self::createStub(MandateAnnouncementHandler::class),
                self::createStub(LoggerInterface::class),
            ])->makePartial();
            $harbor->shouldReceive('amqpConnection')->andReturn($connection);
            $harbor->shouldNotHaveReceived('basic_publish');
            $harbor->shouldNotHaveReceived('propagateCustomer');

            return $harbor;
        });

        $customer = new CustomerFactory()->createOneQuietly([
            'customer_number' => 1,
        ]);

        new CustomerAddressFactory()->createQuietly([
            'customer_id' => $customer->id,
        ]);

        $anonymizeCustomerAction = self::resolve(AnonymizeCustomerAction::class);
        $anonymizeCustomerAction->execute($customer);

        $customer->refresh();

        self::assertSame('anonymized-first_name-1', $customer->first_name);
    }

    private function getMockedAMQPConnection(): AMQPStreamConnection
    {
        $channel = Mockery::spy(AMQPChannel::class);
        $channel->shouldReceive('basic_publish')->andReturnNull();
        $channel->shouldReceive('queue_bind')->andReturnNull();
        $channel->shouldReceive('close')->andReturnNull();
        $channel->shouldReceive('is_open')->andReturnTrue();

        $connection = Mockery::mock(AMQPStreamConnection::class);
        $connection->shouldReceive('channel')->andReturn($channel);
        $connection->shouldReceive('close')->andReturnNull();
        $connection->shouldReceive('isConnected')->andReturnTrue();
        $connection->shouldReceive('isBlocked')->andReturnFalse();

        return $connection;
    }
}
