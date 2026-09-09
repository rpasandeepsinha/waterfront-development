<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Message;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\DTO\Message\InvoiceLineMessageConfig;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Queue\HarborQueue;

#[CoversClass(MessageService::class)]
#[AllowMockObjectsWithoutExpectations]
class MessageBuilderTest extends IntegrationTestCase
{
    private const array DEBTOR_REQUIRED_CUSTOMER_VALUES = [
        'locale'          => 'nl-NL',
        'phone_number'    => '+31 113643281',
        'customer_number' => 123,
    ];

    private MessageService $messageService;

    private MockObject&HarborQueue $harborQueue;

    public function setUp(): void
    {
        parent::setUp();

        $this->harborQueue = self::createMock(HarborQueue::class);
        $this->app->bind(HarborQueue::class, fn () => $this->harborQueue);

        $this->messageService = self::resolve(MessageService::class);
    }

    #[Test]
    public function buildingWithCustomerThatHasNoAddressThrowsInvoiceLineToHarborException(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        $invoices = [
            new InvoiceFactory()->for($customer)->for($product)->createOne(),
            new InvoiceFactory()->for($customer)->for($product)->createOne(),
            new InvoiceFactory()->for($customer)->for($product)->createOne(),
        ];

        self::expectException(InvoiceLineToHarborException::class);
        self::expectExceptionMessageIs(
            sprintf(
                'The Address for customer with name %s and id %s is not set!',
                $customer->name,
                $customer->id,
            )
        );

        $this->messageService->build($customer, $invoices);
    }

    #[Test]
    public function buildingWithEmptyArrayReturnsEmptyItemsInDebtorInvoiceLines(): void
    {
        $customer = new CustomerFactory()
            ->withAddress()
            ->createOne(self::DEBTOR_REQUIRED_CUSTOMER_VALUES);

        $message = $this->messageService->build($customer, []);

        self::assertSame($customer->customer_number, $message->getDebtor()->getCustomerNumber());
        self::assertEmpty($message->getInvoiceLines());
    }

    /**
     * CONTEXT: If you don't pass an array of InvoiceLineMessageConfig instances,
     * it should create one itself based on the current Invoice(line) that's being processed,
     * with the correct values.
     */
    #[Test]
    public function buildingWithoutConfigInstancesAssumesCorrectConfig(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        $customer = new CustomerFactory()->withAddress()->createOne(self::DEBTOR_REQUIRED_CUSTOMER_VALUES);
        $subscription = new SubscriptionFactory()->for($customer)->for($product)->createOne();

        $invoices = [
            new InvoiceFactory()
                ->for($customer)
                ->for($subscription)
                ->for($product)
                ->createOne([
                    'credit_reason'     => InvoiceLineCreditReason::REASON_OTHER,
                ]),
        ];

        $message = $this->messageService->build($customer, $invoices);
        $invoiceLinesMessages = iterator_to_array($message->getInvoiceLines()->getIterator());
        $invoiceLineMessage = reset($invoiceLinesMessages);
        $invoice = reset($invoices);

        self::assertInstanceOf(InvoiceLine::class, $invoiceLineMessage);

        self::assertSame($invoice->id, $invoiceLineMessage->getWaterfrontInvoiceId());
        self::assertSame($product->id, $invoiceLineMessage->getProductId());
        self::assertSame($subscription->id, $invoiceLineMessage->getSubscriptionId());

        self::assertSame($invoice->start_date->format(DateTimeFormat::DATE), $invoiceLineMessage->getStartDate());
        self::assertSame($invoice->end_date->format(DateTimeFormat::DATE), $invoiceLineMessage->getEndDate());
        self::assertSame($invoice->period, $invoiceLineMessage->getPeriodMonths());
        self::assertSame($invoice->title, $invoiceLineMessage->getTitle());
        self::assertSame($invoice->description, $invoiceLineMessage->getDescription());

        self::assertNull($invoiceLineMessage->getPurchaseReference());

        self::assertSame($invoice->group_label, $invoiceLineMessage->getGroupLabel());
        self::assertSame($invoice->gross_price, $invoiceLineMessage->getGrossPrice());
        self::assertSame($invoice->net_price, $invoiceLineMessage->getNetPrice());

        self::assertNull($invoiceLineMessage->getCreditedInvoiceId());

        self::assertSame((string) $invoice->ledger_code, $invoiceLineMessage->getLedgerCode());
        self::assertSame($invoice->vat_code, $invoiceLineMessage->getVatCode());
        self::assertSame($invoice->vat_rate, $invoiceLineMessage->getVatRate());

        self::assertFalse($invoiceLineMessage->isPrepaid());
        self::assertNull($invoiceLineMessage->getPrepaidReference());

        self::assertSame($invoice->type, $invoiceLineMessage->getType());
        self::assertSame($invoice->credit_reason, $invoiceLineMessage->getCreditReason());
    }

