<?php

declare(strict_types=1);

namespace Tests\Domain\CustomerActionNeeded\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\CustomerActionNeeded\DTO\CustomerActionNeeded;
use Waterfront\Domain\CustomerActionNeeded\Enums\CustomerActionSlug;
use Waterfront\Domain\CustomerActionNeeded\Services\CustomerActionService;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Repositories\OrderRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(CustomerActionService::class)]
#[AllowMockObjectsWithoutExpectations]
class CustomerActionServiceTest extends TestCase
{
    private OrderRepository&MockObject $orderRepository;

    private DomainService&MockObject $domainService;

    private Microsoft365CustomerInfoRepository&MockObject $m365CustomerInfoRepository;

    private CustomerActionService $customerActionService;

    private UuidInterface $orderUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = self::createMock(OrderRepository::class);
        $this->domainService = self::createMock(DomainService::class);
        $this->m365CustomerInfoRepository = self::createMock(Microsoft365CustomerInfoRepository::class);
        $translator = self::createMock(TranslatorInterface::class);

        $translator
            ->method('translate')
            ->willReturnCallback(
                static fn (string $key, array $replace = []): string => $replace === []
                    ? $key
                    : $key . ':' . implode(',', $replace),
            );

        $this->customerActionService = new CustomerActionService(
            $this->orderRepository,
            $this->domainService,
            $this->m365CustomerInfoRepository,
            $translator,
        );

