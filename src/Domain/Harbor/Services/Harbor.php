<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services;

use Carbon\CarbonImmutable;
use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtCollectionStatusUpdated;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use SandwaveIo\HarborMessages\Message\DebtorSsoUrl;
use SandwaveIo\HarborMessages\Message\InvoicePaymentAnnouncement;
use SandwaveIo\HarborMessages\Message\MandateAnnouncement;
use SandwaveIo\HarborMessages\Message\Serializer\JsonSerializer;
use SandwaveIo\HarborMessages\Message\Serializer\SerializerException;
use SandwaveIo\HarborMessages\Message\WithdrawInvoicePaymentAnnouncement;
use Throwable;
use Waterfront\Domain\Customers\Exceptions\InvalidCountryCodeException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Harbor\DTO\Message\InvoiceLineMessageConfig;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;
use Waterfront\Domain\Harbor\Services\Message\Handler\DebtCollectionStatusUpdatedHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\DebtorSsoUrlHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\InvoicePaymentAnnouncementHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\MandateAnnouncementHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\WithdrawInvoicePaymentAnnouncementHandler;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class Harbor implements CommunicatesWithHarbor
{
    public function __construct(
        private readonly JsonSerializer $jsonSerializer,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly MessageService $messageService,
        private readonly CustomerVatService $vatService,
        private readonly ConfigurationInterface $configuration,
        private readonly DebtorSsoUrlHandler $debtorSsoUrlHandler,
        private readonly InvoicePaymentAnnouncementHandler $invoicePaymentAnnouncementHandler,
        private readonly WithdrawInvoicePaymentAnnouncementHandler $withdrawInvoicePaymentAnnouncementHandler,
        private readonly DebtCollectionStatusUpdatedHandler $debtCollectionStatusUpdatedHandler,
        private readonly MandateAnnouncementHandler $messageAnnouncementHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws InvoiceLineToHarborException
     * @throws InvalidCountryCodeException
     * @throws SerializerException
     */
    public function propagateInvoice(Invoice $invoice): void
    {
        if (! $this->configuration->getAsBoolean('harbor.enabled')) {
            $this->logger->warning(
                'Not propagating invoice {invoice_line.wf_id} as Harbor is currently disabled.',
                [
                    LoggingContextKeys::INVOICE_LINE_ID => $invoice->id,
            ]
            );
            return;
        }

        $this->logger->debug(
            'Getting information to propagate invoice {invoice_line.wf_id} to Harbor.',
            [
                LoggingContextKeys::INVOICE_LINE_ID => $invoice->id,
            ]
        );

        if ($invoice->exists === false) {
            throw InvoiceLineToHarborException::invoiceNotStoredInWaterfront(
                $invoice->customer_id,
                $invoice->subscription_id
            );
        }

        $customer = $invoice->customer;
        $subscription = $invoice->subscription;
        $product = $invoice->product;
        $prepaidReference = $invoice->prepaid_reference ?? $this->invoiceRepository->findPrepaidPayment($invoice->paid, $subscription);

        if ($invoice->vat_code === null || $invoice->vat_rate === null) {
            $customerVatDTO = $this->vatService->getCustomerVatData($customer);

            $invoice->vat_code = $customerVatDTO->vatCode;
            $invoice->vat_rate = $customerVatDTO->vatRate;
        }

        $message = $this->messageService->build($customer, [$invoice], [new InvoiceLineMessageConfig(
            invoice: $invoice,
            product: $product,
            subscription: $subscription,
            prepaidReference: $prepaidReference,
        )]);

        $this->logger->debug(
            'Propagating invoice {invoice_line.wf_id} to Harbor.',
            [
                LoggingContextKeys::INVOICE_LINE_ID => $invoice->id,
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription?->id,
            ]
        );

        $this->sendMessage($message);

        $this->logger->info(
            'Successfully propagated invoice {invoice_line.wf_id} to Harbor.',
            [
                LoggingContextKeys::INVOICE_LINE_ID => $invoice->id,
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription?->id,
            ]
        );

        $invoice->sent_to_harbor_at = CarbonImmutable::now();
        $invoice->save();
    }

    /**
     * @throws SerializerException
     * @throws InvoiceLineToHarborException
     */
    public function propagateCustomer(Customer $customer): void
    {
        if (! $this->configuration->getAsBoolean('harbor.enabled')) {
            $this->logger->warning(
                'Not propagating customer {customer.id} as Harbor is currently disabled.',
                [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
            ]
            );
            return;
        }

        $this->logger->debug(
            'Getting information to propagate customer {customer.id} to Harbor.',
            [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
            ]
        );

        $message = $this->buildMessageForCustomerOnly($customer);

        $this->logger->debug(
            'Propagating customer {customer.id} to Harbor.',
            [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
            ]
        );

        $this->sendMessage($message);

        $this->logger->info(
            'Successfully propagated customer {customer.id} to Harbor.',
            [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function amqpConnection(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            $this->configuration->getAsString('harbor.host'),
            $this->configuration->getAsInteger('harbor.port'),
            $this->configuration->getAsString('harbor.user'),
            $this->configuration->getAsString('harbor.password'),
            $this->configuration->getAsString('harbor.vhost')
        );
    }

    public function receiveMessage(AMQPMessage $message): void
    {
        try {
            $decodedMessage = $this->jsonSerializer->decode($message->getBody());

            switch (true) {
                case $decodedMessage instanceof DebtorSsoUrl:
                    $this->debtorSsoUrlHandler->handle($decodedMessage);
                    break;
                case $decodedMessage instanceof InvoicePaymentAnnouncement:
                    $this->invoicePaymentAnnouncementHandler->handle($decodedMessage);
                    break;
                case $decodedMessage instanceof WithdrawInvoicePaymentAnnouncement:
                    $this->withdrawInvoicePaymentAnnouncementHandler->handle($decodedMessage);
                    break;
                case $decodedMessage instanceof DebtCollectionStatusUpdated:
                    $this->debtCollectionStatusUpdatedHandler->handle($decodedMessage);
                    break;
                case $decodedMessage instanceof MandateAnnouncement:
                    $this->messageAnnouncementHandler->handle($decodedMessage);
                    break;
                case $decodedMessage instanceof DebtorInvoiceLines:
                    break;
                default:
                    $this->logger->error(sprintf(
                        'No handler defined for received message: %s',
                        $message::class
                    ));
            }

            $message->ack();
        } catch (SerializerException $exception) {
            $this->logger->error(
                'Failed deserializing HarborMessage: {exception.message}',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            throw $exception;
        } catch (Throwable $throwable) {
            $this->logger->error(
                'Failed to process received message HarborMessage: {exception.message}',
                [
                    LoggingContextKeys::EXCEPTION => $throwable,
                ]
            );
            throw $throwable;
        }
    }

    /**
     * @throws SerializerException
     * @throws InvoiceLineToHarborException
     * @throws Exception
     */
    private function sendMessage(DebtorInvoiceLines $message): void
    {
        $connection = $this->amqpConnection();
        $serializedMessage = $this->jsonSerializer->encode($message);

        $channel = $connection->channel();
        $channel->queue_bind(queue: $this->configuration->getAsString('harbor.queue_incoming'), exchange: $this->configuration->getAsString('harbor.exchange'));

        try {
            $channel->basic_publish(
                msg: new AMQPMessage(
                    body: $serializedMessage,
                    properties: ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
                ),
                exchange: 'messages',
            );
        } catch (Throwable $exception) {
            if ($channel->is_open()) {
                $channel->close();
            }

            if ($connection->isConnected()) {
                $connection->close();
            }

            /** @var InvoiceLine $invoiceLine */
            $invoiceLine = $message->getInvoiceLines()->toArray()[0];

            throw InvoiceLineToHarborException::propagationToHarbourException(
                $invoiceLine->getWaterfrontInvoiceId(),
                $exception,
                $channel->is_open(),
                $connection->isConnected(),
                $connection->isBlocked()
            );
        }

        if ($channel->is_open()) {
            $channel->close();
        }

        if ($connection->isConnected()) {
            $connection->close();
        }
    }

    /**
     * @throws InvoiceLineToHarborException
     */
    private function buildMessageForCustomerOnly(Customer $customer): DebtorInvoiceLines
    {
        return $this->messageService->build($customer, []);
    }
}
