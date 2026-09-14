<?php

declare(strict_types=1);

namespace Tests\Domain\Redirects\Jobs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Domain\Provision\ProvisionGatewayTest;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Results\GetRedirectResult;
use Waterfront\Domain\Provision\Redirects\Results\ListRedirectResult;
use Waterfront\Domain\Redirects\Jobs\UpgradeFreeRedirectJob;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;

#[CoversClass(UpgradeFreeRedirectJob::class)]
class UpgradeFreeRedirectJobTest extends IntegrationTestCase
{
    private const string DOMAIN = 'yourhosting-redirect.nl';

    private HostingDeployment $freeRedirectDeployment;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();
        $freeRedirectProduct = new ProductFactory()->freeRedirect()->createOne();

        $this->freeRedirectDeployment = new HostingDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->for($customer)
                    ->for($freeRedirectProduct)
                    ->createOne([
                        'domain' => self::DOMAIN,
                    ]),
            )
            ->withPleskProvider()
            ->createOne();
    }

    #[Test]
    public function upgradeFreeRedirectJob(): void
    {
        $cancellationService = self::createMock(CancellationService::class);
        $cancellationService->expects(self::never())->method('cancel');

        $subscription = $this->freeRedirectDeployment->subscription;

        $mockRequest = ProvisionGatewayTest::createMockRequest(
            context: Uuid::fromString($subscription->uuid),
            type: ProvisionType::REDIRECT,
        );

        $mockGateway = self::createMock(ProvisionGateway::class);
        $stubRedirectResult = new GetRedirectResult(
            provisionData: $mockRequest,
            provisionStatus: ProvisionStatus::PENDING,
        );
        $mockGateway
            ->expects(self::once())
            ->method('request')
            ->with(self::callback(
                fn (ListRedirectsRequest $request) => $request->context->toString() === $subscription->uuid,
            ))
            ->willReturn(new ListRedirectResult(
                provisionData: $mockRequest,
                provisionStatus: ProvisionStatus::SUCCESS,
                redirects: [$stubRedirectResult],
            ));

        $newProduct = self::createStub(Product::class);
        $productRepository = self::createMock(ProductRepository::class);
        $productRepository->expects(self::once())->method('findProductBySlug')->willReturn($newProduct);

        $subscriptionChangeService = self::createMock(SubscriptionChangeService::class);
        $subscriptionChangeService
            ->expects(self::once())
            ->method('change')
            ->with(
                changeType: ProductChangeType::UPGRADE,
                subscription: $subscription,
                newProduct: $newProduct,
                invoiceTheChange: false,
                sendMail: false,
            );

        $job = new UpgradeFreeRedirectJob($subscription);
        $job->handle(
            cancellationService: $cancellationService,
            subscriptionChangeService: $subscriptionChangeService,
            provisionGateway: $mockGateway,
            productRepository: $productRepository,
            domainDeploymentRepository: self::createStub(DomainDeploymentRepository::class),
            logger: self::createStub(LoggerInterface::class),
        );
    }

    #[Test]
    public function upgradeFreeRedirectJobSkippedWhenNoRedirectsAreFound(): void
    {
        $cancellationService = self::createMock(CancellationService::class);
        $cancellationService->expects(self::once())->method('cancel');

        $subscription = $this->freeRedirectDeployment->subscription;

        $mockRequest = ProvisionGatewayTest::createMockRequest(
            context: Uuid::fromString($subscription->uuid),
            type: ProvisionType::REDIRECT,
        );

        $mockGateway = self::createMock(ProvisionGateway::class);
        $mockGateway
            ->expects(self::once())
            ->method('request')
            ->with(self::callback(
                fn (ListRedirectsRequest $request) => $request->context->toString() === $subscription->uuid,
            ))
            ->willReturn(new ListRedirectResult(
                provisionData: $mockRequest,
                provisionStatus: ProvisionStatus::SUCCESS,
                redirects: [],
            ));

        $productRepository = self::createMock(ProductRepository::class);
        $productRepository->expects(self::never())->method('findProductBySlug');

        $subscriptionChangeService = self::createMock(SubscriptionChangeService::class);
        $subscriptionChangeService->expects(self::never())->method('change');

        $job = new UpgradeFreeRedirectJob($this->freeRedirectDeployment->subscription);
        $job->handle(
            cancellationService: $cancellationService,
            subscriptionChangeService: $subscriptionChangeService,
            provisionGateway: $mockGateway,
            productRepository: $productRepository,
            domainDeploymentRepository: self::createStub(DomainDeploymentRepository::class),
            logger: self::createStub(LoggerInterface::class),
        );
    }
}
