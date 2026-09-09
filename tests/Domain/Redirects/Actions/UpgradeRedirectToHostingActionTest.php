<?php

declare(strict_types=1);

namespace Tests\Domain\Redirects\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Domain\Provision\ProvisionGatewayTest;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Events\CreateHosting;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Redirects\Actions\UpgradeRedirectToHostingAction;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;

#[CoversClass(UpgradeRedirectToHostingAction::class)]
class UpgradeRedirectToHostingActionTest extends IntegrationTestCase
{
    public const string DOMAIN = 'testdomain.com';

    private Customer $customer;

    private ProductGroup $redirectProductGroup;

    private Product $hostingProduct;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $this->redirectProductGroup = new ProductGroupFactory()->redirect()->createOne();
        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();

        $this->hostingProduct = new ProductFactory()
            ->for($hostingProductGroup)
            ->createOne([
                'name' => 'super',
            ]);
    }

    #[Test]
    public function upgradeRedirectHosting(): void
    {
        $redirectProduct = new ProductFactory()
            ->for($this->redirectProductGroup)
            ->createOne(['slug' => ProductType::FREE_REDIRECT]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($redirectProduct)
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'domain' => self::DOMAIN,
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'contract_period' => 12,
            ]);

        $mockRequest = ProvisionGatewayTest::createMockRequest(
            context: Uuid::fromString($subscription->uuid),
            type: ProvisionType::REDIRECT,
        );

        $mockGateway = self::createMock(ProvisionGateway::class);
        $mockGateway->expects(self::once())
            ->method('request')
            ->with(self::callback(fn (TerminateRedirectsRequest $request) => $request->context->toString() === $subscription->uuid))
            ->willReturn(new RedirectResult(provisionData: $mockRequest, provisionStatus: ProvisionStatus::SUCCESS));

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(fn (CreateHosting $event) => $event->subscriptionUuid === $subscription->uuid &&
            $event->technicalStatus === TechnicalStatus::REGISTRATION->value &&
            $event->contactEmail === $subscription->customer->email &&
            $event->product->slug === $subscription->product->slug));

        $changeHostingAction = new UpgradeRedirectToHostingAction(
            eventDispatcher: $eventDispatcher,
            logger: self::createStub(LoggerInterface::class),
            provisionGateway: $mockGateway,
        );
        $result = $changeHostingAction->execute($subscription, $this->hostingProduct);
        self::assertSame(Result::STATUS_OK, $result->status);
    }

    #[Test]
    public function upgradeRedirectHostingUnableToDeleteRedirects(): void
    {
        $redirectProduct = new ProductFactory()
            ->for($this->redirectProductGroup)
            ->createOne(['slug' => ProductType::FREE_REDIRECT]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($redirectProduct)
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'domain' => self::DOMAIN,
                'start_date' => CarbonImmutable::now(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'contract_period' => 12,
            ]);

        $expectedException = new SubscriptionChangeException('whoopsie');

        $mockRequest = ProvisionGatewayTest::createMockRequest(
            context: Uuid::fromString($subscription->uuid),
            type: ProvisionType::REDIRECT,
        );

        $mockGateway = self::createMock(ProvisionGateway::class);
        $mockGateway->expects(self::once())
            ->method('request')
            ->with(self::callback(fn (TerminateRedirectsRequest $request) => $request->context->toString() === $subscription->uuid))
            ->willReturn(new RedirectResult(provisionData: $mockRequest, provisionStatus: ProvisionStatus::FAILED, exception: $expectedException));

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher->expects(self::never())
            ->method('dispatch')
            ->with(self::callback(fn (CreateHosting $event) => $event->subscriptionUuid === $subscription->uuid &&
                $event->technicalStatus === TechnicalStatus::REGISTRATION->value &&
                $event->contactEmail === $subscription->customer->email &&
                $event->product->slug === $subscription->product->slug));

        $changeHostingAction = new UpgradeRedirectToHostingAction(
            eventDispatcher: $eventDispatcher,
            logger: self::createStub(LoggerInterface::class),
            provisionGateway: $mockGateway,
        );

        self::expectException($expectedException::class);
        self::expectExceptionMessageMatches('/' . preg_quote($expectedException->getMessage(), '/') . '/');

        $changeHostingAction->execute($subscription, $this->hostingProduct);
    }
}
