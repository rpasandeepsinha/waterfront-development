<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Domain\VPS\Actions\ReinstallVirtualMachineAction;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(ReinstallVirtualMachineAction::class)]
#[AllowMockObjectsWithoutExpectations]
class ReinstallVirtualMachineActionTest extends IntegrationTestCase
{
    private SubscriptionChangeService&MockObject $changeService;

    private VirtualMachineServiceInterface&MockObject $vmService;

    private ProductRepository&MockObject $productRepository;

    private LoggerInterface&MockObject $logger;

    private ReinstallVirtualMachineAction $action;

    private Subscription $subscription;

    private VirtualMachineDeployment $deployment;

    private Product $newProduct;

    private string $newOsUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->changeService     = self::createMock(SubscriptionChangeService::class);
        $this->vmService         = self::createMock(VirtualMachineServiceInterface::class);
        $this->productRepository = self::createMock(ProductRepository::class);
        $this->logger            = self::createMock(LoggerInterface::class);

        $this->action = new ReinstallVirtualMachineAction(
            changeService: $this->changeService,
            vmService: $this->vmService,
            productRepository: $this->productRepository,
            logger: $this->logger,
        );

        $vpsProduct     = new ProductFactory()->vps()->createOne();
        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($vpsProduct, 'product')
            ->createOne();

        $environment = new CloudstackEnvironmentFactory()->createOne();

        $managerDomainDeployment = new CloudstackManagerDomainDeploymentFactory()
            ->for($this->subscription->customer)
            ->for($environment)
            ->createOne();

        $this->deployment = new CloudstackVirtualMachineDeploymentFactory()
            ->for($managerDomainDeployment)
            ->for($this->subscription, 'subscription')
            ->createOne();

        $this->newProduct = new ProductFactory()->ubuntu()->createOne();
        $this->newOsUuid  = $this->newProduct->uuid;
    }

    #[Test]
    public function happyPathReturnsTrue(): void
    {
        $sshKey = null;

        $this->productRepository
            ->expects(self::once())
            ->method('findProductByUuid')
            ->with(self::equalTo($this->newOsUuid))
            ->willReturn($this->newProduct);

        $this->changeService
            ->expects(self::once())
            ->method('change')
            ->with(
                self::equalTo(ProductChangeType::REINSTALL),
                self::equalTo($this->subscription),
                self::equalTo($this->newProduct),
                false,
                false
            );

        $this->vmService
            ->expects(self::once())
            ->method('reinstall')
            ->with(self::equalTo($this->deployment), self::equalTo($this->newProduct), self::equalTo($sshKey))
            ->willReturn(true);

        $result = $this->action->execute(
            $this->subscription,
            $this->deployment,
            $this->newOsUuid,
            $sshKey
        );

        self::assertTrue($result);
    }

    #[Test]
    public function returnsFalseIfVmServiceFails(): void
    {
        $sshKey = 'some-ssh-key-uuid';

        $this->productRepository
            ->method('findProductByUuid')
            ->willReturn($this->newProduct);

        $this->changeService
            ->expects(self::once())
            ->method('change');

        $this->vmService
            ->expects(self::once())
            ->method('reinstall')
            ->with($this->deployment, $this->newProduct, $sshKey)
            ->willReturn(false);

        self::assertFalse(
            $this->action->execute(
                $this->subscription,
                $this->deployment,
                $this->newOsUuid,
                $sshKey
            )
        );
    }

    #[Test]
    public function logsAndThrowsWhenSubscriptionChangeFails(): void
    {
        $this->productRepository
            ->method('findProductByUuid')
            ->willReturn($this->newProduct);

        $this->changeService
            ->expects(self::once())
            ->method('change')
            ->willThrowException(new SubscriptionChangeException('oops'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Failed to change product for subscription'),
                self::callback(
                    fn (array $ctx) =>
                    $ctx[LoggingContextKeys::PROVISIONING_ID] === $this->deployment->id
                        && $ctx[LoggingContextKeys::PROVISIONING_TYPE] === ProvisionType::VPS
                        && $ctx[LoggingContextKeys::PROVISIONING_PROVIDER] === ProvisionProvider::CLOUDSTACK
                        && $ctx[LoggingContextKeys::SUBSCRIPTION_UUID] === $this->subscription->uuid
                        && $ctx[LoggingContextKeys::PRODUCT_UUID] === $this->newOsUuid
                )
            );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('administrative change failed when reinstalling the VM');

        $this->action->execute(
            $this->subscription,
            $this->deployment,
            $this->newOsUuid
        );
    }

    #[Test]
    public function bubblesModelNotFoundIfProductMissing(): void
    {
        $this->productRepository
            ->expects(self::once())
            ->method('findProductByUuid')
            ->willThrowException(new ModelNotFoundException());

        $this->expectException(ModelNotFoundException::class);

        $this->action->execute(
            $this->subscription,
            $this->deployment,
            $this->newOsUuid
        );
    }

    #[Test]
    public function bubblesClientExceptionFromVmService(): void
    {
        $this->productRepository
            ->method('findProductByUuid')
            ->willReturn($this->newProduct);

        $this->changeService
            ->expects(self::once())
            ->method('change');

        $this->vmService
            ->expects(self::once())
            ->method('reinstall')
            ->willThrowException(new ClientException('cloudstack error'));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessageIs('cloudstack error');

        $this->action->execute(
            $this->subscription,
            $this->deployment,
            $this->newOsUuid
        );
    }

    #[Test]
    public function throwsWhenUsingParentSubscriptionInsteadOfOsChild(): void
    {
        $this->productRepository
            ->expects(self::once())
            ->method('findProductByUuid')
            ->with(self::equalTo($this->newOsUuid))
            ->willReturn($this->newProduct);

        $this->changeService
            ->expects(self::once())
            ->method('change')
            ->with(
                ProductChangeType::REINSTALL,
                $this->subscription,
                $this->newProduct,
                false,
                false
            )
            ->willThrowException(
                SubscriptionChangeException::noPotentialProducts(
                    subscriptionUuid: $this->subscription->uuid,
                    productName: $this->newProduct->name
                )
            );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('administrative change failed when reinstalling the VM');

        $this->action->execute(
            $this->subscription,
            $this->deployment,
            $this->newOsUuid
        );
    }
}
