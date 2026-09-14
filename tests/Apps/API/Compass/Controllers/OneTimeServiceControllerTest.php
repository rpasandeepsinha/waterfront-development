<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\OneTimeServiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\OneTimeServiceController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceInvoiceService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(OneTimeServiceController::class)]
class OneTimeServiceControllerTest extends IntegrationTestCase
{
    private OneTimeService $oneTimeService;

    private Customer $customer;

    private Subscription $subscription;

    private Product $oneTimeServiceProduct;

    protected function setUp(): void
    {
        parent::setUp();

        // OneTimeServiceInvoiceService walks Invoice->subscription while queueing to Harbor.
        Model::preventLazyLoading(false);

        CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-20 09:00:00'));

        $this->customer = new CustomerFactory()
            ->withAddress()
            ->createOne([
                'vat_rate' => 21.0,
                'icp' => false,
                'has_direct_debit' => true,
            ]);

        $subscriptionProduct = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($subscriptionProduct)
            ->createOne();

        $this->oneTimeServiceProduct = new ProductFactory()->for(
            new ProductGroupFactory()->oneTimeService(),
        )->createOne();

        new ProductPriceComponentFactory()
            ->oneTimeService()
            ->for($this->oneTimeServiceProduct)
            ->createOne(['price' => 2500]);

        $this->oneTimeService = new OneTimeServiceFactory()
            ->for($this->oneTimeServiceProduct)
            ->for($this->subscription)
            ->for($this->customer)
            ->createOne();
    }