        $this->orderUuid = Uuid::uuid4();
    }

    #[Test]
    public function emptyCollectionWhenOrderDoesNotExist(): void
    {
        $this->orderRepository
            ->expects(self::once())
            ->method('getOrderByUuid')
            ->with($this->orderUuid)
            ->willReturn(null);

        $this->orderRepository->expects(self::never())->method('getSubscriptionsByOrderUuid');

        self::assertEmpty($this->customerActionService->getCustomerActionsFromOrderUuid($this->orderUuid));
    }

    #[Test]
    public function emptyCollectionWhenOrderHasNoSubscriptions(): void
    {
        $this->orderRepository->method('getOrderByUuid')->willReturn($this->makeOrder(isVerified: false));
        $this->orderRepository
            ->expects(self::once())
            ->method('getSubscriptionsByOrderUuid')
            ->with($this->orderUuid)
            ->willReturn(new Collection());

        self::assertEmpty($this->customerActionService->getCustomerActionsFromOrderUuid($this->orderUuid));
    }

    #[Test]
    public function multipleSubscriptionsResultInMultipleActions(): void
    {
        $this->orderRepository->method('getOrderByUuid')->willReturn($this->makeOrder(isVerified: true));
        $this->orderRepository
            ->method('getSubscriptionsByOrderUuid')
            ->willReturn(new Collection([
                $this->makeSubscription(ProductGroupType::BACKUP, 'acronis-backup'),
                $this->makeSubscription(ProductGroupType::HOSTING, 'hosting-small'),
                $this->makeSubscription(ProductGroupType::BACKUP, 'acronis-backup-large'),
            ]));

        $customerActions = $this->customerActionService->getCustomerActionsFromOrderUuid($this->orderUuid);

        self::assertCount(2, $customerActions);
        self::assertSame(
            ['acronis-backup', 'acronis-backup-large'],
            $customerActions->pluck('productSlug')->all(),
        );
    }

    #[Test]
    public function accountVerificationActionForUnverifiedCustomer(): void
    {
        $this->orderRepository
            ->method('getOrderByUuid')
            ->willReturn(
                $this->makeOrder(isVerified: false, email: 'customer@example.com'),
            );
        $this->orderRepository
            ->method('getSubscriptionsByOrderUuid')
            ->willReturn(new Collection([
                $this->makeSubscription(ProductGroupType::HOSTING, 'hosting-small'),
            ]));

        $customerActions = $this->customerActionService->getCustomerActionsFromOrderUuid($this->orderUuid);

        self::assertCount(1, $customerActions);

        $customerAction = $customerActions->first();
        self::assertInstanceOf(CustomerActionNeeded::class, $customerAction);
        self::assertSame('customer-action.account-verification.title', $customerAction->title);
        self::assertSame('customer-action.account-verification.title:customer@example.com', $customerAction->message);
        self::assertNull($customerAction->productGroupSlug);
        self::assertNull($customerAction->productSlug);
    }

    #[Test]
    public function noAccountVerificationActionForVerifiedCustomer(): void
    {
        $this->orderRepository->method('getOrderByUuid')->willReturn($this->makeOrder(isVerified: true));
        $this->orderRepository
            ->method('getSubscriptionsByOrderUuid')
            ->willReturn(new Collection([
                $this->makeSubscription(ProductGroupType::HOSTING, 'hosting-small'),
            ]));

        self::assertEmpty($this->customerActionService->getCustomerActionsFromOrderUuid($this->orderUuid));
    }

    #[Test]
    public function domainNameActions(): void
    {
        $domainDeployment = new DomainDeployment();
        $subscription = $this->makeSubscription(ProductGroupType::EXTENSION, 'nl-domain', $domainDeployment);
        $domainActions = new Collection([
            new CustomerActionNeeded(
                title: 'customer-action.deferred-transfer.title',
                message: 'customer-action.deferred-transfer',
                slug: CustomerActionSlug::DOMAIN_DEFERRED_TRANSFER,
                productGroupSlug: ProductGroupType::EXTENSION,
                productSlug: 'nl-domain',
            ),
        ]);

        $this->domainService
            ->expects(self::once())
            ->method('getCustomerActions')
            ->with($domainDeployment)
            ->willReturn($domainActions);

        self::assertSame(
            $domainActions->all(),
            $this->customerActionService->getCustomerActionsFromSubscription($subscription)->all(),
        );
    }

    #[Test]
    public function acronisActionForBackup(): void
    {
        $subscription = $this->makeSubscription(ProductGroupType::BACKUP, 'acronis-backup');

        $customerActions = $this->customerActionService->getCustomerActionsFromSubscription($subscription);

        self::assertCount(1, $customerActions);

        $customerAction = $customerActions->first();
        self::assertInstanceOf(CustomerActionNeeded::class, $customerAction);
        self::assertSame('customer-action.acronis.title', $customerAction->title);
        self::assertSame('customer-action.acronis', $customerAction->message);
        self::assertSame(ProductGroupType::BACKUP, $customerAction->productGroupSlug);
        self::assertSame('acronis-backup', $customerAction->productSlug);
    }

    #[Test]
    public function ActionForMicrosoft365(): void
    {
        $subscription = $this->makeSubscription(ProductGroupType::MICROSOFT_365, 'microsoft-365-business');

        $this->m365CustomerInfoRepository
            ->expects(self::once())
            ->method('findByCustomerAndDomain')
            ->with($subscription->customer, 'example.com')
            ->willReturn($this->makeMicrosoft365CustomerInfo(null));

        $customerActions = $this->customerActionService->getCustomerActionsFromSubscription($subscription);

        self::assertCount(1, $customerActions);

        $customerAction = $customerActions->first();
        self::assertInstanceOf(CustomerActionNeeded::class, $customerAction);
        self::assertSame('customer-action.m365-license.title', $customerAction->title);
        self::assertSame('customer-action.m365-license', $customerAction->message);
        self::assertSame(ProductGroupType::MICROSOFT_365, $customerAction->productGroupSlug);
        self::assertSame('microsoft-365-business', $customerAction->productSlug);
    }

    #[Test]
    #[DataProvider('productGroupsWithoutCustomerActionsProvider')]
    public function getCustomerActionsFromSubscriptionWillReturnEmptyCollectionForUnsupportedProductGroups(
        ProductGroupType $productGroupType,
    ): void {
        $this->domainService->expects(self::never())->method('getCustomerActions');
        $this->m365CustomerInfoRepository->expects(self::never())->method('findByCustomerAndDomain');

        $subscription = $this->makeSubscription($productGroupType, 'some-product');

        self::assertEmpty($this->customerActionService->getCustomerActionsFromSubscription($subscription));
    }

    /**
     * @return array<string, array{ProductGroupType}>
     */
    public static function productGroupsWithoutCustomerActionsProvider(): array
    {
        return [
            'hosting' => [ProductGroupType::HOSTING],
            'ssl' => [ProductGroupType::SSL],
            'dns' => [ProductGroupType::DNS],
            'vps' => [ProductGroupType::VPS],
            'add-on' => [ProductGroupType::ADD_ON],
        ];
    }

    #[Test]
    public function m365EmptyInfoNotFound(): void
    {
        $subscription = $this->makeSubscription(ProductGroupType::MICROSOFT_365, 'microsoft-365-business');

        $this->m365CustomerInfoRepository
            ->expects(self::once())
            ->method('findByCustomerAndDomain')
            ->with($subscription->customer, 'example.com')
            ->willReturn(null);

        self::assertEmpty($this->customerActionService->getM365CustomerAction($subscription));
    }

    #[Test]
    public function emptyCollectionWhenMcaIsSigned(): void
    {
        $subscription = $this->makeSubscription(ProductGroupType::MICROSOFT_365, 'microsoft-365-business');

        $this->m365CustomerInfoRepository
            ->method('findByCustomerAndDomain')
            ->willReturn($this->makeMicrosoft365CustomerInfo(CarbonImmutable::now()));

        self::assertEmpty($this->customerActionService->getM365CustomerAction($subscription));
    }

    private function makeOrder(bool $isVerified, string $email = 'customer@example.com'): Order
    {
        $customer = new Customer();
        $customer->email = $email;
        $customer->is_verified = $isVerified;

        $order = new Order();
        $order->setRelation('customer', $customer);

        return $order;
    }

    private function makeSubscription(
        ProductGroupType $productGroupType,
        string $productSlug,
        ?DomainDeployment $domainDeployment = null,
    ): Subscription {
        $productGroup = new ProductGroup();
        $productGroup->slug = $productGroupType;

        $product = new Product();
        $product->slug = $productSlug;
        $product->setRelation('productGroup', $productGroup);

        $subscription = new Subscription();
        $subscription->domain = 'example.com';
        $subscription->setRelation('product', $product);
        $subscription->setRelation('customer', new Customer());
        $subscription->setRelation('domainDeployment', $domainDeployment);

        return $subscription;
    }

    private function makeMicrosoft365CustomerInfo(?CarbonImmutable $mcaSignedAt): Microsoft365CustomerInfo
    {
        // The datetime cast resolves the connection's date format through the container,
        // which is not booted in this plain PHPUnit test, so bypass the cast.
        $customerInfo = new Microsoft365CustomerInfo();
        $customerInfo->setRawAttributes(['mca_signed_at' => $mcaSignedAt]);

        return $customerInfo;
    }
}
