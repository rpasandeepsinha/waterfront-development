<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\CancelSubscriptionsController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Services\CreditAndDispatchInvoiceLinesToHarbor;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Actions\CancelSubscriptionsAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Infra\Common\DateTimeFormat;

#[CoversClass(CancelSubscriptionsController::class)]
class CancelSubscriptionsCheckControllerTest extends IntegrationTestCase
{
    private const int INVOICE_LINE_NET_PRICE = 12_000;

    private const int INVOICE_LINE_PERIOD_IN_DAYS = 365;

    private Customer $customer;

    private ProductGroup $productGroup;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::now()->startOfDay());

        $this->customer = new CustomerFactory()->createOne();
        $this->productGroup = new ProductGroupFactory()->dns()->createOne();
        $this->product = new ProductFactory()->for($this->productGroup)->createOne();

        $creditAndDispatchInvoiceLinesToHarbor = self::createMock(CreditAndDispatchInvoiceLinesToHarbor::class);
        $creditAndDispatchInvoiceLinesToHarbor->expects(self::never())->method('creditAndDispatch');
        $this->app->bind(
            CreditAndDispatchInvoiceLinesToHarbor::class,
            fn (): CreditAndDispatchInvoiceLinesToHarbor => $creditAndDispatchInvoiceLinesToHarbor,
        );

        $cancelSubscriptionsAction = self::createMock(CancelSubscriptionsAction::class);
        $cancelSubscriptionsAction->expects(self::never())->method('execute');
        $this->app->bind(
            CancelSubscriptionsAction::class,
            fn (): CancelSubscriptionsAction => $cancelSubscriptionsAction,
        );
    }

    #[Test]
    public function checkReturnsTheSelectionWithoutInvoicesWhenNothingHasBeenFilledInYet(): void
    {
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), ['subscription_uuids' => [
                $subscription->uuid,
            ]])
            ->assertOk()
            ->assertJson([
                'subscriptions' => [
                    [
                        'id' => $subscription->id,
                        'uuid' => $subscription->uuid,
                        'domain' => $subscription->domain,
                        'administrative_status' => AdministrativeStatus::ACTIVE->value,
                        'parent_subscription_id' => null,
                    ],
                ],
                'blocking_problems' => [],
                'credit_allowed' => false,
                'credit_applied' => false,
                'creditable_invoice_lines' => [],
                'credit_invoice_lines' => [],
                'credit_total' => 0,
            ])
            ->assertJsonCount(1, 'subscriptions');
    }

    #[Test]
    public function checkAddsDependentSubscriptions(): void
    {
        $parentSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();
        $childSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne(['parent_subscription_id' => $parentSubscription->id]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), ['subscription_uuids' => [
                $parentSubscription->uuid,
            ]])
            ->assertOk()
            ->assertJsonCount(2, 'subscriptions')
            ->assertJson([
                'subscriptions' => [
                    ['id' => $parentSubscription->id, 'parent_subscription_id' => null],
                    ['id' => $childSubscription->id, 'parent_subscription_id' => $parentSubscription->id],
                ],
            ]);
    }

    #[DataProvider('archivedStatusProvider')]
    #[Test]
    public function checkLeavesOutDependentSubscriptionsThatAreAlreadyArchived(string $status): void
    {
        $parentSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();
        new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne([
                'parent_subscription_id' => $parentSubscription->id,
                'administrative_status' => $status,
            ]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), ['subscription_uuids' => [
                $parentSubscription->uuid,
            ]])
            ->assertOk()
            ->assertJsonCount(1, 'subscriptions')
            ->assertJsonPath('subscriptions.0.id', $parentSubscription->id);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function archivedStatusProvider(): Generator
    {
        yield 'archived' => [AdministrativeStatus::ARCHIVED->value];
        yield 'archiving' => [AdministrativeStatus::ARCHIVING->value];
    }

    #[Test]
    public function checkPreviewsTheCreditInvoiceLinesThatWouldBeGenerated(): void
    {
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne([
                'end_date' => CarbonImmutable::now()->addDays(self::INVOICE_LINE_PERIOD_IN_DAYS),
            ]);
        $invoiceLine = new InvoiceFactory()
            ->for($this->customer)
            ->for($this->product)
            ->for($subscription)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addDays(self::INVOICE_LINE_PERIOD_IN_DAYS),
                'net_price' => self::INVOICE_LINE_NET_PRICE,
                'parent_invoice_id' => null,
            ]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), [
                'subscription_uuids' => [$subscription->uuid],
                'reason' => SubscriptionCancelReason::REASON_CANCELLATION->value,
                'type' => SubscriptionCancelType::CANCEL_OTHER->value,
                'type_other_date' => CarbonImmutable::now()->format(DateTimeFormat::DATE),
                'credit' => true,
            ])
            ->assertOk()
            ->assertJson([
                'credit_allowed' => true,
                'credit_applied' => true,
                'creditable_invoice_lines' => [
                    [
                        'subscription_id' => $subscription->id,
                        'invoice_line_id' => $invoiceLine->id,
                        'net_price' => self::INVOICE_LINE_NET_PRICE,
                    ],
                ],
                'credit_invoice_lines' => [
                    [
                        'subscription_id' => $subscription->id,
                        'invoice_line_id' => $invoiceLine->id,
                        'net_price' => -self::INVOICE_LINE_NET_PRICE,
                    ],
                ],
                'credit_total' => -self::INVOICE_LINE_NET_PRICE,
            ]);
    }

    #[Test]
    public function checkOnlyListsCreditableInvoiceLinesWhenCreditingIsNotRequested(): void
    {
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne([
                'end_date' => CarbonImmutable::now()->addDays(self::INVOICE_LINE_PERIOD_IN_DAYS),
            ]);
        new InvoiceFactory()
            ->for($this->customer)
            ->for($this->product)
            ->for($subscription)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addDays(self::INVOICE_LINE_PERIOD_IN_DAYS),
                'net_price' => self::INVOICE_LINE_NET_PRICE,
                'parent_invoice_id' => null,
            ]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), [
                'subscription_uuids' => [$subscription->uuid],
                'reason' => SubscriptionCancelReason::REASON_CANCELLATION->value,
                'type' => SubscriptionCancelType::CANCEL_OTHER->value,
                'type_other_date' => CarbonImmutable::now()->format(DateTimeFormat::DATE),
                'credit' => false,
            ])
            ->assertOk()
            ->assertJson([
                'credit_allowed' => true,
                'credit_applied' => false,
                'credit_invoice_lines' => [],
                'credit_total' => 0,
            ])
            ->assertJsonCount(1, 'creditable_invoice_lines');
    }

    #[DataProvider('creditingNotAllowedProvider')]
    #[Test]
    public function checkReportsThatCreditingIsNotPossibleAndShowsNoInvoiceLines(
        SubscriptionCancelReason $reason,
        SubscriptionCancelType $type,
    ): void {
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne([
                'end_date' => CarbonImmutable::now()->addDays(self::INVOICE_LINE_PERIOD_IN_DAYS),
            ]);
        new InvoiceFactory()
            ->for($this->customer)
            ->for($this->product)
            ->for($subscription)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addDays(self::INVOICE_LINE_PERIOD_IN_DAYS),
                'net_price' => self::INVOICE_LINE_NET_PRICE,
                'parent_invoice_id' => null,
            ]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), [
                'subscription_uuids' => [$subscription->uuid],
                'reason' => $reason->value,
                'type' => $type->value,
                'type_other_date' => $type === SubscriptionCancelType::CANCEL_OTHER
                    ? CarbonImmutable::now()->format(DateTimeFormat::DATE)
                    : null,
                'credit' => true,
            ])
            ->assertOk()
            ->assertJson([
                'credit_allowed' => false,
                'credit_applied' => false,
                'creditable_invoice_lines' => [],
                'credit_invoice_lines' => [],
                'credit_total' => 0,
            ]);
    }

    /**
     * @return Generator<string, array{SubscriptionCancelReason, SubscriptionCancelType}>
     */
    public static function creditingNotAllowedProvider(): Generator
    {
        yield 'cancelling on the end date' => [
            SubscriptionCancelReason::REASON_CANCELLATION,
            SubscriptionCancelType::CANCEL_END_DATE,
        ];

        yield 'cancelling because of a transfer' => [
            SubscriptionCancelReason::REASON_TRANSFER,
            SubscriptionCancelType::CANCEL_OTHER,
        ];

        yield 'cancelling because of bad debt' => [
            SubscriptionCancelReason::REASON_BAD_DEBT,
            SubscriptionCancelType::CANCEL_OTHER,
        ];

        yield 'cancelling because the domain was transferred away' => [
            SubscriptionCancelReason::REASON_DOMAIN_TRANSFERRED_AWAY,
            SubscriptionCancelType::CANCEL_OTHER,
        ];
    }

    #[Test]
    public function checkReturnsTheHighestEndDateOfTheAffectedSubscriptionsAsMaxSelectableDate(): void
    {
        $earliest = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne(['end_date' => CarbonImmutable::now()->addDays(30)]);
        $latest = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne(['end_date' => CarbonImmutable::now()->addDays(300)]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), ['subscription_uuids' => [
                $earliest->uuid,
                $latest->uuid,
            ]])
            ->assertOk()
            ->assertJsonPath('max_selectable_end_date', $latest->end_date->format(DateTimeFormat::DATE));
    }

    #[Test]
    public function checkReportsSubscriptionsOfMultipleCustomersAsABlockingProblem(): void
    {
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();
        $otherCustomersSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for(new CustomerFactory()->createOne())
            ->createOne();

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), [
                'subscription_uuids' => [$subscription->uuid, $otherCustomersSubscription->uuid],
            ])
            ->assertOk()
            ->assertJsonPath('blocking_problems', [
                ['subscription_id' => null, 'message' => 'subscription.cancel.failure_multi_customers'],
            ])
            ->assertJsonCount(2, 'subscriptions');
    }

    #[DataProvider('nonCancellableStatusesProvider')]
    #[Test]
    public function checkReportsANonCancellableSubscriptionAsABlockingProblemAndStillListsIt(string $status): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->createOne(['administrative_status' => $status]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), ['subscription_uuids' => [
                $subscription->uuid,
            ]])
            ->assertOk()
            ->assertJsonPath('blocking_problems', [
                ['subscription_id' => $subscription->id, 'message' => 'subscription.cancel.failure_non_cancellable'],
            ])
            ->assertJson([
                'subscriptions' => [
                    [
                        'id' => $subscription->id,
                        'domain' => $subscription->domain,
                        'product' => ['name' => $this->product->name],
                        'administrative_status' => $status,
                    ],
                ],
            ]);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function nonCancellableStatusesProvider(): Generator
    {
        yield 'archiving' => [AdministrativeStatus::ARCHIVING->value];
        yield 'archived' => [AdministrativeStatus::ARCHIVED->value];
        yield 'expired' => [AdministrativeStatus::EXPIRED->value];
    }

    #[Test]
    public function checkReportsAProblemPerBlockedSubscription(): void
    {
        $cancellable = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();
        $archived = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->createOne(['administrative_status' => AdministrativeStatus::ARCHIVED->value]);
        $expired = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->createOne(['administrative_status' => AdministrativeStatus::EXPIRED->value]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), [
                'subscription_uuids' => [$cancellable->uuid, $archived->uuid, $expired->uuid],
            ])
            ->assertOk()
            ->assertJsonPath('blocking_problems', [
                ['subscription_id' => $archived->id, 'message' => 'subscription.cancel.failure_non_cancellable'],
                ['subscription_id' => $expired->id, 'message' => 'subscription.cancel.failure_non_cancellable'],
            ]);
    }

    #[Test]
    public function checkReportsAChildThatMayNotBeCancelledSeparatelyAsABlockingProblem(): void
    {
        $parentSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();
        $childProduct = new ProductFactory()->for($this->productGroup)->createOne();
        new ProductSpecFactory()
            ->disable(ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD)
            ->for($childProduct)
            ->createOne();
        $childSubscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($childProduct)
            ->for($this->customer)
            ->createOne(['parent_subscription_id' => $parentSubscription->id]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), ['subscription_uuids' => [
                $childSubscription->uuid,
            ]])
            ->assertOk()
            ->assertJsonPath('blocking_problems', [
                [
                    'subscription_id' => $childSubscription->id,
                    'message' => 'subscription.cancel.failure_cancel_with_parent',
                ],
            ])
            ->assertJsonPath('subscriptions.0.id', $childSubscription->id)
            ->assertJsonPath('subscriptions.0.product.name', $childProduct->name);
    }

    #[Test]
    public function checkSkipsTheCreditPreviewForABlockedSelection(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->createOne([
                'administrative_status' => AdministrativeStatus::ARCHIVED->value,
                'end_date' => CarbonImmutable::now()->addDays(self::INVOICE_LINE_PERIOD_IN_DAYS),
            ]);
        new InvoiceFactory()
            ->for($this->customer)
            ->for($this->product)
            ->for($subscription)
            ->createOne([
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addDays(self::INVOICE_LINE_PERIOD_IN_DAYS),
                'net_price' => self::INVOICE_LINE_NET_PRICE,
                'parent_invoice_id' => null,
            ]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), [
                'subscription_uuids' => [$subscription->uuid],
                'reason' => SubscriptionCancelReason::REASON_CANCELLATION->value,
                'type' => SubscriptionCancelType::CANCEL_OTHER->value,
                'type_other_date' => CarbonImmutable::now()->format(DateTimeFormat::DATE),
                'credit' => true,
            ])
            ->assertOk()
            ->assertJson([
                'credit_allowed' => false,
                'credit_applied' => false,
                'creditable_invoice_lines' => [],
                'credit_invoice_lines' => [],
                'credit_total' => 0,
            ]);
    }

    #[Test]
    public function checkRequiresADateWhenCancellingOnAnotherDate(): void
    {
        $subscription = new SubscriptionFactory()
            ->administrativeStatusActive()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.subscriptions.cancel-and-credit.check'), [
                'subscription_uuids' => [$subscription->uuid],
                'reason' => SubscriptionCancelReason::REASON_CANCELLATION->value,
                'type' => SubscriptionCancelType::CANCEL_OTHER->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('type_other_date');
    }
}
