<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Subscriptions\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaDowngradeAndCreditSubscriptionsAction;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Actions\DowngradeSubscriptionsAction;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Domain\Subscriptions\Services\CreditSubscriptionService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(NovaDowngradeAndCreditSubscriptionsAction::class)]
#[AllowMockObjectsWithoutExpectations]
class DowngradeAndCreditSubscriptionTest extends IntegrationTestCase
{
    private NovaDowngradeAndCreditSubscriptionsAction $action;

    private CreditSubscriptionService&MockObject $creditSubscriptionService;

    private DowngradeSubscriptionsAction&MockObject $downgradeSubscriptionsAction;

    private ProductRepository&MockObject $productRepository;

    private SubscriptionChangeService&MockObject $subscriptionChangeService;

    private LoggerInterface&MockObject $logger;

    private Product $premiumDnsProduct;

    private Product $freeDnsProduct;

    public function setUp(): void
    {
        parent::setUp();

        $this->creditSubscriptionService = self::createMock(CreditSubscriptionService::class);
        $this->downgradeSubscriptionsAction = self::createMock(DowngradeSubscriptionsAction::class);
        $this->productRepository = self::createMock(ProductRepository::class);
        $this->subscriptionChangeService = self::createMock(SubscriptionChangeService::class);
        $this->logger = self::createMock(LoggerInterface::class);

        $this->action = new NovaDowngradeAndCreditSubscriptionsAction(
            translator: self::resolve(TranslatorInterface::class),
            invoiceRepository: self::resolve(InvoiceRepository::class),
            creditSubscriptionService: $this->creditSubscriptionService,
            downgradeSubscriptionsAction: $this->downgradeSubscriptionsAction,
            productRepository: $this->productRepository,
            productAllowedChangeRepository: self::resolve(ProductAllowedChangeRepository::class),
            subscriptionChangeService: $this->subscriptionChangeService,
            logger: $this->logger,
        );

        $dnsProductGroup = ProductGroupFactory::new()->dns()->createOne();
        $this->premiumDnsProduct = ProductFactory::new()->premiumDns($dnsProductGroup)->createOne();
        $this->freeDnsProduct = ProductFactory::new()->freeDns($dnsProductGroup)->createOne();
    }

    #[Test]
    public function subscriptionIsNotDowngradable(): void
    {
        $fields = $this->getActionFields(
            product: $this->freeDnsProduct,
            reason: SubscriptionCancelReason::REASON_CANCELLATION,
            reasonOther: null,
            credit: false
        );
        $subscription = SubscriptionFactory::new()->withCustomer()->for($this->premiumDnsProduct)->createOne([
            'administrative_status' => AdministrativeStatus::ARCHIVED->value,
        ]);

        $subscriptionsCollection = new Collection();
        $subscriptionsCollection->add($subscription);

        $result = $this->action->handle($fields, $subscriptionsCollection);
        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.downgrade_subscription.failure', $danger->text);
    }

    #[Test]
    public function downgradeWithoutCredit(): void
    {
        $fields = $this->getActionFields(
            product: $this->freeDnsProduct,
            reason: SubscriptionCancelReason::REASON_CANCELLATION,
            reasonOther: null,
            credit: false
        );
        $subscription = SubscriptionFactory::new()->withCustomer()->for($this->premiumDnsProduct)->createOne();
        $this->productRepository->expects(self::once())
            ->method('findProductById')
            ->with($this->freeDnsProduct->id)
            ->willReturn($this->freeDnsProduct);

        $this->creditSubscriptionService->expects(self::never())
            ->method('creditSubscriptions');

        $this->downgradeSubscriptionsAction->expects(self::once())
            ->method('execute')
            ->with($subscription, $this->freeDnsProduct)
            ->willReturn(new SubscriptionChangeResult(status: SubscriptionChangeResult::STATUS_OK));

        $this->subscriptionChangeService->expects(self::once())
            ->method('change')
            ->with(ProductChangeType::DOWNGRADE, $subscription, $this->freeDnsProduct);

        $subscriptionsCollection = new Collection();
        $subscriptionsCollection->add($subscription);

        $result = $this->action->handle($fields, $subscriptionsCollection);
        self::assertInstanceOf(ActionResponse::class, $result);
        $message = $result['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.downgrade_subscription.success', $message->text);
    }