    #[Test]
    public function showSuccess(): void
    {
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.one-time-services.show', [
                'oneTimeService' => $this->oneTimeService->uuid,
            ]))
            ->assertOk()
            ->assertJsonPath('uuid', $this->oneTimeService->uuid->toString());
    }

    #[Test]
    public function showReturnsNotFoundForUnknownUuid(): void
    {
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.one-time-services.show', [
                'oneTimeService' => Uuid::uuid4()->toString(),
            ]))
            ->assertNotFound();
    }

    #[Test]
    public function invoiceCreatesInvoicesAndQueuesThemToHarbor(): void
    {
        $messageService = self::createMock(MessageService::class);
        $messageService->expects(self::once())->method('queue');
        $this->app->bind(MessageService::class, fn (): MessageService => $messageService);

        $this->actingAsEmployee()
            ->postJson($this->invoiceRoute())
            ->assertOk()
            ->assertJsonPath('data.uuid', $this->oneTimeService->uuid->toString())
            ->assertJsonPath('data.invoiced', true);

        $invoices = $this->oneTimeService->invoices()->get();

        self::assertCount(1, $invoices);
        self::assertSame(2500, $invoices->sole()->gross_price);
        self::assertSame(2500, $invoices->sole()->net_price);
        self::assertSame($this->subscription->id, $invoices->sole()->subscription_id);
    }

    #[Test]
    public function invoiceRejectsAnAlreadyInvoicedOneTimeService(): void
    {
        $messageService = self::createMock(MessageService::class);
        $messageService->expects(self::never())->method('queue');
        $this->app->bind(MessageService::class, fn (): MessageService => $messageService);

        $existingInvoice = new InvoiceFactory()
            ->for($this->customer)
            ->for($this->subscription)
            ->for($this->oneTimeServiceProduct)
            ->createOne();
        $this->oneTimeService->invoices()->attach($existingInvoice);

        $this->actingAsEmployee()
            ->postJson($this->invoiceRoute())
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => self::resolve(TranslatorInterface::class)
                    ->translate('one_time_service.invoice.already_invoiced'),
            ]);

        self::assertCount(1, $this->oneTimeService->invoices()->get());
        self::assertCount(1, Invoice::all());
    }

    #[Test]
    public function invoiceReturnsNotFoundForUnknownUuid(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.one-time-services.invoice', [
                'oneTimeService' => Uuid::uuid4()->toString(),
            ]))
            ->assertNotFound();

        self::assertCount(0, Invoice::all());
    }

    #[Test]
    public function storeWithoutInvoicingCreatesAnUninvoicedOneTimeService(): void
    {
        $expectedComment = 'Migration fee';

        $response = $this->actingAsEmployee()
            ->postJson($this->storeRoute(), $this->payload(['comment' => $expectedComment]))
            ->assertCreated();

        $created = $this->createdOneTimeService();

        $response
            ->assertJsonPath('data.uuid', $created->uuid->toString())
            ->assertJsonPath('data.invoiced', false)
            ->assertJsonPath('data.amount', 2)
            ->assertJsonPath('data.discount_percentage', 10)
            ->assertJsonPath('data.status', OneTimeServiceStatus::OPEN->value)
            ->assertJsonPath('data.product.uuid', $this->oneTimeServiceProduct->uuid)
            ->assertJsonPath('data.subscription.uuid', $this->subscription->uuid);

        self::assertSame(2, $created->amount);
        self::assertSame(10, $created->discount_percentage);
        self::assertSame(2500, $created->gross_price);
        self::assertCount(0, $created->invoices()->get());
        self::assertCount(0, Invoice::all());

        self::assertDatabaseHas('notes', [
            'subscription_id' => $this->subscription->id,
            'note' => sprintf(
                'One-time service created for %s x2 with 10%% discount: %s',
                $this->oneTimeServiceProduct->name,
                $expectedComment,
            ),
        ]);
    }

    #[Test]
    public function storeWithoutCommentStillCreatesTheOneTimeService(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->storeRoute(), $this->payload(['comment' => null]))
            ->assertCreated();

        self::assertDatabaseHas('notes', [
            'subscription_id' => $this->subscription->id,
            'note' => sprintf(
                'One-time service created for %s x2 with 10%% discount: ',
                $this->oneTimeServiceProduct->name,
            ),
        ]);
    }

    #[Test]
    public function storeWithInvoiceNowCreatesInvoicesAndQueuesThemToHarbor(): void
    {
        $messageService = self::createMock(MessageService::class);
        $messageService->expects(self::once())->method('queue');
        $this->app->bind(MessageService::class, fn (): MessageService => $messageService);

        $this->actingAsEmployee()
            ->postJson($this->storeRoute(), $this->payload(['invoice_now' => true]))
            ->assertCreated()
            ->assertJsonPath('data.invoiced', true);

        $created = $this->createdOneTimeService();
        $invoices = $created->invoices()->get();

        // Amount 2 results in two identical invoice lines.
        self::assertCount(2, $invoices);
        foreach ($invoices as $invoice) {
            self::assertSame(2500, $invoice->gross_price);
            self::assertSame(2250, $invoice->net_price);
            self::assertSame($this->subscription->id, $invoice->subscription_id);
        }
    }

    #[Test]
    public function storeLeavesTheOneTimeServiceUninvoicedWhenInvoicingFails(): void
    {
        $invoiceService = self::createStub(OneTimeServiceInvoiceService::class);
        $invoiceService->method('createFromCollection')->willThrowException(new RuntimeException('Harbor is down'));
        $this->app->bind(OneTimeServiceInvoiceService::class, fn (): OneTimeServiceInvoiceService => $invoiceService);

        $this->actingAsEmployee()
            ->postJson($this->storeRoute(), $this->payload(['invoice_now' => true]))
            ->assertServerError();

        $created = $this->createdOneTimeService();

        self::assertCount(0, $created->invoices()->get());
        self::assertCount(0, Invoice::all());
    }

    #[Test]
    public function storeWorksForACancelledSubscription(): void
    {
        $cancelledSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->subscription->product)
            ->administrativeStatusCancelled()
            ->createOne();

        $this->actingAsEmployee()
            ->postJson($this->storeRoute($cancelledSubscription), $this->payload())
            ->assertCreated();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[Test]
    #[DataProvider('invalidPayloads')]
    public function storeRejectsInvalidInput(array $overrides, string $expectedInvalidField): void
    {
        $this->actingAsEmployee()
            ->postJson($this->storeRoute(), $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor($expectedInvalidField);

        self::assertCount(1, OneTimeService::all());
    }

    /**
     * @return iterable<string, array{overrides: array<string, mixed>, expectedInvalidField: string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'amount below one' => [
            'overrides' => ['amount' => 0],
            'expectedInvalidField' => 'amount',
        ];

        yield 'negative discount percentage' => [
            'overrides' => ['discount_percentage' => -1],
            'expectedInvalidField' => 'discount_percentage',
        ];

        yield 'discount percentage above hundred' => [
            'overrides' => ['discount_percentage' => 101],
            'expectedInvalidField' => 'discount_percentage',
        ];

        yield 'execution date in the past' => [
            'overrides' => ['execution_date' => '2026-08-19'],
            'expectedInvalidField' => 'execution_date',
        ];

        yield 'unknown status' => [
            'overrides' => ['status' => 'cancelled'],
            'expectedInvalidField' => 'status',
        ];

        yield 'unknown product' => [
            'overrides' => ['product_uuid' => '00000000-0000-4000-8000-000000000000'],
            'expectedInvalidField' => 'product_uuid',
        ];
    }

    #[Test]
    public function storeRejectsAProductOutsideTheOneTimeServiceProductGroup(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->storeRoute(), $this->payload(['product_uuid' => $this->subscription->product->uuid]))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('product_uuid');

        self::assertCount(1, OneTimeService::all());
    }

    #[Test]
    public function previewReturnsTheInvoiceLinesThatWouldBeCreated(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->previewRoute(), $this->payload())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.subscriptionId')
            ->assertJsonPath('data.0.domain', $this->subscription->domain)
            ->assertJsonPath('data.0.amount', 2)
            // Minor units: 2500 gross with 10% discount.
            ->assertJsonPath('data.0.price', 2250)
            ->assertJsonPath(
                'data.0.title',
                fn (mixed $title): bool => (
                    is_string($title) && str_contains($title, $this->oneTimeServiceProduct->name)
                ),
            );

        self::assertCount(1, OneTimeService::all());
        self::assertCount(0, Invoice::all());
    }

    #[Test]
    public function previewRejectsInvalidInput(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->previewRoute(), $this->payload(['amount' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('amount');
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'product_uuid' => $this->oneTimeServiceProduct->uuid,
            'amount' => 2,
            'discount_percentage' => 10,
            'execution_date' => '2026-08-21',
            'status' => OneTimeServiceStatus::OPEN->value,
            'invoice_now' => false,
            'comment' => null,
            ...$overrides,
        ];
    }

    private function storeRoute(?Subscription $subscription = null): string
    {
        return $this->generateRoute(
            'admin.subscriptions.subscription.one-time-services.store',
            ['subscription' => ($subscription ?? $this->subscription)->id],
        );
    }

    private function invoiceRoute(): string
    {
        return $this->generateRoute(
            'admin.one-time-services.invoice',
            ['oneTimeService' => $this->oneTimeService->uuid],
        );
    }

    private function previewRoute(): string
    {
        return $this->generateRoute(
            'admin.subscriptions.subscription.one-time-services.preview',
            ['subscription' => $this->subscription->id],
        );
    }

    private function createdOneTimeService(): OneTimeService
    {
        return OneTimeService::where('id', '!=', $this->oneTimeService->id)->sole();
    }
}