    #[Test]
    public function buildsCorrectMessageWhenGivenAConfig(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        $customer = new CustomerFactory()->withAddress()->createOne(self::DEBTOR_REQUIRED_CUSTOMER_VALUES);
        $subscription = new SubscriptionFactory()->for($customer)->for($product)->createOne();

        $invoices = [
            new InvoiceFactory()
                ->for($customer)
                ->for($subscription)
                ->for($product)
                ->createOne([
                    'credit_reason'     => InvoiceLineCreditReason::REASON_CANCELLATION,
                ]),
        ];

        $invoice = reset($invoices);
        $message = $this->messageService->build($customer, $invoices, [
            new InvoiceLineMessageConfig(
                invoice: $invoice,
                product: $product,
                prepaidReference: 'tr_test123',
            ),
        ]);
        $invoiceLinesMessages = iterator_to_array($message->getInvoiceLines()->getIterator());
        $invoiceLineMessage = reset($invoiceLinesMessages);

        self::assertInstanceOf(InvoiceLine::class, $invoiceLineMessage);

        self::assertSame($invoice->id, $invoiceLineMessage->getWaterfrontInvoiceId());
        self::assertSame($invoice->product_id, $invoiceLineMessage->getProductId());

        self::assertSame($invoice->start_date->format(DateTimeFormat::DATE), $invoiceLineMessage->getStartDate());
        self::assertSame($invoice->end_date->format(DateTimeFormat::DATE), $invoiceLineMessage->getEndDate());
        self::assertSame($invoice->period, $invoiceLineMessage->getPeriodMonths());
        self::assertSame($invoice->description, $invoiceLineMessage->getDescription());

        self::assertNull($invoiceLineMessage->getPurchaseReference());

        self::assertSame($invoice->group_label, $invoiceLineMessage->getGroupLabel());
        self::assertSame($invoice->gross_price, $invoiceLineMessage->getGrossPrice());
        self::assertSame($invoice->net_price, $invoiceLineMessage->getNetPrice());

        self::assertNull($invoiceLineMessage->getCreditedInvoiceId());

        self::assertSame((string) $invoice->ledger_code, $invoiceLineMessage->getLedgerCode());
        self::assertSame($invoice->vat_code, $invoiceLineMessage->getVatCode());
        self::assertSame($invoice->vat_rate, $invoiceLineMessage->getVatRate());
        self::assertSame($invoice->paid, $invoiceLineMessage->isPrepaid());
        self::assertNull($invoiceLineMessage->getSubscriptionId());
        self::assertSame('tr_test123', $invoiceLineMessage->getPrepaidReference());
        self::assertSame(InvoiceLineCreditReason::REASON_CANCELLATION, $invoiceLineMessage->getCreditReason());
    }

    #[Test]
    public function queuesAndMarksInvoicesAsSent(): void
    {
        $product = new ProductFactory()->nlDomain()->createOne();
        $customer = new CustomerFactory()->withAddress()->createOne(self::DEBTOR_REQUIRED_CUSTOMER_VALUES);
        $subscription = new SubscriptionFactory()->for($customer)->for($product)->createOne();

        $invoices = [
            new InvoiceFactory()
                ->for($customer)
                ->for($subscription)
                ->for($product)
                ->createOne([
                    'start_date'        => CarbonImmutable::now(),
                    'end_date'          => CarbonImmutable::now()->addYear(),
                    'credit_reason'     => InvoiceLineCreditReason::REASON_CANCELLATION,
                ]),
            new InvoiceFactory()
                ->for($customer)
                ->for($subscription)
                ->for($product)
                ->createOne([
                    'start_date'        => CarbonImmutable::now(),
                    'end_date'          => CarbonImmutable::now()->addYear(),
                    'credit_reason'     => InvoiceLineCreditReason::REASON_CANCELLATION,
                ]),
        ];

        $this->harborQueue->expects(self::once())
            ->method('publish');

        $this->messageService->queue($customer, $invoices);

        foreach ($invoices as $i => $invoice) {
            self::assertNotNull($invoice->sent_to_harbor_at, sprintf('Index %d', $i));
        }
    }
}
