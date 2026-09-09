<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaCancelAndCreditSubscriptionsAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Subscriptions\Actions\CancelSubscriptionsAction;
use Waterfront\Domain\Subscriptions\DTO\Cancellation;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaCancelAndCreditSubscriptionsAction::class)]
#[AllowMockObjectsWithoutExpectations]
class CancelAndCreditSubscriptionsTest extends IntegrationTestCase
{
    private Customer $customer;

    private ProductGroup $productGroup;

    private Product $product;

    private TranslatorInterface $translator;

    private InvoiceRepository $invoiceRepository;

    private SubscriptionRepository $subscriptionPartnerRepository;

    private CreditSubscriptionService&MockObject $creditSubscriptionService;

    private CancelSubscriptionsAction&MockObject $cancelSubscriptionsAction;

    private ProductSpecRepository $productSpecRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $this->productGroup = new ProductGroupFactory()->dns()->createOne();
        $this->product = new ProductFactory()->for($this->productGroup)->createOne();

        $this->translator = self::resolve(TranslatorInterface::class);
        $this->subscriptionPartnerRepository = self::resolve(SubscriptionRepository::class);
        $this->invoiceRepository = self::resolve(InvoiceRepository::class);
        $this->creditSubscriptionService = self::createMock(CreditSubscriptionService::class);
        $this->cancelSubscriptionsAction = self::createMock(CancelSubscriptionsAction::class);
        $this->productSpecRepository = self::resolve(ProductSpecRepository::class);
    }

    /**
     * @param array<int, array{'status': string, 'end_date': CarbonImmutable}> $subscriptions
     *
     * @throws Exception
     */
    #[DataProvider('dataAlreadyCancelled')]
    #[Test]
    public function cancellingAlreadyCancelledSubscriptionsFails(
        array $subscriptions,
    ): void {
        $fields = $this->getActionFields(
            reason: SubscriptionCancelReason::REASON_CANCELLATION,
            reasonOther: null,
            type: SubscriptionCancelType::CANCEL_END_DATE,
            typeOtherDate: null,
            credit: false
        );

        $subscriptionsCollection = new Collection();
        foreach ($subscriptions as $data) {
            $subscriptionsCollection->add(
                $this->getSubscription($data['status'], $data['end_date'])
            );
        }

        $this->creditSubscriptionService
            ->expects(self::never())
            ->method('creditSubscriptions');

        $this->cancelSubscriptionsAction
            ->expects(self::never())
            ->method('execute');

        $result = $this->runActionHandle(
            $fields,
            $subscriptionsCollection
        );

        self::assertEmpty($result['message'] ?? '');
        self::assertNotEmpty($result['danger'] ?? '');
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function dataAlreadyCancelled(): iterable
    {
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $statusActive = AdministrativeStatus::ACTIVE->value;
        $statusArchived = AdministrativeStatus::ARCHIVED->value;
        $statusArchiving = AdministrativeStatus::ARCHIVING->value;

        yield 'single subscription already archived' => [
            'subscriptions' => [
                [
                    'status' => $statusArchived,
                    'end_date' => $now->addMonths(2),
                ],
            ],
        ];

        yield 'single subscription already archiving' => [
            'subscriptions' => [
                [
                    'status' => $statusArchiving,
                    'end_date' => $now,
                ],
            ],
        ];

        yield 'multiple subscriptions but one is already archived' => [
            'subscriptions' => [
                [
                    'status' => $statusActive,
                    'end_date' => $now->addMonths(2),
                ],
                [
                    'status' => $statusArchived,
                    'end_date' => $now->addMonths(2),
                ],
            ],
        ];

        yield 'multiple subscriptions but one is already archiving' => [
            'subscriptions' => [
                [
                    'status' => $statusActive,
                    'end_date' => $now->addMonths(2),
                ],
                [
                    'status' => $statusArchiving,
                    'end_date' => $now,
                ],
            ],
        ];
    }

    /**
     *
     * @param array<int, array{
     *      status: string,
     *      end_date: CarbonImmutable,
     *      selected: bool,
     *      expected: bool
     * }> $existingSubscriptionsData
     * @param array{
     *      reason: SubscriptionCancelReason,
     *      reason_other: ?string,
     *      type: SubscriptionCancelType,
     *      typeOtherDate: ?string,
     *      credit: bool
     * } $actionFieldsData
     *
     * @throws Exception
     */
    #[DataProvider('handleData')]
    #[Test]
    public function handleMustExecuteWithCorrectCancellation(
        CarbonImmutable $now,
        array $existingSubscriptionsData,
        array $actionFieldsData,
        CarbonImmutable $expectedCancellationEndDate,
        bool $expectCredit,
    ): void {
        CarbonImmutable::setTestNow($now);

        $fields = $this->getActionFields(...array_values($actionFieldsData));

        $existingSubscriptions = new Collection();
        $selectedSubscriptions = new Collection();
        $expectedSubscriptionsInCancellation = new Collection();
        foreach ($existingSubscriptionsData as $data) {
            $subscription = $this->getSubscription($data['status'], $data['end_date']);
            $existingSubscriptions->add($subscription);

            if ($data['selected']) {
                $selectedSubscriptions->add($subscription);
            }

            if ($data['expected']) {
                $expectedSubscriptionsInCancellation->add($subscription);
            }
        }

        $this->creditSubscriptionService
            ->expects($expectCredit ? self::once() : self::never())
            ->method('creditSubscriptions')
            ->with(self::callback(
                static fn (Cancellation $cancellation): bool
                => $cancellation->getSubscriptions()->count() === $expectedSubscriptionsInCancellation->count()
                    && $expectedSubscriptionsInCancellation->diff($cancellation->getSubscriptions())->count() === 0
                    && $cancellation->getCancelReason() === $actionFieldsData['reason']
                    && $cancellation->getCancelReasonOther() === $actionFieldsData['reason_other']
                    && $cancellation->getCancelType() === $actionFieldsData['type']
                    && $cancellation->shouldCreditRelatedInvoices() === $actionFieldsData['credit']
                    && $cancellation->getSelectedCancellationEndDate()?->format(DateTimeFormat::DATE)
                    === $expectedCancellationEndDate->format(DateTimeFormat::DATE)
                    && $cancellation->shouldCreditRelatedInvoices() === $expectCredit
            ));

        $this->cancelSubscriptionsAction
            ->expects(self::once())
            ->method('execute')
            ->with(self::callback(
                static fn (Cancellation $cancellation): bool
                     => $cancellation->getSubscriptions()->count() === $expectedSubscriptionsInCancellation->count()
                        && $expectedSubscriptionsInCancellation->diff($cancellation->getSubscriptions())->count() === 0
                        && $cancellation->getCancelReason() === $actionFieldsData['reason']
                        && $cancellation->getCancelReasonOther() === $actionFieldsData['reason_other']
                        && $cancellation->getCancelType() === $actionFieldsData['type']
                        && $cancellation->shouldCreditRelatedInvoices() === $actionFieldsData['credit']
                        && $cancellation->getSelectedCancellationEndDate()?->format(DateTimeFormat::DATE)
                            === $expectedCancellationEndDate->format(DateTimeFormat::DATE)
                        && $cancellation->shouldCreditRelatedInvoices() === $expectCredit
            ));

        $result = $this->runActionHandle(
            $fields,
            $selectedSubscriptions
        );

        self::assertNotEmpty($result['message'] ?? '');
        self::assertEmpty($result['danger'] ?? '');
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function handleData(): iterable
    {
        $now = CarbonImmutable::now();

        $statusActive = AdministrativeStatus::ACTIVE->value;

        $otherDate = $now->subDays(10);
        yield 'Two subscriptions, other date selection, with credit' => [
            'now' => $now,
            'existingSubscriptionsData' => [
                [
                    'status' => $statusActive,
                    'end_date' => $now->addMonths(2),
                    'selected' => true,
                    'expected' => true,
                ], [
                    'status' => $statusActive,
                    'end_date' => $now->addMonths(2),
                    'selected' => true,
                    'expected' => true,
                ],
            ],
            'actionFieldsData' => [
                'reason' => SubscriptionCancelReason::REASON_WET_VAN_DAM,
                'reason_other' => null,
                'type' => SubscriptionCancelType::CANCEL_OTHER,
                'type_other_date' => $otherDate->format(DateTimeFormat::DATE),
                'credit' => true,
            ],
            'expectedCancellationEndDate' => $otherDate,
            'expectCredit' => true,
        ];
    }

    #[Test]
    public function selectingParentSubscriptionMustSelectedActiveChildAsWell(): void
    {
        $now = CarbonImmutable::now();
        $endDate = $now->addMonths(2);
        CarbonImmutable::setTestNow($now);

        $statusActive = AdministrativeStatus::ACTIVE->value;
        $statusCanceled = AdministrativeStatus::CANCELED->value;

        $parentSubscription = $this->getSubscription(
            $statusActive,
            $endDate
        );

        $childSubscriptionActive = $this->getSubscription($statusActive, $endDate);
        $parentSubscription->children()->save($childSubscriptionActive);

        $childSubscriptionAlreadyCanceled = $this->getSubscription($statusCanceled, $endDate);
        $parentSubscription->children()->save($childSubscriptionAlreadyCanceled);

        $actionFields = $this->getActionFields(
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_END_DATE,
            null,
            false
        );

        $parentSubscription->refresh();
        $childSubscriptionActive->refresh();
        $childSubscriptionAlreadyCanceled->refresh();

        $expectedSubscriptionsToCancel = [$parentSubscription->uuid, $childSubscriptionActive->uuid, $childSubscriptionAlreadyCanceled->uuid];

        $this->creditSubscriptionService
            ->expects(self::never())
            ->method('creditSubscriptions');

        $this->cancelSubscriptionsAction
            ->expects(self::once())
            ->method('execute')
            ->with(self::callback(
                static fn (Cancellation $cancellation): bool
                    => $cancellation->getSubscriptions()->count() === 3
                        && in_array($cancellation->getSubscriptions()[0]?->uuid, $expectedSubscriptionsToCancel, true)
                        && in_array($cancellation->getSubscriptions()[1]?->uuid, $expectedSubscriptionsToCancel, true)
                        && in_array($cancellation->getSubscriptions()[2]?->uuid, $expectedSubscriptionsToCancel, true)
            ));

        $this->runActionHandle(
            $actionFields,
            new Collection([$parentSubscription])
        );
    }

    #[DataProvider('allowCancellationAsChildProvider')]
    #[Test]
    public function subscriptionWithCancelWithParentSpec(bool $allowCancelAsChild): void
    {
        $nlDomain = new ProductFactory()
            ->nlDomain();

        $parentSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($nlDomain)
            ->createOne();

        $premiumDnsProduct = new ProductFactory()
            ->premiumDns($this->productGroup)
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                    'value' => $allowCancelAsChild,
                ])
            )
            ->createOne();

        $childSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($premiumDnsProduct)
            ->parentSubscription($parentSubscription)
            ->createOne();

        $actionFields = $this->getActionFields(
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_END_DATE,
            null,
            false
        );

        $result = $this->runActionHandle(
            $actionFields,
            new Collection([$childSubscription])
        );

        if ($allowCancelAsChild) {
            /**
             * if the PRODUCT_ALLOW_CANCEL_AS_CHILD spec is true, we should not see an error.
             * We can cancel the child subscription without cancelling the parent subscription.
             */
            $message = $result['message'];
            self::assertInstanceOf(Message::class, $message);
            self::assertNull($result['danger']);
            self::assertSame('nova-action.cancel_subscriptions.success', $message->text);
        } else {
            /**
             * if the PRODUCT_ALLOW_CANCEL_AS_CHILD spec is false, we should see an error.
             * We cannot cancel the child subscription without cancelling the parent subscription.
             */
            $danger = $result['danger'];
            self::assertInstanceOf(Message::class, $danger);
            self::assertSame('nova-action.cancel_subscriptions.failure_cancel_with_parent', $danger->text);
            self::assertNull($result['message']);
        }
    }

    public static function allowCancellationAsChildProvider(): Generator
    {
        yield 'Allow cancellation as child true' => [true];
        yield 'Allow cancellation as child false' => [false];
    }

    #[Test]
    public function subscriptionWithMicrosoft365ChildWithAllowCancelChildSpec(): void
    {
        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();

        $parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);

        $childProduct = new ProductFactory()->for($productGroup)
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                    'value' => '1',
                ])
            )
            ->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        $parentSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($parentProduct)
            ->createOne();

        $childSubscriptions = new SubscriptionFactory()
            ->for($this->customer)
            ->for($childProduct)
            ->parentSubscription($parentSubscription)
            ->createMany(2);

        $actionFields = $this->getActionFields(
            SubscriptionCancelReason::REASON_CANCELLATION,
            null,
            SubscriptionCancelType::CANCEL_END_DATE,
            null,
            false
        );

        $result = $this->runActionHandle(
            $actionFields,
            new Collection([$childSubscriptions->firstOrFail()])
        );

        $message = $result['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.cancel_subscriptions.success', $message->text);
        self::assertNull($result['danger']);
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
                'start_date' => $endDate->subMonths(12),
                'end_date' => $endDate,
                'next_billing_date' => $endDate,
                'administrative_status' => $administrativeStatus,
                'gross_price' => 100,
                'net_price' => 100,
            ]);
    }

    private function getActionFields(
        SubscriptionCancelReason $reason,
        ?string $reasonOther,
        SubscriptionCancelType $type,
        ?string $typeOtherDate,
        bool $credit
    ): ActionFields {
        return new ActionFields(
            new Collection([
                'reason' => $reason->value,
                'reason_other' => $reasonOther,
                'type' => $type->value,
                'type_other_date' => $typeOtherDate,
                'credit' => $credit,
            ]),
            new Collection([])
        );
    }

    /**
     * @param Collection<int, Subscription> $collection
     *
     * @throws Exception
     */
    private function runActionHandle(
        ActionFields $fields,
        Collection $collection
    ): ActionResponse {
        $action = new NovaCancelAndCreditSubscriptionsAction(
            $this->translator,
            $this->subscriptionPartnerRepository,
            $this->invoiceRepository,
            $this->creditSubscriptionService,
            $this->cancelSubscriptionsAction,
            $this->productSpecRepository,
            self::createStub(LoggerInterface::class)
        );

        $result = $action->handle($fields, $collection);
        self::assertInstanceOf(ActionResponse::class, $result);
        return $result;
    }
}
