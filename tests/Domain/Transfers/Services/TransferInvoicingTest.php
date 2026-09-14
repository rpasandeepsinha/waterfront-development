<?php

declare(strict_types=1);

namespace Tests\Domain\Transfers\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\CustomerProductDiscountFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Services\ConsolidatedInvoiceCreator;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\VolumeDiscountService;
use Waterfront\Domain\Subscriptions\Jobs\RenewSubscription;
use Waterfront\Domain\Transfers\Interfaces\ExecuteTransferInterface;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferCompletedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCompletedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferCreatedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCreatedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferStartedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferStartedSender;
use Waterfront\Domain\Transfers\Services\TransferService;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Queue\HarborQueue;

#[CoversClass(TransferService::class)]
class TransferInvoicingTest extends IntegrationTestCase
{
    private TransferService $transfers;

    private ExecuteTransferInterface $executeTransfers;

    private Customer $customerWithoutDiscount;

    private Customer $customerWithDiscount;

    private ConsolidatedInvoiceCreator $invoiceCreator;

    private int $renewalDays;

    private int $consolidatingDays;

    public function setUp(): void
    {
        parent::setUp();

        $harborQueue = self::createStub(HarborQueue::class);
        $this->app->bind(HarborQueue::class, fn (): HarborQueue => $harborQueue);

        $this->transfers = self::resolve(TransferService::class);
        $this->executeTransfers = self::resolve(ExecuteTransferInterface::class);
        $this->customerWithDiscount = new CustomerFactory()->withAddress()->createOne();
        $this->customerWithoutDiscount = new CustomerFactory()->withAddress()->createOne();
        $this->invoiceCreator = self::resolve(ConsolidatedInvoiceCreator::class);

        $this->seedProductAndDiscount($this->customerWithDiscount);

        new TemplateFactory()->createMany([
            [
                'slug' => MailTransferCreatedSender::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferCreatedReceiver::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferAcceptedSender::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferAcceptedReceiver::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferStartedSender::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferStartedReceiver::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferCompletedSender::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferCompletedReceiver::getTemplateSlug(),
            ],
        ]);

        $configuration = self::resolve(ConfigurationInterface::class);
        $this->renewalDays = $configuration->getAsInteger('constants.invoice-ahead-days');
        $this->consolidatingDays = $configuration->getAsInteger('constants.invoice-consolidating-days');
    }

    #[Test]
    public function transferToAccountWithoutDiscount(): void
    {
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => Product::where('name', 'DNS')->firstOrFail()->uuid,
                'customer_id' => $this->customerWithDiscount->id,
                'billing_period' => 12,
                'contract_period' => 12,
                'start_date' => $now,
                'end_date' => $now->addYear(),
                'next_billing_date' => $now->addYears(2),
            ]);

        // renew
        self::resolve(Dispatcher::class)->dispatchSync(new RenewSubscription($subscription));

        CarbonImmutable::setTestNow($now->addYear());
        $billingDate = CarbonImmutable::today()
            ->addDays($this->renewalDays + $this->consolidatingDays)
            ->addYear();
        $this->invoiceCreator->invoiceCustomer($this->customerWithDiscount, $billingDate);

        $firstInvoice = Invoice::orderBy('id', 'desc')->firstOrFail();

        self::assertSame(125, $firstInvoice->net_price);

        $collection = new Collection([$subscription]);

        $transfer = $this->transfers->createTransfer(
            $collection,
            $this->customerWithDiscount,
            $this->customerWithoutDiscount,
        );

        $transfer->accept();

        $this->executeTransfers->execute($transfer);

        $subscription->refresh();

        // renew again, after product transfer
        self::resolve(Dispatcher::class)->dispatchSync(new RenewSubscription($subscription));

        CarbonImmutable::setTestNow($now->addYears(2));
        $billingDate = CarbonImmutable::today()
            ->addDays($this->renewalDays + $this->consolidatingDays)
            ->addYears(2);
        $this->invoiceCreator->invoiceCustomer($subscription->customer, $billingDate);

        $secondInvoice = Invoice::orderBy('id', 'desc')->firstOrFail();

        self::assertSame(250, $secondInvoice->net_price);
    }

    #[Test]
    public function transferToAccountWithDiscount(): void
    {
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => Product::where('name', 'DNS')->firstOrFail()->uuid,
                'customer_id' => $this->customerWithoutDiscount->id,
                'billing_period' => 12,
                'contract_period' => 12,
                'start_date' => $now,
                'end_date' => $now->addYear(),
                'next_billing_date' => $now->addYears(2),
            ]);

        // renew
        self::resolve(Dispatcher::class)->dispatchSync(new RenewSubscription($subscription));

        CarbonImmutable::setTestNow($now->addYear());
        $billingDate = CarbonImmutable::today()
            ->addDays($this->renewalDays + $this->consolidatingDays)
            ->addYear();
        $this->invoiceCreator->invoiceCustomer($subscription->customer, $billingDate);

        $firstInvoice = Invoice::orderBy('id', 'desc')->firstOrFail();

        self::assertSame(250, $firstInvoice->net_price);

        $collection = new Collection([$subscription]);

        $transfer = $this->transfers->createTransfer(
            $collection,
            $this->customerWithoutDiscount,
            $this->customerWithDiscount,
        );

        $transfer->accept();

        $this->executeTransfers->execute($transfer);

        $subscription->refresh();

        // renew again, after product transfer
        self::resolve(Dispatcher::class)->dispatchSync(new RenewSubscription($subscription));

        CarbonImmutable::setTestNow($now->addYears(2));
        $billingDate = CarbonImmutable::today()
            ->addDays($this->renewalDays + $this->consolidatingDays)
            ->addYears(2);
        $this->invoiceCreator->invoiceCustomer($subscription->customer, $billingDate);

        $secondInvoice = Invoice::orderBy('id', 'desc')->firstOrFail();

        self::assertSame(125, $secondInvoice->net_price);
    }

    private function seedProductAndDiscount(Customer $discountCustomer): void
    {
        $productDiscountService = self::resolve(VolumeDiscountService::class);
        $group = new ProductGroupFactory()->createOne([
            'slug' => 'dns',
            'name' => 'DNS',
        ]);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $group->id,
            'name' => 'DNS',
            'slug' => ProductType::BASIC_DNS->value,
        ]);
        new ProductPriceComponentFactory()
            ->for($product)
            ->prolongation()
            ->createOne(['price' => 250]);

        $staffel = new ProductDiscountFactory()->for($product)->createOne();
        new CustomerProductDiscountFactory()
            ->for($discountCustomer)
            ->for($staffel)
            ->createOne();
        $staffelPrice = new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::PROLONGATION_STAFFEL,
            'price' => 125,
        ]);
        $productDiscountService->attachPrice($staffel, $staffelPrice);
    }
}
