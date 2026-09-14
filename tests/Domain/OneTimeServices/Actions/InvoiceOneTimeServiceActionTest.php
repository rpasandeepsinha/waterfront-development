<?php

declare(strict_types=1);

namespace Tests\Domain\OneTimeServices\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OneTimeServiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\DTO\OneTimeServiceContext;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Services\GrossPriceResolver;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceInvoiceService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductPriceAlternative;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(OneTimeServiceInvoiceService::class)]
#[AllowMockObjectsWithoutExpectations]
class InvoiceOneTimeServiceActionTest extends IntegrationTestCase
{
    private CarbonImmutable $endDate;

    private Customer $customer;

    private Product $subscriptionProduct1;

    private Product $subscriptionProduct2;

    private Product $oneTimeServiceProduct;

    private MessageService&MockObject $messageService;

    private CustomerVatService $vatService;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        // We are testing on a yearly subscription, exactly half way the period
        $now = new CarbonImmutable('today 00:00:00');
        $this->endDate = $now->addMonths(6);
        CarbonImmutable::setTestNow($now);

        $this->customer = new CustomerFactory()
            ->withAddress()
            ->createOne(
                [
                    'vat_rate' => 21.0,
                    'icp' => false,
                ],
            );

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $this->subscriptionProduct1 = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'product-1',
        ]);

        $this->subscriptionProduct2 = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'product-2',
        ]);

        $this->oneTimeServiceProduct = new ProductFactory()->for(
            new ProductGroupFactory()->oneTimeService(),
        )->createOne();
        $price = new ProductPriceComponentFactory()
            ->oneTimeService()
            ->for($this->oneTimeServiceProduct)
            ->createOne([
                'price' => 2500,
            ]);
        $alternativePrice = new ProductPriceAlternative();
        $alternativePrice->product_id = $this->oneTimeServiceProduct->id;
        $alternativePrice->billing_period = $price->billing_period;
        $alternativePrice->contract_period = $price->contract_period;
        $alternativePrice->alternative_product_id = $this->subscriptionProduct2->id;
        $alternativePrice->gross_price = 5000;
        $alternativePrice->save();

        $this->messageService = self::createMock(MessageService::class);
        $this->vatService = self::resolve(CustomerVatService::class);

        // Acting user required for audit logging (notes)
        $this->actingAsEmployee();
    }

    /**
     * Note: this works only because in `.env.testing` the `BILL_ADMINISTRATION_FEES_DAILY_BILLING` is set to `true`.
     */
    #[Test]
    public function createFromCollectionForCustomerWithoutMandateWillAddAdminFeesInvoice(): void
    {
        $customer = CustomerFactory::new()->withAddress()->createOne([
            'has_direct_debit' => false,
        ]);

        $product = ProductFactory::new()->administrationFees()->createOne();
        ProductPriceComponentFactory::new()->administrationFee()->createOne([
            'product_id' => $product->id,
        ]);

        self::assertCount(0, Invoice::all());
        $executionDate = CarbonImmutable::tomorrow();

        $oneTimeService = new OneTimeServiceFactory()
            ->for($this->getSubscription($this->subscriptionProduct1))
            ->for($customer)
            ->for($this->oneTimeServiceProduct)
            ->createOne([
                'amount' => 2,
                'discount_percentage' => 10,
                'gross_price' => 1000,
                'execution_date' => $executionDate,
            ]);

        $oneTimeServices = new Collection([$oneTimeService]);

        $this->messageService->expects(self::exactly(1))->method('queue');

        $this->getOneTimeServiceInvoiceService()->createFromCollection($oneTimeServices);

        $invoices = $oneTimeService->invoices()->get();
        self::assertCount(2, $invoices);

        // Because of the amount 2, we expect 2 exactly the same invoice lines for the same subscription
        foreach ($invoices as $invoice) {
            self::assertSame($oneTimeService->subscription->id, $invoice->subscription_id);
            self::assertSame($this->oneTimeServiceProduct->id, $invoice->product_id);
            self::assertSame($oneTimeService->subscription->domain, $invoice->title);
            self::assertSame($oneTimeService->subscription->domain, $invoice->group_label);
            self::assertStringContainsString($this->oneTimeServiceProduct->name, $invoice->description);
            self::assertSame(InvoiceLine::TYPE_DEFAULT, $invoice->type);
            self::assertSame($executionDate->getTimestamp(), $invoice->start_date->getTimestamp());
            self::assertSame($executionDate->getTimestamp(), $invoice->end_date->getTimestamp());
            self::assertSame(21.0, $invoice->vat_rate);
            self::assertSame(0, $invoice->period);
            self::assertSame(1000, $invoice->gross_price);
            self::assertSame(900, $invoice->net_price);
        }

        self::assertCount(3, Invoice::all());

        self::assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'subscription_id' => null,
            'start_date' => CarbonImmutable::now(),
            'end_date' => CarbonImmutable::now(),
            'product_id' => $product->id,
            'title' => $product->name,
        ]);
    }

    /**
     * @return iterable<string,mixed>
     */
    public static function dataInvoiceLinesToCreate(): iterable
    {
        yield 'Customer with direct debit, does not get admin fees charged' => [
            'adminFeesEnabled' => true,
            'hasDirectDebit' => true,
            'expectedAdminFees' => false,
            'expectedAmountOfInvoices' => 2,
        ];

        yield 'Customer without direct debit, admin fees will be charged' => [
            'adminFeesEnabled' => true,
            'hasDirectDebit' => false,
            'expectedAdminFees' => true,
            'expectedAmountOfInvoices' => 3,
        ];

        yield 'Customer without direct debit, but admin fees disabled' => [
            'adminFeesEnabled' => false,
            'hasDirectDebit' => false,
            'expectedAdminFees' => false,
            'expectedAmountOfInvoices' => 2,
        ];
    }

    /**
     * @param array<string> $subscriptionsByProduct
     * @param array<int>    $expectedNetPrices
     */
    #[DataProvider('dataInvoiceLinesToPreview')]
    #[Test]
    public function getInvoiceLinesPreview(
        array $subscriptionsByProduct,
        int $amount,
        int $discountPercentage,
        ?string $comment,
        int $expectedInvoiceLines,
        array $expectedNetPrices,
    ): void {
        $products = [
            $this->subscriptionProduct1->slug => $this->subscriptionProduct1,
            $this->subscriptionProduct2->slug => $this->subscriptionProduct2,
        ];

        $executionDate = CarbonImmutable::tomorrow();

        $contexts = new Collection();
        foreach ($subscriptionsByProduct as $subscriptionProduct) {
            $contexts->add(
                new OneTimeServiceContext(
                    subscription: $this->getSubscription($products[$subscriptionProduct]),
                    product: $this->oneTimeServiceProduct,
                    amount: $amount,
                    discountPercentage: $discountPercentage,
                    status: OneTimeServiceStatus::OPEN,
                    executionDate: $executionDate,
                    comment: $comment,
                    grossPrice: null,
                ),
            );
        }

        $invoiceLinesPreview = new Collection(
            $this->getOneTimeServiceInvoiceService()->getInvoiceLinesPreview($contexts),
        );
        $invoiceLinesPreview = $invoiceLinesPreview->keyBy('subscriptionId');

        self::assertCount($expectedInvoiceLines, $invoiceLinesPreview);

        foreach ($contexts as $i => $context) {
            $subscription = $context->subscription;

            self::assertTrue($invoiceLinesPreview->has($subscription->id));
            $invoiceLinePreview = $invoiceLinesPreview[$subscription->id];

            self::assertNotNull($invoiceLinePreview);
            self::assertSame($expectedNetPrices[$i], $invoiceLinePreview['price']);
            self::assertSame($amount, $invoiceLinePreview['amount']);
        }
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function dataInvoiceLinesToPreview(): iterable
    {
        yield '1 subscription, amount 1, no discount, expects 1 invoice line' => [
            'subscriptionsByProduct' => ['product-1'],
            'amount' => 1,
            'discountPercentage' => 0,
            'comment' => null,
            'expectedInvoiceLines' => 1,
            'expectedNetPrices' => [2500],
        ];

        yield '1 subscription, amount 1, 10% discount, expects 1 invoice line' => [
            'subscriptionsByProduct' => ['product-1'],
            'amount' => 1,
            'discountPercentage' => 10,
            'comment' => null,
            'expectedInvoiceLines' => 1,
            'expectedNetPrices' => [2250],
        ];

        yield '2 subscriptions, amount 1, no discount, expects 2 invoice lines' => [
            'subscriptionsByProduct' => ['product-1', 'product-1'],
            'amount' => 1,
            'discountPercentage' => 0,
            'comment' => null,
            'expectedInvoiceLines' => 2,
            'expectedNetPrices' => [2500, 2500],
        ];

        yield '2 subscriptions, amount 2, no discount, expects 2 invoice lines' => [
            'subscriptionsByProduct' => ['product-1', 'product-1'],
            'amount' => 2,
            'discountPercentage' => 0,
            'comment' => null,
            'expectedInvoiceLines' => 2,
            'expectedNetPrices' => [2500, 2500],
        ];

        yield '2 subscriptions, amount 2, 10% discount, expects 2 invoice lines' => [
            'subscriptionsByProduct' => ['product-1', 'product-1'],
            'amount' => 2,
            'discountPercentage' => 10,
            'comment' => null,
            'expectedInvoiceLines' => 2,
            'expectedNetPrices' => [2250, 2250],
        ];

        yield '1 subscription (with alternative pricing), amount 1, 10% discount, expects 1 invoice line' => [
            'subscriptionsByProduct' => ['product-2'],
            'amount' => 1,
            'discountPercentage' => 10,
            'comment' => null,
            'expectedInvoiceLines' => 1,
            'expectedNetPrices' => [4500],
        ];

        yield '2 subscriptions (one with alternative pricing), amount 2, no discount, expects 2 invoice lines' => [
            'subscriptionsByProduct' => ['product-1', 'product-2'],
            'amount' => 2,
            'discountPercentage' => 0,
            'comment' => null,
            'expectedInvoiceLines' => 2,
            'expectedNetPrices' => [2500, 5000],
        ];
    }

    private function getOneTimeServiceInvoiceService(): OneTimeServiceInvoiceService
    {
        return new OneTimeServiceInvoiceService(
            self::resolve(GrossPriceResolver::class),
            self::createStub(TranslatorInterface::class),
            $this->vatService,
            $this->messageService,
            self::createStub(LoggerInterface::class),
            self::resolve(AdministrationFeesManager::class),
        );
    }

    private function getSubscription(Product $subscriptionProduct): Subscription
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($subscriptionProduct)
            ->createOneQuietly([
                'contract_period' => 12,
                'billing_period' => 12,
                'start_date' => $this->endDate->subMonths(12),
                'end_date' => $this->endDate,
                'next_billing_date' => $this->endDate,
            ]);

        $this->customer->refresh();
        $this->subscriptionProduct1->refresh();

        return $subscription;
    }
}
