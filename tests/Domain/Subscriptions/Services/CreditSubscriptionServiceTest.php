<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines;
use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;
use SortDirection;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Harbor\Services\HarborApiClient\HarborApi;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\DTO\Cancellation;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;

#[CoversClass(CreditSubscriptionService::class)]
#[AllowMockObjectsWithoutExpectations]
class CreditSubscriptionServiceTest extends IntegrationTestCase
{
    private CarbonImmutable $now;

    private CarbonImmutable $endDate;

    private Customer $customer;

    private Product $product;

    private HarborApi&MockObject $harborApi;

    private CreditSubscriptionService $creditSubscriptionService;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        // We are testing on a yearly subscription, exactly half way the period
        $this->now = new CarbonImmutable('today 00:00:00');
        $this->endDate = $this->now->addMonths(6);
        CarbonImmutable::setTestNow($this->now);

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $this->product = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();

        $this->harborApi = self::createMock(HarborApi::class);
        $this->app->bind(HarborApi::class, fn () => $this->harborApi);

        $this->creditSubscriptionService = self::resolve(CreditSubscriptionService::class);

        // Acting user required for audit logging (notes)
        $this->actingAsEmployee();
    }

    #[Test]
    public function creditShouldCreditCorrectRemainder(): void
    {
        // Setup
        $subscription = $this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate,
        );

        $originalInvoice = $this->createInvoiceLine($subscription, $this->endDate, 100);

        $cancelTypeOtherDate = $this->now;
        $cancellation = new Cancellation(
            new Collection([$subscription]),
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_DOWNGRADE,
            $cancelTypeOtherDate,
            true,
        );

        $this->harborApi->expects(self::once())->method('sendCredit');

        // Run service
        $this->creditSubscriptionService->creditSubscriptions($cancellation);

        $invoices = Invoice::query()->where('subscription_id', $subscription->id)->orderBy('id')->get();

        /* We now expect 1 invoice lines:
         * 0 is the original; 100
         * 1 is the credited line (partially in half); -50 */
        self::assertCount(2, $invoices);
        self::assertSame($originalInvoice->id, $invoices[0]?->id);
        self::assertSame(-50, $invoices[1]?->net_price);
    }

    #[Test]
    public function creditWithInvoiceAfterSubscriptionEndDateShouldCreditCorrectPeriodAndRemainder(): void
    {
        // Setup
        $subscription = $this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate,
        );

        /* The invoice is created for a 12-month period with a start date calculated on the given end date.
         * We give an end date here 13 months in the future, so we will get an invoice starting one month
         * after the end date of the subscription. */
        $originalInvoice = $this->createInvoiceLine($subscription, $this->endDate->addMonths(13), 100);

        $cancelTypeOtherDate = $this->now;
        $cancellation = new Cancellation(
            new Collection([$subscription]),
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_DOWNGRADE,
            $cancelTypeOtherDate,
            true,
        );

        $this->harborApi->expects(self::once())->method('sendCredit');

        // Run service
        $this->creditSubscriptionService->creditSubscriptions($cancellation);

        $invoices = Invoice::query()->where('subscription_id', $subscription->id)->orderBy('id')->get();

        /* We now expect 1 invoice lines:
         * 0 is the original
         * 1 is the credited line; -100 */
        self::assertCount(2, $invoices);
        self::assertSame($originalInvoice->id, $invoices[0]?->id);
        self::assertSame(-100, $invoices[1]?->net_price);

        // The start date of the credit must be same as original
    }

    /**
     * @return iterable<string,array<string,mixed>>
     */
    public static function fullCreditIsEnforcedByReasonTypeData(): iterable
    {
        yield 'full credit expected for REASON_REVOCATION' => [
            'reason' => SubscriptionCancelReason::REASON_REVOCATION,
            'fullCredit' => true,
        ];

        yield 'full credit expected for REASON_DISSATISFIED' => [
            'reason' => SubscriptionCancelReason::REASON_DISSATISFIED,
            'fullCredit' => true,
        ];

        yield 'full credit expected for REASON_FAILURE' => [
            'reason' => SubscriptionCancelReason::REASON_FAILURE,
            'fullCredit' => true,
        ];

        yield 'full credit expected for REASON_CANCELLATION_RENEWAL' => [
            'reason' => SubscriptionCancelReason::REASON_CANCELLATION_RENEWAL,
            'fullCredit' => true,
        ];

        yield 'full credit not expected for REASON_CANCELLATION' => [
            'reason' => SubscriptionCancelReason::REASON_CANCELLATION,
            'fullCredit' => false,
        ];

        yield 'full credit not expected for REASON_LAW_VAN_DAM' => [
            'reason' => SubscriptionCancelReason::REASON_WET_VAN_DAM,
            'fullCredit' => false,
        ];
    }

    #[DataProvider('fullCreditIsEnforcedByReasonTypeData')]
    #[Test]
    public function testCreditWhereFullCreditIsEnforcedByReasonType(
        SubscriptionCancelReason $reason,
        bool $fullCredit,
    ): void {
        // Setup
        $subscription = $this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate,
        );

        $originalInvoice = $this->createInvoiceLine($subscription, $this->endDate, 100);

        $cancelTypeOtherDate = $this->now;
        $cancellation = new Cancellation(
            new Collection([$subscription]),
            $reason,
            null,
            SubscriptionCancelType::CANCEL_DOWNGRADE,
            $cancelTypeOtherDate,
            true,
        );

        $this->harborApi->expects(self::once())->method('sendCredit');

        // Run service
        $this->creditSubscriptionService->creditSubscriptions($cancellation);

        $invoices = Invoice::query()->where('subscription_id', $subscription->id)->orderBy('id')->get();

        /* We now expect 2 invoice lines:
         * 0 is the original; 100
         * 1 is the credited line full or the remainder of 6 months */
        self::assertCount(2, $invoices);
        self::assertSame($originalInvoice->id, $invoices[0]?->id);

        if ($fullCredit) {
            self::assertSame(-100, $invoices[1]?->net_price);
            self::assertSame($invoices[0]->start_date->timestamp, $invoices[1]->start_date->timestamp);
        } else {
            self::assertSame(-50, $invoices[1]?->net_price);
            self::assertSame($cancelTypeOtherDate->timestamp, $invoices[1]->start_date->timestamp);
        }
    }

    #[Test]
    public function creditButInvoiceLineIsForBeforeCancellationEndDate(): void
    {
        // Setup
        $subscription = $this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate,
        );

        $originalInvoice = $this->createInvoiceLine($subscription, $this->now->addMonths(4), 100);

        $cancelTypeOtherDate = $this->now->addMonths(5);
        $cancellation = new Cancellation(
            new Collection([$subscription]),
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_OTHER,
            $cancelTypeOtherDate,
            true,
        );

        $this->harborApi->expects(self::never())->method('sendCredit');

        // Run service
        $this->creditSubscriptionService->creditSubscriptions($cancellation);

        $invoices = Invoice::query()->where('subscription_id', $subscription->id)->orderBy('id')->get();

        /* We now expect only 1 invoice line:
         * Only the original */
        self::assertCount(1, $invoices);
        self::assertSame($originalInvoice->id, $invoices[0]?->id);
    }

    #[Test]
    public function creditButInvoiceLinesAlreadyCredited(): void
    {
        // Setup
        $subscription = $this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate,
        );

        $debitInvoiceLine = $this->createInvoiceLine($subscription, $this->endDate, 100);
        $creditInvoiceLine = $this->createInvoiceLine($subscription, $this->endDate, -100, $debitInvoiceLine);

        $cancelTypeOtherDate = $this->now;
        $cancellation = new Cancellation(
            new Collection([$subscription]),
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_DOWNGRADE,
            $cancelTypeOtherDate,
            true,
        );

        $this->harborApi->expects(self::never())->method('sendCredit');

        // Run service
        $this->creditSubscriptionService->creditSubscriptions($cancellation);

        $invoices = Invoice::query()->where('subscription_id', $subscription->id)->orderBy('id')->get();

        /* We now expect only 2 invoice line:
         * Only the original and already existing credit line */
        self::assertCount(2, $invoices);
        self::assertSame($debitInvoiceLine->id, $invoices[0]?->id);
        self::assertSame($creditInvoiceLine->id, $invoices[1]?->id);
    }

    #[Test]
    public function creditShouldCreditVoucherAsWell(): void
    {
        // Setup
        $subscription = $this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate,
        );

        $originalInvoice = $this->createInvoiceLine($subscription, $this->endDate, 100);
        $originalVoucherInvoice = $this->createInvoiceLine($subscription, $this->endDate, -10);

        $cancelTypeOtherDate = $this->now;
        $cancellation = new Cancellation(
            new Collection([$subscription]),
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_DOWNGRADE,
            $cancelTypeOtherDate,
            true,
        );

        $this->harborApi->expects(self::once())->method('sendCredit');

        // Run service
        $this->creditSubscriptionService->creditSubscriptions($cancellation);

        $invoices = Invoice::query()->where('subscription_id', $subscription->id)->orderBy('id')->get();

        /* We now expect 4 invoice lines:
         * 2 are the original; 100 and -10
         * 2 are the credited lines (partially in half); -50 and 5 */
        self::assertCount(4, $invoices);
        self::assertSame($originalInvoice->id, $invoices[0]?->id);
        self::assertSame($originalVoucherInvoice->id, $invoices[1]?->id);
        self::assertSame(-50, $invoices[2]?->net_price);
        self::assertSame(5, $invoices[3]?->net_price);
    }

    #[Test]
    public function getInvoiceLinesToCreditBatchFromCancellation(): void
    {
        $subscription1 = $this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate,
        );

        $subscription2 = $this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate,
        );

        $invoiceLine1 = $this->createInvoiceLine($subscription1, $this->endDate, 100);
        $this->createInvoiceLine($subscription1, $this->endDate, -50, $invoiceLine1);
        $this->createInvoiceLine($subscription2, $this->endDate, 200);

        $cancelTypeOtherDate = $this->now;
        $cancellation = new Cancellation(
            new Collection([$subscription1, $subscription2]),
            SubscriptionCancelReason::REASON_REVOCATION,
            null,
            SubscriptionCancelType::CANCEL_DOWNGRADE,
            $cancelTypeOtherDate,
            true,
        );

        $batch = $this->creditSubscriptionService->getInvoiceLinesToCreditBatch($cancellation);

        /* We now expect 2 credit invoice lines:
         * One (already partially credited); -50 and -200 */
        $invoicesToCredit = $batch->getInvoicesToCredit();
        self::assertSame(2, $batch->count());
        self::assertSame(50, $invoicesToCredit[0]->getAmountToCredit());
        self::assertSame(200, $invoicesToCredit[1]->getAmountToCredit());
    }

    #[Test]
    public function getInvoiceLinesToCreditBatchFromSpecificDate(): void
    {
        $subscription = $this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate,
        );

        $debitInvoice = $this->createInvoiceLine(
            $subscription,
            $this->endDate,
            100,
        );

        $discountInvoice = $this->createInvoiceLine(
            $subscription,
            $this->endDate,
            -10,
        );

        $batch = $this->creditSubscriptionService->getInvoiceLinesToCreditBatchFromDate(
            subscription: $subscription,
            creditFromDate: $this->now,
            cancelReason: SubscriptionCancelReason::REASON_REVOCATION,
        );

        $invoicesToCredit = $batch->getInvoicesToCredit();

        self::assertCount(2, $invoicesToCredit);
        self::assertSame(
            $debitInvoice->id,
            $invoicesToCredit[0]->getInvoice()->id,
        );

        self::assertSame(
            100,
            $invoicesToCredit[0]->getAmountToCredit(),
        );

        self::assertSame(
            $discountInvoice->id,
            $invoicesToCredit[1]->getInvoice()->id,
        );

        self::assertSame(
            -10,
            $invoicesToCredit[1]->getAmountToCredit(),
        );
    }

    /**
     * @return iterable<string,array<string,mixed>>
     */
    public static function cancelReasonToCreditReason(): iterable
    {
        yield 'mapping to credit reason CANCELLATION for cancel reason REASON_REVOCATION' => [
            'cancelReason' => SubscriptionCancelReason::REASON_REVOCATION,
            'creditReason' => InvoiceLineCreditReason::REASON_CANCELLATION,
        ];

        yield 'mapping to credit reason CANCELLATION for cancel reason REASON_DISSATISFIED' => [
            'cancelReason' => SubscriptionCancelReason::REASON_DISSATISFIED,
            'creditReason' => InvoiceLineCreditReason::REASON_CANCELLATION,
        ];

        yield 'mapping to credit reason CANCELLATION for cancel reason REASON_FAILURE' => [
            'cancelReason' => SubscriptionCancelReason::REASON_FAILURE,
            'creditReason' => InvoiceLineCreditReason::REASON_CANCELLATION,
        ];

        yield 'mapping to credit reason CANCELLATION for cancel reason REASON_CANCELLATION_RENEWAL' => [
            'cancelReason' => SubscriptionCancelReason::REASON_CANCELLATION_RENEWAL,
            'creditReason' => InvoiceLineCreditReason::REASON_CANCELLATION,
        ];

        yield 'mapping to credit reason CANCELLATION for cancel reason REASON_CANCELLATION' => [
            'cancelReason' => SubscriptionCancelReason::REASON_CANCELLATION,
            'creditReason' => InvoiceLineCreditReason::REASON_CANCELLATION,
        ];

        yield 'mapping to credit reason CANCELLATION for cancel reason REASON_WET_VAN_DAM' => [
            'cancelReason' => SubscriptionCancelReason::REASON_WET_VAN_DAM,
            'creditReason' => InvoiceLineCreditReason::REASON_CANCELLATION,
        ];

        yield 'mapping to credit reason CANCELLATION for cancel reason REASON_OTHER' => [
            'cancelReason' => SubscriptionCancelReason::REASON_OTHER,
            'creditReason' => InvoiceLineCreditReason::REASON_CANCELLATION,
        ];

        yield 'mapping to credit reason CANCELLATION for cancel reason REASON_BAD_DEBT' => [
            'cancelReason' => SubscriptionCancelReason::REASON_BAD_DEBT,
            'creditReason' => InvoiceLineCreditReason::REASON_CANCELLATION,
        ];

        yield 'mapping to credit reason CANCELLATION for cancel reason REASON_DOMAIN_TRANSFERRED_AWAY' => [
            'cancelReason' => SubscriptionCancelReason::REASON_DOMAIN_TRANSFERRED_AWAY,
            'creditReason' => InvoiceLineCreditReason::REASON_CANCELLATION,
        ];

        yield 'mapping to credit reason CANCELLATION for cancel reason REASON_TRANSFER' => [
            'cancelReason' => SubscriptionCancelReason::REASON_TRANSFER,
            'creditReason' => InvoiceLineCreditReason::REASON_CANCELLATION,
        ];

        yield 'mapping to credit reason REASON_ABUSE for cancel reason REASON_ABUSE' => [
            'cancelReason' => SubscriptionCancelReason::REASON_ABUSE,
            'creditReason' => InvoiceLineCreditReason::REASON_ABUSE,
        ];
    }

    #[DataProvider('cancelReasonToCreditReason')]
    #[Test]
    public function testCreditInvoiceLinesHaveCreditReason(
        SubscriptionCancelReason $cancelReason,
        InvoiceLineCreditReason $creditReason,
    ): void {
        $subscription = $this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate,
        );

        $this->createInvoiceLine($subscription, $this->endDate, 100);

        $cancelTypeOtherDate = $this->now;
        $cancellation = new Cancellation(
            new Collection([$subscription]),
            $cancelReason,
            null,
            SubscriptionCancelType::CANCEL_OTHER,
            $cancelTypeOtherDate,
            true,
        );

        $this->harborApi
            ->expects(self::once())
            ->method('sendCredit')
            ->with(self::callback(function (DebtorInvoiceLines $message) use ($creditReason): bool {
                $lines = $message->getInvoiceLines();

                self::assertCount(1, $lines);
                self::assertSame(
                    $creditReason->value,
                    iterator_to_array($lines->getIterator())[0]->getCreditReason()?->value,
                );

                return true;
            }));

        $this->creditSubscriptionService->creditSubscriptions($cancellation);

        $invoices = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->orderBy('id', SortDirection::Descending)
            ->get();

        self::assertCount(2, $invoices);
        self::assertSame($creditReason, $invoices[0]?->credit_reason);
    }

    #[Test]
    public function cancelTypeOtherWithCreditButSubscriptionIsJustRenewedAheadOfTime(): void
    {
        // Setup
        // Subscription has just been renewed, 6 days ahead.
        $this->endDate = $this->now->addDays(6)->addMonths(12);
        $subscription = $this->getSubscription(
            AdministrativeStatus::ACTIVE->value,
            $this->endDate,
        );

        $beforeRenewalInvoice = $this->createInvoiceLine($subscription, $this->endDate->subMonths(12), 100);
        $afterRenewalInvoice = $this->createInvoiceLine($subscription, $this->endDate, 200);

        $cancelTypeOtherDate = $this->now;
        $cancellation = new Cancellation(
            new Collection([$subscription]),
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_OTHER,
            $cancelTypeOtherDate,
            true,
        );

        $this->harborApi->expects(self::once())->method('sendCredit');

        // Run service
        $this->creditSubscriptionService->creditSubscriptions($cancellation);

        $invoices = Invoice::query()->where('subscription_id', $subscription->id)->orderBy('id')->get();

        /* We only expect the renewal invoice to be credited */
        self::assertCount(4, $invoices);
        self::assertSame($beforeRenewalInvoice->id, $invoices[0]?->id);
        self::assertSame($afterRenewalInvoice->id, $invoices[1]?->id);

        self::assertNotNull($invoices[2]?->id);
        self::assertSame($cancelTypeOtherDate->timestamp, $invoices[2]->start_date->timestamp);
        self::assertLessThan(0, $invoices[2]->net_price);

        self::assertNotNull($invoices[3]?->id);
        self::assertSame($afterRenewalInvoice->start_date->timestamp, $invoices[3]->start_date->timestamp);
        self::assertLessThan(0, $invoices[3]->net_price);
    }

    private function getSubscription(
        string $administrativeStatus,
        CarbonImmutable $endDate,
    ): Subscription {
        return new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->product)
            ->createOneQuietly([
                'contract_period' => 12,
                'billing_period' => 12,
                'start_date' => $endDate->subMonths(24),
                'end_date' => $endDate,
                'next_billing_date' => $endDate,
                'administrative_status' => $administrativeStatus,
                'gross_price' => 100,
                'net_price' => 100,
            ]);
    }

    private function createInvoiceLine(
        Subscription $subscription,
        CarbonImmutable $endDate,
        int $price,
        ?Invoice $parentInvoiceLine = null,
    ): Invoice {
        return new InvoiceFactory()
            ->for($subscription)
            ->for($subscription->customer)
            ->for($subscription->product)
            ->createOneQuietly([
                'start_date' => $endDate->subMonths(12),
                'end_date' => $endDate,
                'net_price' => $price,
                'sent_to_harbor_at' => $endDate,
                'parent_invoice_id' => $parentInvoiceLine?->id,
            ]);
    }
}