    #[Test]
    public function downgradeWithCredit(): void
    {
        $fields = $this->getActionFields(
            product: $this->freeDnsProduct,
            reason: SubscriptionCancelReason::REASON_CANCELLATION,
            reasonOther: null,
            credit: true
        );
        $subscription = SubscriptionFactory::new()->withCustomer()->for($this->premiumDnsProduct)->createOne();
        $this->productRepository->expects(self::once())
            ->method('findProductById')
            ->with($this->freeDnsProduct->id)
            ->willReturn($this->freeDnsProduct);

        $this->creditSubscriptionService->expects(self::once())
            ->method('creditSubscriptions');

        $this->downgradeSubscriptionsAction->expects(self::once())
            ->method('execute')
            ->with($subscription, $this->freeDnsProduct)
            ->willReturn(new SubscriptionChangeResult(status: SubscriptionChangeResult::STATUS_OK));

        $this->subscriptionChangeService->expects(self::once())
            ->method('change')
            ->with(ProductChangeType::DOWNGRADE, $subscription, $this->freeDnsProduct);

        $subscriptionsCollection = new Collection();
        $subscriptionsCollection->add($subscription);

        $result = $this->action->handle($fields, $subscriptionsCollection);
        self::assertInstanceOf(ActionResponse::class, $result);
        $message = $result['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.downgrade_subscription.success', $message->text);
    }

    #[Test]
    public function downgradeIsAbortedWhenTechnicalDowngradeFails(): void
    {
        $fields = $this->getActionFields(
            product: $this->freeDnsProduct,
            reason: SubscriptionCancelReason::REASON_CANCELLATION,
            reasonOther: null,
            credit: false
        );
        $subscription = SubscriptionFactory::new()->withCustomer()->for($this->premiumDnsProduct)->createOne();
        $this->productRepository->expects(self::once())
            ->method('findProductById')
            ->with($this->freeDnsProduct->id)
            ->willReturn($this->freeDnsProduct);

        $this->downgradeSubscriptionsAction->expects(self::once())
            ->method('execute')
            ->with($subscription, $this->freeDnsProduct)
            ->willReturn(new SubscriptionChangeResult(status: SubscriptionChangeResult::STATUS_ERROR, errorMessage: 'panel unreachable'));

        $this->subscriptionChangeService->expects(self::never())
            ->method('change');

        $this->subscriptionChangeService->expects(self::once())
            ->method('storeChangeRecord')
            ->with($subscription, $this->freeDnsProduct, ProductChangeType::DOWNGRADE, SubscriptionChangeStatus::EXECUTION_FAILED);

        $this->logger->expects(self::once())
            ->method('error');

        $subscriptionsCollection = new Collection();
        $subscriptionsCollection->add($subscription);

        $result = $this->action->handle($fields, $subscriptionsCollection);
        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.downgrade_subscription.technical_failure', $danger->text);
    }

    #[Test]
    public function downgradeWillThrowSubscriptionChangeException(): void
    {
        $fields = $this->getActionFields(
            product: $this->freeDnsProduct,
            reason: SubscriptionCancelReason::REASON_CANCELLATION,
            reasonOther: null,
            credit: false
        );
        $subscription = SubscriptionFactory::new()->withCustomer()->for($this->premiumDnsProduct)->createOne();
        $this->productRepository->expects(self::once())
            ->method('findProductById')
            ->with($this->freeDnsProduct->id)
            ->willReturn($this->freeDnsProduct);

        $exception = new SubscriptionChangeException('dummy message');
        $this->downgradeSubscriptionsAction->expects(self::once())
            ->method('execute')
            ->with($subscription, $this->freeDnsProduct)
            ->willThrowException($exception);

        $this->logger->expects(self::once())
            ->method('critical')
            ->with(
                'Nova action for downgrade and credit failed with exception: dummy message',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

        $subscriptionsCollection = new Collection();
        $subscriptionsCollection->add($subscription);

        $result = $this->action->handle($fields, $subscriptionsCollection);
        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('Action failed: dummy message', $danger->text);
    }

    #[Test]
    public function downgradeWillThrowNotImplementedException(): void
    {
        $fields = $this->getActionFields(
            product: $this->freeDnsProduct,
            reason: SubscriptionCancelReason::REASON_CANCELLATION,
            reasonOther: null,
            credit: false
        );
        $subscription = SubscriptionFactory::new()->withCustomer()->for($this->premiumDnsProduct)->createOne();
        $this->productRepository->expects(self::once())
            ->method('findProductById')
            ->with($this->freeDnsProduct->id)
            ->willReturn($this->freeDnsProduct);

        $exception = new NotImplementedException('dummy message');
        $this->downgradeSubscriptionsAction->expects(self::once())
            ->method('execute')
            ->with($subscription, $this->freeDnsProduct)
            ->willThrowException($exception);

        $this->logger->expects(self::never())
            ->method('critical');

        $subscriptionsCollection = new Collection();
        $subscriptionsCollection->add($subscription);

        $result = $this->action->handle($fields, $subscriptionsCollection);
        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.downgrade_subscription.not_implemented', $danger->text);
    }

    private function getActionFields(
        Product $product,
        SubscriptionCancelReason $reason,
        ?string $reasonOther,
        bool $credit
    ): ActionFields {
        return new ActionFields(
            new Collection([
                'product_id' => (string) $product->id,
                'reason' => $reason->value,
                'reason_other' => $reasonOther,
                'credit' => $credit,
            ]),
            new Collection([])
        );
    }
}
